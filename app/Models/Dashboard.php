<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Read-only aggregate queries that power the dashboard.
 *
 * Revenue definition: completed sales + delivered repairs.
 * Gross profit: (sale total − snapshotted cost) + (repair charge − parts cost).
 */
final class Dashboard extends Model
{
    public function todaySalesTotal(): float
    {
        return (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total),0) FROM sales
             WHERE status = 'completed' AND DATE(created_at) = CURDATE()"
        );
    }

    public function monthSalesTotal(): float
    {
        return (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total),0) FROM sales
             WHERE status = 'completed'
               AND YEAR(created_at) = YEAR(CURDATE())
               AND MONTH(created_at) = MONTH(CURDATE())"
        );
    }

    public function totalRevenue(): float
    {
        $sales = (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total),0) FROM sales WHERE status = 'completed'"
        );
        $repairs = (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total_cost),0) FROM repairs WHERE status = 'delivered'"
        );

        return $sales + $repairs;
    }

    public function totalGrossProfit(): float
    {
        $sales = (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total - total_cost),0) FROM sales WHERE status = 'completed'"
        );
        $repairs = (float) $this->fetchValue(
            "SELECT COALESCE(SUM(r.total_cost - IFNULL(pc.cost,0)),0)
             FROM repairs r
             LEFT JOIN (
                SELECT repair_id, SUM(unit_cost * quantity) AS cost
                FROM repair_parts GROUP BY repair_id
             ) pc ON pc.repair_id = r.id
             WHERE r.status = 'delivered'"
        );

        return $sales + $repairs;
    }

    public function productCount(): int
    {
        return (int) $this->fetchValue('SELECT COUNT(*) FROM products WHERE is_active = 1');
    }

    public function lowStockCount(): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE is_active = 1 AND quantity <= min_stock'
        );
    }

    public function pendingRepairCount(): int
    {
        return (int) $this->fetchValue(
            "SELECT COUNT(*) FROM repairs
             WHERE status IN ('received','diagnosing','repairing','ready')"
        );
    }

    public function monthExpensesTotal(): float
    {
        return (float) $this->fetchValue(
            'SELECT COALESCE(SUM(amount),0) FROM expenses
             WHERE YEAR(expense_date) = YEAR(CURDATE())
               AND MONTH(expense_date) = MONTH(CURDATE())'
        );
    }

    /** @return array{labels: string[], values: float[]} 14-day sales trend */
    public function salesTrend14d(): array
    {
        $rows = $this->fetchAll(
            "SELECT DATE(created_at) AS d, SUM(total) AS t
             FROM sales
             WHERE status = 'completed'
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at)"
        );
        $byDay = array_column($rows, 't', 'd');

        $labels = [];
        $values = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('d M', strtotime($day));
            $values[] = round((float) ($byDay[$day] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: string[], revenue: float[], expenses: float[]} last 6 months */
    public function revenueVsExpenses6m(): array
    {
        $salesRows = $this->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, SUM(total) AS t
             FROM sales
             WHERE status = 'completed'
               AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $repairRows = $this->fetchAll(
            "SELECT DATE_FORMAT(delivered_at, '%Y-%m') AS m, SUM(total_cost) AS t
             FROM repairs
             WHERE status = 'delivered' AND delivered_at IS NOT NULL
               AND delivered_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(delivered_at, '%Y-%m')"
        );
        $expenseRows = $this->fetchAll(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS m, SUM(amount) AS t
             FROM expenses
             WHERE expense_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(expense_date, '%Y-%m')"
        );

        $sales    = array_column($salesRows, 't', 'm');
        $repairs  = array_column($repairRows, 't', 'm');
        $expenses = array_column($expenseRows, 't', 'm');

        $labels = [];
        $rev = [];
        $exp = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("first day of -{$i} months"));
            $labels[] = date('M Y', strtotime($key . '-01'));
            $rev[] = round((float) ($sales[$key] ?? 0) + (float) ($repairs[$key] ?? 0), 2);
            $exp[] = round((float) ($expenses[$key] ?? 0), 2);
        }

        return ['labels' => $labels, 'revenue' => $rev, 'expenses' => $exp];
    }

    /** Top products by revenue, last 30 days. */
    public function topProducts30d(int $limit = 5): array
    {
        return $this->fetchAll(
            "SELECT si.product_name, SUM(si.quantity) AS qty, SUM(si.line_total) AS revenue
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE s.status = 'completed'
               AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY si.product_name
             ORDER BY revenue DESC
             LIMIT " . max(1, $limit)
        );
    }

    public function recentSales(int $limit = 8): array
    {
        return $this->fetchAll(
            "SELECT s.id, s.invoice_no, s.total, s.status, s.created_at,
                    COALESCE(c.name, 'Walk-in customer') AS customer_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             ORDER BY s.id DESC
             LIMIT " . max(1, $limit)
        );
    }

    public function activeRepairs(int $limit = 6): array
    {
        return $this->fetchAll(
            "SELECT r.id, r.ticket_no, r.device_type, r.brand, r.model, r.status,
                    r.received_at, c.name AS customer_name
             FROM repairs r
             JOIN customers c ON c.id = r.customer_id
             WHERE r.status IN ('received','diagnosing','repairing','ready')
             ORDER BY r.received_at ASC
             LIMIT " . max(1, $limit)
        );
    }

    public function lowStockList(int $limit = 6): array
    {
        return $this->fetchAll(
            'SELECT id, name, quantity, min_stock
             FROM products
             WHERE is_active = 1 AND quantity <= min_stock
             ORDER BY (quantity - min_stock) ASC
             LIMIT ' . max(1, $limit)
        );
    }

    // ------------------------------------------------- v1.1: credit & after-sales --

    public function outstandingCreditTotal(): float
    {
        return (float) $this->fetchValue(
            "SELECT COALESCE(SUM(total - paid_amount),0)
             FROM sales WHERE status = 'completed' AND paid_amount < total"
        );
    }

    public function returnsTotal30d(): float
    {
        return (float) $this->fetchValue(
            'SELECT COALESCE(SUM(total_value),0) FROM product_returns
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
    }

    public function refundsTotal30d(): float
    {
        return (float) $this->fetchValue(
            'SELECT COALESCE(SUM(amount),0) FROM refunds
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
    }

    public function productsWithoutPriceCount(): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM products WHERE is_active = 1 AND selling_price IS NULL'
        );
    }

    /** Warranty items expiring within the next $days days (still active). */
    public function warrantiesExpiringSoon(int $days = 30, int $limit = 6): array
    {
        return $this->fetchAll(
            "SELECT si.product_name, si.warranty_expires, s.id AS sale_id, s.invoice_no,
                    COALESCE(c.name, 'Walk-in customer') AS customer_name
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.status = 'completed'
               AND si.warranty_expires IS NOT NULL
               AND si.warranty_expires BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL "
                . max(1, $days) . " DAY)
             ORDER BY si.warranty_expires
             LIMIT " . max(1, $limit)
        );
    }

    public function warrantiesExpiringSoonCount(int $days = 30): int
    {
        return (int) $this->fetchValue(
            "SELECT COUNT(*)
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE s.status = 'completed'
               AND si.warranty_expires IS NOT NULL
               AND si.warranty_expires BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL "
                . max(1, $days) . ' DAY)'
        );
    }

    /**
     * Monthly revenue split by payment method (cash / card / credit), last 6 months.
     * @return array{labels: string[], cash: float[], card: float[], credit: float[]}
     */
    public function paymentMixMonthly(): array
    {
        $rows = $this->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, payment_method, SUM(total) AS t
             FROM sales
             WHERE status = 'completed'
               AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m'), payment_method"
        );

        $byMonth = [];
        foreach ($rows as $row) {
            // Legacy methods (bank_transfer/other) count with card for this chart.
            $bucket = match ($row['payment_method']) {
                'cash'   => 'cash',
                'credit' => 'credit',
                default  => 'card',
            };
            $byMonth[$row['m']][$bucket] = ($byMonth[$row['m']][$bucket] ?? 0) + (float) $row['t'];
        }

        $labels = [];
        $cash = [];
        $card = [];
        $credit = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("first day of -{$i} months"));
            $labels[] = date('M Y', strtotime($key . '-01'));
            $cash[] = round($byMonth[$key]['cash'] ?? 0, 2);
            $card[] = round($byMonth[$key]['card'] ?? 0, 2);
            $credit[] = round($byMonth[$key]['credit'] ?? 0, 2);
        }

        return ['labels' => $labels, 'cash' => $cash, 'card' => $card, 'credit' => $credit];
    }

    /**
     * Monthly totals for a simple one-series chart, last 6 months.
     * $table is whitelisted: 'product_returns' (total_value) or 'refunds' (amount).
     * @return array{labels: string[], values: float[]}
     */
    public function monthlySeries(string $table): array
    {
        [$tableSql, $column] = match ($table) {
            'product_returns' => ['product_returns', 'total_value'],
            'refunds'         => ['refunds', 'amount'],
            default           => throw new \RuntimeException('Unknown series table.'),
        };

        $rows = $this->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, SUM({$column}) AS t
             FROM {$tableSql}
             WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $byMonth = array_column($rows, 't', 'm');

        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("first day of -{$i} months"));
            $labels[] = date('M Y', strtotime($key . '-01'));
            $values[] = round((float) ($byMonth[$key] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
