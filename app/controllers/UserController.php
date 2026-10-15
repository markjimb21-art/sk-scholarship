<?php
declare(strict_types=1);

/** Staff accounts (admin, reviewer, treasurer): list, create, edit (role changes are audit-logged), activate, reset password. */
final class UserController
{
    public function users(): void
    {
        $q = trim(get_str('q'));
        $role = in_array($_GET['role'] ?? '', [...STAFF_ROLES, 'applicant', ...User::SHOWN_ROLES], true) ? $_GET['role'] : '';
        $users = User::search($q, $role);
        render('admin/users', compact('users', 'q', 'role') + ['pageScripts' => [asset('js/pages/users.js')]], 'Users');
    }

    /** Create or edit a STAFF account (admin, reviewer, treasurer); applicants register themselves. Role changes are audit-logged. */
    public function userSave(): void
    {
        require_post('admin/users');
        $id = post_int('id');
        $roleName = post_str('role');
        if (!in_array($roleName, STAFF_ROLES, true)) { flash('danger', 'Choose a role: admin, reviewer or treasurer.'); redirect('admin/users'); }
        $name = trim(post_str('full_name'));
        $email = strtolower(trim(post_str('email')));
        $contact = digits_only($_POST['contact_number'] ?? '');
        $password = post_str('password');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('danger', 'Name and a valid e-mail are required.'); redirect('admin/users'); }
        if (User::emailTaken($email, $id)) { flash('danger', 'That e-mail is already used.'); redirect('admin/users'); }

        if ($id > 0) {
            $oldRole = User::roleName($id);
            if (!in_array($oldRole, STAFF_ROLES, true)) { flash('danger', 'Only staff accounts are managed here.'); redirect('admin/users'); }
            if ($oldRole !== $roleName) {
                if ($id === admin_id()) { flash('danger', 'You cannot change your own role.'); redirect('admin/users'); }
                if ($oldRole === 'admin' && User::otherActiveAdmins($id) === 0) { flash('danger', 'This is the last active admin — make someone else admin first.'); redirect('admin/users'); }
            }
            User::updateStaff($id, $name, $email, $contact, $roleName);
            AuditLog::write('user_updated', 'users', $id, "Updated account $email");
            if ($oldRole !== $roleName) {
                AuditLog::write('user_role_changed', 'users', $id, "Role of $email changed from $oldRole to $roleName", ['role' => $oldRole], ['role' => $roleName, 'by' => admin_id()]);
            }
            flash('success', $oldRole !== $roleName ? "Account updated; role changed to $roleName." : 'Account updated.');
        } else {
            $v = (new Validator($_POST))->newPassword('password', 'password_confirm');
            if ($v->fails()) { flash('danger', $v->firstError()); redirect('admin/users'); }
            $newId = User::createStaff($roleName, $name, $email, $password, $contact);
            AuditLog::write('user_created', 'users', $newId, "Created $roleName account $email", null, ['role' => $roleName, 'by' => admin_id()]);
            flash('success', ucfirst($roleName) . ' account created.');
        }
        redirect('admin/users');
    }

    public function userToggle(): void
    {
        require_post('admin/users');
        $id = post_int('id');
        if ($id === admin_id()) { flash('danger', 'You cannot deactivate your own account.'); redirect('admin/users'); }
        $row = User::status($id);
        if (!$row) { flash('danger', 'User not found.'); redirect('admin/users'); }
        if ($row['is_active'] && $row['role_name'] === 'admin' && User::otherActiveAdmins($id) === 0) {
            flash('danger', 'This is the last active admin, so it can’t be deactivated.'); redirect('admin/users');
        }
        $new = $row['is_active'] ? 0 : 1;
        User::setActive($id, $new);
        AuditLog::write('user_status_changed', 'users', $id, ($new ? 'Activated' : 'Deactivated') . " {$row['email']}");
        flash('success', $new ? 'Account activated.' : 'Account deactivated.');
        redirect('admin/users');
    }

    public function userReset(): void
    {
        require_post('admin/users');
        $id = post_int('id');
        $password = post_str('new_password');
        $v = (new Validator($_POST))->newPassword('new_password', 'new_password_confirm');
        if ($v->fails()) { flash('danger', $v->firstError()); redirect('admin/users'); }
        User::resetPassword($id, $password);
        AuditLog::write('user_password_reset', 'users', $id, 'Admin reset the password');
        flash('success', 'Password reset.');
        redirect('admin/users');
    }
}
