<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Finance — the single source of truth for every money metric.
 * The dashboard and the reports both call THIS class, so they can
 * never disagree. Full plain-English documentation: docs/FINANCE.md.
 *
 * Definitions (all on completed sales / delivered repairs):
 *
 *   gross_sales      SUM(sales.total)
 *   refunds          SUM(refunds.amount)                    — money handed back
 *   return_credits   SUM(customer_payments 'return_credit') — sale value reversed
 *                                                             by returned goods on
 *                                                             an unpaid invoice
 *   deductions       refunds + return_credits
 *   net_sales        gross_sales − deductions
 *   cogs_sold        SUM(sales.total_cost)   — cost of everything sold
 *   cogs_returned    cost of returned units (they went back on the shelf)
 *   cogs_net         cogs_sold − cogs_returned
 *   repair_revenue   SUM(repairs.total_cost)
 *   repair_profit    repair_revenue − actual parts cost
 *   gross_profit     net_sales − cogs_net + repair_profit
 *   expenses         SUM(expenses.amount)
 *   net_profit       gross_profit − expenses
 */
final class Finance extends Model
{
    /**
     * Every metric for a date range (Y-m-d, inclusive). Null = all time.
     *
     * @return array<string, float>
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $grossSales = $this->rangedValue(
            "SELECT COALESCE(SUM(total),0) FROM sales WHERE status = 'completed'",
            'created_at', $from, $to
        );
        $cogsSold = $this->rangedValue(
            "SELECT COALESCE(SUM(total_cost),0) FROM sales WHERE status = 'completed'",
            'created_at', $from, $to
        );
        $refunds = $this->rangedValue(
            'SELECT COALESCE(SUM(amount),0) FROM refunds WHERE 1=1',
            'created_at', $from, $to
        );
        $returnCredits = $this->rangedValue(
            "SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE method = 'return_credit'",
            'created_at', $from, $to
        );
        $cogsReturned = $this->returnedCogs($from, $to);

        $repairRevenue = $this->rangedValue(
            "SELECT COALESCE(SUM(total_cost),0) FROM repairs
             WHERE status = 'delivered' AND delivered_at IS NOT NULL",
            'delivered_at', $from, $to
        );
        $repairPartsCost = $this->rangedValue(
            "SELECT COALESCE(SUM(pc.cost),0)
             FROM repairs r
             JOIN (SELECT repair_id, SUM(unit_cost * quantity) AS cost
                   FROM repair_parts GROUP BY repair_id) pc ON pc.repair_id = r.id
             WHERE r.status = 'delivered' AND r.delivered_at IS NOT NULL",
            'r.delivered_at', $from, $to
        );
        $expenses = $this->rangedValue(
            'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE 1=1',
            'expense_date', $from, $to
        );

        $deductions = round($refunds + $returnCredits, 2);
        $netSales = round($grossSales - $deductions, 2);
        $cogsNet = round($cogsSold - $cogsReturned, 2);
        $repairProfit = round($repairRevenue - $repairPartsCost, 2);
        $grossProfit = round($netSales - $cogsNet + $repairProfit, 2);

        return [
            'gross_sales'    => round($grossSales, 2),
            'refunds'        => round($refunds, 2),
            'return_credits' => round($returnCredits, 2),
            'deductions'     => $deductions,
            'net_sales'      => $netSales,
            'repair_revenue' => round($repairRevenue, 2),
            'total_revenue'  => round($netSales + $repairRevenue, 2),
            'cogs_sold'      => round($cogsSold, 2),
            'cogs_returned'  => round($cogsReturned, 2),
            'cogs_net'       => $cogsNet,
            'repair_profit'  => $repairProfit,
            'gross_profit'   => $grossProfit,
            'expenses'       => round($expenses, 2),
            'net_profit'     => round($grossProfit - $expenses, 2),
        ];
    }

    /**
     * Cost of goods that came BACK through returns (join to the original
     * sale line, whose unit_cost was frozen at sale time).
     */
    public function returnedCogs(?string $from = null, ?string $to = null): float
    {
        return $this->rangedValue(
            'SELECT COALESCE(SUM(ri.quantity * si.unit_cost),0)
             FROM return_items ri
             JOIN product_returns pr ON pr.id = ri.return_id
             JOIN sale_items si ON si.id = ri.sale_item_id
             WHERE 1=1',
            'pr.created_at', $from, $to
        );
    }

    /**
     * Per-day components for the daily reports — same definitions,
     * grouped by calendar day. Missing days are absent (caller merges).
     *
     * @return array<string, array<string, float>> day => metric => value
     */
    public function dailyComponents(string $from, string $to): array
    {
        $days = [];
        $merge = function (array $rows, string $key) use (&$days): void {
            foreach ($rows as $row) {
                $days[$row['d']][$key] = (float) $row['t'];
            }
        };

        $merge($this->fetchAll(
            "SELECT DATE(created_at) AS d, SUM(total) AS t FROM sales
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)", ['f' => $from, 't' => $to]
        ), 'gross_sales');
        $merge($this->fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS t FROM sales
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)", ['f' => $from, 't' => $to]
        ), 'orders');
        $merge($this->fetchAll(
            "SELECT DATE(created_at) AS d, SUM(total_cost) AS t FROM sales
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)", ['f' => $from, 't' => $to]
        ), 'cogs_sold');
        $merge($this->fetchAll(
            'SELECT DATE(created_at) AS d, SUM(amount) AS t FROM refunds
             WHERE DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)', ['f' => $from, 't' => $to]
        ), 'refunds');
        $merge($this->fetchAll(
            "SELECT DATE(created_at) AS d, SUM(amount) AS t FROM customer_payments
             WHERE method = 'return_credit' AND DATE(created_at) BETWEEN :f AND :t
             GROUP BY DATE(created_at)", ['f' => $from, 't' => $to]
        ), 'return_credits');
        $merge($this->fetchAll(
            'SELECT DATE(pr.created_at) AS d, SUM(ri.quantity * si.unit_cost) AS t
             FROM return_items ri
             JOIN product_returns pr ON pr.id = ri.return_id
             JOIN sale_items si ON si.id = ri.sale_item_id
             WHERE DATE(pr.created_at) BETWEEN :f AND :t
             GROUP BY DATE(pr.created_at)', ['f' => $from, 't' => $to]
        ), 'cogs_returned');
        $merge($this->fetchAll(
            "SELECT DATE(r.delivered_at) AS d, SUM(r.total_cost) AS t FROM repairs r
             WHERE r.status = 'delivered' AND r.delivered_at IS NOT NULL
               AND DATE(r.delivered_at) BETWEEN :f AND :t
             GROUP BY DATE(r.delivered_at)", ['f' => $from, 't' => $to]
        ), 'repair_revenue');
        $merge($this->fetchAll(
            "SELECT DATE(r.delivered_at) AS d, SUM(pc.cost) AS t
             FROM repairs r
             JOIN (SELECT repair_id, SUM(unit_cost * quantity) AS cost
                   FROM repair_parts GROUP BY repair_id) pc ON pc.repair_id = r.id
             WHERE r.status = 'delivered' AND r.delivered_at IS NOT NULL
               AND DATE(r.delivered_at) BETWEEN :f AND :t
             GROUP BY DATE(r.delivered_at)", ['f' => $from, 't' => $to]
        ), 'repair_parts_cost');
        $merge($this->fetchAll(
            'SELECT expense_date AS d, SUM(amount) AS t FROM expenses
             WHERE expense_date BETWEEN :f AND :t
             GROUP BY expense_date', ['f' => $from, 't' => $to]
        ), 'expenses');

        ksort($days);

        return $days;
    }

    /**
     * Monthly net revenue vs expenses for the dashboard chart, last 6 months.
     * Net revenue = (sales + repairs) − refunds − return credits.
     *
     * @return array{labels: string[], revenue: float[], expenses: float[]}
     */
    public function netRevenueVsExpenses6m(): array
    {
        $monthly = function (string $sql): array {
            return array_column($this->fetchAll($sql), 't', 'm');
        };

        $sales = $monthly(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, SUM(total) AS t FROM sales
             WHERE status = 'completed'
               AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $repairs = $monthly(
            "SELECT DATE_FORMAT(delivered_at, '%Y-%m') AS m, SUM(total_cost) AS t FROM repairs
             WHERE status = 'delivered' AND delivered_at IS NOT NULL
               AND delivered_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(delivered_at, '%Y-%m')"
        );
        $refunds = $monthly(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, SUM(amount) AS t FROM refunds
             WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $returnCredits = $monthly(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, SUM(amount) AS t FROM customer_payments
             WHERE method = 'return_credit'
               AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')"
        );
        $expenses = $monthly(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS m, SUM(amount) AS t FROM expenses
             WHERE expense_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
             GROUP BY DATE_FORMAT(expense_date, '%Y-%m')"
        );

        $labels = [];
        $rev = [];
        $exp = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("first day of -{$i} months"));
            $labels[] = date('M Y', strtotime($key . '-01'));
            $rev[] = round(
                (float) ($sales[$key] ?? 0) + (float) ($repairs[$key] ?? 0)
                - (float) ($refunds[$key] ?? 0) - (float) ($returnCredits[$key] ?? 0),
                2
            );
            $exp[] = round((float) ($expenses[$key] ?? 0), 2);
        }

        return ['labels' => $labels, 'revenue' => $rev, 'expenses' => $exp];
    }

    /** Money actually received on one invoice (excludes return credits). */
    public function moneyReceived(int $saleId): float
    {
        return (float) $this->fetchValue(
            "SELECT s.paid_amount - COALESCE((
                        SELECT SUM(cp.amount) FROM customer_payments cp
                        WHERE cp.sale_id = s.id AND cp.method = 'return_credit'
                    ), 0)
             FROM sales s WHERE s.id = :id",
            ['id' => $saleId]
        );
    }

    /** What may still be refunded on one invoice: money received − already refunded. */
    public function refundableFor(int $saleId): float
    {
        return round(
            $this->moneyReceived($saleId)
            - (float) $this->fetchValue(
                'SELECT COALESCE(SUM(amount),0) FROM refunds WHERE sale_id = :id',
                ['id' => $saleId]
            ),
            2
        );
    }

    // ------------------------------------------------------------- private --

    /** Append a BETWEEN range to a WHERE-complete aggregate query and run it. */
    private function rangedValue(string $sql, string $dateColumn, ?string $from, ?string $to): float
    {
        $params = [];
        if ($from !== null && $to !== null) {
            $sql .= " AND DATE({$dateColumn}) BETWEEN :rfrom AND :rto";
            $params = ['rfrom' => $from, 'rto' => $to];
        }

        return (float) $this->fetchValue($sql, $params);
    }
}
