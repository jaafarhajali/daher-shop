<?php
/**
 * Front controller — the single entry point for every request.
 *
 * Routing uses a query string parameter, e.g.:
 *   index.php?r=products/index
 *   index.php?r=sales/pos
 * so the app works on any XAMPP install without mod_rewrite configuration.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/config/config.php';

date_default_timezone_set(APP_TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0775, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

// --- Autoloader: App\Controllers\ProductController -> app/Controllers/ProductController.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = APP_PATH . DIRECTORY_SEPARATOR
              . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_PATH . '/Core/helpers.php';

// --- Hardened session --------------------------------------------------------
session_name('daher_session');
session_set_cookie_params([
    'lifetime' => 0,             // cookie dies with the browser
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
ini_set('session.use_strict_mode', '1');
session_start();

// Inactivity timeout
if (isset($_SESSION['last_activity'])
    && time() - (int) $_SESSION['last_activity'] > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    session_start();
    \App\Core\Flash::set('warning', 'Your session expired. Please sign in again.');
}
$_SESSION['last_activity'] = time();

// --- Global error safety net ---------------------------------------------
set_exception_handler(static function (Throwable $e): void {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre style="padding:2rem;font:14px/1.6 monospace;color:#b91c1c;">'
           . e(get_class($e) . ': ' . $e->getMessage()) . "\n\n"
           . e($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<div style="font:16px/1.6 system-ui;padding:4rem;text-align:center;">'
           . '<h1>Something went wrong</h1>'
           . '<p>The error has been logged. Please try again.</p></div>';
    }
    exit;
});

// --- Dispatch ----------------------------------------------------------------
(new \App\Core\App())->run();
