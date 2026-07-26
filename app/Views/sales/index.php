<?php
/**
 * Sales history with filters. Expects: $pg, $filters
 */
?>
<div class="page-heading">
  <div>
    <h1>Sales</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Sales</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('sales/pos') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-basket2 me-1"></i>New sale
  </a>
</div>

<div class="card">
  <div class="card-header">
    <form class="filters-bar" method="get" action="index.php">
      <input type="hidden" name="r" value="sales/index">
      <div>
        <label class="form-label mb-1 small">Search</label>
        <input type="search" class="form-control form-control-sm" name="q"
               placeholder="Invoice # or customer…" value="<?= e($filters['q']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">From</label>
        <input type="date" class="form-control form-control-sm" name="from" value="<?= e($filters['from']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">To</label>
        <input type="date" class="form-control form-control-sm" name="to" value="<?= e($filters['to']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="">All</option>
          <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
          <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>
      <div>
        <label class="form-label mb-1 small">Payment</label>
        <select class="form-select form-select-sm" name="method">
          <option value="">All</option>
          <option value="cash" <?= $filters['method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
          <option value="card" <?= $filters['method'] === 'card' ? 'selected' : '' ?>>Card</option>
          <option value="credit" <?= $filters['method'] === 'credit' ? 'selected' : '' ?>>Credit</option>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('sales/index') ?>">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0">
      <thead>
        <tr>
          <th>Invoice</th>
          <th>Customer</th>
          <th>Date</th>
          <th class="num">Items</th>
          <th>Payment</th>
          <th>Status</th>
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
        <tr><td colspan="7">
          <div class="empty-state"><i class="bi bi-receipt"></i>No sales found for these filters.</div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($pg['rows'] as $s): ?>
        <tr>
          <td><a class="data fw-semibold" href="<?= url('sales/show', ['id' => $s['id']]) ?>"><?= e($s['invoice_no']) ?></a></td>
          <td><?= e($s['customer_name']) ?></td>
          <td class="small"><?= e(fmt_date($s['created_at'], true)) ?></td>
          <td class="num"><?= (int) $s['item_count'] ?></td>
          <td class="small"><?= e(payment_label($s['payment_method'])) ?></td>
          <td>
            <?php if ($s['status'] === 'cancelled'): ?>
              <span class="badge text-bg-danger">cancelled</span>
            <?php else: $pm = paid_status_meta((float) $s['total'], (float) $s['paid_amount']); ?>
              <span class="badge text-bg-<?= $pm['color'] ?>"><?= e($pm['label']) ?></span>
            <?php endif; ?>
          </td>
          <td class="num fw-semibold"><?= e(money($s['total'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php require APP_PATH . '/Views/partials/pagination.php'; ?>
</div>
