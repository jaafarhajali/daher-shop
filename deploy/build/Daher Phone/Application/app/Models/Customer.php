<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Customer extends Model
{
    public function search(string $q, int $page, int $perPage = 15): array
    {
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3)';
            $like = '%' . $q . '%';
            $params = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }

        return $this->paginate(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.id) AS sale_count,
                    (SELECT COUNT(*) FROM repairs r WHERE r.customer_id = c.id) AS repair_count
             FROM customers c
             WHERE {$where}
             ORDER BY c.name",
            "SELECT COUNT(*) FROM customers WHERE {$where}",
            $params,
            $page,
            $perPage
        );
    }

    /** For dropdowns / POS quick pick. */
    public function quickSearch(string $q, int $limit = 10): array
    {
        $like = '%' . $q . '%';

        return $this->fetchAll(
            'SELECT id, name, phone FROM customers
             WHERE name LIKE :q1 OR phone LIKE :q2
             ORDER BY name LIMIT ' . max(1, $limit),
            ['q1' => $like, 'q2' => $like]
        );
    }

    public function all(): array
    {
        return $this->fetchAll('SELECT id, name, phone FROM customers ORDER BY name');
    }

    public function find(int $id): ?array
    {
        return $this->fetch('SELECT * FROM customers WHERE id = :id', ['id' => $id]);
    }

    public function create(array $d): int
    {
        $this->execute(
            'INSERT INTO customers (name, phone, email, address, notes)
             VALUES (:name, :phone, :email, :address, :notes)',
            [
                'name'    => $d['name'],
                'phone'   => $d['phone'] ?: null,
                'email'   => $d['email'] ?: null,
                'address' => $d['address'] ?: null,
                'notes'   => $d['notes'] ?: null,
            ]
        );

        return $this->lastId();
    }

    public function update(int $id, array $d): void
    {
        $this->execute(
            'UPDATE customers SET name = :name, phone = :phone, email = :email,
                    address = :address, notes = :notes
             WHERE id = :id',
            [
                'name'    => $d['name'],
                'phone'   => $d['phone'] ?: null,
                'email'   => $d['email'] ?: null,
                'address' => $d['address'] ?: null,
                'notes'   => $d['notes'] ?: null,
                'id'      => $id,
            ]
        );
    }

    /** @return bool false when the customer still has repairs (FK RESTRICT) */
    public function delete(int $id): bool
    {
        $repairs = (int) $this->fetchValue(
            'SELECT COUNT(*) FROM repairs WHERE customer_id = :id',
            ['id' => $id]
        );
        if ($repairs > 0) {
            return false;
        }

        // Sales keep their history: customer_id becomes NULL (walk-in).
        $this->execute('DELETE FROM customers WHERE id = :id', ['id' => $id]);

        return true;
    }

    public function purchaseHistory(int $id, int $limit = 25): array
    {
        return $this->fetchAll(
            'SELECT id, invoice_no, total, paid_amount, status, payment_method, created_at
             FROM sales WHERE customer_id = :id
             ORDER BY id DESC LIMIT ' . max(1, $limit),
            ['id' => $id]
        );
    }

    public function repairHistory(int $id, int $limit = 25): array
    {
        return $this->fetchAll(
            'SELECT id, ticket_no, device_type, brand, model, status, total_cost, paid_amount, received_at
             FROM repairs WHERE customer_id = :id
             ORDER BY id DESC LIMIT ' . max(1, $limit),
            ['id' => $id]
        );
    }

    /** Lifetime value = net sales (after refunds/return credits) + delivered repairs. */
    public function lifetimeValue(int $id): float
    {
        $sales = (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total),0) FROM sales WHERE customer_id = :id AND status = 'completed'",
            ['id' => $id]
        );
        $sales -= (float) $this->fetchValue(
            "SELECT COALESCE(SUM(cp.amount),0) FROM customer_payments cp
             JOIN sales s ON s.id = cp.sale_id
             WHERE cp.method = 'return_credit' AND s.customer_id = :id AND s.status = 'completed'",
            ['id' => $id]
        );
        $sales -= (float) $this->fetchValue(
            "SELECT COALESCE(SUM(r.amount),0) FROM refunds r
             JOIN sales s ON s.id = r.sale_id
             WHERE s.customer_id = :id AND s.status = 'completed'",
            ['id' => $id]
        );
        $repairs = (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total_cost),0) FROM repairs WHERE customer_id = :id AND status = 'delivered'",
            ['id' => $id]
        );

        return $sales + $repairs;
    }
}
