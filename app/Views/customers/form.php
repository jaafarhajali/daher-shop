<?php
/**
 * Customer create/edit form. Expects: $customer (null for create)
 * Optional GET params: return=pos (redirect back to POS after saving)
 */
$isEdit = $customer !== null;
$val = static fn (string $key) => old($key, $isEdit ? ($customer[$key] ?? '') : '');
$returnToPos = ($_GET['return'] ?? '') === 'pos';
?>
<div class="page-heading">
  <div>
    <h1><?= $isEdit ? 'Edit customer' : 'Add customer' ?></h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= url('customers/index') ?>">Customers</a></li>
        <li class="breadcrumb-item active"><?= $isEdit ? e($customer['name']) : 'New' ?></li>
      </ol>
    </nav>
  </div>
</div>

<div class="row">
  <div class="col-lg-7">
    <form method="post" action="<?= $isEdit ? url('customers/update') : url('customers/store') ?>">
      <?= csrf_field() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $customer['id'] ?>"><?php endif; ?>
      <?php if ($returnToPos): ?><input type="hidden" name="return" value="pos"><?php endif; ?>

      <div class="card mb-3">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="cName">Full name <span class="text-danger">*</span></label>
              <input class="form-control" id="cName" name="name" required maxlength="100" value="<?= $val('name') ?>">
              <?php if (form_error('name')): ?><div class="text-danger small mt-1"><?= form_error('name') ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="cPhone">Phone</label>
              <input class="form-control data" id="cPhone" name="phone" maxlength="30" value="<?= $val('phone') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="cEmail">Email</label>
              <input class="form-control" id="cEmail" name="email" type="email" maxlength="150" value="<?= $val('email') ?>">
              <?php if (form_error('email')): ?><div class="text-danger small mt-1"><?= form_error('email') ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="cAddress">Address</label>
              <input class="form-control" id="cAddress" name="address" maxlength="255" value="<?= $val('address') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="cNotes">Notes</label>
              <textarea class="form-control" id="cNotes" name="notes" rows="3" maxlength="2000"><?= $val('notes') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save changes' : 'Add customer' ?>
        </button>
        <a class="btn btn-outline-secondary" href="<?= $returnToPos ? url('sales/pos') : url('customers/index') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>
