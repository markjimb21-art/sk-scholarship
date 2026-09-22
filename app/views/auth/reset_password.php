<!doctype html><html><head>
<meta charset="utf-8"><title>Reset Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8); min-height:100vh;">
<div class="container py-5">
  <div class="row justify-content-center"><div class="col-md-5">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-body p-4 p-md-5">
        <h4 class="fw-bold mb-3">Reset Password</h4>
        <?php foreach (get_flashes() as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
        <form method="POST" action="<?= e(url('reset-password?token=' . urlencode((string)($_GET['token'] ?? '')))) ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" minlength="<?= PASSWORD_MIN_LENGTH ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Reset Password</button>
        </form>
      </div>
    </div>
  </div></div>
</div>
</body></html>