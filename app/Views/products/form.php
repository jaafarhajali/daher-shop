<?php
/**
 * Product create/edit form with live profit calculation.
 * Expects: $product (null for create), $categories, $movements (edit only)
 */
$isEdit = $product !== null;
$val = static fn (string $key, $fallback = '') => old($key, $isEdit ? ($product[$key] ?? $fallback) : $fallback);
?>
<div class="page-heading">
  <div>
    <h1><?= $isEdit ? 'Edit product' : 'Add product' ?></h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('products/index') ?>">Products</a></li>
        <li class="breadcrumb-item active"><?= $isEdit ? e($product['name']) : 'New' ?></li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('products/index') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to list
  </a>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <form method="post"
          action="<?= $isEdit ? url('products/update') : url('products/store') ?>">
      <?= csrf_field() ?>
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
      <?php endif; ?>

      <div class="card mb-3">
        <div class="card-header">Product details</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label" for="pName">Product name <span class="text-danger">*</span></label>
              <input class="form-control" id="pName" name="name" required maxlength="150"
                     value="<?= $val('name') ?>">
              <?php if (form_error('name')): ?><div class="text-danger small mt-1"><?= form_error('name') ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="pCategory">Category <span class="text-danger">*</span></label>
              <select class="form-select" id="pCategory" name="category_id" required>
                <option value="">Choose…</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>"
                  <?= (string) $c['id'] === $val('category_id') ? 'selected' : '' ?>>
                  <?= e($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="pDesc">Description</label>
              <textarea class="form-control" id="pDesc" name="description" rows="2"
                        maxlength="2000"><?= $val('description') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="pBarcode">Barcode <span class="text-secondary">(optional)</span></label>
              <input class="form-control data" id="pBarcode" name="barcode" maxlength="64"
                     value="<?= $val('barcode') ?>" placeholder="Scan or type…">
              <?php if (form_error('barcode')): ?><div class="text-danger small mt-1"><?= form_error('barcode') ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="pWarranty">Warranty (days)</label>
              <input class="form-control data" id="pWarranty" name="warranty_days" type="number"
                     min="0" max="3650" step="1" placeholder="e.g. 30, 90, 180, 365"
                     value="<?= $val('warranty_days', '0') ?>">
              <div class="form-text">Days of warranty from the sale date. 0 or empty = no warranty.</div>
              <?php if (form_error('warranty_days')): ?><div class="text-danger small mt-1"><?= form_error('warranty_days') ?></div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Pricing</div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label" for="pCost">Purchase cost <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
                <input class="form-control data" id="pCost" name="cost_price" type="number"
                       step="0.01" min="0" required value="<?= $val('cost_price') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="pPrice">Selling price <span class="text-secondary">(optional)</span></label>
              <div class="input-group">
                <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
                <input class="form-control data" id="pPrice" name="selling_price" type="number"
                       step="0.01" min="0" placeholder="Not set yet" value="<?= $val('selling_price') ?>">
              </div>
              <div class="form-text">Leave empty if unknown — the product cannot be sold until a price is set.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Profit per item</label>
              <div class="form-control-plaintext data fw-semibold" id="pProfit">—</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Stock</div>
        <div class="card-body">
          <div class="row g-3">
            <?php if (!$isEdit): ?>
            <div class="col-md-6">
              <label class="form-label" for="pQty">Opening quantity</label>
              <input class="form-control data" id="pQty" name="quantity" type="number" min="0"
                     value="<?= $val('quantity', '0') ?>">
            </div>
            <?php else: ?>
            <div class="col-md-6">
              <label class="form-label">Current quantity</label>
              <div class="form-control-plaintext data fw-semibold"><?= (int) $product['quantity'] ?></div>
              <div class="form-text">Use “Adjust stock” on the right — every change is journaled.</div>
            </div>
            <?php endif; ?>
            <div class="col-md-6">
              <label class="form-label" for="pMin">Low-stock alert level <span class="text-danger">*</span></label>
              <input class="form-control data" id="pMin" name="min_stock" type="number" min="0" required
                     value="<?= $val('min_stock', setting('default_min_stock', '3')) ?>">
              <div class="form-text">Alerts appear when stock falls to this level or below.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save changes' : 'Add product' ?>
        </button>
        <a class="btn btn-outline-secondary" href="<?= url('products/index') ?>">Cancel</a>
      </div>
    </form>
  </div>

  <?php if ($isEdit): ?>
  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header">Adjust stock</div>
      <div class="card-body">
        <form method="post" action="<?= url('products/adjust-stock') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
          <div class="mb-3">
            <label class="form-label" for="adjChange">Quantity change</label>
            <input class="form-control data" id="adjChange" name="change" type="number" required
                   placeholder="+5 restock · -2 correction">
            <div class="form-text">Positive adds stock, negative removes it.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="adjNote">Reason</label>
            <input class="form-control" id="adjNote" name="note" maxlength="255"
                   placeholder="e.g. Supplier delivery">
          </div>
          <button class="btn btn-outline-primary w-100" type="submit">
            <i class="bi bi-arrow-repeat me-1"></i>Apply adjustment
          </button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Stock history</div>
      <div class="table-responsive" style="max-height:340px">
        <table class="table table-sm mb-0">
          <tbody>
            <?php if (empty($movements)): ?>
              <tr><td class="text-secondary small p-3">No movements yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($movements ?? [] as $mv): ?>
            <tr>
              <td>
                <span class="data <?= (int) $mv['change_qty'] >= 0 ? 'text-success' : 'text-danger' ?>">
                  <?= (int) $mv['change_qty'] >= 0 ? '+' : '' ?><?= (int) $mv['change_qty'] ?>
                </span>
                <span class="small text-secondary ms-1"><?= e($mv['type']) ?></span>
                <?php if (!empty($mv['reference'])): ?>
                  <span class="data small text-secondary"><?= e($mv['reference']) ?></span>
                <?php endif; ?>
                <div class="small text-secondary">
                  <?= e(fmt_date($mv['created_at'], true)) ?><?= !empty($mv['note']) ? ' · ' . e($mv['note']) : '' ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
// Live profit preview: selling price − purchase cost.
(function () {
  var cost = document.getElementById('pCost');
  var price = document.getElementById('pPrice');
  var out = document.getElementById('pProfit');

  function refresh() {
    if (price.value === '') {
      out.textContent = '— no selling price yet';
      out.classList.remove('text-success', 'text-danger');
      return;
    }
    var p = (parseFloat(price.value) || 0) - (parseFloat(cost.value) || 0);
    out.textContent = '<?= e(setting('currency_symbol', '$')) ?>' + DS.money(p);
    out.classList.toggle('text-success', p > 0);
    out.classList.toggle('text-danger', p < 0);
  }
  cost.addEventListener('input', refresh);
  price.addEventListener('input', refresh);
  refresh();
})();
</script>
