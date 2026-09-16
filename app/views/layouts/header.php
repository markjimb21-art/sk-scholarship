<?php
$user = Auth::user();
$roleName = $user['role_name'] ?? 'guest';
$unread = $user ? Notification::unreadCount((int)$user['id']) : 0;
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · SK Scholarship MIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
  <?php require VIEW_PATH . '/layouts/sidebar.php'; ?>

  <div class="app-main">
    <nav class="navbar navbar-expand-lg app-navbar sticky-top">
      <div class="container-fluid">
        <button class="btn btn-link d-lg-none" id="toggleSidebar"><i class="bi bi-list fs-4"></i></button>
        <span class="navbar-brand mb-0 h6 d-none d-sm-inline"><?= e($pageTitle) ?></span>
        <div class="ms-auto d-flex align-items-center gap-3">
          <a href="<?= url($roleName === 'applicant' ? 'applicant/notifications' : 'admin/notifications') ?>" class="position-relative text-dark">
            <i class="bi bi-bell fs-5"></i>
            <?php if ($unread > 0): ?>
              <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill">
                <?= $unread > 99 ? '99+' : $unread ?>
              </span>
            <?php endif; ?>
          </a>
          <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
              <div class="avatar-circle"><?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
              <span class="ms-2 d-none d-md-inline small"><?= e($user['full_name'] ?? '') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><span class="dropdown-item-text small text-muted"><?= e(ucfirst($roleName)) ?></span></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <main class="app-content">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
          <?= e($f['message']) ?>
          <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>