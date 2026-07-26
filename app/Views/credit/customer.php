<?php
/**
 * Credit — one customer: totals, open invoices with payment forms, history.
 * Expects: $customer, $invoices, $totals, $history
 */
?>
<div class="page-heading">
  <div>
    <h1>Credit — <?= e($customer['name']) ?></h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('credit/index') ?>">Credit</a></li>
        <li class="breadcrumb-item active"><?= e($customer['name']) ?></li>
      </ol>
    </nav>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('customers/show', ['id' => $customer['id']]) ?>">
    <i class="bi bi-person me-1"></i>Customer profile
  </a>
</div>

<!-- Totals -->
<div class="row g-3 mb-4">
  <?php
  $tiles = [
      ['label' => 'Total purchases', 'value' => money($totals['purchases']), 'icon' => 'bag',        'fg' => '#0d9488', 'bg' => 'rgba(13,148,136,.12)'],
      ['label' => 'Total paid',      'value' => money($totals['paid']),      'icon' => 'check2-circle', 'fg' => '#16a34a', 'bg' => 'rgba(22,163,74,.12)'],
      ['label' => 'Outstanding (دين)', 'value' => money($totals['outstanding']), 'icon' => 'exclamation-circle', 'fg' => '#dc2626', 'bg' => 'rgba(220,38,38,.12)'],
  ];
  foreach ($tiles as $t): ?>
  <div class="col-md-4">
    <div class="card kpi-card h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="kpi-icon" style="background:<?= $t['bg'] ?>;color:<?= $t['fg'] ?>">
          <i class="bi bi-<?= $t['icon'] ?>"></i>
        </div>
        <div class="min-w-0">
          <div class="kpi-label"><?= e($t['label']) ?></div>
          <div class="kpi-value"><?= e($t['value']) ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header">Open invoices</div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Date</th>
              <th class="num">Total</th>
              <th class="num">Paid</th>
              <th class="num">Balance</th>
              <th style="min-width:250px">Record payment</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($invoices === []): ?>
            <tr><td colspan="6">
              <div class="empty-state py-4"><i class="bi bi-emoji-smile"></i>No open invoices — this customer owes nothing.</div>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): $bal = (float) $inv['balance']; ?>
            <tr>
              <td><a class="data fw-semibold" href="<?= url('sales/show', ['id' => $inv['id']]) ?>"><?= e($inv['invoice_no']) ?></a></td>
              <td class="small"><?= e(fmt_date($inv['created_at'])) ?></td>
              <td class="num"><?= e(money($inv['total'])) ?></td>
              <td class="num text-success"><?= e(money($inv['paid_amount'])) ?></td>
              <td class="num fw-bold text-danger"><?= e(money($bal)) ?></td>
              <td>
                <form class="d-flex gap-1" method="post" action="<?= url('credit/pay') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="sale_id" value="<?= (int) $inv['id'] ?>">
                  <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
                  <input class="form-control form-control-sm data" style="width:90px" name="amount"
                         type="number" min="0.01" step="0.01" max="<?= e(number_format($bal, 2, '.', '')) ?>"
                         value="<?= e(number_format($bal, 2, '.', '')) ?>" required title="Amount">
                  <select class="form-select form-select-sm" name="method" style="width:78px" title="Method">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                  </select>
                  <input class="form-control form-control-sm" name="notes" placeholder="Note"
                         maxlength="255" style="width:90px">
                  <button class="btn btn-primary btn-sm" type="submit" title="Record payment">
                    <i class="bi bi-check-lg"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card">
      <div class="card-header">Payment history</div>
      <ul class="list-group list-group-flush small" style="max-height:420px;overflow-y:auto">
        <?php if ($history === []): ?>
          <li class="list-group-item"><div class="empty-state py-4"><i class="bi bi-clock-history"></i>No payments recorded yet.</div></li>
        <?php endif; ?>
        <?php foreach ($history as $h): ?>
        <li class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <span class="data"><?= e($h['invoice_no']) ?></span> · <?= e(payment_label($h['method'])) ?>
            <div class="text-secondary">
              <?= e(fmt_date($h['created_at'], true)) ?><?= !empty($h['username']) ? ' · ' . e($h['username']) : '' ?>
            </div>
            <?php if (!empty($h['notes'])): ?><div class="text-secondary"><?= e($h['notes']) ?></div><?php endif; ?>
          </div>
          <span class="data text-success">+<?= e(money($h['amount'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
