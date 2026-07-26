<?php
/**
 * Printable refund receipt (80mm-friendly, auto-print). Expects: $refund
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($refund['refund_no']) ?> · <?= e(setting('shop_name', APP_NAME)) ?></title>
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/app.css">
<style>
  body { background: #fff; }
  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>
<div class="receipt-80mm py-4">
  <div class="text-center">
    <div class="fw-bold fs-5"><?= e(setting('shop_name', APP_NAME)) ?></div>
    <?php if (setting('shop_address')): ?><div><?= e(setting('shop_address')) ?></div><?php endif; ?>
    <?php if (setting('shop_phone')): ?><div>Tel: <?= e(setting('shop_phone')) ?></div><?php endif; ?>
  </div>

  <div class="rule"></div>

  <div class="d-flex justify-content-between">
    <strong>REFUND RECEIPT</strong>
    <strong class="data"><?= e($refund['refund_no']) ?></strong>
  </div>
  <div class="d-flex justify-content-between">
    <span>Date</span><span><?= e(fmt_date($refund['created_at'], true)) ?></span>
  </div>
  <div class="d-flex justify-content-between">
    <span>Invoice</span><span class="data"><?= e($refund['invoice_no']) ?></span>
  </div>

  <div class="rule"></div>

  <div><strong>Customer:</strong> <?= e($refund['customer_name']) ?></div>
  <?php if (!empty($refund['customer_phone'])): ?>
    <div><strong>Phone:</strong> <span class="data"><?= e($refund['customer_phone']) ?></span></div>
  <?php endif; ?>
  <div class="mt-1"><strong>Reason:</strong> <?= e($refund['reason']) ?></div>

  <div class="rule"></div>

  <table class="w-100">
    <tr class="fw-bold fs-5">
      <td>REFUNDED (<?= e(payment_label($refund['method'])) ?>)</td>
      <td class="text-end data"><?= e(money($refund['amount'])) ?></td>
    </tr>
  </table>

  <div class="rule"></div>

  <div class="text-center">
    Processed by <?= e($refund['processed_by'] ?? '—') ?><br>
    <?= e(setting('receipt_footer', 'Thank you for your business!')) ?>
  </div>

  <div class="text-center mt-3 no-print">
    <button class="btn btn-primary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <a class="btn btn-outline-secondary btn-sm"
       href="<?= url('refunds/show', ['id' => $refund['id']]) ?>">Back</a>
  </div>
</div>
<script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
