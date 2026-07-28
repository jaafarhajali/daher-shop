<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Pure-PHP database backup / restore.
 *
 * The dump format keeps every SQL statement on a single line (newlines inside
 * string values are escaped), so restore() can parse files safely line by line.
 */
final class Backup extends Model
{
    /** Only files matching this pattern may be downloaded/restored/deleted. */
    private const FILE_PATTERN = '/^backup_[0-9]{8}_[0-9]{6}\.sql$/';

    /** @return string generated filename */
    public function create(): string
    {
        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0775, true);
        }

        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $path = BACKUP_PATH . DIRECTORY_SEPARATOR . $filename;

        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Could not create the backup file. Check folder permissions.');
        }

        try {
            fwrite($fh, "-- " . APP_NAME . " database backup\n");
            fwrite($fh, "-- Database: " . DB_NAME . "\n");
            fwrite($fh, "-- Created: " . date('Y-m-d H:i:s') . "\n\n");
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($fh, "SET NAMES utf8mb4;\n\n");

            $tables = array_column(
                $this->fetchAll('SHOW TABLES'),
                'Tables_in_' . DB_NAME
            );

            foreach ($tables as $table) {
                // Table name comes from SHOW TABLES, not from user input.
                $create = $this->fetch('SHOW CREATE TABLE `' . $table . '`');
                fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fh, str_replace("\n", ' ', (string) $create['Create Table']) . ";\n");

                $stmt = $this->db->query('SELECT * FROM `' . $table . '`');
                while (($row = $stmt->fetch()) !== false) {
                    $cols = '`' . implode('`, `', array_keys($row)) . '`';
                    $vals = implode(', ', array_map(
                        fn ($v): string => $this->quoteValue($v),
                        array_values($row)
                    ));
                    fwrite($fh, "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n");
                }
                fwrite($fh, "\n");
            }

            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($fh);
        }

        return $filename;
    }

    /** @return array<int, array{name:string, size:int, created:int}> newest first */
    public function listFiles(): array
    {
        if (!is_dir(BACKUP_PATH)) {
            return [];
        }

        $files = [];
        foreach (scandir(BACKUP_PATH) ?: [] as $name) {
            if (preg_match(self::FILE_PATTERN, $name)) {
                $path = BACKUP_PATH . DIRECTORY_SEPARATOR . $name;
                $files[] = [
                    'name'    => $name,
                    'size'    => (int) filesize($path),
                    'created' => (int) filemtime($path),
                ];
            }
        }
        usort($files, static fn (array $a, array $b): int => $b['created'] <=> $a['created']);

        return $files;
    }

    /** Validate a client-supplied filename and return its full path. */
    public function resolve(string $filename): ?string
    {
        if (!preg_match(self::FILE_PATTERN, $filename)) {
            return null;
        }
        $path = BACKUP_PATH . DIRECTORY_SEPARATOR . $filename;

        return is_file($path) ? $path : null;
    }

    public function delete(string $filename): bool
    {
        $path = $this->resolve($filename);

        return $path !== null && unlink($path);
    }

    /**
     * Restore a .sql file (one of ours, or a phpMyAdmin export of this DB).
     * Runs inside a transaction where possible; DDL statements auto-commit in
     * MySQL, so a failed restore may be partial — the UI warns about this.
     *
     * @return int number of executed statements
     */
    public function restore(string $path): int
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Could not open the backup file.');
        }

        $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
        $executed = 0;
        $buffer = '';

        try {
            while (($line = fgets($fh)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    continue;
                }
                $buffer .= $line;
                if (str_ends_with(rtrim($line), ';')) {
                    $this->db->exec($buffer);
                    $executed++;
                    $buffer = '';
                }
            }
        } finally {
            fclose($fh);
            $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return $executed;
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Quote, then force the statement onto a single line.
        $quoted = $this->db->quote((string) $value);

        return str_replace(["\r", "\n"], ['\\r', '\\n'], $quoted);
    }
}
