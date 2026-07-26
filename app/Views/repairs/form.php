<?php
/**
 * New repair ticket. Expects: $customers, $preselected, $deviceTypes
 */
?>
<div class="page-heading">
  <div>
    <h1>New repair ticket</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('repairs/index') ?>">Repairs</a></li>
        <li class="breadcrumb-item active">New</li>
      </ol>
    </nav>
  </div>
</div>

<form method="post" action="<?= url('repairs/store') ?>">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header">Customer &amp; device</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label" for="rCustomer">Customer <span class="text-danger">*</span></label>
              <select class="form-select" id="rCustomer" name="customer_id" required>
                <option value="">Choose a customer…</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= (int) $c['id'] ?>"
                  <?= ($preselected !== null && (int) $preselected['id'] === (int) $c['id'])
                        || old('customer_id') === (string) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">
                Not listed? <a href="<?= url('customers/create') ?>">Add a customer first</a>.
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="rType">Device type <span class="text-danger">*</span></label>
              <select class="form-select" id="rType" name="device_type" required>
                <?php foreach ($deviceTypes as $t): ?>
                <option value="<?= e($t) ?>" <?= old('device_type') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="rBrand">Brand</label>
              <input class="form-control" id="rBrand" name="brand" maxlength="50"
                     placeholder="e.g. Apple, Samsung" value="<?= old('brand') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="rModel">Model</label>
              <input class="form-control" id="rModel" name="model" maxlength="80"
                     placeholder="e.g. iPhone 13 Pro" value="<?= old('model') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="rSerial">Serial / IMEI</label>
              <input class="form-control data" id="rSerial" name="serial_no" maxlength="80" value="<?= old('serial_no') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="rProblem">Problem description <span class="text-danger">*</span></label>
              <textarea class="form-control" id="rProblem" name="problem" rows="3" required
                        maxlength="5000" placeholder="What the customer reported…"><?= old('problem') ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header">Money</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="rLabor">Estimated labor charge</label>
            <div class="input-group">
              <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
              <input class="form-control data" id="rLabor" name="labor_cost" type="number"
                     min="0" step="0.01" value="<?= old('labor_cost', '0') ?>">
            </div>
            <div class="form-text">You can adjust this later; parts are added on the ticket page.</div>
          </div>
          <div class="mb-1">
            <label class="form-label" for="rDeposit">Deposit taken now</label>
            <div class="input-group">
              <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
              <input class="form-control data" id="rDeposit" name="deposit" type="number"
                     min="0" step="0.01" value="<?= old('deposit', '0') ?>">
            </div>
          </div>
        </div>
      </div>

      <button class="btn btn-primary w-100 py-2" type="submit">
        <i class="bi bi-check-lg me-1"></i>Create ticket
      </button>
    </div>
  </div>
</form>
