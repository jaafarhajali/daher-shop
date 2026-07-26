<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    public function findByUsername(string $username): ?array
    {
        return $this->fetch(
            'SELECT * FROM users WHERE username = :u LIMIT 1',
            ['u' => $username]
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function updatePassword(int $id, string $plainPassword): void
    {
        $this->execute(
            'UPDATE users SET password_hash = :h WHERE id = :id',
            ['h' => password_hash($plainPassword, PASSWORD_BCRYPT), 'id' => $id]
        );
    }

    public function updateProfile(int $id, string $fullName, ?string $email): void
    {
        $this->execute(
            'UPDATE users SET full_name = :n, email = :e WHERE id = :id',
            ['n' => $fullName, 'e' => $email ?: null, 'id' => $id]
        );
    }
}
