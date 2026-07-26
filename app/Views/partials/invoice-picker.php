<?php
/**
 * Live invoice picker used by Returns and Refunds.
 * Expects: $pickerMode = 'return' | 'refund'
 * Behavior lives in assets/js/invoice-picker.js (loaded via $pageScript).
 */
$isReturn = $pickerMode === 'return';
?>
<div class="card mb-3 invoice-picker" id="invoicePicker" data-mode="<?= e($pickerMode) ?>">
  <div class="card-body">

    <label class="form-label" for="ipSearch">
      <i class="bi bi-receipt-cutoff me-1"></i>Find the invoice
    </label>
    <div class="row g-2 align-items-start">
      <div class="col-md-8">
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="search" class="form-control" id="ipSearch" autocomplete="off" autofocus
                 placeholder="Invoice #, customer, phone or product…">
        </div>
        <div class="form-text">
          Type anything — <span class="data">INV-000125</span>, a customer name, a phone number
          or a product name. Results update as you type.
        </div>
      </div>
      <div class="col-md-4">
        <select class="form-select" id="ipSort" title="Sort results">
          <option value="date_desc">Latest date first</option>
          <option value="date_asc">Oldest date first</option>
          <option value="invoice">Invoice number</option>
          <option value="customer">Customer name</option>
          <option value="total">Total amount</option>
        </select>
      </div>
    </div>

    <div class="position-relative mt-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <?php if ($isReturn): ?>
            <tr>
              <th>Invoice</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Phone</th>
              <th class="num">Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th style="width:96px" class="text-end">Action</th>
            </tr>
            <?php else: ?>
            <tr>
              <th>Invoice</th>
              <th>Date</th>
              <th>Customer</th>
              <th class="num">Total</th>
              <th class="num">Paid</th>
              <th class="num">Refundable</th>
              <th style="width:96px" class="text-end">Action</th>
            </tr>
            <?php endif; ?>
          </thead>
          <tbody id="ipBody"></tbody>
        </table>
      </div>

      <div id="ipEmpty" class="empty-state d-none">
        <i class="bi bi-search"></i>
        <span id="ipEmptyText">No invoices found.</span>
      </div>

      <div id="ipLoading" class="ip-loading d-none">
        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
        <span class="ms-2 small text-secondary">Searching…</span>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <span class="small text-secondary" id="ipPageInfo"></span>
      <div class="btn-group">
        <button class="btn btn-outline-secondary btn-sm" id="ipPrev" type="button" disabled>
          <i class="bi bi-chevron-left"></i> Prev
        </button>
        <button class="btn btn-outline-secondary btn-sm" id="ipNext" type="button" disabled>
          Next <i class="bi bi-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</div>
