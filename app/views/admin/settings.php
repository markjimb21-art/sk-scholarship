<h4 class="fw-bold mb-3">Settings</h4>

<div class="row g-3">
  <div class="col-lg-6">
    <form method="POST" action="<?= url('admin/settings/save') ?>" class="card border-0 shadow-sm">
      <?= csrf_field() ?>
      <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-envelope me-1"></i> Notifications</h6></div>
      <div class="card-body">
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="email_notifications" value="1" id="emailNotif" <?= $settings['email_notifications'] === '1' ? 'checked' : '' ?>>
          <label class="form-check-label" for="emailNotif">Send e-mails for status changes and schedules</label>
          <div class="form-text">When off, applicants still get in-app notifications. OTP codes are always e-mailed.</div>
        </div>
        <label class="form-label">Interview reminder — hours before the interview</label>
        <input type="number" min="0" max="168" name="interview_reminder_hours" class="form-control" value="<?= e($settings['interview_reminder_hours']) ?>">
        <div class="form-text">Used by <code>bin/send_reminders.php</code> (see README). 0 turns interview reminders off.</div>

        <div class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" name="daily_digest_enabled" value="1" id="digestOn" <?= $settings['daily_digest_enabled'] === '1' ? 'checked' : '' ?>>
          <label class="form-check-label" for="digestOn">Daily interview digest</label>
          <div class="form-text">On the first run of <code>bin/send_reminders.php</code> each day: "Your interview is today" to each applicant
            interviewed today, and one list of today's interviews to the admins.
            Last sent: <?= $settings['daily_digest_sent_on'] !== '' ? e(date('M d, Y', strtotime($settings['daily_digest_sent_on']))) : 'never' ?>.</div>
        </div>
        <div class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" name="admin_event_emails" value="1" id="adminEvents" <?= $settings['admin_event_emails'] === '1' ? 'checked' : '' ?>>
          <label class="form-check-label" for="adminEvents">Also e-mail admins about new submissions</label>
          <div class="form-text">Admins always get an in-app notification when an application, claim or renewal is submitted, a first no-show is
            recorded, or an applicant requests an interview slot. Turn this on to e-mail them too.</div>
        </div>
      </div>
      <div class="card-footer bg-white"><button class="btn btn-primary"><i class="bi bi-save"></i> Save settings</button></div>
    </form>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-send me-1"></i> Send test e-mail</h6></div>
      <div class="card-body">
        <form method="POST" action="<?= url('admin/settings/test-email') ?>" class="d-flex gap-2">
          <?= csrf_field() ?>
          <input type="email" name="test_email" class="form-control" placeholder="you@example.com" required>
          <button class="btn btn-outline-primary text-nowrap"><i class="bi bi-send"></i> Send</button>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-envelope-check me-1"></i> E-mail configuration (.env)</h6></div>
      <div class="card-body small">
        <p class="mb-1"><strong>SMTP host / port:</strong> <?= e($mailStatus['host'] ?? 'not set') ?> : <?= (int)$mailStatus['port'] ?> (<?= e($mailStatus['encryption']) ?>)</p>
        <p class="mb-1"><strong>SMTP username:</strong> <?= $mailStatus['user_configured'] ? '<span class="text-success">set</span>' : '<span class="text-danger">missing</span>' ?>
          · <strong>password:</strong> <?= $mailStatus['pass_configured'] ? '<span class="text-success">set</span>' : '<span class="text-danger">missing</span>' ?></p>
        <p class="mb-1"><strong>Sender:</strong> <?= $mailStatus['from_is_placeholder'] ? '<span class="text-warning">MAIL_FROM not set — the SMTP account is used</span>' : '<span class="text-success">set</span>' ?></p>
        <p class="mb-0"><strong>PHPMailer:</strong> <?= $mailStatus['phpmailer_loaded'] ? '<span class="text-success">installed</span>' : '<span class="text-danger">missing — run composer install</span>' ?>
          · <strong>OpenSSL:</strong> <?= $mailStatus['openssl_enabled'] ? '<span class="text-success">on</span>' : '<span class="text-danger">off</span>' ?></p>
      </div>
    </div>
  </div>
</div>
