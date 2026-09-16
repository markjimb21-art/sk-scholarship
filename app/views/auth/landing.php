<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SK Scholarship MIS · Barangay Estefania</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body>
<section class="landing-hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="badge bg-light text-primary mb-3"><i class="bi bi-geo-alt-fill"></i> Barangay Estefania</span>
        <h1 class="display-4 fw-bold mb-3">Sangguniang Kabataan Scholarship Management System</h1>
        <p class="lead opacity-90 mb-4">
          A dedicated scholarship program for <strong>college students who are residents of Barangay Estefania</strong>.
          Apply online, submit requirements, schedule your interview, and track your scholarship — all in one place.
        </p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="<?= url('register') ?>" class="btn btn-light btn-lg px-4">
            <i class="bi bi-person-plus me-1"></i> Register as Applicant
          </a>
          <a href="<?= url('login') ?>" class="btn btn-outline-light btn-lg px-4">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
          </a>
        </div>
        <p class="small mt-4 opacity-75">
          <i class="bi bi-shield-lock me-1"></i>
          This program is exclusively for college students residing in Barangay Estefania.
        </p>
      </div>
      <div class="col-lg-5 text-center d-none d-lg-block">
        <i class="bi bi-mortarboard-fill" style="font-size: 18rem; opacity:.25;"></i>
      </div>
    </div>
  </div>
</section>
</body>
</html>