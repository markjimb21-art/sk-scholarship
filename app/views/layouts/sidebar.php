<?php
$roleName = Auth::role();
$current = $_GET['r'] ?? '';
$item = function (string $route, string $icon, string $label) use ($current) {
    $active = ($current === $route || str_starts_with($current, $route . '/')) ? ' active' : '';
    echo '<li class="nav-item">'
       . '<a class="nav-link' . $active . '" href="' . url($route) . '">'
       . '<i class="bi ' . $icon . '"></i><span>' . $label . '</span></a></li>';
};
?>
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-brand">
    <i class="bi bi-mortarboard-fill"></i>
    <div>
      <div class="fw-bold"><?= e(APP_NAME_SHORT) ?></div>
      <small>Barangay Estefania</small>
    </div>
  </div>

  <ul class="nav flex-column sidebar-nav">
    <?php if ($roleName === 'applicant'): ?>
      <?php
        $item('applicant/dashboard', 'bi-speedometer2', 'Dashboard');
        $item('applicant/application', 'bi-file-earmark-text', 'My Application');
        $item('applicant/scholarship', 'bi-award', 'My Scholarship');
        $item('applicant/notifications', 'bi-bell', 'Notifications');
        $item('applicant/profile', 'bi-person-circle', 'My Profile');
      ?>
    <?php else: ?>
    <?php
      // In process order (docs/PROCESS.md). Only the pages this staff role may open (AdminController::canAccess);
      // a section heading is printed only when at least one of its pages is shown.
      $pendingSection = null;
      $section = static function (string $label) use (&$pendingSection): void { $pendingSection = $label; };
      $staffItem = $item;
      $item = static function (string $route, string $icon, string $label) use (&$pendingSection, $staffItem): void {
          if (!AdminController::canAccess(substr($route, 6))) return;
          if ($pendingSection !== null) {
              echo '<li class="nav-item small text-uppercase text-muted px-3 pt-3 pb-1" style="font-size:.7rem">' . e($pendingSection) . '</li>';
              $pendingSection = null;
          }
          $staffItem($route, $icon, $label);
      };
      $item('admin/dashboard', 'bi-speedometer2', 'Dashboard');
      // Overview across both processes (interviews + claim/renewal deadlines), so it sits with the dashboard.
      $item('admin/calendar', 'bi-calendar3', 'Calendar');
      $section('Application process');
      $item('admin/programs', 'bi-collection', 'Programs');
      $item('admin/screening', 'bi-inbox', 'Screening');
      $item('admin/interviews', 'bi-calendar-plus', 'Interviews');
      $item('admin/decisions', 'bi-clipboard-check', 'Decisions');
      $section('Releasing of budget');
      $item('admin/budget', 'bi-wallet2', 'Budget');
      $item('admin/release-schedule', 'bi-calendar-check', 'Claim schedule');
      $item('admin/claims', 'bi-file-earmark-check', 'Claims');
      $item('admin/releases', 'bi-cash-coin', 'Releases');
      $item('admin/renewal-schedule', 'bi-calendar-range', 'Renewal schedule');
      $item('admin/renewals', 'bi-arrow-repeat', 'Renewals');
      $item('admin/scholars', 'bi-mortarboard', 'Scholars');
      $section('Records & reports');
      $item('admin/applications', 'bi-file-earmark-text', 'Applications');
      $item('admin/reports', 'bi-printer', 'Reports');
      $item('admin/analytics', 'bi-graph-up-arrow', 'Analytics');
      $section('System');
      $item('admin/users', 'bi-person-badge', 'Users');
      $item('admin/settings', 'bi-gear', 'Settings');
      $item('admin/audit-logs', 'bi-shield-check', 'Audit Logs');
    ?>
  <?php endif; ?>
  </ul>
</aside>