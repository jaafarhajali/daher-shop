<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Money refunds against an invoice (full or partial).
 *
 * Rule: you can only refund money that was actually received —
 * refundable = paid_amount − refunds already given. Because paid_amount
 * never exceeds the invoice total, a refund can never exceed it either.
 */
final class Refund extends Model
{
    public function create(
        int $saleId,
        float $amount,
        string $reason,
        string $method,
        int $userId
    ): int {
        return (int) $this->transaction(function () use ($saleId, $amount, $reason, $method, $userId): int {
            $sale = $this->fetch(
                'SELECT id, invoice_no, customer_id, status, total, paid_amount
                 FROM sales WHERE id = :id FOR UPDATE',
                ['id' => $saleId]
            );
            if ($sale === null) {
                throw new \RuntimeException('Invoice not found.');
            }
            if ($sale['status'] !== 'completed') {
                throw new \RuntimeException('Refunds are only possible on completed invoices.');
            }

            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw new \RuntimeException('Enter a refund amount greater than zero.');
            }

            // Refundable = money ACTUALLY received − already refunded.
            // paid_amount alone would overstate it: it also contains return
            // credits (debt reduced by returned goods), which is not cash.
            $returnCredits = (float) $this->fetchValue(
                "SELECT COALESCE(SUM(amount),0) FROM customer_payments
                 WHERE sale_id = :id AND method = 'return_credit'",
                ['id' => $saleId]
            );
            $alreadyRefunded = (float) $this->fetchValue(
                'SELECT COALESCE(SUM(amount),0) FROM refunds WHERE sale_id = :id',
                ['id' => $saleId]
            );
            $refundable = round((float) $sale['paid_amount'] - $returnCredits - $alreadyRefunded, 2);

            if ($amount > $refundable + 0.004) {
                throw new \RuntimeException(
                    'The refund (' . money($amount) . ') exceeds the refundable amount of '
                    . money(max(0, $refundable)) . ' — only money actually received can be refunded.'
                );
            }
            if (!in_array($method, ['cash', 'card'], true)) {
                $method = 'cash';
            }

            $this->execute(
                'INSERT INTO refunds (refund_no, sale_id, customer_id, amount, reason, method, user_id)
                 VALUES (:no, :sale, :cust, :amount, :reason, :method, :user)',
                [
                    'no'     => 'TMP-' . bin2hex(random_bytes(6)),
                    'sale'   => $saleId,
                    'cust'   => $sale['customer_id'],
                    'amount' => $amount,
                    'reason' => $reason,
                    'method' => $method,
                    'user'   => $userId,
                ]
            );
            $refundId = $this->lastId();
            $this->execute(
                'UPDATE refunds SET refund_no = :no WHERE id = :id',
                ['no' => 'RFD-' . str_pad((string) $refundId, 6, '0', STR_PAD_LEFT), 'id' => $refundId]
            );

            return $refundId;
        });
    }

    public function search(array $f, int $page, int $perPage = 15): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = '(r.refund_no LIKE :q OR s.invoice_no LIKE :q OR c.name LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['from'])) {
            $where[] = 'DATE(r.created_at) >= :dfrom';
            $params['dfrom'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'DATE(r.created_at) <= :dto';
            $params['dto'] = $f['to'];
        }

        $whereSql = implode(' AND ', $where);

        return $this->paginate(
            "SELECT r.*, s.invoice_no, COALESCE(c.name, 'Walk-in customer') AS customer_name
             FROM refunds r
             JOIN sales s ON s.id = r.sale_id
             LEFT JOIN customers c ON c.id = r.customer_id
             WHERE {$whereSql}
             ORDER BY r.id DESC",
            "SELECT COUNT(*) FROM refunds r
             JOIN sales s ON s.id = r.sale_id
             LEFT JOIN customers c ON c.id = r.customer_id
             WHERE {$whereSql}",
            $params,
            $page,
            $perPage
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch(
            "SELECT r.*, s.invoice_no, s.total AS sale_total,
                    COALESCE(c.name, 'Walk-in customer') AS customer_name,
                    c.phone AS customer_phone, u.full_name AS processed_by
             FROM refunds r
             JOIN sales s ON s.id = r.sale_id
             LEFT JOIN customers c ON c.id = r.customer_id
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.id = :id",
            ['id' => $id]
        );
    }

    /** Refunds recorded against one invoice (for the invoice page). */
    public function forSale(int $saleId): array
    {
        return $this->fetchAll(
            'SELECT * FROM refunds WHERE sale_id = :id ORDER BY id',
            ['id' => $saleId]
        );
    }
}
