<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router / dispatcher.
 *
 * Routes look like "controller/action" and arrive via the "r" query
 * parameter: index.php?r=products/edit&id=5
 * "products/low-stock" maps to ProductController::lowStock().
 */
final class App
{
    /** Routes reachable without a login session. */
    private const PUBLIC_ROUTES = ['auth/login', 'auth/attempt'];

    /** Route prefixes restricted to the admin role. */
    private const ADMIN_PREFIXES = ['backup/', 'updates/', 'settings/shop', 'settings/save-shop'];

    /** controller-segment => class name */
    private const CONTROLLERS = [
        'auth'       => 'AuthController',
        'dashboard'  => 'DashboardController',
        'categories' => 'CategoryController',
        'products'   => 'ProductController',
        'customers'  => 'CustomerController',
        'sales'      => 'SaleController',
        'credit'     => 'CreditController',
        'returns'    => 'ReturnController',
        'refunds'    => 'RefundController',
        'repairs'    => 'RepairController',
        'expenses'   => 'ExpenseController',
        'reports'    => 'ReportController',
        'backup'     => 'BackupController',
        'updates'    => 'UpdateController',
        'settings'   => 'SettingController',
    ];

    public function run(): void
    {
        $route = (string) ($_GET['r'] ?? 'dashboard/index');

        if (!preg_match('~^[a-z]+/[a-z-]+$~', $route)) {
            $this->abort404();
        }

        [$segment, $actionSlug] = explode('/', $route, 2);

        if (!isset(self::CONTROLLERS[$segment])) {
            $this->abort404();
        }

        // Authentication guard — everything except the login screen.
        if (!in_array($route, self::PUBLIC_ROUTES, true) && !Auth::check()) {
            redirect('auth/login');
        }

        // Role guard.
        foreach (self::ADMIN_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix) && !Auth::isAdmin()) {
                Flash::set('danger', 'You do not have permission to access that page.');
                redirect('dashboard/index');
            }
        }

        $class = 'App\\Controllers\\' . self::CONTROLLERS[$segment];
        // "low-stock" -> "lowStock"
        $action = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $actionSlug))));

        if (!method_exists($class, $action)) {
            $this->abort404();
        }

        (new $class())->{$action}();
    }

    private function abort404(): never
    {
        http_response_code(404);
        if (Auth::check()) {
            (new \App\Controllers\DashboardController())->notFound();
        } else {
            echo '<div style="font:16px/1.6 system-ui;padding:4rem;text-align:center;">'
               . '<h1>404 — Page not found</h1>'
               . '<p><a href="index.php?r=auth/login">Go to login</a></p></div>';
        }
        exit;
    }
}
