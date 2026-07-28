<?php
/**
 * Application configuration.
 *
 * Two layers:
 *   1. The defaults below — suit a developer machine with XAMPP.
 *   2. config/app.ini      — written by the installer / production build.
 *      Any value there overrides the default. The file is optional and is
 *      never overwritten by application updates.
 *
 * app.ini example:
 *   [database]
 *   host = 127.0.0.1
 *   port = 3307
 *   name = daher_store
 *   user = root
 *   pass =
 *   [app]
 *   debug = 0
 *   timezone = Asia/Beirut
 */

declare(strict_types=1);

$ini = [];
if (is_file(__DIR__ . '/app.ini')) {
    $parsed = parse_ini_file(__DIR__ . '/app.ini', true, INI_SCANNER_TYPED);
    if (is_array($parsed)) {
        $ini = $parsed;
    }
}

// --- Database ---------------------------------------------------------------
define('DB_HOST', (string) ($ini['database']['host'] ?? '127.0.0.1'));
define('DB_PORT', (int) ($ini['database']['port'] ?? 3306));
define('DB_NAME', (string) ($ini['database']['name'] ?? 'daher_store'));
define('DB_USER', (string) ($ini['database']['user'] ?? 'root'));
define('DB_PASS', (string) ($ini['database']['pass'] ?? ''));

// --- Application --------------------------------------------------------------
const APP_NAME = 'Daher Phone';

/**
 * The version lives in the VERSION file so the auto-updater can bump it
 * without touching PHP code. The constant is the fallback for dev checkouts.
 */
$versionFile = dirname(__DIR__) . '/VERSION';
define(
    'APP_VERSION',
    is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '1.3.0'
);

/** Detailed error pages — always OFF in production (app.ini sets debug = 0). */
define('APP_DEBUG', (bool) ($ini['app']['debug'] ?? true));

/** Session inactivity timeout in seconds (default 8 hours = one work day). */
const SESSION_LIFETIME = 28800;

/** Local timezone used for all dates, invoices and reports. */
define('APP_TIMEZONE', (string) ($ini['app']['timezone'] ?? 'Asia/Beirut'));

// --- Paths (derived, do not edit) ---------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

/**
 * Backups and update workspace. The packaged product points these at the
 * top-level Backups\ and Updates\ folders via app.ini; developer checkouts
 * fall back to storage/.
 */
$resolvePath = static function (string $path): string {
    $path = rtrim($path, '/\\');
    // Relative entries in app.ini are relative to the application folder.
    if (!preg_match('~^([A-Za-z]:[\\\\/]|[\\\\/])~', $path)) {
        $path = BASE_PATH . DIRECTORY_SEPARATOR . $path;
    }
    return $path;
};
define('BACKUP_PATH', isset($ini['paths']['backups'])
    ? $resolvePath((string) $ini['paths']['backups'])
    : STORAGE_PATH . DIRECTORY_SEPARATOR . 'backups');
define('UPDATES_PATH', isset($ini['paths']['updates'])
    ? $resolvePath((string) $ini['paths']['updates'])
    : STORAGE_PATH . DIRECTORY_SEPARATOR . 'updates');
