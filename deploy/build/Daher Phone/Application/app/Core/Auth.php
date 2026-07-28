<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Session-based authentication with login throttling.
 */
final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300; // 5 minutes

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    /** @return array{id:int, username:string, full_name:string, role:string}|null */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id'        => (int) $_SESSION['user_id'],
            'username'  => (string) $_SESSION['username'],
            'full_name' => (string) $_SESSION['full_name'],
            'role'      => (string) $_SESSION['role'],
        ];
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    /**
     * @return string|null null on success, otherwise a user-facing error message
     */
    public static function attempt(string $username, string $password): ?string
    {
        if (self::isLockedOut()) {
            $wait = (int) ceil((($_SESSION['lockout_until'] ?? 0) - time()) / 60);

            return "Too many failed attempts. Try again in about {$wait} minute(s).";
        }

        $user = (new User())->findByUsername($username);

        if ($user === null
            || !password_verify($password, $user['password_hash'])
            || (int) $user['is_active'] !== 1) {
            self::recordFailure();

            return 'Invalid username or password.';
        }

        // Success: reset throttle, rotate the session ID, store identity.
        unset($_SESSION['failed_logins'], $_SESSION['lockout_until']);
        session_regenerate_id(true);

        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];

        (new User())->touchLastLogin((int) $user['id']);

        // Rehash transparently if PHP's default cost changed.
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            (new User())->updatePassword((int) $user['id'], $password);
        }

        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    private static function isLockedOut(): bool
    {
        return isset($_SESSION['lockout_until']) && time() < (int) $_SESSION['lockout_until'];
    }

    private static function recordFailure(): void
    {
        $_SESSION['failed_logins'] = (int) ($_SESSION['failed_logins'] ?? 0) + 1;
        if ($_SESSION['failed_logins'] >= self::MAX_ATTEMPTS) {
            $_SESSION['lockout_until'] = time() + self::LOCKOUT_SECONDS;
            $_SESSION['failed_logins'] = 0;
        }
    }

    private function __construct()
    {
    }
}
