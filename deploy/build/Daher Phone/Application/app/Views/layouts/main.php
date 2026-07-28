<?php
/**
 * Main application layout.
 * Controllers render views into $content, then include this file.
 *
 * Available: $content, $pageTitle, plus anything the controller passed.
 */

use App\Core\Auth;
use App\Core\Flash;

$currentRoute = (string) ($_GET['r'] ?? 'dashboard/index');
$user = Auth::user();
$flashes = Flash::pull();
$accent = setting('accent_color', '#0d9488');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(setting('shop_name', APP_NAME)) ?></title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/app.css">
<?php if (preg_match('/^#[0-9a-fA-F]{6}$/', $accent)): ?>
<style>:root { --ds-accent: <?= e($accent) ?>; --ds-accent-soft: <?= e($accent) ?>1f; }</style>
<?php endif; ?>
<script>
// Apply saved theme before first paint to avoid a flash of the wrong mode.
(function () {
  var t = localStorage.getItem('ds-theme');
  if (!t) { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
  document.documentElement.setAttribute('data-bs-theme', t);
  if (localStorage.getItem('ds-sidebar') === 'collapsed' && window.innerWidth > 991) {
    document.documentElement.className += ' boot-collapsed';
  }
})();
</script>
</head>
<body class="">
<div class="app-shell">

  <?php require APP_PATH . '/Views/partials/sidebar.php'; ?>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="app-main">
    <?php require APP_PATH . '/Views/partials/topbar.php'; ?>

    <main class="app-content">
      <?= $content ?>
    </main>
  </div>
</div>

<!-- Toasts -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080">
  <?php foreach ($flashes as $f):
      $icon = match ($f['type']) {
          'success' => 'check-circle-fill',
          'danger'  => 'exclamation-triangle-fill',
          'warning' => 'exclamation-circle-fill',
          default   => 'info-circle-fill',
      };
  ?>
  <div class="toast align-items-center text-bg-<?= e($f['type']) ?> border-0 js-flash-toast" role="alert">
    <div class="d-flex">
      <div class="toast-body">
        <i class="bi bi-<?= $icon ?> me-2"></i><?= e($f['message']) ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Shared delete-confirmation modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <i class="bi bi-exclamation-triangle text-danger" style="font-size:2rem"></i>
        <p class="mt-3 mb-0" id="confirmModalText">Are you sure?</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmModalYes">Yes, continue</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chartjs/chart.umd.js"></script>
<script src="assets/js/app.js"></script>
<?php if (!empty($pageScript)): ?>
<script src="assets/js/<?= e($pageScript) ?>.js"></script>
<?php endif; ?>
<?php if (!empty($inlineScript)): ?>
<script><?= $inlineScript /* controller-built, never raw user input */ ?></script>
<?php endif; ?>
</body>
</html>
<?php clear_form_stash(); ?>
