<!doctype html>
<html lang="en">
<head>
<?php require VIEW_PATH . '/layouts/head_assets.php'; ?>
<title><?= e(APP_NAME) ?></title>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<main id="main" class="landing-hero" tabindex="-1">
  <div class="container py-4">
    <div class="d-flex justify-content-end mb-3"><div class="auth-topbar p-0"><?php require VIEW_PATH . '/layouts/text_size.php'; ?></div></div>
    <div class="row align-items-center g-4 gx-lg-5">
      <div class="col-lg-7">
        <div class="brand-logos justify-content-start mb-4">
          <img class="logo-on-dark" src="<?= e(asset('img/sk-logo.png')) ?>" alt="SK Barangay Estefania logo" width="96" height="96">
          <img class="logo-on-dark" src="<?= e(asset('img/barangay-seal.png')) ?>" alt="Barangay Estefania seal" width="96" height="96">
        </div>
        <p class="fw-bold text-uppercase mb-1" style="letter-spacing:.06em;"><?= e(APP_SUBTITLE) ?></p>
        <h1 class="display-5 fw-bold mb-0"><?= e(APP_NAME_SHORT) ?></h1>
        <p class="h2 fw-bold mb-0">Educational Assistance for College Students</p>
        <div class="hero-accent" aria-hidden="true"></div>
        <p class="lead mb-4">
          An educational assistance program for <strong>college students who live in Barangay Estefania</strong>.
          Apply online, upload your requirements, see your interview schedule, and follow your assistance — all in one place.
        </p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="<?= url('register') ?>" class="btn btn-light btn-lg px-4">
            <i class="bi bi-person-plus" aria-hidden="true"></i> Register as an applicant
          </a>
          <a href="<?= url('login') ?>" class="btn btn-outline-light btn-lg px-4">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Sign In
          </a>
        </div>
        <p class="mt-4 mb-0">
          <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
          This program is only for college students living in Barangay Estefania.
        </p>
      </div>
      <div class="col-lg-5">
        <h2 class="h4 fw-bold">What you need</h2>
        <ul class="list-unstyled d-grid gap-2 mb-0">
          <?php foreach (array_merge(['Application form (filled in online here)'], DOCUMENT_TYPES) as $req): ?>
            <li class="feature d-flex align-items-center gap-3"><i class="bi bi-check2-circle" aria-hidden="true"></i><span><?= e($req) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</main>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
</body>
</html>
