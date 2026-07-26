<?php
/**
 * Customer profile: contact card + purchase & repair history.
 * Expects: $customer, $purchases, $repairs, $lifetime
 */
?>
<div class="page-heading">
  <div>
    <h1><?= e($customer['name']) ?></h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('customers/index') ?>">Customers</a></li>
        <li class="breadcrumb-item active"><?= e($customer['name']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('repairs/create', ['customer_id' => $customer['id']]) ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-tools me-1"></i>New repair
    </a>
    <a href="<?= url('customers/edit', ['id' => $customer['id']]) ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-pencil me-1"></i>Edit
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header">Contact</div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-4 text-secondary">Phone</dt>
          <dd class="col-8 data"><?= e($customer['phone'] ?? '—') ?></dd>
          <dt class="col-4 text-secondary">Email</dt>
          <dd class="col-8"><?= e($customer['email'] ?? '—') ?></dd>
          <dt class="col-4 text-secondary">Address</dt>
          <dd class="col-8"><?= e($customer['address'] ?? '—') ?></dd>
          <dt class="col-4 text-secondary">Customer since</dt>
          <dd class="col-8"><?= e(fmt_date($customer['created_at'])) ?></dd>
          <dt class="col-4 text-secondary">Lifetime value</dt>
          <dd class="col-8 data fw-semibold text-accent"><?= e(money($lifetime)) ?></dd>
        </dl>
        <?php if (!empty($customer['notes'])): ?>
          <hr>
          <div class="small"><span class="text-secondary">Notes:</span> <?= nl2br(e($customer['notes'])) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <form method="post" action="<?= url('customers/delete') ?>"
          data-confirm="Delete &quot;<?= e($customer['name']) ?>&quot;? Past sales are kept as walk-in sales.">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
      <button class="btn btn-outline-danger btn-sm w-100">
        <i class="bi bi-trash me-1"></i>Delete customer
      </button>
    </form>
  </div>

  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-header">Purchase history <span class="badge badge-soft ms-1"><?= count($purchases) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr><th>Invoice</th><th>Date</th><th>Payment</th><th>Status</th><th class="num">Total</th></tr>
          </thead>
          <tbody>
            <?php if ($purchases === []): ?>
              <tr><td colspan="5"><div class="empty-state py-4"><i class="bi bi-receipt"></i>No purchases yet.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($purchases as $s): ?>
            <tr>
              <td><a class="data" href="<?= url('sales/show', ['id' => $s['id']]) ?>"><?= e($s['invoice_no']) ?></a></td>
              <td class="small"><?= e(fmt_date($s['created_at'], true)) ?></td>
              <td class="small"><?= e(payment_label($s['payment_method'])) ?></td>
              <td>
                <span class="badge text-bg-<?= $s['status'] === 'completed' ? 'success' : 'danger' ?>">
                  <?= e($s['status']) ?>
                </span>
              </td>
              <td class="num"><?= e(money($s['total'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Repair history <span class="badge badge-soft ms-1"><?= count($repairs) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr><th>Ticket</th><th>Device</th><th>Received</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th></tr>
          </thead>
          <tbody>
            <?php if ($repairs === []): ?>
              <tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-tools"></i>No repairs yet.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($repairs as $r): $meta = repair_status_meta($r['status']); ?>
            <tr>
              <td><a class="data" href="<?= url('repairs/show', ['id' => $r['id']]) ?>"><?= e($r['ticket_no']) ?></a></td>
              <td class="small"><?= e(trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? ''))) ?: e($r['device_type']) ?></td>
              <td class="small"><?= e(fmt_date($r['received_at'])) ?></td>
              <td><span class="badge text-bg-<?= $meta['color'] ?>"><?= e($meta['label']) ?></span></td>
              <td class="num"><?= e(money($r['total_cost'])) ?></td>
              <td class="num <?= (float) $r['total_cost'] - (float) $r['paid_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?= e(money((float) $r['total_cost'] - (float) $r['paid_amount'])) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
