/**
 * Staff accounts page: the Add / Edit modal (data-user-modal: empty for Add, the account as JSON for Edit) and the
 * Reset password modal (data-reset-id / data-reset-email on each key button).
 */
(function () {
  'use strict';

  function openUserModal(u) {
    document.getElementById('userId').value = u ? u.id : '';
    document.getElementById('userName').value = u ? u.full_name : '';
    document.getElementById('userEmail').value = u ? u.email : '';
    document.getElementById('userContact').value = u ? (u.contact_number || '') : '';
    document.getElementById('userPasswordRow').style.display = u ? 'none' : '';
    document.getElementById('userRole').value = u ? u.role_name : 'reviewer';
    document.getElementById('userModalTitle').textContent = u ? 'Edit staff account' : 'Add staff';
  }

  document.querySelectorAll('[data-user-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { openUserModal(btn.dataset.userModal ? JSON.parse(btn.dataset.userModal) : null); });
  });
  document.querySelectorAll('[data-reset-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('resetId').value = btn.dataset.resetId;
      document.getElementById('resetEmail').textContent = btn.dataset.resetEmail;
    });
  });
})();
