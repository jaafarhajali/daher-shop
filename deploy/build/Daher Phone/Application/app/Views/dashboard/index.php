<?php
/**
 * Dashboard: KPI tiles, charts, activity lists.
 */
?>
<div class="page-heading">
  <div>
    <h1>Dashboard</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb"><li class="breadcrumb-item active">Overview · <?= e(date('l, d F Y')) ?></li></ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('repairs/create') ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-tools me-1"></i>New repair
    </a>
    <a href="<?= url('sales/pos') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-basket2 me-1"></i>New sale
    </a>
  </div>
</div>

<!-- KPI row 1: money -->
<div class="row g-3 mb-3">
  <?php
  $kpis = [
      ['label' => "Today's sales",   'value' => money($todaySales),   'icon' => 'cash-coin',   'bg' => 'rgba(13,148,136,.12)',  'fg' => '#0d9488', 'sub' => 'gross, before refunds'],
      ['label' => 'Sales this month','value' => money($monthSales),   'icon' => 'calendar3',   'bg' => 'rgba(2,132,199,.12)',   'fg' => '#0284c7', 'sub' => 'gross · ' . date('F Y')],
      ['label' => 'Total revenue',   'value' => money($totalRevenue), 'icon' => 'graph-up-arrow','bg' => 'rgba(22,163,74,.12)', 'fg' => '#16a34a', 'sub' => 'net of refunds & return credits'],
      ['label' => 'Net profit',      'value' => money($netProfit),    'icon' => 'piggy-bank',  'bg' => 'rgba(180,83,9,.12)',    'fg' => '#b45309', 'sub' => 'gross profit ' . money($grossProfit) . ' − expenses'],
  ];
  foreach ($kpis as $k): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card kpi-card h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="kpi-icon" style="background:<?= $k['bg'] ?>;color:<?= $k['fg'] ?>">
          <i class="bi bi-<?= $k['icon'] ?>"></i>
        </div>
        <div class="min-w-0">
          <div class="kpi-label"><?= e($k['label']) ?></div>
          <div class="kpi-value"><?= e($k['value']) ?></div>
          <div class="kpi-sub"><?= e($k['sub']) ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- KPI row 2: operations -->
<div class="row g-3 mb-4">
  <?php
  $ops = [
      ['label' => 'Products',        'value' => (string) $productCount,  'icon' => 'box-seam',        'href' => url('products/index'),   'fg' => '#0d9488', 'bg' => 'rgba(13,148,136,.12)', 'sub' => 'active products'],
      ['label' => 'Current stock value', 'value' => money($stockValue),  'icon' => 'boxes',           'href' => url('reports/index', ['type' => 'inventory']), 'fg' => '#16a34a', 'bg' => 'rgba(22,163,74,.12)', 'sub' => 'inventory at purchase cost'],
      ['label' => 'Low stock',       'value' => (string) $lowStockCount, 'icon' => 'exclamation-triangle', 'href' => url('products/low-stock'), 'fg' => '#d97706', 'bg' => 'rgba(217,119,6,.12)', 'sub' => 'need restocking'],
      ['label' => 'Pending repairs', 'value' => (string) $pendingRepairs,'icon' => 'tools',           'href' => url('repairs/index'),    'fg' => '#0284c7', 'bg' => 'rgba(2,132,199,.12)', 'sub' => 'in the workshop'],
      ['label' => 'Expenses (month)','value' => money($monthExpenses),   'icon' => 'wallet2',         'href' => url('expenses/index'),   'fg' => '#dc2626', 'bg' => 'rgba(220,38,38,.12)', 'sub' => date('F Y')],
  ];
  foreach ($ops as $k): ?>
  <div class="col-sm-6 col-xl">
    <a href="<?= $k['href'] ?>" class="text-decoration-none text-reset">
      <div class="card kpi-card h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="kpi-icon" style="background:<?= $k['bg'] ?>;color:<?= $k['fg'] ?>">
            <i class="bi bi-<?= $k['icon'] ?>"></i>
          </div>
          <div class="min-w-0">
            <div class="kpi-label"><?= e($k['label']) ?></div>
            <div class="kpi-value"><?= e($k['value']) ?></div>
            <div class="kpi-sub"><?= e($k['sub']) ?></div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- KPI row 3: credit & after-sales -->
<div class="row g-3 mb-4">
  <?php
  $afterSales = [
      ['label' => 'Outstanding credit', 'value' => money($creditTotal), 'icon' => 'wallet2', 'href' => url('credit/index'), 'fg' => '#dc2626', 'bg' => 'rgba(220,38,38,.12)', 'sub' => 'owed by customers'],
      ['label' => 'Returns (30 days)', 'value' => money($returns30d), 'icon' => 'arrow-counterclockwise', 'href' => url('returns/index'), 'fg' => '#d97706', 'bg' => 'rgba(217,119,6,.12)', 'sub' => 'goods returned'],
      ['label' => 'Refunds (30 days)', 'value' => money($refunds30d), 'icon' => 'cash-coin', 'href' => url('refunds/index'), 'fg' => '#ea580c', 'bg' => 'rgba(234,88,12,.12)', 'sub' => 'money given back'],
      ['label' => 'No selling price', 'value' => (string) $noPriceCount, 'icon' => 'tag', 'href' => url('products/index', ['price' => 'missing']), 'fg' => '#0284c7', 'bg' => 'rgba(2,132,199,.12)', 'sub' => 'products to price'],
  ];
  foreach ($afterSales as $k): ?>
  <div class="col-sm-6 col-xl-3">
    <a href="<?= $k['href'] ?>" class="text-decoration-none text-reset">
      <div class="card kpi-card h-100">
        <div class="d-flex align-items-center gap-3">
          <div class="kpi-icon" style="background:<?= $k['bg'] ?>;color:<?= $k['fg'] ?>">
            <i class="bi bi-<?= $k['icon'] ?>"></i>
          </div>
          <div class="min-w-0">
            <div class="kpi-label"><?= e($k['label']) ?></div>
            <div class="kpi-value"><?= e($k['value']) ?></div>
            <div class="kpi-sub"><?= e($k['sub']) ?></div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Sales — last 14 days</span>
        <a class="small text-decoration-none" href="<?= url('reports/index') ?>">All reports →</a>
      </div>
      <div class="card-body"><div class="chart-box"><canvas id="chartTrend"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header">Revenue vs expenses — last 6 months</div>
      <div class="card-body"><div class="chart-box"><canvas id="chartRevExp"></canvas></div></div>
    </div>
  </div>
</div>

<!-- Credit vs cash + returns/refunds trends -->
<div class="row g-3 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header">Sales by payment — cash / card / credit (6 months)</div>
      <div class="card-body"><div class="chart-box" style="height:250px"><canvas id="chartPayMix"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card h-100">
      <div class="card-header">Returns — monthly</div>
      <div class="card-body"><div class="chart-box" style="height:250px"><canvas id="chartReturns"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6">
    <div class="card h-100">
      <div class="card-header">Refunds — monthly</div>
      <div class="card-body"><div class="chart-box" style="height:250px"><canvas id="chartRefunds"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Top products -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header">Top products — last 30 days</div>
      <div class="card-body">
        <div class="chart-box" style="height:250px"><canvas id="chartTop"></canvas></div>
        <div id="chartTopEmpty" class="empty-state d-none py-4">
          <i class="bi bi-bar-chart"></i>
          No sales in the last 30 days yet.
        </div>
      </div>
    </div>
  </div>

  <!-- Recent sales -->
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent sales</span>
        <a class="small text-decoration-none" href="<?= url('sales/index') ?>">View all →</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if ($recentSales === []): ?>
          <div class="empty-state py-4"><i class="bi bi-receipt"></i>No sales yet. Press <strong>F4</strong> to open the POS.</div>
        <?php endif; ?>
        <?php foreach ($recentSales as $s): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= url('sales/show', ['id' => $s['id']]) ?>">
          <div class="min-w-0">
            <span class="data small"><?= e($s['invoice_no']) ?></span>
            <?php if ($s['status'] === 'cancelled'): ?>
              <span class="badge text-bg-danger ms-1">cancelled</span>
            <?php endif; ?>
            <div class="small text-secondary text-truncate"><?= e($s['customer_name']) ?> · <?= e(fmt_date($s['created_at'], true)) ?></div>
          </div>
          <span class="data fw-semibold"><?= e(money($s['total'])) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Workshop + low stock -->
  <div class="col-xl-4 d-flex flex-column gap-3">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>In the workshop</span>
        <a class="small text-decoration-none" href="<?= url('repairs/index') ?>">View all →</a>
      </div>
      <div class="list-group list-group-flush">
        <?php if ($activeRepairs === []): ?>
          <div class="empty-state py-4"><i class="bi bi-tools"></i>No repairs in progress.</div>
        <?php endif; ?>
        <?php foreach ($activeRepairs as $r): $meta = repair_status_meta($r['status']); ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= url('repairs/show', ['id' => $r['id']]) ?>">
          <div class="min-w-0">
            <span class="data small"><?= e($r['ticket_no']) ?></span>
            <div class="small text-secondary text-truncate">
              <?= e(trim($r['brand'] . ' ' . $r['model'])) ?: e($r['device_type']) ?> · <?= e($r['customer_name']) ?>
            </div>
          </div>
          <span class="badge text-bg-<?= $meta['color'] ?>"><?= e($meta['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($lowStockList !== []): ?>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Low stock</span>
        <a class="small text-decoration-none" href="<?= url('products/low-stock') ?>">View all →</a>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($lowStockList as $p): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= url('products/edit', ['id' => $p['id']]) ?>">
          <span class="text-truncate me-2"><?= e($p['name']) ?></span>
          <span class="data small text-<?= (int) $p['quantity'] === 0 ? 'danger' : 'warning-emphasis' ?>">
            <?= (int) $p['quantity'] ?> / min <?= (int) $p['min_stock'] ?>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($warrantySoon !== []): ?>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-shield-check me-1"></i>Warranties expiring soon</span>
        <span class="badge badge-soft"><?= (int) $warrantySoonCnt ?> in 30 days</span>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($warrantySoon as $w): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
           href="<?= url('sales/show', ['id' => $w['sale_id']]) ?>">
          <div class="min-w-0">
            <span class="text-truncate d-block"><?= e($w['product_name']) ?></span>
            <span class="small text-secondary"><?= e($w['customer_name']) ?> · <?= e($w['invoice_no']) ?></span>
          </div>
          <span class="data small text-warning-emphasis"><?= e(fmt_date($w['warranty_expires'])) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
