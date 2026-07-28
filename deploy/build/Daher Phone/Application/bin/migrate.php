<?php
/**
 * Run pending database migrations.
 *
 *   php bin/migrate.php            run everything pending
 *   php bin/migrate.php --status   list applied / pending without running
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Migrator;

$migrator = new Migrator();

if (in_array('--status', $argv, true)) {
    $applied = $migrator->applied();
    $pending = $migrator->pending();
    out('Applied (' . count($applied) . '):');
    foreach ($applied as $f) {
        out('  [x] ' . $f);
    }
    out('Pending (' . count($pending) . '):');
    foreach ($pending as $f) {
        out('  [ ] ' . $f);
    }
    exit(0);
}

try {
    $migrator->backfillLegacy();
    $done = $migrator->run();
    if ($done === []) {
        out('Nothing to migrate - database is up to date.');
    } else {
        foreach ($done as $f) {
            out('Applied: ' . $f);
        }
    }
    exit(0);
} catch (Throwable $e) {
    out('MIGRATION FAILED: ' . $e->getMessage());
    out('The failed migration was NOT recorded; fix the problem and run again.');
    exit(1);
}
