<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In · SK Scholarship MIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8); min-height:100vh;">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <i class="bi bi-mortarboard-fill text-primary" style="font-size:2.75rem;"></i>
            <h4 class="fw-bold mt-2 mb-0">SK Scholarship MIS</h4>
            <p class="text-muted small">Barangay Estefania</p>
          </div>

          <?php foreach (get_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
          <?php endforeach; ?>

          <form method="POST" action="<?= url('login') ?>" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control" required
                       value="<?= e(old('email')) ?>" autofocus>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" required>
              </div>
            </div>
            <div class="text-end mb-3">
              <a href="<?= url('forgot-password') ?>" class="small">Forgot password?</a>
            </div>
            <button class="btn btn-primary w-100 py-2 fw-semibold">
              <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
          </form>

          <hr class="my-4">
          <p class="text-center small mb-0">
            Don't have an account? <a href="<?= url('register') ?>">Register as Applicant</a>
          </p>
          <p class="text-center small"><a href="<?= url('home') ?>">&larr; Back to home</a></p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>