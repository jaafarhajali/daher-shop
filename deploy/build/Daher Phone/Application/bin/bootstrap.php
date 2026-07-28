<?php
/**
 * CLI bootstrap — shared by the bin/ scripts (install, migrate, backup).
 * Loads configuration, the autoloader and helpers. No session, no routing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/config/config.php';

date_default_timezone_set(APP_TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', '1');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $file = APP_PATH . DIRECTORY_SEPARATOR
              . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_PATH . '/Core/helpers.php';

/** Console line, flushed immediately so the launcher can stream progress. */
function out(string $message): void
{
    echo $message . PHP_EOL;
    flush();
}
