<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Customer credit: outstanding balances and payments against invoices.
 *
 * An invoice is "outstanding" while status = completed AND paid_amount < total.
 * Every payment is journaled in customer_payments and added to sales.paid_amount,
 * so the invoice drops out of the debt lists automatically at zero balance.
 */
final class CreditPayment extends Model
{
    /** Customers who currently owe money, with their totals. */
    public function debtors(): array
    {
        return $this->fetchAll(
            "SELECT c.id, c.name, c.phone,
                    COUNT(s.id) AS open_invoices,
                    SUM(s.total - s.paid_amount) AS outstanding,
                    MIN(s.created_at) AS oldest_invoice_at
             FROM sales s
             JOIN customers c ON c.id = s.customer_id
             WHERE s.status = 'completed' AND s.paid_amount < s.total
             GROUP BY c.id, c.name, c.phone
             ORDER BY outstanding DESC"
        );
    }

    /** Grand total the shop is owed right now. */
    public function totalOutstanding(): float
    {
        return (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total - paid_amount),0)
             FROM sales WHERE status = 'completed' AND paid_amount < total"
        );
    }

    /** Open (not fully paid) invoices for one customer. */
    public function outstandingInvoices(int $customerId): array
    {
        return $this->fetchAll(
            "SELECT id, invoice_no, created_at, total, paid_amount,
                    (total - paid_amount) AS balance
             FROM sales
             WHERE customer_id = :c AND status = 'completed' AND paid_amount < total
             ORDER BY created_at",
            ['c' => $customerId]
        );
    }

    /**
     * Record a payment against a credit invoice (full or partial).
     *
     * @return float the remaining balance after this payment
     * @throws \RuntimeException when the amount is invalid or exceeds the balance
     */
    public function recordPayment(
        int $saleId,
        float $amount,
        string $method,
        string $notes,
        int $userId
    ): float {
        return (float) $this->transaction(
            fn (): float => $this->applyPayment($saleId, $amount, $method, $notes, $userId)
        );
    }

    /**
     * Transaction-less core (callers like returns already run inside one).
     */
    public function applyPayment(
        int $saleId,
        float $amount,
        string $method,
        ?string $notes,
        int $userId
    ): float {
        $sale = $this->fetch(
            'SELECT id, customer_id, total, paid_amount, status
             FROM sales WHERE id = :id FOR UPDATE',
            ['id' => $saleId]
        );
        if ($sale === null || $sale['status'] !== 'completed') {
            throw new \RuntimeException('Invoice not found or not eligible for payments.');
        }

        $balance = round((float) $sale['total'] - (float) $sale['paid_amount'], 2);
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new \RuntimeException('Enter a payment amount greater than zero.');
        }
        if ($amount > $balance + 0.004) {
            throw new \RuntimeException(
                'The payment (' . money($amount) . ') exceeds the remaining balance of '
                . money($balance) . '.'
            );
        }
        if (!in_array($method, ['cash', 'card', 'return_credit'], true)) {
            $method = 'cash';
        }

        $this->execute(
            'INSERT INTO customer_payments (sale_id, customer_id, amount, method, notes, user_id)
             VALUES (:sale, :cust, :amount, :method, :notes, :user)',
            [
                'sale'   => $saleId,
                'cust'   => $sale['customer_id'],
                'amount' => $amount,
                'method' => $method,
                'notes'  => $notes !== null && $notes !== '' ? $notes : null,
                'user'   => $userId,
            ]
        );
        $this->execute(
            'UPDATE sales SET paid_amount = paid_amount + :a WHERE id = :id',
            ['a' => $amount, 'id' => $saleId]
        );

        return round($balance - $amount, 2);
    }

    /** Payment history for one customer (newest first). */
    public function historyForCustomer(int $customerId, int $limit = 30): array
    {
        return $this->fetchAll(
            'SELECT cp.*, s.invoice_no, u.username
             FROM customer_payments cp
             JOIN sales s ON s.id = cp.sale_id
             LEFT JOIN users u ON u.id = cp.user_id
             WHERE cp.customer_id = :c
             ORDER BY cp.id DESC
             LIMIT ' . max(1, $limit),
            ['c' => $customerId]
        );
    }

    /** Payments recorded against one invoice. */
    public function paymentsForSale(int $saleId): array
    {
        return $this->fetchAll(
            'SELECT cp.*, u.username
             FROM customer_payments cp
             LEFT JOIN users u ON u.id = cp.user_id
             WHERE cp.sale_id = :id
             ORDER BY cp.id',
            ['id' => $saleId]
        );
    }

    /**
     * Purchases / paid / outstanding block for the customer profile.
     *
     * purchases   = invoice totals minus return credits (what they truly owe/owed
     *               after giving goods back)
     * paid        = MONEY received (return credits are not money, so excluded)
     * outstanding = still to pay — and purchases − paid = outstanding always holds
     */
    public function customerTotals(int $customerId): array
    {
        $row = $this->fetch(
            "SELECT COALESCE(SUM(total),0) AS gross,
                    COALESCE(SUM(paid_amount),0) AS paid_incl_credits,
                    COALESCE(SUM(total - paid_amount),0) AS outstanding
             FROM sales
             WHERE customer_id = :c AND status = 'completed'",
            ['c' => $customerId]
        );
        $returnCredits = (float) $this->fetchValue(
            "SELECT COALESCE(SUM(cp.amount),0)
             FROM customer_payments cp
             JOIN sales s ON s.id = cp.sale_id
             WHERE cp.method = 'return_credit'
               AND s.customer_id = :c AND s.status = 'completed'",
            ['c' => $customerId]
        );

        return [
            'purchases'   => round((float) ($row['gross'] ?? 0) - $returnCredits, 2),
            'paid'        => round((float) ($row['paid_incl_credits'] ?? 0) - $returnCredits, 2),
            'outstanding' => (float) ($row['outstanding'] ?? 0),
        ];
    }
}
