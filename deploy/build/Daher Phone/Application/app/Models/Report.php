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
        'finance'         => 'Financial summary',
        'sales-daily'     => 'Sales by day',
        'sales-list'      => 'Sales — detailed list',
        'profit'          => 'Profit by day (sales + repairs)',
        'credit-out'      => 'Outstanding customer credit',
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
            'finance'         => $this->financeSummary($p),
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

    /**
     * Sales by day — same definitions as the dashboard (Finance model):
     * deductions = refunds + return credits; COGS is net of returned goods.
     */
    private function salesDaily(array $p): array
    {
        $days = (new Finance())->dailyComponents($p['from'], $p['to']);

        $rows = [];
        foreach ($days as $d => $v) {
            $gross = $v['gross_sales'] ?? 0;
            $deductions = ($v['refunds'] ?? 0) + ($v['return_credits'] ?? 0);
            $cogsNet = ($v['cogs_sold'] ?? 0) - ($v['cogs_returned'] ?? 0);
            $netRevenue = $gross - $deductions;
            // Skip days that only carry repair/expense activity — those belong
            // to the profit report; this one is about product sales.
            if ($gross == 0.0 && $deductions == 0.0) {
                continue;
            }
            $rows[] = [
                'sale_date'   => $d,
                'orders'      => (int) ($v['orders'] ?? 0),
                'gross_sales' => round($gross, 2),
                'deductions'  => round($deductions, 2),
                'net_revenue' => round($netRevenue, 2),
                'cogs'        => round($cogsNet, 2),
                'profit'      => round($netRevenue - $cogsNet, 2),
            ];
        }

        return [
            'title'   => 'Sales by day',
            'columns' => [
                'sale_date'   => ['label' => 'Date'],
                'orders'      => ['label' => 'Orders', 'num' => true],
                'gross_sales' => ['label' => 'Gross sales', 'money' => true],
                'deductions'  => ['label' => 'Refunds + return credits', 'money' => true],
                'net_revenue' => ['label' => 'Net revenue', 'money' => true],
                'cogs'        => ['label' => 'Cost of goods (net)', 'money' => true],
                'profit'      => ['label' => 'Profit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['orders', 'gross_sales', 'deductions', 'net_revenue', 'cogs', 'profit']),
            'chart'  => ['x' => 'sale_date', 'y' => 'net_revenue', 'label' => 'Net revenue'],
        ];
    }

    /** One-page financial statement for the chosen period. */
    private function financeSummary(array $p): array
    {
        $s = (new Finance())->summary($p['from'], $p['to']);

        $rows = [
            ['metric' => 'Gross sales',            'amount' => $s['gross_sales'],    'explanation' => 'All completed sale invoices'],
            ['metric' => 'Refunds',                'amount' => -$s['refunds'],       'explanation' => 'Money given back to customers'],
            ['metric' => 'Return credits',         'amount' => -$s['return_credits'],'explanation' => 'Sale value cancelled by returned goods on unpaid invoices'],
            ['metric' => 'Net sales',              'amount' => $s['net_sales'],      'explanation' => 'Gross sales minus refunds and return credits'],
            ['metric' => 'Repair income',          'amount' => $s['repair_revenue'], 'explanation' => 'Delivered repair tickets'],
            ['metric' => 'Total revenue',          'amount' => $s['total_revenue'],  'explanation' => 'Net sales + repair income'],
            ['metric' => 'Cost of goods sold',     'amount' => -$s['cogs_sold'],     'explanation' => 'Cost of every item sold (frozen at sale time)'],
            ['metric' => 'Cost of returned goods', 'amount' => $s['cogs_returned'],  'explanation' => 'Returned items went back to stock, so their cost is recovered'],
            ['metric' => 'Repair parts cost',      'amount' => -($s['repair_revenue'] - $s['repair_profit']), 'explanation' => 'What the shop paid for parts on delivered repairs'],
            ['metric' => 'Gross profit',           'amount' => $s['gross_profit'],   'explanation' => 'Total revenue minus all costs of goods and parts'],
            ['metric' => 'Expenses',               'amount' => -$s['expenses'],      'explanation' => 'Rent, electricity, salaries and other operating costs'],
            ['metric' => 'NET PROFIT',             'amount' => $s['net_profit'],     'explanation' => 'What the shop actually earned in this period'],
        ];

        return [
            'title'   => 'Financial summary',
            'columns' => [
                'metric'      => ['label' => 'Metric'],
                'amount'      => ['label' => 'Amount', 'money' => true],
                'explanation' => ['label' => 'What it means'],
            ],
            'rows'   => $rows,
            'totals' => [],
            'chart'  => null,
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

    /**
     * Profit by day — identical arithmetic to the dashboard (Finance model):
     * sales profit is net of deductions AND credits back the cost of
     * returned goods; expenses land on their own day.
     */
    private function profitDaily(array $p): array
    {
        $days = (new Finance())->dailyComponents($p['from'], $p['to']);

        $rows = [];
        foreach ($days as $d => $v) {
            $netSales = ($v['gross_sales'] ?? 0)
                      - ($v['refunds'] ?? 0) - ($v['return_credits'] ?? 0);
            $cogsNet = ($v['cogs_sold'] ?? 0) - ($v['cogs_returned'] ?? 0);
            $salesProfit = $netSales - $cogsNet;
            $repairProfit = ($v['repair_revenue'] ?? 0) - ($v['repair_parts_cost'] ?? 0);
            $expenses = $v['expenses'] ?? 0;

            $rows[] = [
                'day'           => $d,
                'net_sales'     => round($netSales, 2),
                'sales_profit'  => round($salesProfit, 2),
                'repair_profit' => round($repairProfit, 2),
                'gross_profit'  => round($salesProfit + $repairProfit, 2),
                'expenses'      => round($expenses, 2),
                'net_profit'    => round($salesProfit + $repairProfit - $expenses, 2),
            ];
        }

        return [
            'title'   => 'Profit by day',
            'columns' => [
                'day'           => ['label' => 'Date'],
                'net_sales'     => ['label' => 'Net sales', 'money' => true],
                'sales_profit'  => ['label' => 'Sales profit', 'money' => true],
                'repair_profit' => ['label' => 'Repair profit', 'money' => true],
                'gross_profit'  => ['label' => 'Gross profit', 'money' => true],
                'expenses'      => ['label' => 'Expenses', 'money' => true],
                'net_profit'    => ['label' => 'Net profit', 'money' => true],
            ],
            'rows'   => $rows,
            'totals' => $this->sumColumns($rows, ['net_sales', 'sales_profit', 'repair_profit', 'gross_profit', 'expenses', 'net_profit']),
            'chart'  => ['x' => 'day', 'y' => 'net_profit', 'label' => 'Net profit'],
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
            'title'   => 'Outstanding customer credit',
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
