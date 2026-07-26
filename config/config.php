<?php
/**
 * Application configuration.
 *
 * Edit the DB_* constants to match your MySQL setup. XAMPP defaults are
 * user "root" with an empty password.
 */

declare(strict_types=1);

// --- Database -------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_NAME = 'daher_store';
const DB_USER = 'root';
const DB_PASS = '';

// --- Application ------------------------------------------------------------
const APP_NAME  = 'Daher Phone';
const APP_VERSION = '1.0.0';

/**
 * Show detailed error pages. Set to false once the shop goes live so
 * customers/staff never see stack traces.
 */
const APP_DEBUG = true;

/** Session inactivity timeout in seconds (default 8 hours = one work day). */
const SESSION_LIFETIME = 28800;

/** Local timezone used for all dates, invoices and reports. */
const APP_TIMEZONE = 'Asia/Beirut';

// --- Paths (derived, do not edit) -------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
define('BACKUP_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'backups');
