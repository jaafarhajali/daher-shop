<?php
/**
 * Repair tickets list. Expects: $pg, $filters
 */

use App\Models\Repair;
?>
<div class="page-heading">
  <div>
    <h1>Repairs</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Repairs</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('repairs/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New repair ticket
  </a>
</div>

<div class="card">
  <div class="card-header">
    <form class="filters-bar" method="get" action="index.php">
      <input type="hidden" name="r" value="repairs/index">
      <div>
        <label class="form-label mb-1 small">Search</label>
        <input type="search" class="form-control form-control-sm" name="q" style="min-width:220px"
               placeholder="Ticket #, customer, serial/IMEI…" value="<?= e($filters['q']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">Status</label>
        <select class="form-select form-select-sm" name="status">
          <option value="">All</option>
          <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>All open</option>
          <?php foreach (Repair::STATUSES as $s): $meta = repair_status_meta($s); ?>
          <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
          <?php endforeach; ?>
        </select>
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
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('repairs/index') ?>">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0">
      <thead>
        <tr>
          <th>Ticket</th>
          <th>Customer</th>
          <th>Device</th>
          <th>Received</th>
          <th>Status</th>
          <th class="num">Total</th>
          <th class="num">Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
        <tr><td colspan="7">
          <div class="empty-state"><i class="bi bi-tools"></i>No repair tickets match.</div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($pg['rows'] as $r): $meta = repair_status_meta($r['status']); $bal = (float) $r['balance']; ?>
        <tr>
          <td><a class="data fw-semibold" href="<?= url('repairs/show', ['id' => $r['id']]) ?>"><?= e($r['ticket_no']) ?></a></td>
          <td>
            <?= e($r['customer_name']) ?>
            <?php if (!empty($r['customer_phone'])): ?>
              <div class="data small text-secondary"><?= e($r['customer_phone']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?= e($r['device_type']) ?>
            <div class="small text-secondary"><?= e(trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? ''))) ?></div>
          </td>
          <td class="small"><?= e(fmt_date($r['received_at'])) ?></td>
          <td><span class="badge text-bg-<?= $meta['color'] ?>"><i class="bi bi-<?= $meta['icon'] ?> me-1"></i><?= e($meta['label']) ?></span></td>
          <td class="num"><?= e(money($r['total_cost'])) ?></td>
          <td class="num <?= $bal > 0.004 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= e(money($bal)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php require APP_PATH . '/Views/partials/pagination.php'; ?>
</div>
