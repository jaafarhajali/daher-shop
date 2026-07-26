<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Expense extends Model
{
    /** Suggested categories (free text is allowed too). */
    public const CATEGORIES = [
        'Rent', 'Electricity', 'Internet', 'Tools', 'Stock purchase',
        'Salaries', 'Marketing', 'Transport', 'General',
    ];

    public function search(array $f, int $page, int $perPage = 15): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = '(name LIKE :q OR notes LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['category'])) {
            $where[] = 'category = :cat';
            $params['cat'] = $f['category'];
        }
        if (!empty($f['from'])) {
            $where[] = 'expense_date >= :dfrom';
            $params['dfrom'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'expense_date <= :dto';
            $params['dto'] = $f['to'];
        }

        $whereSql = implode(' AND ', $where);

        return $this->paginate(
            "SELECT * FROM expenses WHERE {$whereSql} ORDER BY expense_date DESC, id DESC",
            "SELECT COUNT(*) FROM expenses WHERE {$whereSql}",
            $params,
            $page,
            $perPage
        );
    }

    /** Sum matching the same filters (shown above the table). */
    public function totalFor(array $f): float
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['q'])) {
            $where[] = '(name LIKE :q OR notes LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['category'])) {
            $where[] = 'category = :cat';
            $params['cat'] = $f['category'];
        }
        if (!empty($f['from'])) {
            $where[] = 'expense_date >= :dfrom';
            $params['dfrom'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'expense_date <= :dto';
            $params['dto'] = $f['to'];
        }

        return (float) $this->fetchValue(
            'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    /** Distinct categories actually in use (for the filter dropdown). */
    public function usedCategories(): array
    {
        return array_column(
            $this->fetchAll('SELECT DISTINCT category FROM expenses ORDER BY category'),
            'category'
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch('SELECT * FROM expenses WHERE id = :id', ['id' => $id]);
    }

    public function create(array $d, int $userId): int
    {
        $this->execute(
            'INSERT INTO expenses (name, category, amount, expense_date, notes, user_id)
             VALUES (:name, :cat, :amount, :date, :notes, :user)',
            [
                'name'   => $d['name'],
                'cat'    => $d['category'],
                'amount' => $d['amount'],
                'date'   => $d['expense_date'],
                'notes'  => $d['notes'] ?: null,
                'user'   => $userId,
            ]
        );

        return $this->lastId();
    }

    public function update(int $id, array $d): void
    {
        $this->execute(
            'UPDATE expenses SET name = :name, category = :cat, amount = :amount,
                    expense_date = :date, notes = :notes
             WHERE id = :id',
            [
                'name'   => $d['name'],
                'cat'    => $d['category'],
                'amount' => $d['amount'],
                'date'   => $d['expense_date'],
                'notes'  => $d['notes'] ?: null,
                'id'     => $id,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM expenses WHERE id = :id', ['id' => $id]);
    }
}
