<!doctype html><html><head>
<meta charset="utf-8"><title>Forgot Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8); min-height:100vh;">
<div class="container py-5">
  <div class="row justify-content-center"><div class="col-md-5">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-body p-4 p-md-5">
        <h4 class="fw-bold mb-3">Forgot Password</h4>
        <p class="text-muted small">Enter your registered email to receive a reset link.</p>
        <?php foreach (get_flashes() as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
        <form method="POST" action="<?= url('forgot-password') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required autofocus>
          </div>
          <button class="btn btn-primary w-100">Send Reset Link</button>
        </form>
        <p class="text-center small mt-3"><a href="<?= url('login') ?>">Back to login</a></p>
      </div>
    </div>
  </div></div>
</div>
</body></html>