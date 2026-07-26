<?php
/**
 * Sidebar navigation. Expects $currentRoute and $user from the layout.
 */

use App\Core\Auth;
use App\Core\Database;

// Live badges: pending repairs + low-stock count (cheap indexed COUNTs).
$navPendingRepairs = 0;
$navLowStock = 0;
try {
    $pdo = Database::pdo();
    $navPendingRepairs = (int) $pdo->query(
        "SELECT COUNT(*) FROM repairs WHERE status IN ('received','diagnosing','repairing','ready')"
    )->fetchColumn();
    $navLowStock = (int) $pdo->query(
        'SELECT COUNT(*) FROM products WHERE is_active = 1 AND quantity <= min_stock'
    )->fetchColumn();
} catch (Throwable) {
    // navigation must render even if the DB hiccups
}

$navSections = [
    'Overview' => [
        ['route' => 'dashboard/index', 'icon' => 'speedometer2', 'label' => 'Dashboard', 'match' => 'dashboard/'],
    ],
    'Operations' => [
        ['route' => 'sales/pos',      'icon' => 'basket2',        'label' => 'Point of Sale', 'match' => 'sales/pos'],
        ['route' => 'sales/index',    'icon' => 'receipt',        'label' => 'Sales',         'match' => 'sales/index|sales/show'],
        ['route' => 'repairs/index',  'icon' => 'tools',          'label' => 'Repairs',       'match' => 'repairs/', 'badge' => $navPendingRepairs],
    ],
    'Catalog' => [
        ['route' => 'products/index',   'icon' => 'box-seam',   'label' => 'Products',   'match' => 'products/', 'badge' => $navLowStock, 'badgeColor' => 'warning'],
        ['route' => 'categories/index', 'icon' => 'tags',       'label' => 'Categories', 'match' => 'categories/'],
    ],
    'People & Money' => [
        ['route' => 'customers/index', 'icon' => 'people',        'label' => 'Customers', 'match' => 'customers/'],
        ['route' => 'expenses/index',  'icon' => 'cash-stack',    'label' => 'Expenses',  'match' => 'expenses/'],
        ['route' => 'reports/index',   'icon' => 'graph-up',      'label' => 'Reports',   'match' => 'reports/'],
    ],
    'System' => [
        ['route' => 'settings/profile', 'icon' => 'gear', 'label' => 'Settings', 'match' => 'settings/'],
    ],
];

if (Auth::isAdmin()) {
    $navSections['System'][] = ['route' => 'backup/index', 'icon' => 'database-down', 'label' => 'Backup', 'match' => 'backup/'];
}
?>
<aside class="app-sidebar">
  <a class="sidebar-brand" href="<?= url('dashboard/index') ?>">
    <span class="brand-mark"><i class="bi bi-wrench-adjustable"></i></span>
    <span class="brand-name"><?= e(setting('shop_name', APP_NAME)) ?></span>
  </a>

  <nav class="sidebar-nav">
    <?php foreach ($navSections as $heading => $items): ?>
      <div class="sidebar-heading"><?= e($heading) ?></div>
      <?php foreach ($items as $item):
          $isActive = (bool) preg_match('~^(' . $item['match'] . ')~', $currentRoute);
      ?>
      <a class="sidebar-link<?= $isActive ? ' active' : '' ?>"
         href="<?= url($item['route']) ?>" title="<?= e($item['label']) ?>">
        <i class="bi bi-<?= e($item['icon']) ?>"></i>
        <span><?= e($item['label']) ?></span>
        <?php if (!empty($item['badge'])): ?>
          <span class="badge rounded-pill text-bg-<?= e($item['badgeColor'] ?? 'primary') ?> ms-auto"><?= (int) $item['badge'] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-foot">
    v<?= e(APP_VERSION) ?> · signed in as <?= e($user['username'] ?? '') ?>
  </div>
</aside>
