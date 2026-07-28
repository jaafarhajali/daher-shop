<?php
/**
 * Reports — type picker, filters, optional chart, result table, exports.
 * Expects: $type, $params, $report, $types, $categories, $customers, $products
 */

$showCategory = in_array($type, ['inventory', 'low-stock'], true);
$showCustomer = in_array($type, ['sales-list', 'credit-out', 'credit-payments', 'returns', 'refunds', 'warranty'], true);
$showMethod   = $type === 'sales-list';
$showProduct  = in_array($type, ['returns', 'warranty'], true);
$showInvoice  = in_array($type, ['sales-list', 'credit-out', 'credit-payments', 'returns', 'refunds', 'warranty'], true);
$showDates    = !in_array($type, ['inventory', 'low-stock', 'credit-out'], true);

$exportParams = array_merge(['type' => $type], $params);
?>
<div class="page-heading">
  <div>
    <h1>Reports</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Reports</li>
      </ol>
    </nav>
  </div>
  <div class="btn-group">
    <a class="btn btn-outline-primary btn-sm" href="<?= url('reports/export', $exportParams + ['format' => 'csv']) ?>">
      <i class="bi bi-filetype-csv me-1"></i>CSV
    </a>
    <a class="btn btn-outline-primary btn-sm" href="<?= url('reports/export', $exportParams + ['format' => 'xls']) ?>">
      <i class="bi bi-file-earmark-excel me-1"></i>Excel
    </a>
    <a class="btn btn-outline-primary btn-sm" target="_blank"
       href="<?= url('reports/export', $exportParams + ['format' => 'print']) ?>">
      <i class="bi bi-file-earmark-pdf me-1"></i>PDF / Print
    </a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="filters-bar" method="get" action="index.php" id="reportForm">
      <input type="hidden" name="r" value="reports/index">
      <div>
        <label class="form-label mb-1 small">Report</label>
        <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
          <?php foreach ($types as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($showDates): ?>
      <div>
        <label class="form-label mb-1 small">Preset</label>
        <select class="form-select form-select-sm" id="rangePreset">
          <option value="">Custom</option>
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="week">This week</option>
          <option value="month">This month</option>
          <option value="last-month">Last month</option>
          <option value="year">This year</option>
        </select>
      </div>
      <div>
        <label class="form-label mb-1 small">From</label>
        <input type="date" class="form-control form-control-sm" name="from" id="fromDate"
               value="<?= e($params['from']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">To</label>
        <input type="date" class="form-control form-control-sm" name="to" id="toDate"
               value="<?= e($params['to']) ?>">
      </div>
      <?php endif; ?>

      <?php if ($showCategory): ?>
      <div>
        <label class="form-label mb-1 small">Category</label>
        <select class="form-select form-select-sm" name="category_id">
          <option value="">All</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $params['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <?php if ($showCustomer): ?>
      <div>
        <label class="form-label mb-1 small">Customer</label>
        <select class="form-select form-select-sm" name="customer_id" style="max-width:220px">
          <option value="">All customers</option>
          <?php foreach ($customers as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $params['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <?php if ($showMethod): ?>
      <div>
        <label class="form-label mb-1 small">Payment</label>
        <select class="form-select form-select-sm" name="method">
          <option value="">All</option>
          <option value="cash" <?= $params['method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
          <option value="card" <?= $params['method'] === 'card' ? 'selected' : '' ?>>Card</option>
          <option value="bank_transfer" <?= $params['method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank transfer</option>
          <option value="other" <?= $params['method'] === 'other' ? 'selected' : '' ?>>Other</option>
        </select>
      </div>
      <?php endif; ?>

      <?php if ($showProduct): ?>
      <div>
        <label class="form-label mb-1 small">Product</label>
        <select class="form-select form-select-sm" name="product_id" style="max-width:220px">
          <option value="">All products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= $params['product_id'] === (int) $p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <?php if ($showInvoice): ?>
      <div>
        <label class="form-label mb-1 small">Invoice #</label>
        <input class="form-control form-control-sm data" name="invoice_no" style="width:140px"
               placeholder="INV-000123" value="<?= e($params['invoice_no']) ?>">
      </div>
      <?php endif; ?>

      <button class="btn btn-primary btn-sm" type="submit">
        <i class="bi bi-play-fill me-1"></i>Run report
      </button>
    </form>
  </div>
</div>

<?php if (!empty($report['chart']) && $report['rows'] !== []): ?>
<div class="card mb-3">
  <div class="card-body"><div class="chart-box" style="height:240px"><canvas id="reportChart"></canvas></div></div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><?= e($report['title']) ?>
      <span class="badge badge-soft ms-1"><?= count($report['rows']) ?> rows</span>
    </span>
    <?php if ($showDates): ?>
    <span class="small text-secondary">
      <?= e(fmt_date($params['from'])) ?> → <?= e(fmt_date($params['to'])) ?>
    </span>
    <?php endif; ?>
  </div>
  <div class="table-responsive" style="max-height:60vh">
    <table class="table table-hover table-sticky align-middle mb-0">
      <thead>
        <tr>
          <?php foreach ($report['columns'] as $col): ?>
          <th class="<?= !empty($col['num']) || !empty($col['money']) ? 'num' : '' ?>"><?= e($col['label']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($report['rows'] === []): ?>
        <tr><td colspan="<?= count($report['columns']) ?>">
          <div class="empty-state"><i class="bi bi-graph-up"></i>No data for this selection.</div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($report['rows'] as $row): ?>
        <tr>
          <?php foreach ($report['columns'] as $key => $col): ?>
          <td class="<?= !empty($col['num']) || !empty($col['money']) ? 'num' : '' ?>">
            <?php if (!empty($col['money'])): ?>
              <?= e(money($row[$key] ?? 0)) ?>
            <?php else: ?>
              <?= e((string) ($row[$key] ?? '')) ?>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($report['totals'] !== [] && $report['rows'] !== []): ?>
      <tfoot class="table-group-divider">
        <tr class="fw-bold">
          <?php $first = true; foreach ($report['columns'] as $key => $col): ?>
          <td class="<?= !empty($col['num']) || !empty($col['money']) ? 'num' : '' ?>">
            <?php if ($first): $first = false; ?>
              TOTAL
            <?php elseif (isset($report['totals'][$key])): ?>
              <?= !empty($col['money']) ? e(money($report['totals'][$key])) : e((string) $report['totals'][$key]) ?>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
