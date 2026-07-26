<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dashboard;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $m = new Dashboard();

        $chartData = [
            'trend'  => $m->salesTrend14d(),
            'revExp' => $m->revenueVsExpenses6m(),
            'top'    => array_map(
                static fn (array $r): array => [
                    'name'    => $r['product_name'],
                    'qty'     => (int) $r['qty'],
                    'revenue' => (float) $r['revenue'],
                ],
                $m->topProducts30d()
            ),
            'currency' => setting('currency_symbol', '$'),
        ];

        $this->render('dashboard/index', [
            'todaySales'     => $m->todaySalesTotal(),
            'monthSales'     => $m->monthSalesTotal(),
            'totalRevenue'   => $m->totalRevenue(),
            'grossProfit'    => $m->totalGrossProfit(),
            'productCount'   => $m->productCount(),
            'lowStockCount'  => $m->lowStockCount(),
            'pendingRepairs' => $m->pendingRepairCount(),
            'monthExpenses'  => $m->monthExpensesTotal(),
            'recentSales'    => $m->recentSales(),
            'activeRepairs'  => $m->activeRepairs(),
            'lowStockList'   => $m->lowStockList(),
            'pageScript'     => 'dashboard',
            'inlineScript'   => 'window.DASH = ' . json_encode(
                $chartData,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            ) . ';',
        ], 'Dashboard');
    }

    /** Friendly in-app 404 (used by the router). */
    public function notFound(): void
    {
        $this->render('errors/404', [], 'Page not found');
    }
}
