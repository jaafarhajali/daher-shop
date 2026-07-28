<?php
/**
 * Shop settings (admin only). Expects: $values (all settings key=>value)
 */
$v = static fn (string $key, string $default = '') => old($key, $values[$key] ?? $default);
?>
<div class="page-heading">
  <div>
    <h1>Shop settings</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Settings · Shop</li>
      </ol>
    </nav>
  </div>
</div>

<form method="post" action="<?= url('settings/save-shop') ?>">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-xl-6">
      <div class="card mb-3">
        <div class="card-header">Shop identity <span class="text-secondary small">(appears on invoices &amp; receipts)</span></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="shName">Shop name <span class="text-danger">*</span></label>
              <input class="form-control" id="shName" name="shop_name" required maxlength="100"
                     value="<?= $v('shop_name', 'Daher Phone') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="shAddress">Address</label>
              <input class="form-control" id="shAddress" name="shop_address" maxlength="255"
                     value="<?= $v('shop_address') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="shPhone">Phone</label>
              <input class="form-control data" id="shPhone" name="shop_phone" maxlength="30"
                     value="<?= $v('shop_phone') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="shEmail">Email</label>
              <input class="form-control" id="shEmail" name="shop_email" type="email" maxlength="150"
                     value="<?= $v('shop_email') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="shFooter">Receipt footer</label>
              <input class="form-control" id="shFooter" name="receipt_footer" maxlength="255"
                     value="<?= $v('receipt_footer', 'Thank you for your business!') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-6">
      <div class="card mb-3">
        <div class="card-header">Preferences</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="shSymbol">Currency symbol <span class="text-danger">*</span></label>
              <input class="form-control data" id="shSymbol" name="currency_symbol" required maxlength="8"
                     value="<?= $v('currency_symbol', '$') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="shPos">Symbol position</label>
              <select class="form-select" id="shPos" name="currency_position">
                <option value="before" <?= $v('currency_position', 'before') === 'before' ? 'selected' : '' ?>>Before — $100</option>
                <option value="after" <?= $v('currency_position') === 'after' ? 'selected' : '' ?>>After — 100 $</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="shDate">Date format</label>
              <select class="form-select" id="shDate" name="date_format">
                <option value="d/m/Y" <?= $v('date_format', 'd/m/Y') === 'd/m/Y' ? 'selected' : '' ?>>31/12/2026</option>
                <option value="m/d/Y" <?= $v('date_format') === 'm/d/Y' ? 'selected' : '' ?>>12/31/2026</option>
                <option value="Y-m-d" <?= $v('date_format') === 'Y-m-d' ? 'selected' : '' ?>>2026-12-31</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="shMin">Default low-stock level</label>
              <input class="form-control data" id="shMin" name="default_min_stock" type="number"
                     min="0" max="1000" value="<?= $v('default_min_stock', '3') ?>">
              <div class="form-text">Used when adding new products.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="shAccent">Accent color</label>
              <div class="input-group">
                <input class="form-control form-control-color" id="shAccentPicker" type="color"
                       value="<?= preg_match('/^#[0-9a-fA-F]{6}$/', $v('accent_color', '#0d9488')) ? $v('accent_color', '#0d9488') : '#0d9488' ?>"
                       title="Pick accent color" style="max-width:56px">
                <input class="form-control data" id="shAccent" name="accent_color" maxlength="7"
                       value="<?= $v('accent_color', '#0d9488') ?>">
              </div>
              <div class="form-text">Buttons, links and highlights across the app.</div>
            </div>
          </div>
        </div>
      </div>

      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Save settings</button>
    </div>
  </div>
</form>

<script>
// Keep the color picker and the hex field in sync.
(function () {
  var picker = document.getElementById('shAccentPicker');
  var text = document.getElementById('shAccent');
  picker.addEventListener('input', function () { text.value = picker.value; });
  text.addEventListener('input', function () {
    if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
  });
})();
</script>
