<?php
/**
 * Backup & restore (admin only). Expects: $files
 */
?>
<div class="page-heading">
  <div>
    <h1>Backup &amp; restore</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Backup</li>
      </ol>
    </nav>
  </div>
  <form method="post" action="<?= url('backup/create') ?>">
    <?= csrf_field() ?>
    <button class="btn btn-primary btn-sm" type="submit">
      <i class="bi bi-database-down me-1"></i>Create backup now
    </button>
  </form>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">Available backups</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr><th>File</th><th>Created</th><th class="num">Size</th><th class="text-end" style="width:220px">Actions</th></tr>
          </thead>
          <tbody>
            <?php if ($files === []): ?>
            <tr><td colspan="4">
              <div class="empty-state"><i class="bi bi-database"></i>
                No backups yet. Create one now — and copy it to a USB drive or cloud storage regularly.
              </div>
            </td></tr>
            <?php endif; ?>
            <?php foreach ($files as $f): ?>
            <tr>
              <td class="data"><?= e($f['name']) ?></td>
              <td class="small"><?= e(date('d/m/Y H:i', $f['created'])) ?></td>
              <td class="num small"><?= e(number_format($f['size'] / 1024, 1)) ?> KB</td>
              <td class="text-end">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('backup/download', ['file' => $f['name']]) ?>">
                  <i class="bi bi-download"></i>
                </a>
                <form class="d-inline" method="post" action="<?= url('backup/restore') ?>"
                      data-confirm="Restore this backup? ALL CURRENT DATA will be replaced with the backup's contents.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                  <button class="btn btn-outline-warning btn-sm" title="Restore">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                </form>
                <form class="d-inline" method="post" action="<?= url('backup/delete') ?>"
                      data-confirm="Delete this backup file?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="file" value="<?= e($f['name']) ?>">
                  <button class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header">Restore from a file</div>
      <div class="card-body">
        <form method="post" action="<?= url('backup/restore') ?>" enctype="multipart/form-data"
              data-confirm="Restore from the uploaded file? ALL CURRENT DATA will be replaced.">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="uploadFile">Upload a .sql backup</label>
            <input class="form-control" type="file" id="uploadFile" name="upload" accept=".sql" required>
          </div>
          <button class="btn btn-warning w-100" type="submit">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Restore uploaded file
          </button>
        </form>
      </div>
    </div>

    <div class="alert alert-info small">
      <i class="bi bi-info-circle me-1"></i>
      <strong>Good practice:</strong> back up at the end of each day and keep copies
      outside this computer (USB drive, cloud). Backups are stored in
      <code>storage/backups/</code>.
    </div>
  </div>
</div>
