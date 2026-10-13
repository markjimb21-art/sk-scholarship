<?php
declare(strict_types=1);

/** Settings that do something (e-mail, reminders, daily digest, suggestion window), the test e-mail, and holidays. */
final class SettingsController
{
    /** Only settings that do something: e-mail on/off, reminder hours, Send Test Email. */
    public function settings(): void
    {
        $rows = Setting::all();
        $settings = [
            'email_notifications'      => (string)($rows['email_notifications'] ?? '1'),
            'interview_reminder_hours' => (string)($rows['interview_reminder_hours'] ?? '24'),
            'daily_digest_enabled'     => (string)($rows['daily_digest_enabled'] ?? '1'),
            'daily_digest_sent_on'     => (string)($rows['daily_digest_sent_on'] ?? ''),
            'admin_event_emails'       => (string)($rows['admin_event_emails'] ?? '0'),
            'interview_suggestion_window_days' => (string)InterviewSlots::suggestionWindowDays(),
        ];
        $mailStatus = Notification::mailConfigStatus();
        $holidays = Holiday::recent();
        render('admin/settings', compact('settings', 'mailStatus', 'holidays'), 'Settings');
    }

    public function settingsSave(): void
    {
        require_post('admin/settings');
        $values = [
            'email_notifications'      => post_bool('email_notifications') ? '1' : '0',
            'interview_reminder_hours' => (string)max(0, min(168, (int)($_POST['interview_reminder_hours'] ?? 24))),
            'daily_digest_enabled'     => post_bool('daily_digest_enabled') ? '1' : '0',
            'admin_event_emails'       => post_bool('admin_event_emails') ? '1' : '0',
            'interview_suggestion_window_days' => (string)InterviewSlots::clampWindowDays((int)($_POST['interview_suggestion_window_days'] ?? InterviewSlots::WINDOW_DEFAULT_DAYS)),
        ];
        Setting::saveMany($values);
        AuditLog::write('settings_updated', 'system_settings', null, 'Settings saved', null, $values);
        flash('success', 'Settings saved.');
        redirect('admin/settings');
    }

    /** Sends a fixed test message. Never echoes SMTP credentials. */
    public function settingsTestEmail(): void
    {
        require_post('admin/settings');
        $to = strtolower(trim(post_str('test_email')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash('danger', 'Enter a valid e-mail address to send the test to.'); redirect('admin/settings'); }
        $result = Notification::sendTestEmail($to);
        AuditLog::write($result['ok'] ? 'test_email_sent' : 'test_email_failed', 'system_settings', null,
            $result['ok'] ? 'Test e-mail sent' : 'Test e-mail failed');
        flash($result['ok'] ? 'success' : 'danger', $result['ok']
            ? 'Test e-mail sent.'
            : 'The test e-mail could not be sent.' . (APP_DEBUG ? ' Check storage/logs/app.log for the SMTP error.' : ''));
        redirect('admin/settings');
    }

    /** Add a holiday (never suggested for interviews). */
    public function holidaySave(): void
    {
        require_post('admin/settings');
        $date = trim(post_str('holiday_date'));
        $name = trim(post_str('name'));
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) { flash('danger', 'Choose a valid date.'); redirect('admin/settings'); }
        if ($name === '') { flash('danger', 'Give the holiday a name.'); redirect('admin/settings'); }
        $holidayId = Holiday::add($date, $name, admin_id());
        if ($holidayId === null) { flash('danger', 'That date is already a holiday.'); redirect('admin/settings'); }
        AuditLog::write('holiday_added', 'holidays', $holidayId, "Holiday $date: $name");
        flash('success', 'Holiday added. Interview suggestions skip it.');
        redirect('admin/settings');
    }

    /** Remove a holiday (a setting, not a record — see "Data retention" in docs/PROCESS.md). */
    public function holidayDelete(): void
    {
        require_post('admin/settings');
        $id = post_int('id');
        $h = Holiday::find($id);
        if ($h) {
            Holiday::delete($id);
            AuditLog::write('holiday_removed', 'holidays', $id, "Removed holiday {$h['holiday_date']}: {$h['name']}", $h);
        }
        flash('success', 'Holiday removed.');
        redirect('admin/settings');
    }
}
