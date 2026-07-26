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
                    'SELECT id, name, cost_price, selling_price, quantity, is_active
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

                // Price may be overridden at the till; cost is always the DB truth.
                $unitPrice = round(max(0.0, (float) $item['unit_price']), 2);
                $unitCost = (float) $product['cost_price'];
                $lineTotal = round($unitPrice * $qty, 2);

                $subtotal += $lineTotal;
                $totalCost += $unitCost * $qty;

                $lines[] = [
                    'product_id' => (int) $product['id'],
                    'name'       => (string) $product['name'],
                    'qty'        => $qty,
                    'price'      => $unitPrice,
                    'cost'       => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = round(min(max(0.0, $header['discount']), $subtotal), 2);
            $total = round($subtotal - $discount, 2);

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
                    'paid'   => $total,
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
                        (sale_id, product_id, product_name, quantity, unit_price, unit_cost, line_total)
                     VALUES (:sale, :prod, :name, :qty, :price, :cost, :total)',
                    [
                        'sale'  => $saleId,
                        'prod'  => $line['product_id'],
                        'name'  => $line['name'],
                        'qty'   => $line['qty'],
                        'price' => $line['price'],
                        'cost'  => $line['cost'],
                        'total' => $line['line_total'],
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
        if (!empty($f['method']) && in_array($f['method'], ['cash', 'card', 'bank_transfer', 'other'], true)) {
            $where[] = 's.payment_method = :method';
            $params['method'] = $f['method'];
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

    public function items(int $saleId): array
    {
        return $this->fetchAll(
            'SELECT * FROM sale_items WHERE sale_id = :id ORDER BY id',
            ['id' => $saleId]
        );
    }
}
