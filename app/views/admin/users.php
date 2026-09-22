<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Users</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> user(s)</p>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal"
          onclick="resetUserForm()">
    <i class="bi bi-person-plus"></i> Add User
  </button>
</div>

<form method="GET" action="<?= url('admin/users') ?>" class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-6"><input name="q" class="form-control" placeholder="Search name or email…" value="<?= e($filters['q']) ?>"></div>
      <div class="col-md-3">
        <select name="role" class="form-select">
          <option value="">All Roles</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r['role_name']) ?>" <?= $filters['role'] === $r['role_name'] ? 'selected' : '' ?>>
              <?= e(ucfirst($r['role_name'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
        <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$users): ?><tr><td colspan="6" class="text-center text-muted py-4">No users.</td></tr><?php endif; ?>
        <?php foreach ($users as $u):
          $roleBadge = ['admin'=>'danger','staff'=>'warning','official'=>'info','applicant'=>'secondary'][$u['role_name']] ?? 'secondary';
        ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($u['full_name']) ?></div>
              <?php if ($u['is_applicant']): ?><small class="badge bg-light text-dark">Applicant profile</small><?php endif; ?>
            </td>
            <td><?= e($u['email']) ?></td>
            <td><span class="badge bg-<?= $roleBadge ?>"><?= e(ucfirst($u['role_name'])) ?></span></td>
            <td>
              <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td><small class="text-muted"><?= $u['last_login'] ? e(date('M d, Y g:i A', strtotime($u['last_login']))) : 'Never' ?></small></td>
            <td class="text-end">
              <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                  <i class="bi bi-three-dots"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item small" href="#"
                       onclick='editUser(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>);return false;'>
                      <i class="bi bi-pencil"></i> Edit
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item small" href="#"
                       onclick='openResetModal(<?= (int)$u["id"] ?>, <?= json_encode($u["email"], JSON_HEX_APOS|JSON_HEX_QUOT) ?>);return false;'>
                      <i class="bi bi-key"></i> Reset Password
                    </a>
                  </li>
                  <li>
                    <form method="POST" action="<?= url('admin/user-toggle') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="dropdown-item small">
                        <i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <form method="POST" action="<?= url('admin/user-delete') ?>" onsubmit="return confirm('Delete this user permanently?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="dropdown-item small text-danger"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer bg-white">
      <ul class="pagination mb-0 justify-content-center">
        <?php for ($i = 1; $i <= $pages; $i++):
          $qs = http_build_query(array_merge($filters, ['page' => $i])); ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= url('admin/users&' . $qs) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/user-save') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="user-id">
      <div class="modal-header">
        <h5 class="modal-title" id="user-modal-title">Add User</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Full Name *</label>
          <input name="full_name" id="user-name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email *</label>
          <input type="email" name="email" id="user-email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role *</label>
          <select name="role_id" id="user-role" class="form-select" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= (int)$r['id'] ?>"><?= e(ucfirst($r['role_name'])) ?> — <?= e($r['description'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Contact Number</label>
          <input name="contact_number" id="user-contact" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label" id="user-pass-label">Password *</label>
          <input type="password" name="password" id="user-password" class="form-control"
                 minlength="<?= PASSWORD_MIN_LENGTH ?>" placeholder="<?= PASSWORD_MIN_LENGTH ?>+ characters">
          <div class="form-text" id="user-pass-hint">Minimum <?= PASSWORD_MIN_LENGTH ?> characters.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="<?= url('admin/user-reset') ?>" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="reset-id">
      <div class="modal-header">
        <h5 class="modal-title">Reset Password</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">Setting a new password for <strong id="reset-email"></strong></p>
        <label class="form-label">New Password *</label>
        <input type="password" name="new_password" class="form-control" minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
        <div class="form-text">Minimum <?= PASSWORD_MIN_LENGTH ?> characters.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function resetUserForm() {
  document.getElementById('user-modal-title').textContent = 'Add User';
  document.getElementById('user-id').value = '';
  document.getElementById('user-name').value = '';
  document.getElementById('user-email').value = '';
  document.getElementById('user-role').value = '';
  document.getElementById('user-contact').value = '';
  document.getElementById('user-password').value = '';
  document.getElementById('user-password').required = true;
  document.getElementById('user-pass-label').textContent = 'Password *';
  document.getElementById('user-pass-hint').textContent = 'Minimum <?= PASSWORD_MIN_LENGTH ?> characters.';
}
function editUser(u) {
  document.getElementById('user-modal-title').textContent = 'Edit User';
  document.getElementById('user-id').value = u.id;
  document.getElementById('user-name').value = u.full_name;
  document.getElementById('user-email').value = u.email;
  document.getElementById('user-role').value = u.role_id;
  document.getElementById('user-contact').value = u.contact_number || '';
  document.getElementById('user-password').value = '';
  document.getElementById('user-password').required = false;
  document.getElementById('user-pass-label').textContent = 'Password (leave blank to keep current)';
  document.getElementById('user-pass-hint').textContent = 'Only fill this if you want to change it.';
  new bootstrap.Modal(document.getElementById('userModal')).show();
}
function openResetModal(id, email) {
  document.getElementById('reset-id').value = id;
  document.getElementById('reset-email').textContent = email;
  new bootstrap.Modal(document.getElementById('resetModal')).show();
}
</script>