<?php
/**
 * New return — step 1: find invoice; step 2: choose items + quantities.
 * Expects: $sale (null until an invoice is chosen), $items (with 'returnable')
 */
$returnableItems = array_filter($items ?? [], static fn (array $i): bool => (int) $i['returnable'] > 0);
?>
<div class="page-heading">
  <div>
    <h1>New return</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('returns/index') ?>">Returns</a></li>
        <li class="breadcrumb-item active">New</li>
      </ol>
    </nav>
  </div>
</div>

<!-- Step 1: invoice lookup -->
<div class="card mb-3" style="max-width:640px">
  <div class="card-body">
    <form class="d-flex gap-2 align-items-end" method="get" action="index.php">
      <input type="hidden" name="r" value="returns/create">
      <div class="flex-grow-1">
        <label class="form-label" for="retInvoice">Invoice number</label>
        <input class="form-control data" id="retInvoice" name="invoice"
               placeholder="e.g. INV-000123" value="<?= e($sale['invoice_no'] ?? ($_GET['invoice'] ?? '')) ?>">
      </div>
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i>Find invoice</button>
    </form>
    <div class="form-text mt-2">
      Tip: you can also open any invoice from <a href="<?= url('sales/index') ?>">Sales</a> and press
      “Return items” there.
    </div>
  </div>
</div>

<?php if ($sale !== null): ?>
<form method="post" action="<?= url('returns/store') ?>"
      data-confirm="Process this return? Selected items go back into stock.">
  <?= csrf_field() ?>
  <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">

  <div class="card mb-3" style="max-width:860px">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>
        Invoice <span class="data"><?= e($sale['invoice_no']) ?></span>
        · <?= e($sale['customer_name'] ?? 'Walk-in customer') ?>
        · <?= e(fmt_date($sale['created_at'])) ?>
      </span>
      <span class="data"><?= e(money($sale['total'])) ?></span>
    </div>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Item</th>
            <th class="num">Sold</th>
            <th class="num">Already returned</th>
            <th class="num">Unit price</th>
            <th style="width:130px">Return qty</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($returnableItems === []): ?>
          <tr><td colspan="5">
            <div class="empty-state py-4"><i class="bi bi-check2-all"></i>
              Everything on this invoice has already been returned.
            </div>
          </td></tr>
          <?php endif; ?>
          <?php foreach ($items as $item): $returnable = (int) $item['returnable']; ?>
          <tr class="<?= $returnable === 0 ? 'opacity-50' : '' ?>">
            <td><?= e($item['product_name']) ?></td>
            <td class="num"><?= (int) $item['quantity'] ?></td>
            <td class="num"><?= (int) $item['quantity'] - $returnable ?></td>
            <td class="num"><?= e(money($item['unit_price'])) ?></td>
            <td>
              <?php if ($returnable > 0): ?>
              <input class="form-control form-control-sm data" type="number"
                     name="qty[<?= (int) $item['id'] ?>]" min="0" max="<?= $returnable ?>" value="0"
                     title="Up to <?= $returnable ?>">
              <?php else: ?>
              <span class="small text-secondary">fully returned</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card-body border-top">
      <div class="row g-3 align-items-end">
        <div class="col-md-8">
          <label class="form-label" for="retReason">Return reason <span class="text-danger">*</span></label>
          <input class="form-control" id="retReason" name="reason" required maxlength="255"
                 placeholder="e.g. Defective on arrival, customer changed mind…" value="<?= old('reason') ?>">
        </div>
        <div class="col-md-4 text-md-end">
          <button class="btn btn-primary" type="submit" <?= $returnableItems === [] ? 'disabled' : '' ?>>
            <i class="bi bi-arrow-counterclockwise me-1"></i>Process return
          </button>
        </div>
      </div>
      <div class="form-text mt-2">
        Stock items go back into inventory automatically. If this invoice still has an unpaid
        balance, the return value reduces the customer's debt.
      </div>
    </div>
  </div>
</form>
<?php endif; ?>
