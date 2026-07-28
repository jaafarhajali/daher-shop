<?php
/**
 * First-run / upgrade database installer.
 *
 *   php bin/install.php
 *
 * Safe to run on EVERY start:
 *   · fresh machine  → creates the database, imports schema.sql (with seed
 *                      data + admin login), marks all migrations applied
 *   · existing data  → NEVER re-imports the schema; only runs migrations
 *                      that have not run yet
 *
 * Exit codes: 0 = ok, 1 = database server unreachable, 2 = install failed.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\Migrator;

$maxWaitSeconds = (int) ($argv[1] ?? 60);

// --- 1. Wait for the database server -----------------------------------------
out('Connecting to database server at ' . DB_HOST . ':' . DB_PORT . ' ...');
$server = null;
$deadline = time() + $maxWaitSeconds;
while (time() < $deadline) {
    try {
        $server = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT),
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        break;
    } catch (PDOException) {
        usleep(500_000);
    }
}
if ($server === null) {
    out('ERROR: database server did not become ready within ' . $maxWaitSeconds . 's.');
    exit(1);
}
out('Database server is ready.');

try {
    // --- 2. Create the database if missing ------------------------------------
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    // --- 3. Fresh install or existing data? -----------------------------------
    $hasUsers = $server
        ->query("SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = '" . DB_NAME . "' AND table_name = 'users'")
        ->fetchColumn() > 0;

    $pdo = Database::pdo();
    $migrator = new Migrator($pdo);

    if (!$hasUsers) {
        out('Fresh installation - importing database schema ...');
        $statements = Migrator::runSqlFile($pdo, BASE_PATH . '/database/schema.sql');
        out("Schema imported ({$statements} statements).");
        $migrator->markAllApplied();
        out('Default login: admin / admin123 - change it after first sign-in.');
    } else {
        out('Existing database found - checking for pending migrations ...');
        $migrator->backfillLegacy();
        $done = $migrator->run();
        if ($done === []) {
            out('Database is up to date.');
        } else {
            foreach ($done as $f) {
                out('Applied migration: ' . $f);
            }
        }
    }

    // --- 4. Verify the core tables exist ----------------------------------------
    $required = ['users', 'products', 'sales', 'sale_items', 'customers',
                 'repairs', 'expenses', 'settings', 'customer_payments',
                 'product_returns', 'refunds', 'migrations'];
    $existing = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
    $missing = array_diff($required, $existing);
    if ($missing !== []) {
        out('ERROR: required tables missing: ' . implode(', ', $missing));
        exit(2);
    }

    out('Database OK (' . count($existing) . ' tables).');
    exit(0);
} catch (Throwable $e) {
    out('ERROR: ' . $e->getMessage());
    exit(2);
}
