<?php
/**
 * Categories — table + modal-based add/edit (no page reloads for the form UI).
 */
?>
<div class="page-heading">
  <div>
    <h1>Categories</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Categories</li>
      </ol>
    </nav>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal"
          data-mode="create">
    <i class="bi bi-plus-lg me-1"></i>Add category
  </button>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span>All categories <span class="badge badge-soft ms-1"><?= count($categories) ?></span></span>
    <input class="form-control form-control-sm" style="max-width:240px" type="search"
           placeholder="Filter…" data-table-filter="#categoriesTable">
  </div>
  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0" id="categoriesTable">
      <thead>
        <tr>
          <th style="width:60px">#</th>
          <th>Name</th>
          <th>Description</th>
          <th class="num">Products</th>
          <th style="width:130px" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($categories === []): ?>
        <tr><td colspan="5">
          <div class="empty-state"><i class="bi bi-tags"></i>No categories yet. Add your first one.</div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $c): ?>
        <tr>
          <td class="data"><?= (int) $c['id'] ?></td>
          <td class="fw-semibold"><?= e($c['name']) ?></td>
          <td class="text-secondary"><?= e($c['description'] ?? '') ?></td>
          <td class="num">
            <a href="<?= url('products/index', ['category_id' => $c['id']]) ?>" class="text-decoration-none">
              <?= (int) $c['product_count'] ?>
            </a>
          </td>
          <td class="text-end">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#categoryModal" data-mode="edit"
                    data-id="<?= (int) $c['id'] ?>"
                    data-name="<?= e($c['name']) ?>"
                    data-description="<?= e($c['description'] ?? '') ?>"
                    title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
            <form class="d-inline" method="post" action="<?= url('categories/delete') ?>"
                  data-confirm="Delete category &quot;<?= e($c['name']) ?>&quot;? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add / edit modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= url('categories/store') ?>" id="categoryForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h5 class="modal-title">Add category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="catName">Name <span class="text-danger">*</span></label>
          <input class="form-control" id="catName" name="name" required maxlength="100"
                 value="<?= old('name') ?>">
          <?php if (form_error('name')): ?><div class="text-danger small mt-1"><?= form_error('name') ?></div><?php endif; ?>
        </div>
        <div class="mb-1">
          <label class="form-label" for="catDesc">Description</label>
          <input class="form-control" id="catDesc" name="description" maxlength="255"
                 value="<?= old('description') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save category</button>
      </div>
    </form>
  </div>
</div>

<script>
// Switch the shared modal between create and edit modes.
document.getElementById('categoryModal').addEventListener('show.bs.modal', function (ev) {
  var btn = ev.relatedTarget;
  if (!btn) return;
  var form = document.getElementById('categoryForm');
  var isEdit = btn.dataset.mode === 'edit';

  form.action = isEdit ? '<?= url('categories/update') ?>' : '<?= url('categories/store') ?>';
  form.querySelector('[name=id]').value = btn.dataset.id || '';
  form.querySelector('[name=name]').value = btn.dataset.name || '';
  form.querySelector('[name=description]').value = btn.dataset.description || '';
  this.querySelector('.modal-title').textContent = isEdit ? 'Edit category' : 'Add category';
});
</script>
