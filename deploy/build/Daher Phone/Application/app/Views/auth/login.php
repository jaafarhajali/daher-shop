<?php
/**
 * Standalone login screen (rendered without the app layout).
 */

use App\Core\Flash;

$flashes = Flash::pull();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(setting('shop_name', APP_NAME)) ?></title>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/app.css">
<script>
(function () {
  var t = localStorage.getItem('ds-theme');
  if (!t) { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
  document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
</head>
<body>
<div class="login-page">
  <div class="login-card">

    <div class="text-center mb-4">
      <span class="brand-mark d-inline-grid mb-3" style="width:52px;height:52px;border-radius:14px;
            background:linear-gradient(135deg, var(--ds-accent), #115e59);
            display:inline-grid;place-items:center;color:#fff;font-size:1.5rem;">
        <i class="bi bi-wrench-adjustable"></i>
      </span>
      <h1 class="h4 mb-1"><?= e(setting('shop_name', APP_NAME)) ?></h1>
      <p class="text-secondary small mb-0">Repair shop management</p>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-4">

        <?php foreach ($flashes as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= url('auth/attempt') ?>" autocomplete="off">
          <?= csrf_field() ?>

          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="username" name="username"
                   placeholder="Username" required autofocus maxlength="50">
            <label for="username"><i class="bi bi-person me-1"></i>Username</label>
          </div>

          <div class="form-floating mb-4">
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="Password" required maxlength="255">
            <label for="password"><i class="bi bi-lock me-1"></i>Password</label>
          </div>

          <button class="btn btn-primary w-100 py-2" type="submit">
            Sign in
          </button>
        </form>
      </div>
    </div>

    <p class="text-center text-secondary small mt-3 mb-0">
      <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?>
    </p>
  </div>
</div>
</body>
</html>
