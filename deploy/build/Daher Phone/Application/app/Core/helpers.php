<?php

declare(strict_types=1);

/**
 * Global helper functions available in controllers and views.
 */

/** HTML-escape for safe output. Accepts anything stringable. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Build an internal URL: url('products/edit', ['id' => 5]) */
function url(string $route, array $params = []): string
{
    $qs = $params === [] ? '' : '&' . http_build_query($params);

    return 'index.php?r=' . $route . $qs;
}

/** Redirect to an internal route and stop. */
function redirect(string $route, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

/** Read a shop setting (cached for the request). */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = \App\Core\Database::pdo()
                ->query('SELECT setting_key, setting_value FROM settings')
                ->fetchAll();
            $cache = array_column($rows, 'setting_value', 'setting_key');
        } catch (Throwable) {
            $cache = [];
        }
    }

    return (string) ($cache[$key] ?? $default);
}

/** Format an amount using the configured currency symbol and position. */
function money(float|int|string|null $amount): string
{
    $formatted = number_format((float) ($amount ?? 0), 2);
    $symbol = setting('currency_symbol', '$');

    return setting('currency_position', 'before') === 'after'
        ? $formatted . ' ' . $symbol
        : $symbol . $formatted;
}

/** Format a DATE/DATETIME string using the configured date format. */
function fmt_date(?string $value, bool $withTime = false): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return '—';
    }
    $format = setting('date_format', 'd/m/Y') . ($withTime ? ' H:i' : '');

    return date($format, strtotime($value));
}

/** CSRF hidden field shortcut for forms. */
function csrf_field(): string
{
    return \App\Core\Csrf::field();
}

/** Previously submitted value (repopulates forms after validation errors). */
function old(string $key, mixed $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

/** Validation error for a field, if any. */
function form_error(string $key): string
{
    return e($_SESSION['_errors'][$key] ?? '');
}

/** Clear the old-input/errors stash (called by the layout after render). */
function clear_form_stash(): void
{
    unset($_SESSION['_old'], $_SESSION['_errors']);
}

/** Human label + bootstrap color for a repair status. */
function repair_status_meta(string $status): array
{
    return match ($status) {
        'received'   => ['label' => 'Received',   'color' => 'secondary', 'icon' => 'inbox'],
        'diagnosing' => ['label' => 'Diagnosing', 'color' => 'info',      'icon' => 'search'],
        'repairing'  => ['label' => 'Repairing',  'color' => 'warning',   'icon' => 'tools'],
        'ready'      => ['label' => 'Ready',      'color' => 'primary',   'icon' => 'check2-circle'],
        'delivered'  => ['label' => 'Delivered',  'color' => 'success',   'icon' => 'bag-check'],
        'cancelled'  => ['label' => 'Cancelled',  'color' => 'danger',    'icon' => 'x-circle'],
        default      => ['label' => ucfirst($status), 'color' => 'secondary', 'icon' => 'circle'],
    };
}

/** Human label for a payment method (legacy values keep their labels). */
function payment_label(string $method): string
{
    return match ($method) {
        'cash'          => 'Cash',
        'card'          => 'Card',
        'credit'        => 'Credit',
        'return_credit' => 'Return credit',
        'bank_transfer' => 'Bank transfer',
        default         => 'Other',
    };
}

/**
 * Warranty state for a snapshotted expiry date.
 * @return array{label:string, color:string, active:bool}
 */
function warranty_status(?string $expires): array
{
    if ($expires === null || $expires === '' || str_starts_with($expires, '0000')) {
        return ['label' => 'No warranty', 'color' => 'secondary', 'active' => false];
    }
    $active = $expires >= date('Y-m-d');

    return $active
        ? ['label' => 'Active',  'color' => 'success', 'active' => true]
        : ['label' => 'Expired', 'color' => 'danger',  'active' => false];
}

/**
 * Payment state of an invoice (total vs paid so far).
 * @return array{label:string, color:string, outstanding:float}
 */
function paid_status_meta(float $total, float $paid): array
{
    $outstanding = round($total - $paid, 2);
    if ($outstanding <= 0.004) {
        return ['label' => 'Paid', 'color' => 'success', 'outstanding' => 0.0];
    }
    if ($paid > 0.004) {
        return ['label' => 'Partially paid', 'color' => 'warning', 'outstanding' => $outstanding];
    }

    return ['label' => 'Unpaid', 'color' => 'danger', 'outstanding' => $outstanding];
}

/**
 * Keep current query parameters while overriding some — used by pagination
 * and sortable table headers.
 */
function url_with(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['r']);

    return url((string) ($_GET['r'] ?? 'dashboard/index'), $params);
}
