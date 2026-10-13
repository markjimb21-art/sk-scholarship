<?php
declare(strict_types=1);

/** A4 picking a suggested interview slot, and the account pages: notifications and profile. */
final class ApplicantAccountController
{
    /** POST kind, schedule_id, date, start: one of the applicant's current suggestions (checked again on the server). */
    public function requestSlot(): void
    {
        require_post('applicant/dashboard');
        $app = Application::currentForApplicant((int)Applicant::current()['id']);
        if (!$app) { flash('danger', 'Please start your application first.'); redirect('applicant/dashboard'); }
        [$kind, $scheduleId, $date, $start] = array_pad(explode('|', post_str('slot')), 4, '');
        $r = SlotRequest::create($app, ['kind' => $kind, 'schedule_id' => (int)$scheduleId, 'date' => $date, 'start' => $start], (int)Auth::user()['id']);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Slot requested. The SK office will confirm it — we will e-mail you.' : $r['error']);
        redirect('applicant/dashboard');
    }

    public function cancelSlot(): void
    {
        require_post('applicant/dashboard');
        $app = Application::currentForApplicant((int)Applicant::current()['id']);
        $r = $app ? SlotRequest::cancel((int)$app['id'], (int)Auth::user()['id']) : ['ok' => false, 'error' => 'Nothing to cancel.'];
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Slot request withdrawn. You can pick another slot.' : $r['error']);
        redirect('applicant/dashboard');
    }

    public function notifications(): void
    {
        $userId = (int)Auth::user()['id'];
        if (!empty($_GET['mark_read'])) {
            Notification::markAllRead($userId);
            json_response(['ok' => true]);
        }
        $notifications = Notification::forUser($userId);
        render('applicant/notifications', compact('userId', 'notifications'), 'Notifications');
    }

    public function profile(): void
    {
        $user = Auth::user();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $name = trim($_POST['full_name'] ?? '');
            $contact = digits_only($_POST['contact_number'] ?? '');
            if ($name === '') { flash('danger', 'Full name required.'); redirect('applicant/profile'); }
            if (post_bool('new_password')) {
                if ($problems = password_problems((string)$_POST['new_password'])) { flash('danger', password_rule_message($problems)); redirect('applicant/profile'); }
                if ((string)$_POST['new_password'] !== post_str('new_password_confirm')) { flash('danger', 'The two new passwords do not match.'); redirect('applicant/profile'); }
                if (!password_verify($_POST['current_password'] ?? '', $user['password_hash'])) { flash('danger', 'Current password incorrect.'); redirect('applicant/profile'); }
                User::changePassword((int)$user['id'], (string)$_POST['new_password']);
            }
            User::updateProfile((int)$user['id'], $name, $contact);
            AuditLog::write('profile_updated', 'users', (int)$user['id'], 'Profile updated');
            flash('success', 'Profile updated.');
            redirect('applicant/profile');
        }
        render('applicant/Profile', compact('user'), 'My Profile');   // file on disk is Profile.php (case-sensitive on Linux)
    }
}
