<?php
/**
 * Product list — filters, sortable columns, stock bars, pagination.
 * Expects: $pg, $filters, $categories
 */

$sortLink = static function (string $key, string $label) use ($filters): string {
    $isCurrent = ($filters['sort'] ?? '') === $key;
    $nextDir = $isCurrent && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
    $arrow = $isCurrent
        ? (($filters['dir'] ?? 'asc') === 'asc' ? ' <i class="bi bi-caret-up-fill small"></i>' : ' <i class="bi bi-caret-down-fill small"></i>')
        : '';

    return '<a class="sort-link" href="' . e(url_with(['sort' => $key, 'dir' => $nextDir, 'page' => 1]))
         . '">' . e($label) . $arrow . '</a>';
};
?>
<div class="page-heading">
  <div>
    <h1>Products</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('dashboard/index') ?>">Home</a></li>
        <li class="breadcrumb-item active">Products</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('products/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Add product
  </a>
</div>

<div class="card" id="productsCard">
  <div class="card-header">
    <form class="filters-bar" method="get" action="index.php" id="productFilters">
      <input type="hidden" name="r" value="products/index">
      <div>
        <label class="form-label mb-1 small">Search</label>
        <input type="search" class="form-control form-control-sm" name="q" id="productSearch"
               style="min-width:220px" autocomplete="off"
               placeholder="Name, barcode, description or category…" value="<?= e($filters['q']) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">Category</label>
        <select class="form-select form-select-sm" name="category_id">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label mb-1 small">Stock</label>
        <select class="form-select form-select-sm" name="stock">
          <option value="">All</option>
          <option value="low" <?= $filters['stock'] === 'low' ? 'selected' : '' ?>>Low stock</option>
          <option value="out" <?= $filters['stock'] === 'out' ? 'selected' : '' ?>>Out of stock</option>
        </select>
      </div>
      <div>
        <label class="form-label mb-1 small">Price</label>
        <select class="form-select form-select-sm" name="price">
          <option value="">All</option>
          <option value="missing" <?= ($filters['price'] ?? '') === 'missing' ? 'selected' : '' ?>>No selling price</option>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('products/index') ?>">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-sticky align-middle mb-0">
      <thead>
        <tr>
          <th><?= $sortLink('name', 'Product') ?></th>
          <th><?= $sortLink('category', 'Category') ?></th>
          <th class="num"><?= $sortLink('cost_price', 'Cost') ?></th>
          <th class="num"><?= $sortLink('selling_price', 'Default price') ?></th>
          <th class="num">Margin</th>
          <th style="min-width:130px"><?= $sortLink('quantity', 'Stock') ?></th>
          <th style="width:110px" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pg['rows'] === []): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <i class="bi bi-box-seam"></i>
            No products match. Try clearing the filters, or add your first product.
          </div>
        </td></tr>
        <?php endif; ?>

        <?php foreach ($pg['rows'] as $p):
            $qty = (int) $p['quantity'];
            $min = max(1, (int) $p['min_stock']);
            $healthy = $min * 2; // full bar at twice the minimum level
            $pct = min(100, (int) round($qty / $healthy * 100));
            $barColor = $qty === 0 ? '#dc2626' : ($qty <= (int) $p['min_stock'] ? '#d97706' : 'var(--ds-accent)');
        ?>
        <tr>
          <td>
            <a class="fw-semibold" href="<?= url('products/edit', ['id' => $p['id']]) ?>"><?= e($p['name']) ?></a>
            <?php if (!empty($p['barcode'])): ?>
              <div class="data small text-secondary"><?= e($p['barcode']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-soft"><?= e($p['category_name']) ?></span></td>
          <td class="num"><?= e(money($p['cost_price'])) ?></td>
          <?php if ($p['selling_price'] === null): ?>
          <td class="num">
            <span class="badge text-bg-danger" title="This product cannot be sold until a price is set">
              <i class="bi bi-exclamation-triangle me-1"></i>No price
            </span>
          </td>
          <td class="num text-secondary">—</td>
          <?php else: ?>
          <td class="num"><?= e(money($p['selling_price'])) ?></td>
          <td class="num text-<?= (float) $p['profit'] >= 0 ? 'success' : 'danger' ?>">
            <?= e(money($p['profit'])) ?>
          </td>
          <?php endif; ?>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="data small" style="min-width:2.5ch"><?= $qty ?></span>
              <div class="stock-bar flex-grow-1"><div style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div></div>
            </div>
            <?php if ($qty === 0): ?>
              <span class="small text-danger">out of stock</span>
            <?php elseif ($qty <= (int) $p['min_stock']): ?>
              <span class="small text-warning-emphasis">low — min <?= (int) $p['min_stock'] ?></span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('products/edit', ['id' => $p['id']]) ?>" title="Edit">
              <i class="bi bi-pencil"></i>
            </a>
            <form class="d-inline" method="post" action="<?= url('products/delete') ?>"
                  data-confirm="Delete &quot;<?= e($p['name']) ?>&quot;? If it appears on past invoices it will be archived instead.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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

<script>
// Live search: results update while typing, no full page reload.
// The server renders the same page; we swap the card's content in place.
(function () {
  var form = document.getElementById('productFilters');
  var search = document.getElementById('productSearch');
  var timer = null;
  var seq = 0;

  function refresh() {
    var mySeq = ++seq;
    var params = new URLSearchParams(new FormData(form));
    var url = 'index.php?' + params.toString();
    fetch(url)
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (mySeq !== seq) return;                    // a newer request finished
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.getElementById('productsCard');
        var card = document.getElementById('productsCard');
        if (fresh && card) {
          card.innerHTML = fresh.innerHTML;
          history.replaceState(null, '', url);
          rebind();
        }
      })
      .catch(function () { /* network hiccup: keep current results */ });
  }

  function rebind() {
    form = document.getElementById('productFilters');
    search = document.getElementById('productSearch');
    var val = search.value;
    search.focus();
    search.setSelectionRange(val.length, val.length);
    attach();
  }

  function attach() {
    search.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(refresh, 300);
    });
    form.querySelectorAll('select').forEach(function (sel) {
      sel.addEventListener('change', refresh);
    });
  }

  attach();
})();
</script>
