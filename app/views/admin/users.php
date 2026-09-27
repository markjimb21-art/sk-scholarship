<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0">Users</h4>
    <p class="text-muted small mb-0">Admins are created here; applicants register themselves and verify their e-mail.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserModal()"><i class="bi bi-person-plus"></i> Add admin</button>
</div>

<form method="GET" action="<?= url('admin/users') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body row g-2">
    <div class="col-md-6"><input name="q" class="form-control" placeholder="Search name or e-mail" value="<?= e($q) ?>"></div>
    <div class="col-md-4">
      <select name="role" class="form-select">
        <option value="">All roles</option>
        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="applicant" <?= $role === 'applicant' ? 'selected' : '' ?>>Applicant / scholar</option>
      </select>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
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
            <td><span class="badge bg-<?= $u['role_name'] === 'admin' ? 'danger' : 'secondary' ?>"><?= $u['role_name'] === 'admin' ? 'Admin' : 'Applicant' ?></span></td>
            <td><span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Deactivated' ?></span></td>
            <td><small class="text-muted"><?= $u['last_login'] ? e(date('M d, Y g:i A', strtotime($u['last_login']))) : 'Never' ?></small></td>
            <td class="text-end text-nowrap">
              <?php if ($u['role_name'] === 'admin'): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick='openUserModal(<?= js_json($u) ?>)'><i class="bi bi-pencil"></i></button>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetModal"
                      onclick='document.getElementById("resetId").value = <?= (int)$u["id"] ?>; document.getElementById("resetEmail").textContent = <?= js_json($u["email"]) ?>;'>
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
      <div class="modal-header"><h5 class="modal-title" id="userModalTitle">Add admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Full name</label><input name="full_name" id="userName" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">E-mail</label><input type="email" name="email" id="userEmail" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Contact number</label><input name="contact_number" id="userContact" class="form-control" maxlength="11"></div>
        <div class="mb-2" id="userPasswordRow"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="<?= PASSWORD_MIN_LENGTH ?>"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/user-reset') ?>" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="id" id="resetId">
      <div class="modal-header"><h5 class="modal-title">Reset password — <span id="resetEmail"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><label class="form-label">New password</label><input type="password" name="new_password" class="form-control" minlength="<?= PASSWORD_MIN_LENGTH ?>" required></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Reset</button></div>
    </form>
  </div>
</div>

<script>
function openUserModal(u) {
  document.getElementById('userId').value = u ? u.id : '';
  document.getElementById('userName').value = u ? u.full_name : '';
  document.getElementById('userEmail').value = u ? u.email : '';
  document.getElementById('userContact').value = u ? (u.contact_number || '') : '';
  document.getElementById('userPasswordRow').style.display = u ? 'none' : '';
  document.getElementById('userModalTitle').textContent = u ? 'Edit admin' : 'Add admin';
}
</script>
