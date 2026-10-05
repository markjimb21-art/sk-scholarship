<?php
$pageTitle = 'Something went wrong';
require VIEW_PATH . '/layouts/auth_head.php';
?>
<div class="auth-card text-center">
  <?php require VIEW_PATH . '/layouts/auth_brand.php'; ?>
  <h1 class="h3">Something went wrong</h1>
  <p>An unexpected error occurred and it has been logged. Please try again, or contact the SK office if the problem continues.</p>
  <a class="btn btn-primary btn-lg" href="javascript:history.back()">Go back</a>
</div>
<?php require VIEW_PATH . '/layouts/auth_foot.php'; ?>
