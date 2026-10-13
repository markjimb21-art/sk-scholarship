<?php
$pageTitle = 'Verify Your E-mail';
$authHeading = 'Verify your e-mail';
$pageScripts = [asset('js/pages/otp-countdown.js')];
require VIEW_PATH . '/layouts/auth_head.php';
?>
<div class="auth-card">
  <?php require VIEW_PATH . '/layouts/auth_brand.php'; ?>
  <p class="text-center">We sent a 6-digit code to <strong><?= e($email) ?></strong>. Enter it below to activate your account.</p>
  <?php require VIEW_PATH . '/layouts/flashes.php'; ?>

  <form method="POST" action="<?= url('verify-otp') ?>">
    <?= csrf_field() ?>
    <label class="form-label" for="otp">6-digit code</label>
    <input type="text" name="otp" id="otp" class="form-control form-control-lg text-center mb-2" style="letter-spacing:.5em;font-weight:700;"
           maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus aria-describedby="otpCountdown">
    <p id="otpCountdown" class="text-muted mb-3">The code expires in <span id="otpTime" data-ttl="<?= Otp::TTL_MINUTES * 60 ?>"><?= Otp::TTL_MINUTES ?>:00</span>.</p>
    <div id="otpExpired" role="alert"></div>
    <button type="submit" class="btn btn-primary btn-lg w-100 mb-2">Verify Code</button>
  </form>

  <form method="POST" action="<?= url('resend-otp') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="purpose" value="registration">
    <button type="submit" class="btn btn-outline-secondary w-100">Resend Code</button>
  </form>

  <hr class="my-4">
  <div class="auth-links"><p class="mb-0"><a href="<?= url('login') ?>">Back to sign in</a></p></div>
</div>
<?php require VIEW_PATH . '/layouts/auth_foot.php'; ?>
