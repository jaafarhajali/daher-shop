<?php
/**
 * Top navigation bar. Expects $pageTitle and $user from the layout.
 */
?>
<header class="app-topbar">
  <button class="btn btn-link text-body p-1" id="sidebarToggle" type="button"
          aria-label="Toggle navigation">
    <i class="bi bi-list fs-4"></i>
  </button>

  <h1 class="topbar-title d-none d-sm-block"><?= e($pageTitle) ?></h1>

  <form class="topbar-search ms-auto flex-grow-1 d-none d-md-block"
        action="index.php" method="get">
    <input type="hidden" name="r" value="products/index">
    <div class="input-group input-group-sm">
      <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
      <input type="search" name="q" id="globalSearch" class="form-control border-start-0"
             placeholder="Search products…  (Ctrl+K)" value="">
    </div>
  </form>

  <a href="<?= url('sales/pos') ?>" class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1 no-print">
    <i class="bi bi-basket2"></i> New sale
  </a>

  <button class="btn btn-link text-body p-1" id="themeToggle" type="button"
          aria-label="Toggle dark mode" title="Toggle dark mode">
    <i class="bi bi-moon-stars fs-5" id="themeToggleIcon"></i>
  </button>

  <div class="dropdown">
    <button class="btn btn-link text-body p-1 dropdown-toggle d-flex align-items-center gap-2"
            data-bs-toggle="dropdown" aria-expanded="false" type="button">
      <span class="d-inline-grid place-items-center rounded-circle text-bg-secondary"
            style="width:30px;height:30px;display:inline-grid;place-items:center;font-size:0.8rem;">
        <?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?>
      </span>
      <span class="d-none d-lg-inline"><?= e($user['full_name'] ?? '') ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
      <li><h6 class="dropdown-header"><?= e($user['username'] ?? '') ?> · <?= e($user['role'] ?? '') ?></h6></li>
      <li><a class="dropdown-item" href="<?= url('settings/profile') ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
      <li><a class="dropdown-item" href="<?= url('settings/shop') ?>"><i class="bi bi-shop me-2"></i>Shop settings</a></li>
      <?php endif; ?>
      <li><hr class="dropdown-divider"></li>
      <li>
        <form method="post" action="<?= url('auth/logout') ?>">
          <?= csrf_field() ?>
          <button class="dropdown-item text-danger" type="submit">
            <i class="bi bi-box-arrow-right me-2"></i>Sign out
          </button>
        </form>
      </li>
    </ul>
  </div>
</header>
