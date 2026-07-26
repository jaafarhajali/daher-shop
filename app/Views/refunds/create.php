<?php
/**
 * New refund. Expects: $sale (null until chosen), $refundable
 */
?>
<div class="page-heading">
  <div>
    <h1>New refund</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('refunds/index') ?>">Refunds</a></li>
        <li class="breadcrumb-item active">New</li>
      </ol>
    </nav>
  </div>
</div>

<?php if ($sale === null): ?>
  <!-- Step 1: find the invoice (live search) -->
  <?php $pickerMode = 'refund'; require APP_PATH . '/Views/partials/invoice-picker.php'; ?>
<?php else: ?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('refunds/create') ?>">
    <i class="bi bi-arrow-left me-1"></i>Choose a different invoice
  </a>
</div>
<?php endif; ?>

<?php if ($sale !== null): ?>
<div class="card" style="max-width:640px">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>
      Invoice <span class="data"><?= e($sale['invoice_no']) ?></span>
      · <?= e($sale['customer_name'] ?? 'Walk-in customer') ?>
    </span>
    <span class="data"><?= e(money($sale['total'])) ?></span>
  </div>
  <div class="card-body">
    <dl class="row small mb-3">
      <dt class="col-6 text-secondary">Paid on this invoice</dt>
      <dd class="col-6 data text-end"><?= e(money($sale['paid_amount'])) ?></dd>
      <dt class="col-6 text-secondary">Refundable now</dt>
      <dd class="col-6 data text-end fw-bold <?= $refundable > 0 ? 'text-success' : 'text-danger' ?>">
        <?= e(money(max(0, $refundable))) ?>
      </dd>
    </dl>

    <?php if ($refundable <= 0.004): ?>
      <div class="alert alert-warning py-2 small mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Nothing is refundable — either nothing was paid yet (credit sale) or everything
        received has already been refunded.
      </div>
    <?php else: ?>
    <form method="post" action="<?= url('refunds/store') ?>"
          data-confirm="Record this refund? This cannot be undone.">
      <?= csrf_field() ?>
      <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="refAmount">Refund amount <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
            <input class="form-control data" id="refAmount" name="amount" type="number" required
                   min="0.01" step="0.01" max="<?= e(number_format($refundable, 2, '.', '')) ?>"
                   value="<?= e(number_format($refundable, 2, '.', '')) ?>">
          </div>
          <div class="form-text">Full refund = the maximum; enter less for a partial refund.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="refMethod">Refund method <span class="text-danger">*</span></label>
          <select class="form-select" id="refMethod" name="method" required>
            <option value="cash">Cash</option>
            <option value="card">Card</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="refReason">Reason <span class="text-danger">*</span></label>
          <input class="form-control" id="refReason" name="reason" required maxlength="255"
                 placeholder="e.g. Device returned defective" value="<?= old('reason') ?>">
        </div>
        <div class="col-12">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-cash-coin me-1"></i>Record refund
          </button>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
