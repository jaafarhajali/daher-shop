<?php
/**
 * Printable repair receipt (80mm-friendly, opens in a new tab, auto-print).
 * Expects: $repair, $parts
 */
$balance = (float) $repair['total_cost'] - (float) $repair['paid_amount'];
$meta = repair_status_meta($repair['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($repair['ticket_no']) ?> · <?= e(setting('shop_name', APP_NAME)) ?></title>
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
    <strong>REPAIR TICKET</strong>
    <strong class="data"><?= e($repair['ticket_no']) ?></strong>
  </div>
  <div class="d-flex justify-content-between">
    <span>Received</span><span><?= e(fmt_date($repair['received_at'], true)) ?></span>
  </div>
  <div class="d-flex justify-content-between">
    <span>Status</span><span><?= e($meta['label']) ?></span>
  </div>

  <div class="rule"></div>

  <div><strong>Customer:</strong> <?= e($repair['customer_name']) ?></div>
  <?php if (!empty($repair['customer_phone'])): ?>
    <div><strong>Phone:</strong> <span class="data"><?= e($repair['customer_phone']) ?></span></div>
  <?php endif; ?>

  <div class="rule"></div>

  <div><strong>Device:</strong>
    <?= e($repair['device_type']) ?>
    <?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? ''))) ?>
  </div>
  <?php if (!empty($repair['serial_no'])): ?>
    <div><strong>Serial/IMEI:</strong> <span class="data"><?= e($repair['serial_no']) ?></span></div>
  <?php endif; ?>
  <div class="mt-1"><strong>Problem:</strong> <?= e($repair['problem']) ?></div>

  <div class="rule"></div>

  <?php if ($parts !== []): ?>
  <table class="w-100">
    <?php foreach ($parts as $p): ?>
    <tr>
      <td><?= e($p['part_name']) ?> ×<?= (int) $p['quantity'] ?></td>
      <td class="text-end data"><?= e(money((float) $p['unit_price'] * (int) $p['quantity'])) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <table class="w-100">
    <tr><td>Labor</td><td class="text-end data"><?= e(money($repair['labor_cost'])) ?></td></tr>
    <tr class="fw-bold"><td>TOTAL</td><td class="text-end data"><?= e(money($repair['total_cost'])) ?></td></tr>
    <tr><td>Paid</td><td class="text-end data"><?= e(money($repair['paid_amount'])) ?></td></tr>
    <tr class="fw-bold"><td>BALANCE DUE</td><td class="text-end data"><?= e(money($balance)) ?></td></tr>
  </table>

  <div class="rule"></div>

  <div class="text-center">
    <?= e(setting('receipt_footer', 'Thank you for your business!')) ?><br>
    Keep this ticket to collect your device.
  </div>

  <div class="text-center mt-3 no-print">
    <button class="btn btn-primary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>Print
    </button>
    <a class="btn btn-outline-secondary btn-sm"
       href="<?= url('repairs/show', ['id' => $repair['id']]) ?>">Back to ticket</a>
  </div>
</div>
<script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
