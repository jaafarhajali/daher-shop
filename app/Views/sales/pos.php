<?php
/**
 * Point of Sale. All cart logic lives in assets/js/pos.js (window.POS data).
 */
?>
<div class="page-heading">
  <div>
    <h1>Point of Sale</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">New sale</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('sales/index') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-receipt me-1"></i>Sales history
  </a>
</div>

<input type="hidden" id="posToken" value="<?= e(\App\Core\Csrf::token()) ?>">

<div class="pos-grid">
  <!-- Left: product search -->
  <div>
    <div class="card">
      <div class="card-body">
        <label class="form-label" for="posSearch">
          <i class="bi bi-upc-scan me-1"></i>Scan barcode or search products
        </label>
        <input type="search" class="form-control form-control-lg" id="posSearch"
               placeholder="Start typing… (Ctrl+K)" autocomplete="off" autofocus>
        <div class="form-text">
          Scanning a barcode adds the item instantly. Press <kbd>Enter</kbd> to add the first result,
          <kbd>F9</kbd> to complete the sale.
        </div>

        <div class="pos-results mt-3" id="posResults">
          <div class="empty-state">
            <i class="bi bi-search"></i>
            Search results appear here.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: cart -->
  <div>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cart3 me-1"></i>Cart</span>
        <button class="btn btn-link btn-sm text-danger p-0" id="posClearCart" type="button">Clear</button>
      </div>
      <div class="card-body">

        <!-- Customer -->
        <div class="mb-3 position-relative">
          <label class="form-label">Customer</label>
          <div id="posCustomerSelected" class="d-none align-items-center justify-content-between
                border rounded px-3 py-2">
            <span><i class="bi bi-person me-1"></i><span id="posCustomerName"></span></span>
            <button class="btn-close" type="button" id="posCustomerClear" aria-label="Remove customer"></button>
          </div>
          <div id="posCustomerPicker">
            <input type="text" class="form-control" id="posCustomerSearch"
                   placeholder="Walk-in — or search name / phone…" autocomplete="off">
            <div class="list-group position-absolute w-100 shadow-sm d-none" id="posCustomerResults"
                 style="z-index:1050; max-height:220px; overflow-y:auto;"></div>
            <a class="small text-decoration-none" href="<?= url('customers/create', ['return' => 'pos']) ?>">
              <i class="bi bi-plus-circle me-1"></i>New customer
            </a>
          </div>
        </div>

        <!-- Lines -->
        <div class="cart-lines" id="posCartBody">
          <div class="empty-state py-4"><i class="bi bi-cart"></i>Cart is empty.</div>
        </div>

        <!-- Totals -->
        <div class="pos-total-row">
          <span class="text-secondary">Subtotal</span>
          <span class="data" id="posSubtotal">—</span>
        </div>
        <div class="pos-total-row align-items-center">
          <span class="text-secondary">Discount</span>
          <div class="input-group input-group-sm" style="width:130px">
            <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
            <input type="number" class="form-control data text-end" id="posDiscount"
                   min="0" step="0.01" value="0">
          </div>
        </div>
        <div class="pos-total-row grand">
          <span>Total</span>
          <span id="posTotal">—</span>
        </div>

        <!-- Payment -->
        <div class="row g-2 mt-1">
          <div class="col-6">
            <label class="form-label small" for="posMethod">Payment method</label>
            <select class="form-select form-select-sm" id="posMethod">
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="credit">Credit (دين)</option>
            </select>
            <div class="form-text text-danger d-none" id="posCreditHint">
              <i class="bi bi-exclamation-circle me-1"></i>Credit sales need a customer.
            </div>
          </div>
          <div class="col-6">
            <label class="form-label small" for="posNotes">Note</label>
            <input class="form-control form-control-sm" id="posNotes" maxlength="255" placeholder="Optional">
          </div>
        </div>

        <button class="btn btn-primary w-100 mt-3 py-2" id="posCheckout" type="button" disabled>
          <span class="spinner-border spinner-border-sm me-2 d-none" id="posSpinner" role="status"></span>
          <i class="bi bi-check2-circle me-1"></i>Complete sale <span class="d-none d-lg-inline">(F9)</span>
        </button>
      </div>
    </div>
  </div>
</div>
