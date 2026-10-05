<?php
$pageTitle = 'Set a New Password';
$authHeading = 'Set a new password';
require VIEW_PATH . '/layouts/auth_head.php';
?>
<div class="auth-card">
  <?php require VIEW_PATH . '/layouts/auth_brand.php'; ?>
  <?php require VIEW_PATH . '/layouts/flashes.php'; ?>
  <form method="POST" action="<?= e(url('reset-password')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="password">New password</label>
      <input type="password" name="password" class="form-control<?= field_invalid('password') ?>" required autofocus <?= password_input_attrs('password') ?>>
      <?= password_checklist('password') ?>
      <?= field_error('password', 'password') ?>
    </div>
    <div class="mb-4">
      <label class="form-label" for="confirm_password">Confirm new password</label>
      <input type="password" name="confirm_password" id="confirm_password" data-pw-confirm-for="password" required autocomplete="new-password"
             class="form-control<?= field_invalid('confirm_password') ?>" aria-describedby="confirm_password-match confirm_password-error">
      <?= password_match_check('confirm_password') ?>
      <?= field_error('confirm_password', 'confirm_password') ?>
    </div>
    <button type="submit" class="btn btn-primary btn-lg w-100">Reset Password</button>
  </form>
</div>
<?php require VIEW_PATH . '/layouts/auth_foot.php'; ?>
