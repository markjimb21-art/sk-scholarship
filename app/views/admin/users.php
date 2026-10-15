<?php
// Staff roles and what each may open (AdminController::ROUTES).
// Applicants show as Scholar while their scholarship is Active, and Graduated after it (User::search shown_role).
$roleLabels = ['admin' => 'Admin', 'reviewer' => 'Reviewer', 'treasurer' => 'Treasurer', 'applicant' => 'Applicant',
               'scholar' => 'Scholar', 'graduated' => 'Graduated'];
$roleColors = ['admin' => 'danger', 'reviewer' => 'primary', 'treasurer' => 'success', 'applicant' => 'secondary',
               'scholar' => 'info', 'graduated' => 'dark'];
$roleHelp = ['admin' => 'everything, including users and settings', 'reviewer' => 'screening, interviews, decisions (no budget or releases)',
             'treasurer' => 'budget, claims, releases, renewals, scholars (no applicant review)'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Users</h4>
    <p class="text-muted small mb-0">Staff accounts (admin, reviewer, treasurer) are created here; applicants register themselves and verify their e-mail.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-user-modal><i class="bi bi-person-plus"></i> Add staff</button>
</div>

<form method="GET" action="<?= url('admin/users') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-6"><div><label class="form-label" for="f_q">Search</label><input id="f_q" name="q" class="form-control" placeholder="Search name or e-mail" value="<?= e($q) ?>"></div></div>
    <div class="col-md-4">
      <div><label class="form-label" for="f_role">Role</label><select id="f_role" name="role" class="form-select">
        <option value="">All roles</option>
        <?php foreach ($roleLabels as $rk => $rl): ?><option value="<?= e($rk) ?>" <?= $role === $rk ? 'selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i></button></div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>Name</th><th>E-mail</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): $self = (int)$u['id'] === admin_id(); ?>
          <tr>
            <td class="fw-semibold"><?= e($u['full_name']) ?></td>
            <td><?= e($u['email']) ?><?= !$u['email_verified'] ? ' <span class="badge bg-warning">not verified</span>' : '' ?></td>
            <td><span class="badge bg-<?= e($roleColors[$u['shown_role']] ?? 'secondary') ?>"><?= e($roleLabels[$u['shown_role']] ?? $u['shown_role']) ?></span></td>
            <td><span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span></td>
            <td><small class="text-muted"><?= $u['last_login'] ? e(fmt_datetime($u['last_login'])) : 'Never' ?></small></td>
            <td class="text-end text-nowrap">
              <?php if ($u['role_name'] !== 'applicant'): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-user-modal="<?= e(js_json($u)) ?>" aria-label="Edit account"><i class="bi bi-pencil" aria-hidden="true"></i></button>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetModal"
                      data-reset-id="<?= (int)$u['id'] ?>" data-reset-email="<?= e($u['email']) ?>" aria-label="Reset password for <?= e($u['email']) ?>">
                <i class="bi bi-key"></i></button>
              <?php if (!$self): ?>
                <form method="POST" action="<?= url('admin/user-toggle') ?>" class="d-inline">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/user-save') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" id="userId">
      <div class="modal-header"><h5 class="modal-title" id="userModalTitle">Add staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Full name</label><input name="full_name" id="userName" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">E-mail</label><input type="email" name="email" id="userEmail" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Contact number</label><input name="contact_number" id="userContact" class="form-control" maxlength="11"></div>
        <div class="mb-2"><label class="form-label">Role</label>
          <select name="role" id="userRole" class="form-select" required>
            <?php foreach ($roleHelp as $rk => $help): ?><option value="<?= e($rk) ?>"><?= e($roleLabels[$rk] . ' — ' . $help) ?></option><?php endforeach; ?>
          </select>
          <div class="form-text">Changing a role is recorded in the audit log. You can’t change your own role, and the last active admin can’t lose the role.</div>
        </div>
        <div id="userPasswordRow">
          <div class="mb-2"><label class="form-label" for="userPassword">Password</label>
            <input type="password" name="password" class="form-control" <?= password_input_attrs('userPassword') ?>>
            <?= password_checklist('userPassword') ?></div>
          <div class="mb-2"><label class="form-label" for="userPasswordConfirm">Confirm password</label>
            <input type="password" name="password_confirm" id="userPasswordConfirm" class="form-control" data-pw-confirm-for="userPassword"
                   autocomplete="new-password" aria-describedby="userPasswordConfirm-match">
            <?= password_match_check('userPasswordConfirm') ?></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/user-reset') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" id="resetId">
      <div class="modal-header"><h5 class="modal-title">Reset password — <span id="resetEmail"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label" for="resetPassword">New password</label>
          <input type="password" name="new_password" class="form-control" required <?= password_input_attrs('resetPassword') ?>>
          <?= password_checklist('resetPassword') ?></div>
        <div class="mb-2"><label class="form-label" for="resetPasswordConfirm">Confirm new password</label>
          <input type="password" name="new_password_confirm" id="resetPasswordConfirm" class="form-control" data-pw-confirm-for="resetPassword" required
                 autocomplete="new-password" aria-describedby="resetPasswordConfirm-match">
          <?= password_match_check('resetPasswordConfirm') ?></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Reset</button></div>
    </form>
  </div>
</div>

