<?php
/**
 * Pagination controls. Expects $pg = ['total','page','pages','per_page'].
 * Keeps all current filters via url_with().
 */
if (!isset($pg) || $pg['pages'] < 2) {
    return;
}
$page = (int) $pg['page'];
$pages = (int) $pg['pages'];
$window = 2;
?>
<nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3">
  <span class="small text-secondary">
    <?= (int) $pg['total'] ?> result<?= $pg['total'] === 1 ? '' : 's' ?> ·
    page <?= $page ?> of <?= $pages ?>
  </span>
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
      <a class="page-link" href="<?= e(url_with(['page' => $page - 1])) ?>" aria-label="Previous">&laquo;</a>
    </li>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === 1 || $i === $pages || abs($i - $page) <= $window): ?>
        <li class="page-item<?= $i === $page ? ' active' : '' ?>">
          <a class="page-link" href="<?= e(url_with(['page' => $i])) ?>"><?= $i ?></a>
        </li>
      <?php elseif ($i === 2 || $i === $pages - 1): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
      <?php endif; ?>
    <?php endfor; ?>
    <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
      <a class="page-link" href="<?= e(url_with(['page' => $page + 1])) ?>" aria-label="Next">&raquo;</a>
    </li>
  </ul>
</nav>
