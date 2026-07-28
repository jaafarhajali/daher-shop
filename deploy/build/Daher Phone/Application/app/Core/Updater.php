<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Backup;

/**
 * Application self-update engine.
 *
 * Update package format: a .zip whose root mirrors the application folder
 * (app/, public/, config/, database/, bin/, VERSION). A package must contain
 * a VERSION file — that is how junk uploads are rejected.
 *
 * Apply pipeline (everything under UPDATES_PATH):
 *   1. database backup (Backup::create)
 *   2. rollback copy of the current code
 *   3. extract package to a staging folder + sanity-check it
 *   4. copy staging over the application (config/app.ini and storage/ excluded)
 *   5. run pending database migrations
 *   On ANY failure after step 4 begins → previous code is restored.
 *
 * Update feed (JSON at the configured URL):
 *   { "version": "1.4.0", "url": "https://.../DaherPhone-update-1.4.0.zip",
 *     "sha256": "...", "notes": "What changed" }
 */
final class Updater
{
    /** Folders/files never overwritten and never rolled back. */
    private const PRESERVE = ['storage', 'config' . DIRECTORY_SEPARATOR . 'app.ini'];

    /** Top-level entries that make up the application code. */
    private const CODE_DIRS = ['app', 'public', 'config', 'database', 'bin', 'docs'];
    private const CODE_FILES = ['VERSION', 'index.php', 'README.md'];

    /**
     * Fetch and parse the update feed.
     *
     * @return array{version:string, url:string, sha256:?string, notes:string,
     *               is_newer:bool}
     */
    public function check(string $feedUrl): array
    {
        if (!preg_match('~^https?://~i', $feedUrl)) {
            throw new \RuntimeException('The update server address must start with http:// or https://');
        }

        $context = stream_context_create(['http' => ['timeout' => 15]]);
        $raw = @file_get_contents($feedUrl, false, $context);
        if ($raw === false) {
            throw new \RuntimeException('Could not reach the update server. Check the internet connection and the address.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['version']) || empty($data['url'])) {
            throw new \RuntimeException('The update server returned an invalid answer.');
        }

        return [
            'version'  => (string) $data['version'],
            'url'      => (string) $data['url'],
            'sha256'   => isset($data['sha256']) ? strtolower((string) $data['sha256']) : null,
            'notes'    => (string) ($data['notes'] ?? ''),
            'is_newer' => version_compare((string) $data['version'], APP_VERSION, '>'),
        ];
    }

    /** Download the package described by a check() result. Returns the zip path. */
    public function download(array $info): string
    {
        if (!preg_match('~^https?://~i', (string) $info['url'])) {
            throw new \RuntimeException('The update package address is invalid.');
        }

        $this->ensureDir(UPDATES_PATH);
        $zipPath = UPDATES_PATH . DIRECTORY_SEPARATOR . 'download_' . $info['version'] . '.zip';

        $context = stream_context_create(['http' => ['timeout' => 300]]);
        $data = @file_get_contents((string) $info['url'], false, $context);
        if ($data === false || $data === '') {
            throw new \RuntimeException('Downloading the update package failed.');
        }
        file_put_contents($zipPath, $data);

        if (!empty($info['sha256']) && !hash_equals($info['sha256'], hash_file('sha256', $zipPath))) {
            unlink($zipPath);
            throw new \RuntimeException('The downloaded package is corrupted (checksum mismatch). Update cancelled.');
        }

        return $zipPath;
    }

    /**
     * Apply an update package zip. Returns the new version string.
     */
    public function apply(string $zipPath): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is missing on this installation.');
        }

        $this->ensureDir(UPDATES_PATH);
        $staging = UPDATES_PATH . DIRECTORY_SEPARATOR . 'staging';
        $rollback = UPDATES_PATH . DIRECTORY_SEPARATOR . 'rollback_' . APP_VERSION . '_' . date('Ymd_His');

        // 1. Database safety net.
        (new Backup())->create();

        // 2. Extract + validate BEFORE touching anything.
        $this->removeDir($staging);
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('The update package could not be opened — is it a valid .zip file?');
        }
        $zip->extractTo($staging);
        $zip->close();

        // Accept packages that wrap everything in a single top folder.
        $root = $staging;
        if (!is_file($root . DIRECTORY_SEPARATOR . 'VERSION')) {
            $entries = array_values(array_diff(scandir($staging) ?: [], ['.', '..']));
            if (count($entries) === 1 && is_dir($staging . DIRECTORY_SEPARATOR . $entries[0])
                && is_file($staging . DIRECTORY_SEPARATOR . $entries[0] . DIRECTORY_SEPARATOR . 'VERSION')) {
                $root = $staging . DIRECTORY_SEPARATOR . $entries[0];
            } else {
                $this->removeDir($staging);
                throw new \RuntimeException('This is not a Daher Phone update package (VERSION file missing).');
            }
        }
        $newVersion = trim((string) file_get_contents($root . DIRECTORY_SEPARATOR . 'VERSION'));
        if ($newVersion === '') {
            $this->removeDir($staging);
            throw new \RuntimeException('The update package has an empty VERSION file.');
        }

        // 3. Rollback copy of the current code.
        $this->copyCode(BASE_PATH, $rollback);

        // 4. Overwrite the application with the staged files.
        try {
            $this->copyTree($root, BASE_PATH);

            // 5. Database migrations shipped with the update.
            (new Migrator())->run();
        } catch (\Throwable $e) {
            // Restore the previous code — MIRROR restore, so files that the
            // broken update ADDED (e.g. a bad migration) disappear as well.
            // Clearing is best-effort: the script currently executing this very
            // code is locked by Windows and cannot be deleted — that is fine,
            // because copyTree() immediately restores its previous content.
            // Data and config are never touched.
            foreach (self::CODE_DIRS as $dir) {
                if (is_dir($rollback . DIRECTORY_SEPARATOR . $dir)
                    && $dir !== 'config' && $dir !== 'storage') {
                    $this->clearBestEffort(BASE_PATH . DIRECTORY_SEPARATOR . $dir);
                }
            }
            $this->copyTree($rollback, BASE_PATH);
            $this->removeDir($staging);
            throw new \RuntimeException(
                'Update failed and the previous version was restored. Reason: ' . $e->getMessage()
            );
        }

        $this->removeDir($staging);

        return $newVersion;
    }

    // ---------------------------------------------------------------- files --

    /** Copy only the application code (used for the rollback snapshot). */
    private function copyCode(string $from, string $to): void
    {
        $this->ensureDir($to);
        foreach (self::CODE_DIRS as $dir) {
            if (is_dir($from . DIRECTORY_SEPARATOR . $dir)) {
                $this->copyTree($from . DIRECTORY_SEPARATOR . $dir, $to . DIRECTORY_SEPARATOR . $dir);
            }
        }
        foreach (self::CODE_FILES as $file) {
            if (is_file($from . DIRECTORY_SEPARATOR . $file)) {
                copy($from . DIRECTORY_SEPARATOR . $file, $to . DIRECTORY_SEPARATOR . $file);
            }
        }
    }

    /** Recursive copy that skips the preserved paths (relative to target root). */
    private function copyTree(string $from, string $to): void
    {
        $this->ensureDir($to);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $relative = substr((string) $item->getPathname(), strlen($from) + 1);
            foreach (self::PRESERVE as $preserve) {
                if ($relative === $preserve || str_starts_with($relative, $preserve . DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }
            $target = $to . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                $this->ensureDir($target);
            } else {
                $this->ensureDir(dirname($target));
                copy((string) $item->getPathname(), $target);
            }
        }
    }

    /** Delete as much of a tree as possible, silently skipping locked files. */
    private function clearBestEffort(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir((string) $item->getPathname()) : @unlink((string) $item->getPathname());
        }
        @rmdir($dir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir((string) $item->getPathname()) : unlink((string) $item->getPathname());
        }
        rmdir($dir);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create folder: ' . $dir);
        }
    }
}
