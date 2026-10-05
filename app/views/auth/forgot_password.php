<?php
$pageTitle = 'Forgot Password';
$authHeading = 'Forgot your password?';
require VIEW_PATH . '/layouts/auth_head.php';
?>
<div class="auth-card">
  <?php require VIEW_PATH . '/layouts/auth_brand.php'; ?>
  <p class="text-center">Enter the email you registered with and we'll send you a 6-digit verification code.</p>
  <?php require VIEW_PATH . '/layouts/flashes.php'; ?>
  <form method="POST" action="<?= url('forgot-password') ?>">
    <?= csrf_field() ?>
    <div class="mb-4">
      <label class="form-label" for="email">Email address</label>
      <input type="email" name="email" id="email" class="form-control" required autofocus autocomplete="email">
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100">Send Verification Code</button>
  </form>
  <hr class="my-4">
  <div class="auth-links"><p class="mb-0"><a href="<?= url('login') ?>">Back to sign in</a></p></div>
</div>
<?php require VIEW_PATH . '/layouts/auth_foot.php'; ?>
