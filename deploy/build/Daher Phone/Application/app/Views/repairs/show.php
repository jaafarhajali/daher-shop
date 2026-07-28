<?php
/**
 * Repair ticket detail.
 * Expects: $repair, $parts, $history, $deviceTypes
 */

use App\Models\Repair;

$meta = repair_status_meta($repair['status']);
$balance = (float) $repair['total_cost'] - (float) $repair['paid_amount'];
$isOpen = in_array($repair['status'], Repair::OPEN_STATUSES, true);

// The delivery pipeline for the trace timeline (cancel is shown separately).
$pipeline = ['received', 'diagnosing', 'repairing', 'ready', 'delivered'];
$currentIdx = array_search($repair['status'], $pipeline, true);
?>
<div class="page-heading">
  <div>
    <h1>Ticket <span class="data"><?= e($repair['ticket_no']) ?></span>
      <span class="badge text-bg-<?= $meta['color'] ?> align-middle ms-1">
        <i class="bi bi-<?= $meta['icon'] ?> me-1"></i><?= e($meta['label']) ?>
      </span>
    </h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('repairs/index') ?>">Repairs</a></li>
        <li class="breadcrumb-item active"><?= e($repair['ticket_no']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" target="_blank"
       href="<?= url('repairs/print', ['id' => $repair['id']]) ?>">
      <i class="bi bi-printer me-1"></i>Print receipt
    </a>
  </div>
</div>

<!-- Status trace timeline -->
<div class="card mb-3">
  <div class="card-body pb-2">
    <?php if ($repair['status'] === 'cancelled'): ?>
      <div class="alert alert-danger mb-2 py-2">
        <i class="bi bi-x-circle me-1"></i>This ticket was cancelled. Stock parts were returned to inventory.
      </div>
    <?php else: ?>
    <div class="trace-timeline">
      <?php foreach ($pipeline as $i => $step): $stepMeta = repair_status_meta($step);
        $cls = $currentIdx !== false && $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'current' : '');
      ?>
      <div class="trace-step <?= $cls ?>">
        <div class="pad"><i class="bi bi-check"></i></div>
        <div class="trace-label"><?= e($stepMeta['label']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($repair['status'] !== 'cancelled' && $repair['status'] !== 'delivered'): ?>
    <form class="d-flex flex-wrap gap-2 align-items-end border-top pt-3 mt-2 pb-2"
          method="post" action="<?= url('repairs/set-status') ?>"
          data-confirm="Change ticket status?">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $repair['id'] ?>">
      <div>
        <label class="form-label small mb-1" for="stStatus">Move to</label>
        <select class="form-select form-select-sm" name="status" id="stStatus">
          <?php foreach (Repair::STATUSES as $s):
              if ($s === $repair['status']) { continue; }
              $sm = repair_status_meta($s); ?>
          <option value="<?= $s ?>"><?= e($sm['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex-grow-1" style="max-width:380px">
        <label class="form-label small mb-1" for="stNote">Note (shown in history)</label>
        <input class="form-control form-control-sm" name="note" id="stNote" maxlength="255"
               placeholder="e.g. Screen replaced, tested OK">
      </div>
      <button class="btn btn-primary btn-sm" type="submit">
        <i class="bi bi-arrow-right-circle me-1"></i>Update status
      </button>
      <?php if ($balance > 0.004): ?>
        <span class="small text-danger align-self-center">
          <i class="bi bi-exclamation-circle me-1"></i>Balance due: <?= e(money($balance)) ?>
        </span>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <!-- Left column: device + problem + parts -->
  <div class="col-xl-8">

    <div class="card mb-3">
      <div class="card-header">Device &amp; diagnosis</div>
      <div class="card-body">
        <form method="post" action="<?= url('repairs/update') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $repair['id'] ?>">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Device type</label>
              <select class="form-select form-select-sm" name="device_type">
                <?php foreach ($deviceTypes as $t): ?>
                <option value="<?= e($t) ?>" <?= $repair['device_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
                <?php if (!in_array($repair['device_type'], $deviceTypes, true)): ?>
                <option value="<?= e($repair['device_type']) ?>" selected><?= e($repair['device_type']) ?></option>
                <?php endif; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Brand</label>
              <input class="form-control form-control-sm" name="brand" maxlength="50"
                     value="<?= e($repair['brand'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Model</label>
              <input class="form-control form-control-sm" name="model" maxlength="80"
                     value="<?= e($repair['model'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Serial / IMEI</label>
              <input class="form-control form-control-sm data" name="serial_no" maxlength="80"
                     value="<?= e($repair['serial_no'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Problem (reported by customer)</label>
              <textarea class="form-control form-control-sm" name="problem" rows="3"
                        maxlength="5000" required><?= e($repair['problem']) ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Technician notes</label>
              <textarea class="form-control form-control-sm" name="tech_notes" rows="3"
                        maxlength="5000" placeholder="Diagnosis, work done…"><?= e($repair['tech_notes'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">Labor charge</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
                <input class="form-control data" name="labor_cost" type="number" min="0" step="0.01"
                       value="<?= e($repair['labor_cost']) ?>">
              </div>
            </div>
            <div class="col-md-8 d-flex align-items-end justify-content-end">
              <button class="btn btn-outline-primary btn-sm" type="submit">
                <i class="bi bi-check-lg me-1"></i>Save details
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Parts -->
    <div class="card mb-3">
      <div class="card-header">Parts used</div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Part</th>
              <th class="num">Qty</th>
              <th class="num">Cost</th>
              <th class="num">Charged</th>
              <th class="num">Total</th>
              <?php if ($isOpen): ?><th style="width:44px"></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($parts === []): ?>
            <tr><td colspan="6"><div class="empty-state py-3"><i class="bi bi-cpu"></i>No parts on this ticket yet.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($parts as $p): ?>
            <tr>
              <td>
                <?= e($p['part_name']) ?>
                <?php if ($p['product_id'] !== null): ?>
                  <span class="badge badge-soft ms-1">stock</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary ms-1">external</span>
                <?php endif; ?>
              </td>
              <td class="num"><?= (int) $p['quantity'] ?></td>
              <td class="num text-secondary"><?= e(money($p['unit_cost'])) ?></td>
              <td class="num"><?= e(money($p['unit_price'])) ?></td>
              <td class="num fw-semibold"><?= e(money((float) $p['unit_price'] * (int) $p['quantity'])) ?></td>
              <?php if ($isOpen): ?>
              <td>
                <form method="post" action="<?= url('repairs/remove-part') ?>"
                      data-confirm="Remove this part? Stock parts return to inventory.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="part_id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="repair_id" value="<?= (int) $repair['id'] ?>">
                  <button class="btn btn-link text-danger p-0" title="Remove part"><i class="bi bi-x-lg"></i></button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($isOpen): ?>
      <div class="card-body border-top">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="fw-semibold small mb-2"><i class="bi bi-box-seam me-1"></i>From stock</div>
            <form class="d-flex gap-2" method="post" action="<?= url('repairs/add-part') ?>" id="stockPartForm">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $repair['id'] ?>">
              <input type="hidden" name="product_id" id="stockPartId" value="">
              <div class="flex-grow-1 position-relative">
                <input class="form-control form-control-sm" id="stockPartSearch" autocomplete="off"
                       placeholder="Search product or scan barcode…">
                <div class="list-group position-absolute w-100 shadow-sm d-none" id="stockPartResults"
                     style="z-index:1050;max-height:220px;overflow-y:auto;"></div>
              </div>
              <input class="form-control form-control-sm data" style="width:70px" type="number"
                     name="quantity" min="1" value="1" title="Quantity">
              <button class="btn btn-outline-primary btn-sm" type="submit" disabled id="stockPartAdd">Add</button>
            </form>
            <div class="form-text">Charged at the product's selling price; edit by removing and adding an external part instead.</div>
          </div>
          <div class="col-lg-6">
            <div class="fw-semibold small mb-2"><i class="bi bi-bag me-1"></i>External part</div>
            <form class="row g-2" method="post" action="<?= url('repairs/add-part') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $repair['id'] ?>">
              <input type="hidden" name="product_id" value="0">
              <div class="col-12">
                <input class="form-control form-control-sm" name="part_name" maxlength="150"
                       placeholder="Part name (e.g. LCD ordered from supplier)" required>
              </div>
              <div class="col-3">
                <input class="form-control form-control-sm data" name="quantity" type="number" min="1" value="1" title="Qty">
              </div>
              <div class="col-4">
                <input class="form-control form-control-sm data" name="unit_cost" type="number" min="0"
                       step="0.01" placeholder="Cost" title="What you paid">
              </div>
              <div class="col-4">
                <input class="form-control form-control-sm data" name="unit_price" type="number" min="0"
                       step="0.01" placeholder="Charge" title="What the customer pays" required>
              </div>
              <div class="col-1">
                <button class="btn btn-outline-primary btn-sm w-100" type="submit">+</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- History -->
    <div class="card">
      <div class="card-header">Status history</div>
      <ul class="list-group list-group-flush">
        <?php foreach (array_reverse($history) as $h): $hm = repair_status_meta($h['status']); ?>
        <li class="list-group-item d-flex gap-3 align-items-start">
          <span class="badge text-bg-<?= $hm['color'] ?> mt-1"><i class="bi bi-<?= $hm['icon'] ?>"></i></span>
          <div>
            <div><strong><?= e($hm['label']) ?></strong>
              <?php if (!empty($h['note'])): ?> — <?= e($h['note']) ?><?php endif; ?>
            </div>
            <div class="small text-secondary">
              <?= e(fmt_date($h['created_at'], true)) ?><?= !empty($h['username']) ? ' · ' . e($h['username']) : '' ?>
            </div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- Right column: customer + money -->
  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header">Customer</div>
      <div class="card-body small">
        <div class="fw-semibold mb-1">
          <a href="<?= url('customers/show', ['id' => $repair['customer_id']]) ?>"><?= e($repair['customer_name']) ?></a>
        </div>
        <?php if (!empty($repair['customer_phone'])): ?>
          <div class="data"><i class="bi bi-telephone me-1"></i><?= e($repair['customer_phone']) ?></div>
        <?php endif; ?>
        <?php if (!empty($repair['customer_address'])): ?>
          <div class="text-secondary mt-1"><?= e($repair['customer_address']) ?></div>
        <?php endif; ?>
        <hr>
        <div class="text-secondary">
          Received <?= e(fmt_date($repair['received_at'], true)) ?><br>
          <?php if (!empty($repair['delivered_at'])): ?>
            Delivered <?= e(fmt_date($repair['delivered_at'], true)) ?><br>
          <?php endif; ?>
          Created by <?= e($repair['created_by'] ?? '—') ?>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Money</div>
      <div class="card-body">
        <div class="pos-total-row"><span class="text-secondary">Labor</span>
          <span class="data"><?= e(money($repair['labor_cost'])) ?></span></div>
        <div class="pos-total-row"><span class="text-secondary">Parts (charged)</span>
          <span class="data"><?= e(money($repair['parts_cost'])) ?></span></div>
        <div class="pos-total-row grand"><span>Total</span>
          <span><?= e(money($repair['total_cost'])) ?></span></div>
        <div class="pos-total-row"><span class="text-secondary">Paid</span>
          <span class="data text-success"><?= e(money($repair['paid_amount'])) ?></span></div>
        <div class="pos-total-row">
          <span class="text-secondary">Balance due</span>
          <span class="data fw-bold <?= $balance > 0.004 ? 'text-danger' : 'text-success' ?>">
            <?= e(money($balance)) ?>
          </span>
        </div>

        <?php if ($balance > 0.004 && $repair['status'] !== 'cancelled'): ?>
        <form class="d-flex gap-2 mt-3" method="post" action="<?= url('repairs/add-payment') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $repair['id'] ?>">
          <div class="input-group input-group-sm">
            <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
            <input class="form-control data" name="amount" type="number" min="0.01"
                   step="0.01" max="<?= e(number_format($balance, 2, '.', '')) ?>"
                   placeholder="Amount" required>
          </div>
          <button class="btn btn-primary btn-sm text-nowrap" type="submit">
            <i class="bi bi-cash me-1"></i>Record payment
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Stock part picker — reuses the POS product search endpoint.
(function () {
  var $search = document.getElementById('stockPartSearch');
  if (!$search) return;
  var $results = document.getElementById('stockPartResults');
  var $id = document.getElementById('stockPartId');
  var $add = document.getElementById('stockPartAdd');
  var timer = null;

  $search.addEventListener('input', function () {
    $id.value = '';
    $add.disabled = true;
    clearTimeout(timer);
    var q = $search.value.trim();
    if (!q) { $results.classList.add('d-none'); return; }
    timer = setTimeout(function () {
      fetch('index.php?r=products/search-json&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          $results.innerHTML = '';
          if (!data.items.length) { $results.classList.add('d-none'); return; }
          data.items.forEach(function (p) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'list-group-item list-group-item-action d-flex justify-content-between';
            b.innerHTML = '<span></span><span class="data small"></span>';
            b.firstChild.textContent = p.name;
            b.lastChild.textContent = p.stock + ' in stock';
            b.addEventListener('click', function () {
              $id.value = p.id;
              $search.value = p.name;
              $results.classList.add('d-none');
              $add.disabled = false;
            });
            $results.appendChild(b);
          });
          $results.classList.remove('d-none');
        });
    }, 220);
  });
})();
</script>
