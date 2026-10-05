<?php
$pageTitle = 'Sign In';
$bothLogos = true;
$authHeading = 'Sign in';
require VIEW_PATH . '/layouts/auth_head.php';
?>
<div class="auth-card">
  <?php require VIEW_PATH . '/layouts/auth_brand.php'; ?>
  <?php require VIEW_PATH . '/layouts/flashes.php'; ?>

  <form method="POST" action="<?= url('login') ?>" novalidate data-login-form>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="email">Email address</label>
      <input type="email" name="email" id="email" class="form-control<?= field_invalid('email') ?>" required autocomplete="email"
             value="<?= e(old('email')) ?>" aria-describedby="email-error"<?= field_invalid('email') ? ' aria-invalid="true"' : '' ?> autofocus>
      <?= field_error('email', 'email') ?>
    </div>
    <div class="mb-2">
      <label class="form-label" for="password">Password</label>
      <input type="password" name="password" id="password" class="form-control<?= field_invalid('password') ?>" required autocomplete="current-password"
             aria-describedby="password-error"<?= field_invalid('password') ? ' aria-invalid="true"' : '' ?>>
      <?= field_error('password', 'password') ?>
    </div>
    <div class="text-end mb-3">
      <a href="<?= url('forgot-password') ?>">Forgot password?</a>
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100">
      <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign In
    </button>
  </form>

  <hr class="my-4">
  <div class="auth-links">
    <p class="mb-1">Don't have an account? <a href="<?= url('register') ?>">Register as an applicant</a></p>
  </div>
</div>
<p class="text-center mt-3"><a class="auth-back" href="<?= url('home') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to home</a></p>

<!-- Shown by ui.js when Sign In is pressed with an empty field; closes with Esc or OK, then focus goes to the first empty field. -->
<div class="modal fade" id="loginMissingModal" tabindex="-1" role="alertdialog" aria-modal="true"
     aria-labelledby="loginMissingTitle" aria-describedby="loginMissingText">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h4" id="loginMissingTitle"><i class="bi bi-exclamation-octagon-fill text-danger me-2" aria-hidden="true"></i>Something is missing</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="fs-5 mb-0" id="loginMissingText">Please enter your email address and password.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-lg px-5" data-bs-dismiss="modal" autofocus>OK</button>
      </div>
    </div>
  </div>
</div>
<?php require VIEW_PATH . '/layouts/auth_foot.php'; ?>
