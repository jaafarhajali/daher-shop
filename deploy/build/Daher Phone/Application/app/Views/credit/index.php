<?php
/**
 * Credit — all customers with outstanding balances.
 * Expects: $debtors, $total
 */
?>
<div class="page-heading">
  <div>
    <h1>Credit</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Credit payments</li>
      </ol>
    </nav>
  </div>
  <div class="text-end">
    <div class="small text-secondary">Total outstanding</div>
    <div class="data fw-bold fs-5 <?= $total > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($total)) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span>Customers with debt <span class="badge badge-soft ms-1"><?= count($debtors) ?></span></span>
    <input class="form-control form-control-sm" style="max-width:240px" type="search"
           placeholder="Filter…" data-table-filter="#debtorsTable">
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0" id="debtorsTable">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Phone</th>
          <th class="num">Open invoices</th>
          <th>Oldest debt</th>
          <th class="num">Outstanding</th>
          <th style="width:150px" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($debtors === []): ?>
        <tr><td colspan="6">
          <div class="empty-state"><i class="bi bi-emoji-smile"></i>No outstanding debts — everyone has paid up.</div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($debtors as $d): ?>
        <tr>
          <td>
            <a class="fw-semibold" href="<?= url('credit/customer', ['id' => $d['id']]) ?>"><?= e($d['name']) ?></a>
          </td>
          <td class="data"><?= e($d['phone'] ?? '—') ?></td>
          <td class="num"><?= (int) $d['open_invoices'] ?></td>
          <td class="small text-secondary"><?= e(fmt_date($d['oldest_invoice_at'])) ?></td>
          <td class="num fw-bold text-danger"><?= e(money($d['outstanding'])) ?></td>
          <td class="text-end">
            <a class="btn btn-primary btn-sm" href="<?= url('credit/customer', ['id' => $d['id']]) ?>">
              <i class="bi bi-cash me-1"></i>Record payment
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
