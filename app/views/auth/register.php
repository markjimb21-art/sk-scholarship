<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register · SK Scholarship MIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8); min-height:100vh;">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
          <div class="text-center mb-4">
            <i class="bi bi-person-plus-fill text-primary" style="font-size:2.5rem;"></i>
            <h4 class="fw-bold mt-2 mb-0">Create Account</h4>
            <p class="text-muted small">College students of Barangay Estefania</p>
          </div>

          <?php foreach (get_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
          <?php endforeach; ?>

          <form method="POST" action="<?= url('register') ?>" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" required
                     value="<?= e(old('full_name')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" required
                     value="<?= e(old('email')) ?>">
              <div class="form-text">This will be your login and the email on your application.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
              <div class="form-text">Minimum <?= PASSWORD_MIN_LENGTH ?> characters.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100 py-2 fw-semibold">
              <i class="bi bi-check2-circle me-1"></i> Create Account
            </button>
          </form>

          <hr class="my-4">
          <p class="text-center small mb-0">
            Already registered? <a href="<?= url('login') ?>">Sign In</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>