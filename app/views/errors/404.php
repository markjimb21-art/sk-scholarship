<?php
$pageTitle = 'Page not found';
require VIEW_PATH . '/layouts/auth_head.php';
?>
<div class="auth-card text-center">
  <?php require VIEW_PATH . '/layouts/auth_brand.php'; ?>
  <h1 class="h3">Page not found</h1>
  <p>The page you were looking for doesn't exist or has moved.</p>
  <a href="<?= url('home') ?>" class="btn btn-primary btn-lg">Go to the home page</a>
</div>
<?php require VIEW_PATH . '/layouts/auth_foot.php'; ?>
