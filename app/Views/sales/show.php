<?php
/**
 * Invoice detail — screen view + print-ready invoice.
 * Expects: $sale, $items, $payments, $returns, $refunds, $refunded, $refundable
 */
$paidMeta = paid_status_meta((float) $sale['total'], (float) $sale['paid_amount']);
$balanceDue = $paidMeta['outstanding'];
$hasWarrantyColumn = array_filter($items, static fn (array $i): bool => (int) ($i['warranty_days'] ?? 0) > 0) !== [];
$isCompleted = $sale['status'] === 'completed';
?>
<div class="page-heading no-print">
  <div>
    <h1>Invoice <span class="data"><?= e($sale['invoice_no']) ?></span>
      <?php if ($isCompleted): ?>
        <span class="badge text-bg-<?= $paidMeta['color'] ?> align-middle ms-1"><?= e($paidMeta['label']) ?></span>
      <?php endif; ?>
    </h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('sales/index') ?>">Sales</a></li>
        <li class="breadcrumb-item active"><?= e($sale['invoice_no']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($isCompleted): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('returns/create', ['sale_id' => $sale['id']]) ?>">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Return items
      </a>
      <?php if ($refundable > 0.004): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= url('refunds/create', ['sale_id' => $sale['id']]) ?>">
        <i class="bi bi-cash-coin me-1"></i>Refund money
      </a>
      <?php endif; ?>
      <?php if ($payments === [] && $returns === [] && $refunds === []): ?>
      <form method="post" action="<?= url('sales/cancel') ?>"
            data-confirm="Cancel this sale? All items return to stock. This cannot be undone.">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $sale['id'] ?>">
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cancel sale</button>
      </form>
      <?php endif; ?>
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
<?php elseif ($balanceDue > 0.004): ?>
<div class="alert alert-warning no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
  <span>
    <i class="bi bi-exclamation-circle me-2"></i>
    Outstanding balance on this invoice: <strong class="data"><?= e(money($balanceDue)) ?></strong>
  </span>
  <?php if (!empty($sale['customer_id'])): ?>
  <a class="btn btn-warning btn-sm" href="<?= url('credit/customer', ['id' => $sale['customer_id']]) ?>">
    <i class="bi bi-cash me-1"></i>Record a payment
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:860px">
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
        <?php elseif ($balanceDue > 0.004): ?>
          <span class="badge text-bg-<?= $paidMeta['color'] ?> mt-1"><?= e($paidMeta['label']) ?></span>
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
            <?php if ($hasWarrantyColumn): ?><th>Warranty</th><?php endif; ?>
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
            <?php if ($hasWarrantyColumn): ?>
            <td class="small">
              <?php if ((int) ($item['warranty_days'] ?? 0) > 0):
                  $ws = warranty_status($item['warranty_expires'] ?? null); ?>
                <span class="badge text-bg-<?= $ws['color'] ?>"><?= e($ws['label']) ?></span>
                <div class="text-secondary mt-1">
                  <?= (int) $item['warranty_days'] ?> days · until <?= e(fmt_date($item['warranty_expires'])) ?>
                </div>
              <?php else: ?>
                <span class="text-secondary">—</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Totals -->
    <div class="d-flex justify-content-end">
      <div style="min-width:280px">
        <div class="pos-total-row"><span class="text-secondary">Subtotal</span>
          <span class="data"><?= e(money($sale['subtotal'])) ?></span></div>
        <?php if ((float) $sale['discount'] > 0): ?>
        <div class="pos-total-row"><span class="text-secondary">Discount</span>
          <span class="data">−<?= e(money($sale['discount'])) ?></span></div>
        <?php endif; ?>
        <div class="pos-total-row grand"><span>Total</span>
          <span><?= e(money($sale['total'])) ?></span></div>
        <?php if ($isCompleted && ($balanceDue > 0.004 || (float) $sale['paid_amount'] < (float) $sale['total'] || $payments !== [] || $returnCredit > 0.004)): ?>
        <div class="pos-total-row"><span class="text-secondary">Paid (money received)</span>
          <span class="data text-success"><?= e(money($moneyReceived)) ?></span></div>
        <?php if ($returnCredit > 0.004): ?>
        <div class="pos-total-row"><span class="text-secondary">Return credit</span>
          <span class="data"><?= e(money($returnCredit)) ?></span></div>
        <?php endif; ?>
        <div class="pos-total-row"><span class="text-secondary">Balance due</span>
          <span class="data fw-bold <?= $balanceDue > 0.004 ? 'text-danger' : 'text-success' ?>">
            <?= e(money($balanceDue)) ?>
          </span></div>
        <?php endif; ?>
        <?php if ($refunded > 0.004): ?>
        <div class="pos-total-row"><span class="text-secondary">Refunded</span>
          <span class="data text-danger">−<?= e(money($refunded)) ?></span></div>
        <?php endif; ?>
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

<?php if ($payments !== [] || $returns !== [] || $refunds !== []): ?>
<div class="row g-3 mt-1 no-print" style="max-width:860px">

  <?php if ($payments !== []): ?>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Payments</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($payments as $p): ?>
        <li class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <?= e(payment_label($p['method'])) ?>
            <div class="text-secondary"><?= e(fmt_date($p['created_at'], true)) ?><?= !empty($p['username']) ? ' · ' . e($p['username']) : '' ?></div>
            <?php if (!empty($p['notes'])): ?><div class="text-secondary"><?= e($p['notes']) ?></div><?php endif; ?>
          </div>
          <span class="data text-success">+<?= e(money($p['amount'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($returns !== []): ?>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Returns</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($returns as $r): ?>
        <li class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <a class="data" href="<?= url('returns/show', ['id' => $r['id']]) ?>"><?= e($r['return_no']) ?></a>
            <div class="text-secondary"><?= (int) $r['item_count'] ?> item(s) · <?= e(fmt_date($r['created_at'], true)) ?></div>
          </div>
          <span class="data"><?= e(money($r['total_value'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($refunds !== []): ?>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Refunds</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($refunds as $r): ?>
        <li class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <a class="data" href="<?= url('refunds/show', ['id' => $r['id']]) ?>"><?= e($r['refund_no']) ?></a>
            <div class="text-secondary"><?= e(payment_label($r['method'])) ?> · <?= e(fmt_date($r['created_at'], true)) ?></div>
          </div>
          <span class="data text-danger">−<?= e(money($r['amount'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
