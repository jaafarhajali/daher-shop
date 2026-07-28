<?php
/**
 * My profile + password change. Expects: $account
 */
?>
<div class="page-heading">
  <div>
    <h1>My profile</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Settings · Profile</li>
      </ol>
    </nav>
  </div>
  <?php if (\App\Core\Auth::isAdmin()): ?>
  <a class="btn btn-outline-primary btn-sm" href="<?= url('settings/shop') ?>">
    <i class="bi bi-shop me-1"></i>Shop settings
  </a>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Account details</div>
      <div class="card-body">
        <form method="post" action="<?= url('settings/save-profile') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control data" value="<?= e($account['username'] ?? '') ?>" disabled>
            <div class="form-text">Role: <?= e($account['role'] ?? '') ?> ·
              last sign-in <?= e(fmt_date($account['last_login_at'] ?? null, true)) ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="pfName">Full name <span class="text-danger">*</span></label>
            <input class="form-control" id="pfName" name="full_name" required maxlength="100"
                   value="<?= old('full_name', $account['full_name'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="pfEmail">Email</label>
            <input class="form-control" id="pfEmail" name="email" type="email" maxlength="150"
                   value="<?= old('email', $account['email'] ?? '') ?>">
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Save profile</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">Change password</div>
      <div class="card-body">
        <form method="post" action="<?= url('settings/change-password') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="cpCurrent">Current password <span class="text-danger">*</span></label>
            <input class="form-control" id="cpCurrent" name="current_password" type="password" required>
            <?php if (form_error('current_password')): ?>
              <div class="text-danger small mt-1"><?= form_error('current_password') ?></div>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="cpNew">New password <span class="text-danger">*</span></label>
            <input class="form-control" id="cpNew" name="new_password" type="password"
                   required minlength="8">
            <div class="form-text">At least 8 characters. Use a phrase you don't use anywhere else.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="cpConfirm">Repeat new password <span class="text-danger">*</span></label>
            <input class="form-control" id="cpConfirm" name="confirm_password" type="password" required>
            <?php if (form_error('confirm_password')): ?>
              <div class="text-danger small mt-1"><?= form_error('confirm_password') ?></div>
            <?php endif; ?>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-shield-lock me-1"></i>Change password</button>
        </form>
      </div>
    </div>
  </div>
</div>
