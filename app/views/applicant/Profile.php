<h4 class="fw-bold mb-3">My Profile</h4>
<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <form method="POST" action="<?= url('applicant/profile') ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Full Name</label>
          <input name="full_name" class="form-control" value="<?= e(Auth::user()['full_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email (read-only)</label>
          <input class="form-control bg-light" readonly value="<?= e(Auth::user()['email']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Contact Number</label>
          <input name="contact_number" class="form-control" value="<?= e(Auth::user()['contact_number'] ?? '') ?>"></div>
      </div>

      <hr class="my-4">
      <h6>Change Password (optional)</h6>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="currentPassword">Current password</label>
          <input type="password" name="current_password" id="currentPassword" class="form-control" autocomplete="current-password"></div>
        <div class="col-md-4"><label class="form-label" for="newPassword">New password</label>
          <input type="password" name="new_password" class="form-control" <?= password_input_attrs('newPassword') ?>>
          <?= password_checklist('newPassword') ?></div>
        <div class="col-md-4"><label class="form-label" for="newPasswordConfirm">Confirm new password</label>
          <input type="password" name="new_password_confirm" id="newPasswordConfirm" class="form-control" data-pw-confirm-for="newPassword"
                 autocomplete="new-password" aria-describedby="newPasswordConfirm-match">
          <?= password_match_check('newPasswordConfirm') ?></div>
      </div>

      <button class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i> Save Changes</button>
    </form>
  </div>
</div>