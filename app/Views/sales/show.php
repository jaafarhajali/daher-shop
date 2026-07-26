<?php
/**
 * Invoice detail — screen view + print-ready invoice.
 * Expects: $sale, $items
 */
$balanceDue = (float) $sale['total'] - (float) $sale['paid_amount'];
?>
<div class="page-heading no-print">
  <div>
    <h1>Invoice <span class="data"><?= e($sale['invoice_no']) ?></span></h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('sales/index') ?>">Sales</a></li>
        <li class="breadcrumb-item active"><?= e($sale['invoice_no']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <?php if ($sale['status'] === 'completed'): ?>
    <form method="post" action="<?= url('sales/cancel') ?>"
          data-confirm="Cancel this sale? All items return to stock. This cannot be undone.">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $sale['id'] ?>">
      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cancel sale</button>
    </form>
    <?php endif; ?>
    <button class="btn btn-primary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print invoice
    </button>
  </div>
</div>

<?php if ($sale['status'] === 'cancelled'): ?>
<div class="alert alert-danger no-print">
  <i class="bi bi-x-circle me-2"></i>This sale was cancelled — its items were returned to stock.
</div>
<?php endif; ?>

<div class="card" style="max-width:820px">
  <div class="card-body p-4">

    <!-- Invoice header -->
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <h2 class="h4 mb-1"><?= e(setting('shop_name', APP_NAME)) ?></h2>
        <div class="small text-secondary">
          <?php if (setting('shop_address')): ?><?= e(setting('shop_address')) ?><br><?php endif; ?>
          <?php if (setting('shop_phone')): ?><i class="bi bi-telephone me-1"></i><?= e(setting('shop_phone')) ?><?php endif; ?>
          <?php if (setting('shop_email')): ?> · <?= e(setting('shop_email')) ?><?php endif; ?>
        </div>
      </div>
      <div class="text-end">
        <div class="h5 data mb-1"><?= e($sale['invoice_no']) ?></div>
        <div class="small text-secondary"><?= e(fmt_date($sale['created_at'], true)) ?></div>
        <?php if ($sale['status'] === 'cancelled'): ?>
          <span class="badge text-bg-danger mt-1">CANCELLED</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Bill to -->
    <div class="row mb-4 small">
      <div class="col-6">
        <div class="text-secondary text-uppercase" style="font-size:0.68rem;letter-spacing:.06em">Billed to</div>
        <div class="fw-semibold"><?= e($sale['customer_name'] ?? 'Walk-in customer') ?></div>
        <?php if (!empty($sale['customer_phone'])): ?><div class="data"><?= e($sale['customer_phone']) ?></div><?php endif; ?>
        <?php if (!empty($sale['customer_address'])): ?><div><?= e($sale['customer_address']) ?></div><?php endif; ?>
      </div>
      <div class="col-6 text-end">
        <div class="text-secondary text-uppercase" style="font-size:0.68rem;letter-spacing:.06em">Payment</div>
        <div><?= e(payment_label($sale['payment_method'])) ?></div>
        <div class="text-secondary">Served by <?= e($sale['cashier_name'] ?? '—') ?></div>
      </div>
    </div>

    <!-- Items -->
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th style="width:44px">#</th>
            <th>Item</th>
            <th class="num">Qty</th>
            <th class="num">Unit price</th>
            <th class="num">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $i => $item): ?>
          <tr>
            <td class="text-secondary"><?= $i + 1 ?></td>
            <td><?= e($item['product_name']) ?></td>
            <td class="num"><?= (int) $item['quantity'] ?></td>
            <td class="num"><?= e(money($item['unit_price'])) ?></td>
            <td class="num"><?= e(money($item['line_total'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Totals -->
    <div class="d-flex justify-content-end">
      <div style="min-width:260px">
        <div class="pos-total-row"><span class="text-secondary">Subtotal</span>
          <span class="data"><?= e(money($sale['subtotal'])) ?></span></div>
        <?php if ((float) $sale['discount'] > 0): ?>
        <div class="pos-total-row"><span class="text-secondary">Discount</span>
          <span class="data">−<?= e(money($sale['discount'])) ?></span></div>
        <?php endif; ?>
        <div class="pos-total-row grand"><span>Total</span>
          <span><?= e(money($sale['total'])) ?></span></div>
      </div>
    </div>

    <?php if (!empty($sale['notes'])): ?>
      <div class="small text-secondary mt-3"><strong>Note:</strong> <?= e($sale['notes']) ?></div>
    <?php endif; ?>

    <div class="text-center small text-secondary mt-4 pt-3 border-top">
      <?= e(setting('receipt_footer', 'Thank you for your business!')) ?>
    </div>
  </div>
</div>
