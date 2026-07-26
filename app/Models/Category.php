<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Category extends Model
{
    /** All categories with their product counts. */
    public function allWithCounts(): array
    {
        return $this->fetchAll(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
             GROUP BY c.id
             ORDER BY c.name'
        );
    }

    /** Plain list for <select> options. */
    public function all(): array
    {
        return $this->fetchAll('SELECT id, name FROM categories ORDER BY name');
    }

    public function find(int $id): ?array
    {
        return $this->fetch('SELECT * FROM categories WHERE id = :id', ['id' => $id]);
    }

    public function nameExists(string $name, int $excludeId = 0): bool
    {
        return (bool) $this->fetchValue(
            'SELECT COUNT(*) FROM categories WHERE name = :n AND id <> :id',
            ['n' => $name, 'id' => $excludeId]
        );
    }

    public function create(string $name, ?string $description): int
    {
        $this->execute(
            'INSERT INTO categories (name, description) VALUES (:n, :d)',
            ['n' => $name, 'd' => $description ?: null]
        );

        return $this->lastId();
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $this->execute(
            'UPDATE categories SET name = :n, description = :d WHERE id = :id',
            ['n' => $name, 'd' => $description ?: null, 'id' => $id]
        );
    }

    /** @return int number of products (any status) still attached */
    public function productCount(int $id): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE category_id = :id',
            ['id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM categories WHERE id = :id', ['id' => $id]);
    }
}
