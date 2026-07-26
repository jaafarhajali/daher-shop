<?php
/**
 * Refund detail. Expects: $refund
 */
?>
<div class="page-heading">
  <div>
    <h1>Refund <span class="data"><?= e($refund['refund_no']) ?></span></h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('refunds/index') ?>">Refunds</a></li>
        <li class="breadcrumb-item active"><?= e($refund['refund_no']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('sales/show', ['id' => $refund['sale_id']]) ?>">
      <i class="bi bi-receipt me-1"></i>Original invoice
    </a>
    <a class="btn btn-primary btn-sm" target="_blank" href="<?= url('refunds/print', ['id' => $refund['id']]) ?>">
      <i class="bi bi-printer me-1"></i>Print receipt
    </a>
  </div>
</div>

<div class="card" style="max-width:560px">
  <div class="card-body p-4">
    <dl class="row mb-0">
      <dt class="col-5 text-secondary">Refund number</dt>
      <dd class="col-7 data"><?= e($refund['refund_no']) ?></dd>
      <dt class="col-5 text-secondary">Original invoice</dt>
      <dd class="col-7">
        <a class="data" href="<?= url('sales/show', ['id' => $refund['sale_id']]) ?>"><?= e($refund['invoice_no']) ?></a>
        <span class="text-secondary small">(total <?= e(money($refund['sale_total'])) ?>)</span>
      </dd>
      <dt class="col-5 text-secondary">Customer</dt>
      <dd class="col-7"><?= e($refund['customer_name']) ?></dd>
      <dt class="col-5 text-secondary">Amount</dt>
      <dd class="col-7 data fw-bold text-danger">−<?= e(money($refund['amount'])) ?></dd>
      <dt class="col-5 text-secondary">Method</dt>
      <dd class="col-7"><?= e(payment_label($refund['method'])) ?></dd>
      <dt class="col-5 text-secondary">Reason</dt>
      <dd class="col-7"><?= e($refund['reason']) ?></dd>
      <dt class="col-5 text-secondary">Date</dt>
      <dd class="col-7"><?= e(fmt_date($refund['created_at'], true)) ?></dd>
      <dt class="col-5 text-secondary">Processed by</dt>
      <dd class="col-7"><?= e($refund['processed_by'] ?? '—') ?></dd>
    </dl>
  </div>
</div>
