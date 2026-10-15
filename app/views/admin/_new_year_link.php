<?php
// "+ New academic year" under an academic-year <select>: the years come only from Releasing of budget → Academic years.
// Shown only to the roles that may open that page.
if (AdminController::canAccess('academic-years')): ?>
  <a class="small text-nowrap" href="<?= url('admin/academic-years') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> New academic year</a>
<?php endif;
