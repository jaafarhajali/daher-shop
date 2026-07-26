<?php
/**
 * Customer list. Expects: $pg, $q
 */
?>
<div class="page-heading">
  <div>
    <h1>Customers</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Customers</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('customers/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-person-plus me-1"></i>Add customer
  </a>
</div>

<div class="card">
  <div class="card-header">
    <form class="filters-bar" method="get" action="index.php">
      <input type="hidden" name="r" value="customers/index">
      <div class="flex-grow-1" style="max-width:340px">
        <input type="search" class="form-control form-control-sm" name="q"
               placeholder="Search by name, phone or email…" value="<?= e($q) ?>">
      </div>
      <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Search</button>
      <?php if ($q !== ''): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('customers/index') ?>">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th class="num">Sales</th>
          <th class="num">Repairs</th>
          <th>Since</th>
          <th style="width:110px" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
        <tr><td colspan="7">
          <div class="empty-state"><i class="bi bi-people"></i>
            <?= $q !== '' ? 'No customers match your search.' : 'No customers yet — add your first one.' ?>
          </div>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($pg['rows'] as $c): ?>
        <tr>
          <td><a class="fw-semibold" href="<?= url('customers/show', ['id' => $c['id']]) ?>"><?= e($c['name']) ?></a></td>
          <td class="data"><?= e($c['phone'] ?? '—') ?></td>
          <td><?= e($c['email'] ?? '—') ?></td>
          <td class="num"><?= (int) $c['sale_count'] ?></td>
          <td class="num"><?= (int) $c['repair_count'] ?></td>
          <td class="text-secondary small"><?= e(fmt_date($c['created_at'])) ?></td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('customers/edit', ['id' => $c['id']]) ?>" title="Edit">
              <i class="bi bi-pencil"></i>
            </a>
            <form class="d-inline" method="post" action="<?= url('customers/delete') ?>"
                  data-confirm="Delete customer &quot;<?= e($c['name']) ?>&quot;?">
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

  <?php require APP_PATH . '/Views/partials/pagination.php'; ?>
</div>
