<?php
$pageTitle = 'Register';
$authHeading = 'Create your account';
require VIEW_PATH . '/layouts/auth_head.php';
?>
<div class="auth-card">
  <?php require VIEW_PATH . '/layouts/auth_brand.php'; ?>
  <p class="text-center">For college students who live in Barangay Estefania.</p>
  <?php require VIEW_PATH . '/layouts/flashes.php'; ?>

  <form method="POST" action="<?= url('register') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="full_name">Full name</label>
      <input type="text" name="full_name" id="full_name" class="form-control<?= field_invalid('full_name') ?>" required autocomplete="name"
             value="<?= e(old('full_name')) ?>" aria-describedby="full_name-error">
      <?= field_error('full_name', 'full_name') ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="email">Email address</label>
      <input type="email" name="email" id="email" class="form-control<?= field_invalid('email') ?>" required autocomplete="email"
             value="<?= e(old('email')) ?>" aria-describedby="email-help email-error">
      <div class="form-text" id="email-help">This will be your login and the email on your application.</div>
      <?= field_error('email', 'email') ?>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <input type="password" name="password" class="form-control<?= field_invalid('password') ?>" required <?= password_input_attrs('password') ?>>
      <?= password_checklist('password') ?>
      <?= field_error('password', 'password') ?>
    </div>
    <div class="mb-4">
      <label class="form-label" for="confirm_password">Confirm password</label>
      <input type="password" name="confirm_password" id="confirm_password" data-pw-confirm-for="password" required autocomplete="new-password"
             class="form-control<?= field_invalid('confirm_password') ?>" aria-describedby="confirm_password-match confirm_password-error">
      <?= password_match_check('confirm_password') ?>
      <?= field_error('confirm_password', 'confirm_password') ?>
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100">
      <i class="bi bi-check2-circle" aria-hidden="true"></i> Create Account
    </button>
  </form>

  <hr class="my-4">
  <div class="auth-links"><p class="mb-0">Already registered? <a href="<?= url('login') ?>">Sign in</a></p></div>
</div>
<p class="text-center mt-3"><a class="auth-back" href="<?= url('home') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to home</a></p>
<?php require VIEW_PATH . '/layouts/auth_foot.php'; ?>
