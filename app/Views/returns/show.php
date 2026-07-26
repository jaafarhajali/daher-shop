<?php
/**
 * Return detail. Expects: $return, $items
 */
?>
<div class="page-heading">
  <div>
    <h1>Return <span class="data"><?= e($return['return_no']) ?></span></h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('returns/index') ?>">Returns</a></li>
        <li class="breadcrumb-item active"><?= e($return['return_no']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('sales/show', ['id' => $return['sale_id']]) ?>">
      <i class="bi bi-receipt me-1"></i>Original invoice
    </a>
    <button class="btn btn-primary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
  </div>
</div>

<div class="card" style="max-width:760px">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
      <div>
        <h2 class="h5 mb-1"><?= e(setting('shop_name', APP_NAME)) ?> — Product return</h2>
        <div class="small text-secondary">
          Against invoice
          <a class="data" href="<?= url('sales/show', ['id' => $return['sale_id']]) ?>"><?= e($return['invoice_no']) ?></a>
        </div>
      </div>
      <div class="text-end">
        <div class="h5 data mb-1"><?= e($return['return_no']) ?></div>
        <div class="small text-secondary"><?= e(fmt_date($return['created_at'], true)) ?></div>
      </div>
    </div>

    <div class="row mb-3 small">
      <div class="col-6">
        <div class="text-secondary text-uppercase" style="font-size:0.68rem;letter-spacing:.06em">Customer</div>
        <div class="fw-semibold"><?= e($return['customer_name']) ?></div>
        <?php if (!empty($return['customer_phone'])): ?><div class="data"><?= e($return['customer_phone']) ?></div><?php endif; ?>
      </div>
      <div class="col-6 text-end">
        <div class="text-secondary text-uppercase" style="font-size:0.68rem;letter-spacing:.06em">Processed by</div>
        <div><?= e($return['processed_by'] ?? '—') ?></div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e($item['product_name']) ?></td>
            <td class="num"><?= (int) $item['quantity'] ?></td>
            <td class="num"><?= e(money($item['unit_price'])) ?></td>
            <td class="num"><?= e(money($item['line_total'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td colspan="3" class="text-end">Return value</td>
            <td class="num"><?= e(money($return['total_value'])) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="small"><span class="text-secondary">Reason:</span> <?= e($return['reason']) ?></div>
  </div>
</div>
