<?php
/**
 * Refunds list. Expects: $pg, $filters
 */
?>
<div class="page-heading">
  <div>
    <h1>Refunds</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Refunds</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('refunds/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-cash-coin me-1"></i>New refund
  </a>
</div>

<div class="card">
  <div class="card-header">
    <form class="filters-bar" method="get" action="index.php">
      <input type="hidden" name="r" value="refunds/index">
      <div>
        <label class="form-label mb-1 small">Search</label>
        <input type="search" class="form-control form-control-sm" name="q" style="min-width:220px"
               placeholder="Refund #, invoice # or customer…" value="<?= e($filters['q']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">From</label>
        <input type="date" class="form-control form-control-sm" name="from" value="<?= e($filters['from']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">To</label>
        <input type="date" class="form-control form-control-sm" name="to" value="<?= e($filters['to']) ?>">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('refunds/index') ?>">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0">
      <thead>
        <tr>
          <th>Refund</th>
          <th>Invoice</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Method</th>
          <th>Reason</th>
          <th class="num">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
        <tr><td colspan="7">
          <div class="empty-state"><i class="bi bi-cash-coin"></i>No refunds recorded.</div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($pg['rows'] as $r): ?>
        <tr>
          <td><a class="data fw-semibold" href="<?= url('refunds/show', ['id' => $r['id']]) ?>"><?= e($r['refund_no']) ?></a></td>
          <td><a class="data" href="<?= url('sales/show', ['id' => $r['sale_id']]) ?>"><?= e($r['invoice_no']) ?></a></td>
          <td><?= e($r['customer_name']) ?></td>
          <td class="small"><?= e(fmt_date($r['created_at'], true)) ?></td>
          <td class="small"><?= e(payment_label($r['method'])) ?></td>
          <td class="small text-secondary text-truncate" style="max-width:220px"><?= e($r['reason']) ?></td>
          <td class="num fw-semibold text-danger">−<?= e(money($r['amount'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php require APP_PATH . '/Views/partials/pagination.php'; ?>
</div>
