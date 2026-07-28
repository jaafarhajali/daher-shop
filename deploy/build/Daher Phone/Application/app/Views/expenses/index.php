<?php
/**
 * Expenses — list + modal CRUD.
 * Expects: $pg, $filters, $total, $categories
 */
?>
<div class="page-heading">
  <div>
    <h1>Expenses</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Expenses</li>
      </ol>
    </nav>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#expenseModal" data-mode="create">
    <i class="bi bi-plus-lg me-1"></i>Add expense
  </button>
</div>

<div class="card">
  <div class="card-header">
    <form class="filters-bar" method="get" action="index.php">
      <input type="hidden" name="r" value="expenses/index">
      <div>
        <label class="form-label mb-1 small">Search</label>
        <input type="search" class="form-control form-control-sm" name="q"
               placeholder="Name or notes…" value="<?= e($filters['q']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">Category</label>
        <select class="form-select form-select-sm" name="category">
          <option value="">All</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= e($c) ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label mb-1 small">From</label>
        <input type="date" class="form-control form-control-sm" name="from" value="<?= e($filters['from']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">To</label>
        <input type="date" class="form-control form-control-sm" name="to" value="<?= e($filters['to']) ?>">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('expenses/index') ?>">Reset</a>
      </div>
      <div class="ms-auto text-end">
        <div class="small text-secondary">Total (filtered)</div>
        <div class="data fw-bold text-danger"><?= e(money($total)) ?></div>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <th>Expense</th>
          <th>Category</th>
          <th>Notes</th>
          <th class="num">Amount</th>
          <th style="width:110px" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
        <tr><td colspan="6">
          <div class="empty-state"><i class="bi bi-cash-stack"></i>No expenses recorded for these filters.</div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($pg['rows'] as $x): ?>
        <tr>
          <td class="small"><?= e(fmt_date($x['expense_date'])) ?></td>
          <td class="fw-semibold"><?= e($x['name']) ?></td>
          <td><span class="badge badge-soft"><?= e($x['category']) ?></span></td>
          <td class="small text-secondary"><?= e($x['notes'] ?? '') ?></td>
          <td class="num text-danger"><?= e(money($x['amount'])) ?></td>
          <td class="text-end">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#expenseModal" data-mode="edit"
                    data-id="<?= (int) $x['id'] ?>"
                    data-name="<?= e($x['name']) ?>"
                    data-category="<?= e($x['category']) ?>"
                    data-amount="<?= e($x['amount']) ?>"
                    data-date="<?= e($x['expense_date']) ?>"
                    data-notes="<?= e($x['notes'] ?? '') ?>" title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
            <form class="d-inline" method="post" action="<?= url('expenses/delete') ?>"
                  data-confirm="Delete this expense?">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
              <button class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php require APP_PATH . '/Views/partials/pagination.php'; ?>
</div>

<!-- Add / edit modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= url('expenses/store') ?>" id="expenseForm">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h5 class="modal-title">Add expense</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label" for="expName">Expense name <span class="text-danger">*</span></label>
            <input class="form-control" id="expName" name="name" required maxlength="150"
                   placeholder="e.g. Shop rent — July" value="<?= old('name') ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label" for="expCategory">Category <span class="text-danger">*</span></label>
            <input class="form-control" id="expCategory" name="category" list="expCategoryList"
                   required maxlength="50" value="<?= old('category', 'General') ?>">
            <datalist id="expCategoryList">
              <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="expAmount">Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><?= e(setting('currency_symbol', '$')) ?></span>
              <input class="form-control data" id="expAmount" name="amount" type="number"
                     min="0.01" step="0.01" required value="<?= old('amount') ?>">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="expDate">Date <span class="text-danger">*</span></label>
            <input class="form-control" id="expDate" name="expense_date" type="date" required
                   value="<?= old('expense_date', date('Y-m-d')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label" for="expNotes">Notes</label>
            <input class="form-control" id="expNotes" name="notes" maxlength="255" value="<?= old('notes') ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save expense</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('expenseModal').addEventListener('show.bs.modal', function (ev) {
  var btn = ev.relatedTarget;
  if (!btn) return;
  var form = document.getElementById('expenseForm');
  var isEdit = btn.dataset.mode === 'edit';

  form.action = isEdit ? '<?= url('expenses/update') ?>' : '<?= url('expenses/store') ?>';
  form.querySelector('[name=id]').value = btn.dataset.id || '';
  form.querySelector('[name=name]').value = btn.dataset.name || '';
  form.querySelector('[name=category]').value = btn.dataset.category || 'General';
  form.querySelector('[name=amount]').value = btn.dataset.amount || '';
  form.querySelector('[name=expense_date]').value = btn.dataset.date || '<?= date('Y-m-d') ?>';
  form.querySelector('[name=notes]').value = btn.dataset.notes || '';
  this.querySelector('.modal-title').textContent = isEdit ? 'Edit expense' : 'Add expense';
});
</script>
