<!doctype html><html><head>
<meta charset="utf-8"><title>Verify Reset Code</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head><body style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8); min-height:100vh;">
<div class="container py-5">
  <div class="row justify-content-center"><div class="col-md-5">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-key text-primary" style="font-size:2.5rem;"></i>
        <h4 class="fw-bold mt-2 mb-1">Enter Verification Code</h4>
        <p class="text-muted small mb-4">If that e-mail is registered, a 6-digit code was sent to it. Enter it below.</p>

        <?php foreach (get_flashes() as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?> py-2 small text-start"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="<?= url('verify-reset-otp') ?>">
          <?= csrf_field() ?>
          <input type="text" name="otp" class="form-control form-control-lg text-center mb-3" style="letter-spacing:8px;font-weight:bold;"
                 maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="------" required autofocus>
          <div id="otpCountdown" class="small text-muted mb-3">Code expires in <span id="otpMins">10</span>:00</div>
          <button class="btn btn-primary w-100 mb-2">Verify Code</button>
        </form>

        <form method="POST" action="<?= url('resend-otp') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="purpose" value="password_reset">
          <button class="btn btn-outline-secondary w-100">Resend Code</button>
        </form>

        <p class="text-center small mt-3"><a href="<?= url('login') ?>">&larr; Back to login</a></p>
      </div>
    </div>
  </div></div>
</div>
<script>
(function(){
  var total = 10*60;
  var timer = setInterval(function(){
    total--;
    if (total <= 0) { clearInterval(timer); document.getElementById('otpCountdown').textContent = 'Code expired — please resend.'; return; }
    var m = Math.floor(total/60), s = total%60;
    document.getElementById('otpCountdown').innerHTML = 'Code expires in ' + m + ':' + (s<10?'0':'') + s;
  }, 1000);
})();
</script>
</body></html>
