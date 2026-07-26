<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Sale extends Model
{
    /**
     * Create a completed sale atomically:
     * insert header → number it → insert items (cost snapshot) → deduct stock.
     *
     * @param array{customer_id:?int, discount:float, payment_method:string, notes:string} $header
     * @param array<int, array{product_id:int, quantity:int, unit_price:float}> $items
     * @return int new sale id
     * @throws \RuntimeException on stock shortage or bad product
     */
    public function createSale(array $header, array $items, int $userId): int
    {
        return (int) $this->transaction(function () use ($header, $items, $userId): int {
            $productModel = new Product();

            $subtotal = 0.0;
            $totalCost = 0.0;
            $lines = [];

            foreach ($items as $item) {
                $product = $this->fetch(
                    'SELECT id, name, cost_price, selling_price, quantity, is_active, warranty_days
                     FROM products WHERE id = :id FOR UPDATE',
                    ['id' => $item['product_id']]
                );
                if ($product === null || (int) $product['is_active'] !== 1) {
                    throw new \RuntimeException('A product in the cart no longer exists.');
                }

                $qty = (int) $item['quantity'];
                if ($qty < 1) {
                    throw new \RuntimeException('Quantities must be at least 1.');
                }

                // The ACTUAL sale price is whatever the till submits (the product's
                // default is only a suggestion). Cost is always the DB truth.
                if ((float) $item['unit_price'] < 0) {
                    throw new \RuntimeException('Sale prices cannot be negative.');
                }
                $unitPrice = round((float) $item['unit_price'], 2);

                // A product with no default price needs a real price typed at the till.
                if ($product['selling_price'] === null && $unitPrice <= 0) {
                    throw new \RuntimeException(
                        'This product does not have a selling price. Please enter a selling '
                        . 'price before completing the sale. (' . $product['name'] . ')'
                    );
                }
                $unitCost = (float) $product['cost_price'];
                $lineTotal = round($unitPrice * $qty, 2);

                $subtotal += $lineTotal;
                $totalCost += $unitCost * $qty;

                // Warranty starts on the sale date and runs for the product's days.
                $warrantyDays = (int) $product['warranty_days'];
                $lines[] = [
                    'product_id'       => (int) $product['id'],
                    'name'             => (string) $product['name'],
                    'qty'              => $qty,
                    'price'            => $unitPrice,
                    'cost'             => $unitCost,
                    'line_total'       => $lineTotal,
                    'warranty_days'    => $warrantyDays,
                    'warranty_expires' => $warrantyDays > 0
                        ? date('Y-m-d', strtotime('+' . $warrantyDays . ' days'))
                        : null,
                ];
            }

            $discount = round(min(max(0.0, $header['discount']), $subtotal), 2);
            $total = round($subtotal - $discount, 2);

            // Credit (دين) sales: nothing paid yet, and the debt needs a customer.
            $isCredit = $header['payment_method'] === 'credit';
            if ($isCredit && empty($header['customer_id'])) {
                throw new \RuntimeException(
                    'Credit (دين) sales must have a customer — please select the customer first.'
                );
            }

            // Header first (placeholder number → real number derived from the id).
            $this->execute(
                'INSERT INTO sales
                    (invoice_no, customer_id, user_id, subtotal, discount, total,
                     total_cost, paid_amount, payment_method, status, notes)
                 VALUES (:no, :cust, :user, :sub, :disc, :total, :cost, :paid, :method, \'completed\', :notes)',
                [
                    'no'     => 'TMP-' . bin2hex(random_bytes(6)),
                    'cust'   => $header['customer_id'],
                    'user'   => $userId,
                    'sub'    => round($subtotal, 2),
                    'disc'   => $discount,
                    'total'  => $total,
                    'cost'   => round($totalCost, 2),
                    'paid'   => $isCredit ? 0.00 : $total,
                    'method' => $header['payment_method'],
                    'notes'  => $header['notes'] !== '' ? $header['notes'] : null,
                ]
            );
            $saleId = $this->lastId();
            $invoiceNo = 'INV-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
            $this->execute(
                'UPDATE sales SET invoice_no = :no WHERE id = :id',
                ['no' => $invoiceNo, 'id' => $saleId]
            );

            foreach ($lines as $line) {
                $this->execute(
                    'INSERT INTO sale_items
                        (sale_id, product_id, product_name, quantity, unit_price, unit_cost,
                         line_total, warranty_days, warranty_expires)
                     VALUES (:sale, :prod, :name, :qty, :price, :cost, :total, :wdays, :wexp)',
                    [
                        'sale'  => $saleId,
                        'prod'  => $line['product_id'],
                        'name'  => $line['name'],
                        'qty'   => $line['qty'],
                        'price' => $line['price'],
                        'cost'  => $line['cost'],
                        'total' => $line['line_total'],
                        'wdays' => $line['warranty_days'],
                        'wexp'  => $line['warranty_expires'],
                    ]
                );

                $productModel->applyStockChange(
                    $line['product_id'],
                    -$line['qty'],
                    'sale',
                    $invoiceNo,
                    null,
                    $userId
                );
            }

            return $saleId;
        });
    }

    /**
     * Cancel a completed sale and restock every line item.
     */
    public function cancel(int $saleId, int $userId): void
    {
        $this->transaction(function () use ($saleId, $userId): void {
            $sale = $this->fetch(
                'SELECT id, invoice_no, status FROM sales WHERE id = :id FOR UPDATE',
                ['id' => $saleId]
            );
            if ($sale === null || $sale['status'] !== 'completed') {
                throw new \RuntimeException('Only completed sales can be cancelled.');
            }

            // An invoice with money or goods movements must stay on the books —
            // use returns / refunds instead of cancelling it outright.
            $hasActivity = (bool) $this->fetchValue(
                'SELECT EXISTS(SELECT 1 FROM customer_payments WHERE sale_id = :a)
                     OR EXISTS(SELECT 1 FROM product_returns  WHERE sale_id = :b)
                     OR EXISTS(SELECT 1 FROM refunds          WHERE sale_id = :c)',
                ['a' => $saleId, 'b' => $saleId, 'c' => $saleId]
            );
            if ($hasActivity) {
                throw new \RuntimeException(
                    'This invoice already has payments, returns or refunds recorded. '
                    . 'Use a return or refund instead of cancelling it.'
                );
            }

            $this->execute(
                "UPDATE sales SET status = 'cancelled' WHERE id = :id",
                ['id' => $saleId]
            );

            $productModel = new Product();
            $items = $this->fetchAll(
                'SELECT product_id, quantity FROM sale_items WHERE sale_id = :id',
                ['id' => $saleId]
            );
            foreach ($items as $item) {
                if ($item['product_id'] !== null) {
                    $productModel->applyStockChange(
                        (int) $item['product_id'],
                        (int) $item['quantity'],
                        'sale_cancel',
                        (string) $sale['invoice_no'],
                        'Sale cancelled',
                        $userId
                    );
                }
            }
        });
    }

    /**
     * Filtered, paginated sales list.
     *
     * @param array{q?:string, from?:string, to?:string, status?:string, method?:string} $f
     */
    public function search(array $f, int $page, int $perPage = 15): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = '(s.invoice_no LIKE :q OR c.name LIKE :q)';
            $params['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['from'])) {
            $where[] = 'DATE(s.created_at) >= :dfrom';
            $params['dfrom'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'DATE(s.created_at) <= :dto';
            $params['dto'] = $f['to'];
        }
        if (!empty($f['status']) && in_array($f['status'], ['completed', 'cancelled'], true)) {
            $where[] = 's.status = :status';
            $params['status'] = $f['status'];
        }
        if (!empty($f['method'])
            && in_array($f['method'], ['cash', 'card', 'credit', 'bank_transfer', 'other'], true)) {
            $where[] = 's.payment_method = :method';
            $params['method'] = $f['method'];
        }
        if (($f['pay'] ?? '') === 'outstanding') {
            $where[] = "s.status = 'completed' AND s.paid_amount < s.total";
        }

        $whereSql = implode(' AND ', $where);

        return $this->paginate(
            "SELECT s.*, COALESCE(c.name, 'Walk-in customer') AS customer_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE {$whereSql}
             ORDER BY s.id DESC",
            "SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON c.id = s.customer_id
             WHERE {$whereSql}",
            $params,
            $page,
            $perPage
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch(
            "SELECT s.*, c.name AS customer_name, c.phone AS customer_phone,
                    c.address AS customer_address, u.full_name AS cashier_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.id = :id",
            ['id' => $id]
        );
    }

    /** Look an invoice up by its human number (INV-000123), case-insensitive. */
    public function findByInvoiceNo(string $invoiceNo): ?array
    {
        $row = $this->fetch(
            'SELECT id FROM sales WHERE invoice_no = :no',
            ['no' => strtoupper(trim($invoiceNo))]
        );

        return $row === null ? null : $this->find((int) $row['id']);
    }

    public function items(int $saleId): array
    {
        return $this->fetchAll(
            'SELECT * FROM sale_items WHERE sale_id = :id ORDER BY id',
            ['id' => $saleId]
        );
    }

    /**
     * Items with how many units are still returnable (sold − already returned).
     */
    public function itemsWithReturnable(int $saleId): array
    {
        return $this->fetchAll(
            'SELECT si.*,
                    si.quantity - COALESCE((
                        SELECT SUM(ri.quantity) FROM return_items ri
                        WHERE ri.sale_item_id = si.id
                    ), 0) AS returnable
             FROM sale_items si
             WHERE si.sale_id = :id
             ORDER BY si.id',
            ['id' => $saleId]
        );
    }

    /** Money already refunded against this invoice. */
    public function refundedTotal(int $saleId): float
    {
        return (float) $this->fetchValue(
            'SELECT COALESCE(SUM(amount),0) FROM refunds WHERE sale_id = :id',
            ['id' => $saleId]
        );
    }
}
