<?php
$user = Auth::user();
$roleName = $user['role_name'] ?? 'guest';
$unread = $user ? Notification::unreadCount((int)$user['id']) : 0;
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
<?php require VIEW_PATH . '/layouts/head_assets.php'; ?>
<title><?= e($pageTitle) ?> · <?= e(APP_NAME_SHORT) ?></title>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<div class="app-wrapper">
  <?php require VIEW_PATH . '/layouts/sidebar.php'; ?>

  <div class="app-main">
    <nav class="navbar app-navbar sticky-top" aria-label="Top bar">
      <div class="container-fluid gap-2 flex-nowrap">
        <button type="button" class="btn btn-link d-lg-none p-0" id="toggleSidebar" aria-label="Open the menu" aria-controls="appSidebar" aria-expanded="false">
          <i class="bi bi-list fs-3" aria-hidden="true"></i>
        </button>
        <span class="navbar-brand mb-0 h6 d-none d-sm-inline text-truncate"><?= e($pageTitle) ?></span>
        <div class="ms-auto d-flex align-items-center gap-2 gap-sm-3">
          <span class="d-none d-md-inline-flex"><?php require VIEW_PATH . '/layouts/text_size.php'; ?></span>
          <a href="<?= url($roleName === 'applicant' ? 'applicant/notifications' : 'admin/notifications') ?>" class="position-relative nav-icon-btn"
             aria-label="Notifications<?= $unread > 0 ? ' — ' . (int)$unread . ' unread' : '' ?>">
            <i class="bi bi-bell fs-4" aria-hidden="true"></i>
            <?php if ($unread > 0): ?>
              <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill" aria-hidden="true">
                <?= $unread > 99 ? '99+' : $unread ?>
              </span>
            <?php endif; ?>
          </a>
          <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle nav-icon-btn" data-bs-toggle="dropdown" aria-expanded="false"
               aria-label="Account menu for <?= e($user['full_name'] ?? '') ?>">
              <span class="avatar-circle" aria-hidden="true"><?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?></span>
              <span class="ms-2 d-none d-md-inline"><?= e($user['full_name'] ?? '') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><span class="dropdown-item-text"><?= e(ucfirst($roleName)) ?></span></li>
              <li class="d-md-none px-3 py-2"><?php require VIEW_PATH . '/layouts/text_size.php'; ?></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('logout') ?>"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out</a></li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <main class="app-content" id="main" tabindex="-1">
      <?php $flashDismissible = true; require VIEW_PATH . '/layouts/flashes.php'; ?>
