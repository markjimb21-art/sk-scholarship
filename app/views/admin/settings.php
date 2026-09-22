<?php
$map = [];
foreach ($settings as $s) $map[$s['setting_key']] = $s['setting_value'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">System Settings</h4>
    <p class="text-muted small mb-0">Global configuration for the SK Scholarship MIS</p>
  </div>
</div>

<form method="POST" action="<?= url('admin/settings/save') ?>">
  <?= csrf_field() ?>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-globe me-1"></i> General</h6></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Site Name</label>
            <input name="site_name" class="form-control" value="<?= e($map['site_name'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Barangay Name</label>
            <input name="barangay_name" class="form-control" value="<?= e($map['barangay_name'] ?? '') ?>">
          </div>
          <div class="form-check form-switch">
            <input type="hidden" name="application_open" value="0">
            <input class="form-check-input" type="checkbox" name="application_open" value="1" id="appOpen"
                   <?= ($map['application_open'] ?? '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="appOpen">Accept new applications</label>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-upload me-1"></i> File Uploads</h6></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Max File Size (MB)</label>
            <input type="number" min="1" max="50" name="max_file_size_mb" class="form-control"
                   value="<?= e($map['max_file_size_mb'] ?? '5') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Allowed Document Types</label>
            <input name="allowed_doc_types" class="form-control"
                   value="<?= e($map['allowed_doc_types'] ?? 'pdf,jpg,jpeg,png') ?>">
            <div class="form-text">Comma-separated extensions.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-envelope me-1"></i> Notifications</h6></div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input type="hidden" name="email_notifications" value="0">
            <input class="form-check-input" type="checkbox" name="email_notifications" value="1" id="emailNotif"
                   <?= ($map['email_notifications'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="emailNotif">Send email notifications</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Interview Reminder (hours before)</label>
            <input type="number" min="1" max="168" name="interview_reminder_hours" class="form-control"
                   value="<?= e($map['interview_reminder_hours'] ?? '24') ?>">
          </div>
          <div class="alert alert-info small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            SMTP is configured via environment variables (<code>MAIL_HOST</code>, <code>MAIL_USER</code>, etc.)
            — not editable here for security.
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-shield-lock me-1"></i> Security Info</h6></div>
        <div class="card-body small">
          <p class="mb-1"><strong>PHP Version:</strong> <?= PHP_VERSION ?></p>
          <p class="mb-1"><strong>Uploads Dir:</strong> <?= UPLOAD_PATH ?></p>
          <p class="mb-1"><strong>Max PHP Upload:</strong> <?= ini_get('upload_max_filesize') ?></p>
          <p class="mb-1"><strong>Max POST:</strong> <?= ini_get('post_max_size') ?></p>
          <p class="mb-1"><strong>Session Lifetime:</strong> <?= SESSION_LIFETIME ?> sec</p>
          <p class="mb-0"><strong>Lockout Attempts:</strong> <?= MAX_LOGIN_ATTEMPTS ?></p>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
  </div>
</form>