<?php
/**
 * Database backup from the command line.
 *
 *   php bin/backup.php           always create a backup
 *   php bin/backup.php --auto    daily mode: skip when the newest backup is
 *                                less than 22 hours old (the launcher calls
 *                                this on every start)
 *
 * Prints the created filename, or "SKIPPED" in auto mode.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Models\Backup;

$backup = new Backup();

if (in_array('--auto', $argv, true)) {
    $files = $backup->listFiles();
    if ($files !== [] && time() - $files[0]['created'] < 22 * 3600) {
        out('SKIPPED (latest backup is recent: ' . $files[0]['name'] . ')');
        exit(0);
    }
}

try {
    $filename = $backup->create();
    out('Backup created: ' . $filename);
    exit(0);
} catch (Throwable $e) {
    out('BACKUP FAILED: ' . $e->getMessage());
    exit(1);
}
