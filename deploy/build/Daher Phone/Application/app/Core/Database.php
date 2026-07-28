<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Shared PDO connection (lazy singleton).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Never leak credentials in the trace.
                throw new PDOException(
                    'Database connection failed. Check that MySQL is running in the '
                    . 'XAMPP Control Panel and that config/config.php credentials are '
                    . 'correct. Driver said: ' . $e->getMessage()
                );
            }
        }

        return self::$pdo;
    }

    private function __construct()
    {
    }
}
