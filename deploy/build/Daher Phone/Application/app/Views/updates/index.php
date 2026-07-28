<?php
/**
 * Updates — check the update server, install from server or from a file.
 * Expects: $updateUrl, $checked (last check() result or null)
 */
?>
<div class="page-heading">
  <div>
    <h1>Updates</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Updates</li>
      </ol>
    </nav>
  </div>
  <span class="badge badge-soft fs-6">Installed version: v<?= e(APP_VERSION) ?></span>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-cloud-arrow-down me-1"></i>Update from the server</div>
      <div class="card-body">
        <form method="post" action="<?= url('updates/check') ?>" class="mb-3">
          <?= csrf_field() ?>
          <label class="form-label" for="updUrl">Update server address</label>
          <div class="input-group">
            <input class="form-control data" id="updUrl" name="update_url" type="url"
                   placeholder="https://example.com/daher-phone/update.json"
                   value="<?= e($updateUrl) ?>">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-arrow-repeat me-1"></i>Check for updates
            </button>
          </div>
          <div class="form-text">Provided by your developer. Checking is safe — nothing installs without confirmation.</div>
        </form>

        <?php if (is_array($checked)): ?>
          <?php if (!empty($checked['is_newer'])): ?>
          <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <strong>New update available: version <?= e($checked['version']) ?></strong>
              <?php if (!empty($checked['notes'])): ?>
                <div class="small mt-1"><?= nl2br(e($checked['notes'])) ?></div>
              <?php endif; ?>
              <div class="small text-secondary mt-1">
                Before installing, the system automatically backs up the database.
                If anything fails, the current version is restored.
              </div>
            </div>
            <form method="post" action="<?= url('updates/apply') ?>"
                  data-confirm="Install version <?= e($checked['version']) ?> now? A database backup is taken first.">
              <?= csrf_field() ?>
              <button class="btn btn-success">
                <i class="bi bi-download me-1"></i>Update now
              </button>
            </form>
          </div>
          <?php else: ?>
          <div class="alert alert-info py-2">
            <i class="bi bi-check2-circle me-1"></i>
            You have the latest version (v<?= e(APP_VERSION) ?>).
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-usb-drive me-1"></i>Update from a file (USB)</div>
      <div class="card-body">
        <form method="post" action="<?= url('updates/apply-file') ?>" enctype="multipart/form-data"
              data-confirm="Install this update package now? A database backup is taken first.">
          <?= csrf_field() ?>
          <div class="input-group">
            <input class="form-control" type="file" name="package" accept=".zip" required>
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-box-arrow-in-down me-1"></i>Install package
            </button>
          </div>
          <div class="form-text">
            For shops without internet: your developer sends a
            <span class="data">DaherPhone-update-x.y.z.zip</span> file.
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">How updates work</div>
      <div class="card-body small">
        <ol class="mb-0 ps-3">
          <li class="mb-1">The database is backed up automatically.</li>
          <li class="mb-1">A copy of the current program is kept for safety.</li>
          <li class="mb-1">The new files are installed.</li>
          <li class="mb-1">Database upgrades (migrations) run once.</li>
          <li class="mb-1">If any step fails, the previous version comes back
              automatically and your data is untouched.</li>
        </ol>
      </div>
    </div>
  </div>
</div>
