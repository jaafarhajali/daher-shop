<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Report engine. Every report returns the same shape so the view and the
 * exporters can render any of them:
 *
 *   [
 *     'title'   => 'Daily sales',
 *     'columns' => ['date' => ['label' => 'Date'], 'total' => ['label' => 'Total', 'money' => true]],
 *     'rows'    => [...],
 *     'totals'  => ['total' => 123.45],          // keyed by column, optional
 *     'chart'   => ['label' => ..., 'x' => 'date', 'y' => 'total'] | null,
 *   ]
 */
final class Report extends Model
{
    public const TYPES = [
        'sales-daily'     => 'Sales by day',
        'sales-list'      => 'Sales — detailed list',
        'profit'          => 'Profit by day (sales + repairs)',
        'credit-out'      => 'Outstanding customer credit (دين)',
        'credit-payments' => 'Credit payments received',
        'returns'         => 'Product returns',
        'refunds'         => 'Money refunds',
        'warranty'        => 'Warranty expiry',
        'expenses'        => 'Expenses',
        'expenses-cat'    => 'Expenses by category',
        'inventory'       => 'Inventory value',
        'low-stock'       => 'Low stock',
        'repairs'         => 'Repair income',
        'top-products'    => 'Top-selling products',
        'top-customers'   => 'Top customers',
    ];

    /**
     * @param array{from:string, to:string, category_id:int, customer_id:int,
     *              method:string, product_id:int, invoice_no:string} $p
     */
    public function build(string $type, array $p): array
    {
        return match ($type) {
            'sales-daily'     => $this->salesDaily($p),
            'sales-list'      => $this->salesList($p),
            'profit'          => $this->profitDaily($p),
            'credit-out'      => $this->creditOutstanding($p),
            'credit-payments' => $this->creditPayments($p),
            'returns'         => $this->returns($p),
            'refunds'         => $this->refunds($p),
            'warranty'        => $this->warrantyExpiry($p),
            'expenses'        => $this->expenses($p),
            'expenses-cat'    => $this->expensesByCategory($p),
            'inventory'       => $this->inventory($p),
            'low-stock'       => $this->lowStock($p),
            'repairs'         => $this->repairIncome($p),
            'top-products'    => $this->topProducts($p),
            'top-customers'   => $this->topCustomers($p),
            default           => throw new \RuntimeException('Unknown report type.'),
        };
    }

    // ------------------------------------------------------------- reports --

    private function salesDaily(array $p): array
    {
        $rows = $this->fetchAll(
            "SELECT DATE(created_at) AS sale_date,
                    COUNT(*) AS orders,
                    SUM(subtotal) AS subtotal,
                    SUM(discount) AS discount,
                    SUM(total) AS revenue,
                    SUM(total_cost) AS cost,
                    SUM(total - total_cost) AS profit
             FROM sales
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)
             ORDER BY sale_date",
            ['f' => $p['from'], 't' => $p['to']]
        );

        // Refunds issued per day reduce that day's net revenue.
        $refundRows = $this->fetchAll(
            'SELECT DATE(created_at) AS d, SUM(amount) AS t
             FROM refunds
             WHERE DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)',
            ['f' => $p['from'], 't' => $p['to']]
        );
        $refundsByDay = array_column($refundRows, 't', 'd');

        foreach ($rows as &$row) {
            $refunded = (float) ($refundsByDay[$row['sale_date']] ?? 0);
            $row['refunds'] = round($refunded, 2);
            $row['net_revenue'] = round((float) $row['revenue'] - $refunded, 2);
            unset($refundsByDay[$row['sale_date']]);
        }
        unset($row);

        // Days with refunds but no sales still belong in the report.
        foreach ($refundsByDay as $day => $amount) {
            $rows[] = [
                'sale_date' => $day, 'orders' => 0, 'subtotal' => 0, 'discount' => 0,
                'revenue' => 0, 'cost' => 0, 'profit' => 0,
                'refunds' => round((float) $amount, 2),
                'net_revenue' => round(-(float) $amount, 2),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['sale_date'], (string) $b['sale_date']));

        return [
            'title'   => 'Sales by day',
            'columns' => [
                'sale_date'   => ['label' => 'Date'],
                'orders'      => ['label' => 'Orders', 'num' => true],
                'subtotal'    => ['label' => 'Subtotal', 'money' => true],
                'discount'    => ['label' => 'Discounts', 'money' => true],
                'revenue'     => ['label' => 'Revenue', 'money' => true],
                'refunds'     => ['label' => 'Refunds', 'money' => true],
                'net_revenue' => ['label' => 'Net revenue', 'money' => true],
                'cost'        => ['label' => 'Cost of goods', 'money' => true],
                'profit'      => ['label' => 'Profit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['orders', 'subtotal', 'discount', 'revenue', 'refunds', 'net_revenue', 'cost', 'profit']),
            'chart'  => ['x' => 'sale_date', 'y' => 'net_revenue', 'label' => 'Net revenue'],
        ];
    }

    private function salesList(array $p): array
    {
        $where = ["s.status = 'completed'", 'DATE(s.created_at) BETWEEN :f AND :t'];
        $params = ['f' => $p['from'], 't' => $p['to']];

        if ($p['customer_id'] > 0) {
            $where[] = 's.customer_id = :cust';
            $params['cust'] = $p['customer_id'];
        }
        if (in_array($p['method'], ['cash', 'card', 'credit', 'bank_transfer', 'other'], true)) {
            $where[] = 's.payment_method = :method';
            $params['method'] = $p['method'];
        }
        if ($p['invoice_no'] !== '') {
            $where[] = 's.invoice_no LIKE :inv';
            $params['inv'] = '%' . $p['invoice_no'] . '%';
        }

        $rows = $this->fetchAll(
            'SELECT s.invoice_no, s.created_at,
                    COALESCE(c.name, \'Walk-in customer\') AS customer,
                    s.payment_method, s.subtotal, s.discount, s.total,
                    (s.total - s.total_cost) AS profit
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY s.id',
            $params
        );

        foreach ($rows as &$row) {
            $row['payment_method'] = payment_label((string) $row['payment_method']);
        }
        unset($row);

        return [
            'title'   => 'Sales — detailed list',
            'columns' => [
                'invoice_no'     => ['label' => 'Invoice'],
                'created_at'     => ['label' => 'Date'],
                'customer'       => ['label' => 'Customer'],
                'payment_method' => ['label' => 'Payment'],
                'subtotal'       => ['label' => 'Subtotal', 'money' => true],
                'discount'       => ['label' => 'Discount', 'money' => true],
                'total'          => ['label' => 'Total', 'money' => true],
                'profit'         => ['label' => 'Profit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['subtotal', 'discount', 'total', 'profit']),
            'chart'  => null,
        ];
    }

    private function profitDaily(array $p): array
    {
        // One row per day: sales profit + repair profit (repairs count on delivery day).
        $sales = $this->fetchAll(
            "SELECT DATE(created_at) AS d,
                    SUM(total) AS revenue,
                    SUM(total - total_cost) AS profit
             FROM sales
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)",
            ['f' => $p['from'], 't' => $p['to']]
        );
        $repairs = $this->fetchAll(
            "SELECT DATE(r.delivered_at) AS d,
                    SUM(r.total_cost) AS revenue,
                    SUM(r.total_cost - IFNULL(pc.cost, 0)) AS profit
             FROM repairs r
             LEFT JOIN (
                SELECT repair_id, SUM(unit_cost * quantity) AS cost
                FROM repair_parts GROUP BY repair_id
             ) pc ON pc.repair_id = r.id
             WHERE r.status = 'delivered' AND r.delivered_at IS NOT NULL
               AND DATE(r.delivered_at) BETWEEN :f AND :t
             GROUP BY DATE(r.delivered_at)",
            ['f' => $p['from'], 't' => $p['to']]
        );

        $refundRows = $this->fetchAll(
            'SELECT DATE(created_at) AS d, SUM(amount) AS t
             FROM refunds
             WHERE DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)',
            ['f' => $p['from'], 't' => $p['to']]
        );

        $days = [];
        foreach ($sales as $row) {
            $days[$row['d']]['sales_revenue'] = (float) $row['revenue'];
            $days[$row['d']]['sales_profit'] = (float) $row['profit'];
        }
        foreach ($repairs as $row) {
            $days[$row['d']]['repair_revenue'] = (float) $row['revenue'];
            $days[$row['d']]['repair_profit'] = (float) $row['profit'];
        }
        foreach ($refundRows as $row) {
            $days[$row['d']]['refunds'] = (float) $row['t'];
        }
        ksort($days);

        $rows = [];
        foreach ($days as $d => $v) {
            $gross = ($v['sales_profit'] ?? 0) + ($v['repair_profit'] ?? 0);
            $rows[] = [
                'day'            => $d,
                'sales_revenue'  => $v['sales_revenue'] ?? 0,
                'sales_profit'   => $v['sales_profit'] ?? 0,
                'repair_revenue' => $v['repair_revenue'] ?? 0,
                'repair_profit'  => $v['repair_profit'] ?? 0,
                'refunds'        => $v['refunds'] ?? 0,
                'total_profit'   => round($gross - ($v['refunds'] ?? 0), 2),
            ];
        }

        return [
            'title'   => 'Profit by day',
            'columns' => [
                'day'            => ['label' => 'Date'],
                'sales_revenue'  => ['label' => 'Sales revenue', 'money' => true],
                'sales_profit'   => ['label' => 'Sales profit', 'money' => true],
                'repair_revenue' => ['label' => 'Repair revenue', 'money' => true],
                'repair_profit'  => ['label' => 'Repair profit', 'money' => true],
                'refunds'        => ['label' => 'Refunds', 'money' => true],
                'total_profit'   => ['label' => 'Net profit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['sales_revenue', 'sales_profit', 'repair_revenue', 'repair_profit', 'refunds', 'total_profit']),
            'chart'  => ['x' => 'day', 'y' => 'total_profit', 'label' => 'Net profit'],
        ];
    }

    /** Outstanding customer credit — one row per unpaid invoice. */
    private function creditOutstanding(array $p): array
    {
        $where = ["s.status = 'completed'", 's.paid_amount < s.total'];
        $params = [];
        if ($p['customer_id'] > 0) {
            $where[] = 's.customer_id = :cust';
            $params['cust'] = $p['customer_id'];
        }
        if ($p['invoice_no'] !== '') {
            $where[] = 's.invoice_no LIKE :inv';
            $params['inv'] = '%' . $p['invoice_no'] . '%';
        }

        $rows = $this->fetchAll(
            "SELECT s.invoice_no, s.created_at,
                    COALESCE(c.name, 'Walk-in customer') AS customer, c.phone,
                    s.total, s.paid_amount,
                    (s.total - s.paid_amount) AS balance,
                    DATEDIFF(CURDATE(), s.created_at) AS days_old
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY s.created_at',
            $params
        );

        return [
            'title'   => 'Outstanding customer credit (دين)',
            'columns' => [
                'invoice_no'  => ['label' => 'Invoice'],
                'created_at'  => ['label' => 'Date'],
                'customer'    => ['label' => 'Customer'],
                'phone'       => ['label' => 'Phone'],
                'total'       => ['label' => 'Total', 'money' => true],
                'paid_amount' => ['label' => 'Paid', 'money' => true],
                'balance'     => ['label' => 'Balance', 'money' => true],
                'days_old'    => ['label' => 'Days old', 'num' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['total', 'paid_amount', 'balance']),
            'chart'  => null,
        ];
    }

    /** Credit payments received in the period. */
    private function creditPayments(array $p): array
    {
        $where = ['DATE(cp.created_at) BETWEEN :f AND :t'];
        $params = ['f' => $p['from'], 't' => $p['to']];
        if ($p['customer_id'] > 0) {
            $where[] = 'cp.customer_id = :cust';
            $params['cust'] = $p['customer_id'];
        }
        if ($p['invoice_no'] !== '') {
            $where[] = 's.invoice_no LIKE :inv';
            $params['inv'] = '%' . $p['invoice_no'] . '%';
        }

        $rows = $this->fetchAll(
            "SELECT cp.created_at, s.invoice_no,
                    COALESCE(c.name, '—') AS customer,
                    cp.method, cp.amount, cp.notes, u.username AS recorded_by
             FROM customer_payments cp
             JOIN sales s ON s.id = cp.sale_id
             LEFT JOIN customers c ON c.id = cp.customer_id
             LEFT JOIN users u ON u.id = cp.user_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY cp.id',
            $params
        );

        foreach ($rows as &$row) {
            $row['method'] = payment_label((string) $row['method']);
        }
        unset($row);

        return [
            'title'   => 'Credit payments received',
            'columns' => [
                'created_at'  => ['label' => 'Date'],
                'invoice_no'  => ['label' => 'Invoice'],
                'customer'    => ['label' => 'Customer'],
                'method'      => ['label' => 'Method'],
                'amount'      => ['label' => 'Amount', 'money' => true],
                'notes'       => ['label' => 'Notes'],
                'recorded_by' => ['label' => 'Recorded by'],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['amount']),
            'chart'  => null,
        ];
    }

    /** Product returns in the period. */
    private function returns(array $p): array
    {
        $where = ['DATE(pr.created_at) BETWEEN :f AND :t'];
        $params = ['f' => $p['from'], 't' => $p['to']];
        if ($p['customer_id'] > 0) {
            $where[] = 'pr.customer_id = :cust';
            $params['cust'] = $p['customer_id'];
        }
        if ($p['invoice_no'] !== '') {
            $where[] = 's.invoice_no LIKE :inv';
            $params['inv'] = '%' . $p['invoice_no'] . '%';
        }
        if ($p['product_id'] > 0) {
            $where[] = 'EXISTS(SELECT 1 FROM return_items ri
                        WHERE ri.return_id = pr.id AND ri.product_id = :prod)';
            $params['prod'] = $p['product_id'];
        }

        $rows = $this->fetchAll(
            "SELECT pr.return_no, pr.created_at, s.invoice_no,
                    COALESCE(c.name, 'Walk-in customer') AS customer,
                    (SELECT COALESCE(SUM(ri.quantity),0) FROM return_items ri
                     WHERE ri.return_id = pr.id) AS items,
                    pr.total_value, pr.reason, u.username AS processed_by
             FROM product_returns pr
             JOIN sales s ON s.id = pr.sale_id
             LEFT JOIN customers c ON c.id = pr.customer_id
             LEFT JOIN users u ON u.id = pr.user_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY pr.id',
            $params
        );

        return [
            'title'   => 'Product returns',
            'columns' => [
                'return_no'    => ['label' => 'Return'],
                'created_at'   => ['label' => 'Date'],
                'invoice_no'   => ['label' => 'Invoice'],
                'customer'     => ['label' => 'Customer'],
                'items'        => ['label' => 'Items', 'num' => true],
                'total_value'  => ['label' => 'Value', 'money' => true],
                'reason'       => ['label' => 'Reason'],
                'processed_by' => ['label' => 'Processed by'],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['items', 'total_value']),
            'chart'  => null,
        ];
    }

    /** Money refunds in the period. */
    private function refunds(array $p): array
    {
        $where = ['DATE(r.created_at) BETWEEN :f AND :t'];
        $params = ['f' => $p['from'], 't' => $p['to']];
        if ($p['customer_id'] > 0) {
            $where[] = 'r.customer_id = :cust';
            $params['cust'] = $p['customer_id'];
        }
        if ($p['invoice_no'] !== '') {
            $where[] = 's.invoice_no LIKE :inv';
            $params['inv'] = '%' . $p['invoice_no'] . '%';
        }

        $rows = $this->fetchAll(
            "SELECT r.refund_no, r.created_at, s.invoice_no,
                    COALESCE(c.name, 'Walk-in customer') AS customer,
                    r.method, r.amount, r.reason, u.username AS processed_by
             FROM refunds r
             JOIN sales s ON s.id = r.sale_id
             LEFT JOIN customers c ON c.id = r.customer_id
             LEFT JOIN users u ON u.id = r.user_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY r.id',
            $params
        );

        foreach ($rows as &$row) {
            $row['method'] = payment_label((string) $row['method']);
        }
        unset($row);

        return [
            'title'   => 'Money refunds',
            'columns' => [
                'refund_no'    => ['label' => 'Refund'],
                'created_at'   => ['label' => 'Date'],
                'invoice_no'   => ['label' => 'Invoice'],
                'customer'     => ['label' => 'Customer'],
                'method'       => ['label' => 'Method'],
                'amount'       => ['label' => 'Amount', 'money' => true],
                'reason'       => ['label' => 'Reason'],
                'processed_by' => ['label' => 'Processed by'],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['amount']),
            'chart'  => null,
        ];
    }

    /** Warranty expiry — items whose warranty runs out inside the date range. */
    private function warrantyExpiry(array $p): array
    {
        $where = [
            "s.status = 'completed'",
            'si.warranty_expires IS NOT NULL',
            'si.warranty_expires BETWEEN :f AND :t',
        ];
        $params = ['f' => $p['from'], 't' => $p['to']];
        if ($p['customer_id'] > 0) {
            $where[] = 's.customer_id = :cust';
            $params['cust'] = $p['customer_id'];
        }
        if ($p['product_id'] > 0) {
            $where[] = 'si.product_id = :prod';
            $params['prod'] = $p['product_id'];
        }
        if ($p['invoice_no'] !== '') {
            $where[] = 's.invoice_no LIKE :inv';
            $params['inv'] = '%' . $p['invoice_no'] . '%';
        }

        $rows = $this->fetchAll(
            "SELECT si.product_name, s.invoice_no, DATE(s.created_at) AS sold_on,
                    COALESCE(c.name, 'Walk-in customer') AS customer, c.phone,
                    si.warranty_days, si.warranty_expires,
                    CASE WHEN si.warranty_expires >= CURDATE() THEN 'Active' ELSE 'Expired' END AS status
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY si.warranty_expires',
            $params
        );

        return [
            'title'   => 'Warranty expiry',
            'columns' => [
                'product_name'     => ['label' => 'Product'],
                'invoice_no'       => ['label' => 'Invoice'],
                'sold_on'          => ['label' => 'Sold on'],
                'customer'         => ['label' => 'Customer'],
                'phone'            => ['label' => 'Phone'],
                'warranty_days'    => ['label' => 'Days', 'num' => true],
                'warranty_expires' => ['label' => 'Expires'],
                'status'           => ['label' => 'Status'],
            ],
            'rows'   => $rows,
            'totals' => [],
            'chart'  => null,
        ];
    }

    private function expenses(array $p): array
    {
        $rows = $this->fetchAll(
            'SELECT expense_date, name, category, notes, amount
             FROM expenses
             WHERE expense_date BETWEEN :f AND :t
             ORDER BY expense_date, id',
            ['f' => $p['from'], 't' => $p['to']]
        );

        return [
            'title'   => 'Expenses',
            'columns' => [
                'expense_date' => ['label' => 'Date'],
                'name'         => ['label' => 'Expense'],
                'category'     => ['label' => 'Category'],
                'notes'        => ['label' => 'Notes'],
                'amount'       => ['label' => 'Amount', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['amount']),
            'chart'  => null,
        ];
    }

    private function expensesByCategory(array $p): array
    {
        $rows = $this->fetchAll(
            'SELECT category, COUNT(*) AS entries, SUM(amount) AS amount
             FROM expenses
             WHERE expense_date BETWEEN :f AND :t
             GROUP BY category
             ORDER BY amount DESC',
            ['f' => $p['from'], 't' => $p['to']]
        );

        return [
            'title'   => 'Expenses by category',
            'columns' => [
                'category' => ['label' => 'Category'],
                'entries'  => ['label' => 'Entries', 'num' => true],
                'amount'   => ['label' => 'Amount', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['entries', 'amount']),
            'chart'  => null,
        ];
    }

    private function inventory(array $p): array
    {
        $where = ['p.is_active = 1'];
        $params = [];
        if ($p['category_id'] > 0) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = $p['category_id'];
        }

        $rows = $this->fetchAll(
            'SELECT p.name, c.name AS category, p.barcode, p.quantity, p.min_stock,
                    p.cost_price, p.selling_price,
                    (p.quantity * p.cost_price) AS stock_value,
                    (p.quantity * p.selling_price) AS retail_value
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.name, p.name',
            $params
        );

        return [
            'title'   => 'Inventory value',
            'columns' => [
                'name'          => ['label' => 'Product'],
                'category'      => ['label' => 'Category'],
                'barcode'       => ['label' => 'Barcode'],
                'quantity'      => ['label' => 'Qty', 'num' => true],
                'min_stock'     => ['label' => 'Min', 'num' => true],
                'cost_price'    => ['label' => 'Cost', 'money' => true],
                'selling_price' => ['label' => 'Price', 'money' => true],
                'stock_value'   => ['label' => 'Stock value (cost)', 'money' => true],
                'retail_value'  => ['label' => 'Retail value', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['quantity', 'stock_value', 'retail_value']),
            'chart'  => null,
        ];
    }

    private function lowStock(array $p): array
    {
        $where = ['p.is_active = 1', 'p.quantity <= p.min_stock'];
        $params = [];
        if ($p['category_id'] > 0) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = $p['category_id'];
        }

        $rows = $this->fetchAll(
            'SELECT p.name, c.name AS category, p.quantity, p.min_stock,
                    (p.min_stock - p.quantity) AS shortfall, p.cost_price
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY shortfall DESC, p.name',
            $params
        );

        return [
            'title'   => 'Low stock',
            'columns' => [
                'name'       => ['label' => 'Product'],
                'category'   => ['label' => 'Category'],
                'quantity'   => ['label' => 'In stock', 'num' => true],
                'min_stock'  => ['label' => 'Minimum', 'num' => true],
                'shortfall'  => ['label' => 'Shortfall', 'num' => true],
                'cost_price' => ['label' => 'Restock cost/unit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => [],
            'chart'  => null,
        ];
    }

    private function repairIncome(array $p): array
    {
        $rows = $this->fetchAll(
            "SELECT r.ticket_no, r.delivered_at, c.name AS customer,
                    CONCAT_WS(' ', r.device_type, r.brand, r.model) AS device,
                    r.labor_cost, r.parts_cost, r.total_cost,
                    IFNULL(pc.cost, 0) AS parts_actual_cost,
                    (r.total_cost - IFNULL(pc.cost, 0)) AS profit
             FROM repairs r
             JOIN customers c ON c.id = r.customer_id
             LEFT JOIN (
                SELECT repair_id, SUM(unit_cost * quantity) AS cost
                FROM repair_parts GROUP BY repair_id
             ) pc ON pc.repair_id = r.id
             WHERE r.status = 'delivered' AND r.delivered_at IS NOT NULL
               AND DATE(r.delivered_at) BETWEEN :f AND :t
             ORDER BY r.delivered_at",
            ['f' => $p['from'], 't' => $p['to']]
        );

        return [
            'title'   => 'Repair income (delivered tickets)',
            'columns' => [
                'ticket_no'         => ['label' => 'Ticket'],
                'delivered_at'      => ['label' => 'Delivered'],
                'customer'          => ['label' => 'Customer'],
                'device'            => ['label' => 'Device'],
                'labor_cost'        => ['label' => 'Labor', 'money' => true],
                'parts_cost'        => ['label' => 'Parts charged', 'money' => true],
                'total_cost'        => ['label' => 'Total charged', 'money' => true],
                'parts_actual_cost' => ['label' => 'Parts cost', 'money' => true],
                'profit'            => ['label' => 'Profit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['labor_cost', 'parts_cost', 'total_cost', 'parts_actual_cost', 'profit']),
            'chart'  => null,
        ];
    }

    private function topProducts(array $p): array
    {
        $rows = $this->fetchAll(
            "SELECT si.product_name,
                    SUM(si.quantity) AS qty_sold,
                    SUM(si.line_total) AS revenue,
                    SUM(si.line_total - si.unit_cost * si.quantity) AS profit
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE s.status = 'completed' AND DATE(s.created_at) BETWEEN :f AND :t
             GROUP BY si.product_name
             ORDER BY revenue DESC
             LIMIT 50",
            ['f' => $p['from'], 't' => $p['to']]
        );

        return [
            'title'   => 'Top-selling products',
            'columns' => [
                'product_name' => ['label' => 'Product'],
                'qty_sold'     => ['label' => 'Units sold', 'num' => true],
                'revenue'      => ['label' => 'Revenue', 'money' => true],
                'profit'       => ['label' => 'Profit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['qty_sold', 'revenue', 'profit']),
            'chart'  => null,
        ];
    }

    private function topCustomers(array $p): array
    {
        $rows = $this->fetchAll(
            "SELECT c.name AS customer, c.phone,
                    COALESCE(sa.orders, 0) AS orders,
                    COALESCE(sa.spent, 0) AS purchases,
                    COALESCE(re.repairs, 0) AS repairs,
                    COALESCE(re.spent, 0) AS repair_spend,
                    COALESCE(sa.spent, 0) + COALESCE(re.spent, 0) AS total_spend
             FROM customers c
             LEFT JOIN (
                SELECT customer_id, COUNT(*) AS orders, SUM(total) AS spent
                FROM sales
                WHERE status = 'completed' AND DATE(created_at) BETWEEN :f1 AND :t1
                GROUP BY customer_id
             ) sa ON sa.customer_id = c.id
             LEFT JOIN (
                SELECT customer_id, COUNT(*) AS repairs, SUM(total_cost) AS spent
                FROM repairs
                WHERE status = 'delivered' AND delivered_at IS NOT NULL
                  AND DATE(delivered_at) BETWEEN :f2 AND :t2
                GROUP BY customer_id
             ) re ON re.customer_id = c.id
             HAVING total_spend > 0
             ORDER BY total_spend DESC
             LIMIT 50",
            ['f1' => $p['from'], 't1' => $p['to'], 'f2' => $p['from'], 't2' => $p['to']]
        );

        return [
            'title'   => 'Top customers',
            'columns' => [
                'customer'     => ['label' => 'Customer'],
                'phone'        => ['label' => 'Phone'],
                'orders'       => ['label' => 'Orders', 'num' => true],
                'purchases'    => ['label' => 'Purchases', 'money' => true],
                'repairs'      => ['label' => 'Repairs', 'num' => true],
                'repair_spend' => ['label' => 'Repair spend', 'money' => true],
                'total_spend'  => ['label' => 'Total spend', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['orders', 'purchases', 'repairs', 'repair_spend', 'total_spend']),
            'chart'  => null,
        ];
    }

    // ------------------------------------------------------------- helpers --

    /** @param string[] $keys */
    private function sumColumns(array $rows, array $keys): array
    {
        $totals = array_fill_keys($keys, 0.0);
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $totals[$key] += (float) ($row[$key] ?? 0);
            }
        }

        return $totals;
    }
}
