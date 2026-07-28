<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Updater;
use App\Models\Setting;

/**
 * In-app updates (admin only — guarded by the router).
 * Sources: the configured update server, or a package file from USB.
 */
final class UpdateController extends Controller
{
    public function index(): void
    {
        $this->render('updates/index', [
            'updateUrl' => setting('update_url', ''),
            'checked'   => $_SESSION['update_check'] ?? null,
        ], 'Updates');
    }

    /** POST updates/check — ask the update server for the latest version. */
    public function check(): void
    {
        $this->requireValidPost();

        $url = trim($this->input('update_url'));
        if ($url !== setting('update_url', '')) {
            (new Setting())->set('update_url', $url);
        }
        if ($url === '') {
            Flash::set('warning', 'Enter the update server address first.');
            redirect('updates/index');
        }

        try {
            $info = (new Updater())->check($url);
            $_SESSION['update_check'] = $info;
            Flash::set(
                $info['is_newer'] ? 'success' : 'info',
                $info['is_newer']
                    ? 'New update available: version ' . $info['version']
                    : 'You already have the latest version (' . APP_VERSION . ').'
            );
        } catch (\RuntimeException $e) {
            unset($_SESSION['update_check']);
            Flash::set('danger', $e->getMessage());
        }

        redirect('updates/index');
    }

    /** POST updates/apply — download from the server and install. */
    public function apply(): void
    {
        $this->requireValidPost();

        $info = $_SESSION['update_check'] ?? null;
        if (!is_array($info) || empty($info['is_newer'])) {
            Flash::set('warning', 'Check for updates first.');
            redirect('updates/index');
        }

        try {
            $updater = new Updater();
            $zip = $updater->download($info);
            $newVersion = $updater->apply($zip);
            unset($_SESSION['update_check']);
            Flash::set('success', 'Updated to version ' . $newVersion . ' — a database backup was taken first.');
        } catch (\RuntimeException $e) {
            Flash::set('danger', $e->getMessage());
        }

        redirect('updates/index');
    }

    /** POST updates/apply-file — install a package copied from USB. */
    public function applyFile(): void
    {
        $this->requireValidPost();

        $file = $_FILES['package'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::set('warning', 'Choose the update package (.zip) first.');
            redirect('updates/index');
        }
        if (!preg_match('/\.zip$/i', (string) $file['name'])) {
            Flash::set('danger', 'The update package must be a .zip file.');
            redirect('updates/index');
        }

        if (!is_dir(UPDATES_PATH)) {
            mkdir(UPDATES_PATH, 0775, true);
        }
        $zipPath = UPDATES_PATH . DIRECTORY_SEPARATOR . 'upload_' . date('Ymd_His') . '.zip';
        if (!move_uploaded_file((string) $file['tmp_name'], $zipPath)) {
            Flash::set('danger', 'Could not store the uploaded package.');
            redirect('updates/index');
        }

        try {
            $newVersion = (new Updater())->apply($zipPath);
            Flash::set('success', 'Updated to version ' . $newVersion . ' — a database backup was taken first.');
        } catch (\RuntimeException $e) {
            Flash::set('danger', $e->getMessage());
        }

        redirect('updates/index');
    }
}
