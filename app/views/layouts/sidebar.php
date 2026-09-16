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
      <div class="fw-bold">SK Scholarship</div>
      <small>Barangay Estefania</small>
    </div>
  </div>

  <ul class="nav flex-column sidebar-nav">
    <?php if ($roleName === 'applicant'): ?>
      <?php
        $item('applicant/dashboard', 'bi-speedometer2', 'Dashboard');
        $item('applicant/application', 'bi-file-earmark-text', 'My Application');
        $item('applicant/documents', 'bi-folder2-open', 'My Documents');
        $item('applicant/interview', 'bi-calendar-event', 'My Interview');
        $item('applicant/scholarship', 'bi-award', 'My Scholarship');
        $item('applicant/notifications', 'bi-bell', 'Notifications');
        $item('applicant/profile', 'bi-person-circle', 'My Profile');
      ?>
    <?php else: ?>
    <?php
      $item('admin/dashboard', 'bi-speedometer2', 'Dashboard');
      if (Auth::hasRole('admin','staff')) {
        $item('admin/applications', 'bi-file-earmark-text', 'Applications');
        $item('admin/applicants', 'bi-people', 'Applicants');
        $item('admin/documents', 'bi-folder2-open', 'Documents');
      }
      if (Auth::hasRole('admin','staff')) {
        $item('admin/interview-scheduling', 'bi-calendar-plus', 'Interview Scheduling');
        $item('admin/interview-calendar', 'bi-calendar3', 'Interview Calendar');
        $item('admin/interview-evaluation', 'bi-clipboard-check', 'Interview Evaluation');
      }
      if (Auth::hasRole('admin','staff')) {
        $item('admin/scholars', 'bi-mortarboard', 'Scholars');
        $item('admin/academic-records', 'bi-journal-text', 'Academic Records');
        $item('admin/releases', 'bi-cash-coin', 'Scholarship Releases');
        $item('admin/renewals', 'bi-arrow-repeat', 'Renewals');
      }
      $item('admin/analytics', 'bi-graph-up-arrow', 'Data Analytics');
      $item('admin/reports', 'bi-printer', 'Reports');
      if (Auth::hasRole('admin')) {
        $item('admin/budget', 'bi-wallet2', 'Budget');
        $item('admin/users', 'bi-person-badge', 'Users');
        $item('admin/audit-logs', 'bi-shield-check', 'Audit Logs');
        $item('admin/settings', 'bi-gear', 'System Settings');
      }
    ?>
  <?php endif; ?>
  </ul>
</aside>