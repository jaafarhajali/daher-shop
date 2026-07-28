<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Database migration runner.
 *
 * Every schema change ships as database/migrations/NNN_name.sql.
 * Applied filenames are recorded in the `migrations` table, so each
 * migration runs exactly once — on developer machines, on fresh installs
 * and on customer machines receiving updates.
 */
final class Migrator
{
    private PDO $db;

    /** Where the .sql migration files live. */
    private string $dir;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::pdo();
        $this->dir = BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    public function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `filename`   VARCHAR(190) NOT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migrations_filename` (`filename`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return string[] all migration filenames on disk, in run order */
    public function all(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }
        $files = array_values(array_filter(
            scandir($this->dir) ?: [],
            static fn (string $f): bool => (bool) preg_match('/^\d{3}_[A-Za-z0-9_\-]+\.sql$/', $f)
        ));
        sort($files, SORT_STRING);

        return $files;
    }

    /** @return string[] applied filenames */
    public function applied(): array
    {
        $this->ensureTable();

        return array_column(
            $this->db->query('SELECT filename FROM migrations ORDER BY filename')->fetchAll(),
            'filename'
        );
    }

    /** @return string[] filenames still to run, in order */
    public function pending(): array
    {
        return array_values(array_diff($this->all(), $this->applied()));
    }

    /**
     * Run every pending migration. Returns the filenames that were applied.
     * A migration is recorded only after ALL of its statements succeeded.
     *
     * @return string[]
     */
    public function run(): array
    {
        $done = [];
        foreach ($this->pending() as $filename) {
            self::runSqlFile($this->db, $this->dir . DIRECTORY_SEPARATOR . $filename);
            $stmt = $this->db->prepare('INSERT INTO migrations (filename) VALUES (:f)');
            $stmt->execute(['f' => $filename]);
            $done[] = $filename;
        }

        return $done;
    }

    /**
     * After importing the full schema.sql (fresh install), every migration is
     * already reflected in the schema — record them all as applied.
     */
    public function markAllApplied(): void
    {
        $this->ensureTable();
        $stmt = $this->db->prepare('INSERT IGNORE INTO migrations (filename) VALUES (:f)');
        foreach ($this->all() as $filename) {
            $stmt->execute(['f' => $filename]);
        }
    }

    /**
     * One-time adoption for databases that predate the migrations table:
     * if the tracking table is empty but migration 001's changes are visibly
     * present (products.warranty_days exists), mark 001 and 002 as applied
     * instead of re-running them against live data.
     */
    public function backfillLegacy(): void
    {
        $this->ensureTable();
        $count = (int) $this->db->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $hasWarrantyDays = $this->db
            ->query("SHOW COLUMNS FROM `products` LIKE 'warranty_days'")
            ->fetch() !== false;

        if ($hasWarrantyDays) {
            $stmt = $this->db->prepare('INSERT IGNORE INTO migrations (filename) VALUES (:f)');
            foreach (['001_credit_returns_refunds.sql', '002_branding_daher_phone.sql'] as $f) {
                $stmt->execute(['f' => $f]);
            }
        }
    }

    /**
     * Execute a .sql file statement by statement (statements end with ';' at
     * end of line — the format of our schema, migrations and backups).
     */
    public static function runSqlFile(PDO $pdo, string $path): int
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot open SQL file: ' . $path);
        }

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
                    $pdo->exec($buffer);
                    $executed++;
                    $buffer = '';
                }
            }
            if (trim($buffer) !== '') {
                $pdo->exec($buffer);
                $executed++;
            }
        } finally {
            fclose($fh);
        }

        return $executed;
    }
}
