<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Product returns: goods coming back against a specific invoice.
 *
 * Rules:
 *  · only completed invoices accept returns
 *  · per line, returned quantity can never exceed quantity sold minus
 *    what was already returned (partial returns supported)
 *  · stock-linked items go straight back into inventory (journaled 'return')
 *  · if the invoice still has an unpaid balance (credit sale), the return's
 *    value automatically reduces the debt as a 'return_credit' payment
 */
final class ProductReturn extends Model
{
    /**
     * @param array<int, array{sale_item_id:int, quantity:int}> $items
     * @return int new return id
     */
    public function create(int $saleId, array $items, string $reason, int $userId): int
    {
        return (int) $this->transaction(function () use ($saleId, $items, $reason, $userId): int {
            $sale = $this->fetch(
                'SELECT id, invoice_no, customer_id, status, total, paid_amount
                 FROM sales WHERE id = :id FOR UPDATE',
                ['id' => $saleId]
            );
            if ($sale === null) {
                throw new \RuntimeException('Invoice not found.');
            }
            if ($sale['status'] !== 'completed') {
                throw new \RuntimeException('Returns are only possible on completed invoices.');
            }

            // Wanted quantities keyed by sale_item id.
            $wanted = [];
            foreach ($items as $item) {
                $id = (int) ($item['sale_item_id'] ?? 0);
                $qty = (int) ($item['quantity'] ?? 0);
                if ($id > 0 && $qty > 0) {
                    $wanted[$id] = $qty;
                }
            }
            if ($wanted === []) {
                throw new \RuntimeException('Select at least one item and quantity to return.');
            }

            $saleItems = $this->fetchAll(
                'SELECT si.*,
                        si.quantity - COALESCE((
                            SELECT SUM(ri.quantity) FROM return_items ri
                            WHERE ri.sale_item_id = si.id
                        ), 0) AS returnable
                 FROM sale_items si
                 WHERE si.sale_id = :id',
                ['id' => $saleId]
            );
            $byId = array_column($saleItems, null, 'id');

            $totalValue = 0.0;
            $lines = [];
            foreach ($wanted as $saleItemId => $qty) {
                $si = $byId[$saleItemId] ?? null;
                if ($si === null) {
                    throw new \RuntimeException('One of the selected items does not belong to this invoice.');
                }
                if ($qty > (int) $si['returnable']) {
                    throw new \RuntimeException(
                        'Cannot return ' . $qty . ' × "' . $si['product_name'] . '" — only '
                        . (int) $si['returnable'] . ' of ' . (int) $si['quantity'] . ' sold remain returnable.'
                    );
                }
                $lineTotal = round((float) $si['unit_price'] * $qty, 2);
                $totalValue += $lineTotal;
                $lines[] = [
                    'sale_item_id' => (int) $si['id'],
                    'product_id'   => $si['product_id'] !== null ? (int) $si['product_id'] : null,
                    'name'         => (string) $si['product_name'],
                    'qty'          => $qty,
                    'unit_price'   => (float) $si['unit_price'],
                    'line_total'   => $lineTotal,
                ];
            }

            $this->execute(
                'INSERT INTO product_returns (return_no, sale_id, customer_id, reason, total_value, user_id)
                 VALUES (:no, :sale, :cust, :reason, :value, :user)',
                [
                    'no'     => 'TMP-' . bin2hex(random_bytes(6)),
                    'sale'   => $saleId,
                    'cust'   => $sale['customer_id'],
                    'reason' => $reason,
                    'value'  => round($totalValue, 2),
                    'user'   => $userId,
                ]
            );
            $returnId = $this->lastId();
            $returnNo = 'RTN-' . str_pad((string) $returnId, 6, '0', STR_PAD_LEFT);
            $this->execute(
                'UPDATE product_returns SET return_no = :no WHERE id = :id',
                ['no' => $returnNo, 'id' => $returnId]
            );

            $productModel = new Product();
            foreach ($lines as $line) {
                $this->execute(
                    'INSERT INTO return_items
                        (return_id, sale_item_id, product_id, product_name, quantity, unit_price, line_total)
                     VALUES (:ret, :si, :prod, :name, :qty, :price, :total)',
                    [
                        'ret'   => $returnId,
                        'si'    => $line['sale_item_id'],
                        'prod'  => $line['product_id'],
                        'name'  => $line['name'],
                        'qty'   => $line['qty'],
                        'price' => $line['unit_price'],
                        'total' => $line['line_total'],
                    ]
                );

                // Goods go back on the shelf (only items that still map to a product).
                if ($line['product_id'] !== null) {
                    $productModel->applyStockChange(
                        $line['product_id'],
                        $line['qty'],
                        'return',
                        $returnNo,
                        null,
                        $userId
                    );
                }
            }

            // Credit invoices: returned goods reduce the outstanding debt.
            $outstanding = round((float) $sale['total'] - (float) $sale['paid_amount'], 2);
            if ($outstanding > 0.004 && $totalValue > 0) {
                (new CreditPayment())->applyPayment(
                    $saleId,
                    min($outstanding, round($totalValue, 2)),
                    'return_credit',
                    'Goods returned (' . $returnNo . ')',
                    $userId
                );
            }

            return $returnId;
        });
    }

    public function search(array $f, int $page, int $perPage = 15): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($f['q'])) {
            $where[] = '(pr.return_no LIKE :q1 OR s.invoice_no LIKE :q2 OR c.name LIKE :q3)';
            $like = '%' . $f['q'] . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (!empty($f['from'])) {
            $where[] = 'DATE(pr.created_at) >= :dfrom';
            $params['dfrom'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'DATE(pr.created_at) <= :dto';
            $params['dto'] = $f['to'];
        }

        $whereSql = implode(' AND ', $where);

        return $this->paginate(
            "SELECT pr.*, s.invoice_no, COALESCE(c.name, 'Walk-in customer') AS customer_name,
                    (SELECT COALESCE(SUM(ri.quantity),0) FROM return_items ri
                     WHERE ri.return_id = pr.id) AS item_count
             FROM product_returns pr
             JOIN sales s ON s.id = pr.sale_id
             LEFT JOIN customers c ON c.id = pr.customer_id
             WHERE {$whereSql}
             ORDER BY pr.id DESC",
            "SELECT COUNT(*) FROM product_returns pr
             JOIN sales s ON s.id = pr.sale_id
             LEFT JOIN customers c ON c.id = pr.customer_id
             WHERE {$whereSql}",
            $params,
            $page,
            $perPage
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetch(
            "SELECT pr.*, s.invoice_no, s.id AS sale_id,
                    COALESCE(c.name, 'Walk-in customer') AS customer_name,
                    c.phone AS customer_phone, u.full_name AS processed_by
             FROM product_returns pr
             JOIN sales s ON s.id = pr.sale_id
             LEFT JOIN customers c ON c.id = pr.customer_id
             LEFT JOIN users u ON u.id = pr.user_id
             WHERE pr.id = :id",
            ['id' => $id]
        );
    }

    public function items(int $returnId): array
    {
        return $this->fetchAll(
            'SELECT * FROM return_items WHERE return_id = :id ORDER BY id',
            ['id' => $returnId]
        );
    }

    /** Returns recorded against one invoice (for the invoice page). */
    public function forSale(int $saleId): array
    {
        return $this->fetchAll(
            'SELECT pr.*, (SELECT COALESCE(SUM(ri.quantity),0) FROM return_items ri
                           WHERE ri.return_id = pr.id) AS item_count
             FROM product_returns pr
             WHERE pr.sale_id = :id
             ORDER BY pr.id',
            ['id' => $saleId]
        );
    }
}
