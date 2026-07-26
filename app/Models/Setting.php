<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Setting extends Model
{
    /** @return array<string, string> */
    public function all(): array
    {
        $rows = $this->fetchAll('SELECT setting_key, setting_value FROM settings');

        return array_column($rows, 'setting_value', 'setting_key');
    }

    public function set(string $key, string $value): void
    {
        $this->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['k' => $key, 'v' => $value]
        );
    }

    /** @param array<string, string> $pairs */
    public function setMany(array $pairs): void
    {
        $this->transaction(function () use ($pairs): void {
            foreach ($pairs as $key => $value) {
                $this->set($key, $value);
            }
        });
    }
}
