<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Product extends Model
{
    /** Whitelist for sortable columns (never interpolate user input directly). */
    private const SORTABLE = [
        'name'          => 'p.name',
        'category'      => 'c.name',
        'cost_price'    => 'p.cost_price',
        'selling_price' => 'p.selling_price',
        'quantity'      => 'p.quantity',
        'created_at'    => 'p.created_at',
    ];

    /**
     * Filtered, sorted, paginated product list.
     *
     * @param array{q?:string, category_id?:int, stock?:string, sort?:string, dir?:string} $f
     */
    public function search(array $f, int $page, int $perPage = 15): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = '(p.name LIKE :q OR p.barcode LIKE :q OR p.description LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['category_id'])) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = (int) $f['category_id'];
        }
        if (($f['stock'] ?? '') === 'low') {
            $where[] = 'p.quantity <= p.min_stock AND p.quantity > 0';
        } elseif (($f['stock'] ?? '') === 'out') {
            $where[] = 'p.quantity = 0';
        }
        if (($f['price'] ?? '') === 'missing') {
            $where[] = 'p.selling_price IS NULL';
        }

        $orderBy = self::SORTABLE[$f['sort'] ?? ''] ?? 'p.name';
        $dir = strtolower($f['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $whereSql = implode(' AND ', $where);

        return $this->paginate(
            "SELECT p.*, c.name AS category_name,
                    (p.selling_price - p.cost_price) AS profit
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE {$whereSql}
             ORDER BY {$orderBy} {$dir}, p.id",
            "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id
             WHERE {$whereSql}",
            $params,
            $page,
            $perPage
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch(
            'SELECT p.*, c.name AS category_name
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id',
            ['id' => $id]
        );
    }

    public function barcodeExists(string $barcode, int $excludeId = 0): bool
    {
        return (bool) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE barcode = :b AND id <> :id',
            ['b' => $barcode, 'id' => $excludeId]
        );
    }

    /** @param array<string, mixed> $d validated field values */
    public function create(array $d, int $userId): int
    {
        return (int) $this->transaction(function () use ($d, $userId): int {
            $this->execute(
                'INSERT INTO products
                   (category_id, name, description, barcode, cost_price, selling_price,
                    quantity, min_stock, warranty_days)
                 VALUES (:cat, :name, :descr, :barcode, :cost, :price, :qty, :min, :warranty)',
                [
                    'cat'      => $d['category_id'],
                    'name'     => $d['name'],
                    'descr'    => $d['description'] ?: null,
                    'barcode'  => $d['barcode'] ?: null,
                    'cost'     => $d['cost_price'],
                    'price'    => $d['selling_price'],   // may be NULL: "no price yet"
                    'qty'      => $d['quantity'],
                    'min'      => $d['min_stock'],
                    'warranty' => $d['warranty_days'],
                ]
            );
            $id = $this->lastId();

            if ((int) $d['quantity'] !== 0) {
                $this->journalStock($id, (int) $d['quantity'], 'initial', null, 'Opening stock', $userId);
            }

            return $id;
        });
    }

    /** Update product fields. Stock is NOT edited here — use adjustStock(). */
    public function update(int $id, array $d): void
    {
        $this->execute(
            'UPDATE products SET
                category_id = :cat, name = :name, description = :descr, barcode = :barcode,
                cost_price = :cost, selling_price = :price, min_stock = :min,
                warranty_days = :warranty
             WHERE id = :id',
            [
                'cat'      => $d['category_id'],
                'name'     => $d['name'],
                'descr'    => $d['description'] ?: null,
                'barcode'  => $d['barcode'] ?: null,
                'cost'     => $d['cost_price'],
                'price'    => $d['selling_price'],   // may be NULL: "no price yet"
                'min'      => $d['min_stock'],
                'warranty' => $d['warranty_days'],
                'id'       => $id,
            ]
        );
    }

    /**
     * Change stock and journal the movement atomically.
     * $change may be negative. Throws if it would drive stock below zero.
     */
    public function adjustStock(
        int $id,
        int $change,
        string $type,
        ?string $reference,
        ?string $note,
        int $userId
    ): void {
        $this->transaction(function () use ($id, $change, $type, $reference, $note, $userId): void {
            $this->applyStockChange($id, $change, $type, $reference, $note, $userId);
        });
    }

    /**
     * Same as adjustStock() but WITHOUT opening a transaction — for callers
     * (sales, repairs) that already run inside one.
     */
    public function applyStockChange(
        int $id,
        int $change,
        string $type,
        ?string $reference,
        ?string $note,
        int $userId
    ): void {
        // Lock the row so two concurrent sales cannot oversell.
        $row = $this->fetch('SELECT quantity, name FROM products WHERE id = :id FOR UPDATE', ['id' => $id]);
        if ($row === null) {
            throw new \RuntimeException('Product #' . $id . ' not found.');
        }
        $newQty = (int) $row['quantity'] + $change;
        if ($newQty < 0) {
            throw new \RuntimeException(
                'Not enough stock for "' . $row['name'] . '" — only ' . $row['quantity'] . ' left.'
            );
        }

        $this->execute('UPDATE products SET quantity = :q WHERE id = :id', ['q' => $newQty, 'id' => $id]);
        $this->journalStock($id, $change, $type, $reference, $note, $userId);
    }

    private function journalStock(
        int $productId,
        int $change,
        string $type,
        ?string $reference,
        ?string $note,
        int $userId
    ): void {
        $this->execute(
            'INSERT INTO stock_movements (product_id, change_qty, type, reference, note, user_id)
             VALUES (:p, :c, :t, :r, :n, :u)',
            ['p' => $productId, 'c' => $change, 't' => $type, 'r' => $reference, 'n' => $note, 'u' => $userId]
        );
    }

    public function movements(int $productId, int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT sm.*, u.username
             FROM stock_movements sm
             LEFT JOIN users u ON u.id = sm.user_id
             WHERE sm.product_id = :id
             ORDER BY sm.id DESC
             LIMIT ' . max(1, $limit),
            ['id' => $productId]
        );
    }

    /** True if the product appears on any sale or repair (history must survive). */
    public function hasHistory(int $id): bool
    {
        return (bool) $this->fetchValue(
            'SELECT EXISTS(SELECT 1 FROM sale_items WHERE product_id = :a)
                 OR EXISTS(SELECT 1 FROM repair_parts WHERE product_id = :b)',
            ['a' => $id, 'b' => $id]
        );
    }

    public function deactivate(int $id): void
    {
        $this->execute('UPDATE products SET is_active = 0 WHERE id = :id', ['id' => $id]);
    }

    public function hardDelete(int $id): void
    {
        $this->execute('DELETE FROM products WHERE id = :id', ['id' => $id]);
    }

    /** Lightweight search for the POS (name or barcode), JSON-friendly rows. */
    public function posSearch(string $q, int $limit = 12): array
    {
        return $this->fetchAll(
            'SELECT id, name, barcode, selling_price, quantity, warranty_days
             FROM products
             WHERE is_active = 1
               AND quantity > 0
               AND (name LIKE :q OR barcode LIKE :qb)
             ORDER BY name
             LIMIT ' . max(1, $limit),
            ['q' => '%' . $q . '%', 'qb' => $q . '%']
        );
    }

    /** Exact barcode hit for scanner input. */
    public function findByBarcode(string $barcode): ?array
    {
        return $this->fetch(
            'SELECT id, name, barcode, selling_price, quantity, warranty_days
             FROM products
             WHERE is_active = 1 AND barcode = :b',
            ['b' => $barcode]
        );
    }

    /** Lightweight id/name list for filter dropdowns. */
    public function allForSelect(): array
    {
        return $this->fetchAll(
            'SELECT id, name FROM products WHERE is_active = 1 ORDER BY name'
        );
    }

    /** How many active products still have no selling price. */
    public function countWithoutPrice(): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE is_active = 1 AND selling_price IS NULL'
        );
    }
}
