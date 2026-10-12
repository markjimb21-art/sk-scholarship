<?php
declare(strict_types=1);

/** Staff accounts, settings, holidays, notifications and the audit log — the write paths of AdminController. */
final class AdminWritePathsTest extends HttpTestCase
{
    public function testCreateEditDeactivateAndResetAStaffAccount(): void
    {
        $c = self::signedIn('admin@test.local');
        $c->post('/admin/user-save', ['full_name' => 'Nina New', 'email' => 'Nina.New@Test.Local', 'contact_number' => '0917-555-0001',
            'role' => 'reviewer', 'password' => 'Secret#123', 'password_confirm' => 'Secret#123'], [], [], '/admin/users');
        $this->assertSame('Reviewer account created.', self::flashAfter($c));
        $u = self::row("SELECT u.id, u.full_name, u.contact_number, u.is_active, u.email_verified, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = 'nina.new@test.local'");
        $this->assertSame(['full_name' => 'Nina New', 'contact_number' => '09175550001', 'is_active' => 1, 'email_verified' => 1, 'role_name' => 'reviewer'],
            array_diff_key($u, ['id' => 0]));

        $c->post('/admin/user-save', ['full_name' => 'Dup', 'email' => 'admin@test.local', 'role' => 'reviewer', 'password' => 'Secret#123',
            'password_confirm' => 'Secret#123'], [], [], '/admin/users');
        $this->assertSame('That e-mail is already used.', self::flashAfter($c));

        $c->post('/admin/user-save', ['id' => $u['id'], 'full_name' => 'Nina Changed', 'email' => 'nina.new@test.local', 'contact_number' => '',
            'role' => 'treasurer'], [], [], '/admin/users');
        $this->assertSame('Account updated; role changed to treasurer.', self::flashAfter($c));
        $this->assertSame('treasurer', self::value('SELECT r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$u['id']]));

        $c->post('/admin/user-toggle', ['id' => $u['id']], [], [], '/admin/users');
        $this->assertSame('Account deactivated.', self::flashAfter($c));
        $this->assertSame(0, (int)self::value('SELECT is_active FROM users WHERE id = ?', [$u['id']]));

        $c->post('/admin/user-reset', ['id' => $u['id'], 'new_password' => 'Other#4567', 'new_password_confirm' => 'Other#4567'], [], [], '/admin/users');
        $this->assertSame('Password reset.', self::flashAfter($c));
        $this->assertTrue(password_verify('Other#4567', (string)self::value('SELECT password_hash FROM users WHERE id = ?', [$u['id']])));
    }

    public function testTheLastActiveAdminIsProtected(): void
    {
        $c = self::signedIn('admin@test.local');
        $c->post('/admin/user-toggle', ['id' => 1], [], [], '/admin/users');
        $this->assertSame('You cannot deactivate your own account.', self::flashAfter($c));
        $c->post('/admin/user-save', ['id' => 1, 'full_name' => 'Ada Admin', 'email' => 'admin@test.local', 'role' => 'reviewer'], [], [], '/admin/users');
        $this->assertSame('You cannot change your own role.', self::flashAfter($c));
    }

    public function testUserSearch(): void
    {
        $c = self::signedIn('admin@test.local');
        $html = $c->get('/admin/users&q=scott&role=applicant')->body;
        $this->assertStringContainsString('scholar@test.local', $html);
        $this->assertStringNotContainsString('draft@test.local', $html);
    }

    public function testSettingsAndHolidays(): void
    {
        $c = self::signedIn('admin@test.local');
        $c->post('/admin/settings/save', ['email_notifications' => '1', 'interview_reminder_hours' => '500', 'daily_digest_enabled' => '1',
            'interview_suggestion_window_days' => '3'], [], [], '/admin/settings');
        $this->assertSame('Settings saved.', self::flashAfter($c));
        $rows = Database::conn()->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(['1', '168', '1', '0', '7'], [$rows['email_notifications'], $rows['interview_reminder_hours'],
            $rows['daily_digest_enabled'], $rows['admin_event_emails'], $rows['interview_suggestion_window_days']], 'clamped to 0–168 and 7–180');

        $date = date('Y-m-d', strtotime('+200 days'));
        $c->post('/admin/settings/holiday-save', ['holiday_date' => $date, 'name' => 'Founding Day'], [], [], '/admin/settings');
        $this->assertSame('Holiday added. Interview suggestions skip it.', self::flashAfter($c));
        $c->post('/admin/settings/holiday-save', ['holiday_date' => $date, 'name' => 'Again'], [], [], '/admin/settings');
        $this->assertSame('That date is already a holiday.', self::flashAfter($c));
        $c->post('/admin/settings/holiday-save', ['holiday_date' => '2026-02-30', 'name' => 'Bad'], [], [], '/admin/settings');
        $this->assertSame('Choose a valid date.', self::flashAfter($c));
        $hid = (int)self::value('SELECT id FROM holidays WHERE holiday_date = ?', [$date]);
        $this->assertStringContainsString('Founding Day', $c->get('/admin/settings')->body);
        $c->post('/admin/settings/holiday-delete', ['id' => $hid], [], [], '/admin/settings');
        $this->assertSame('Holiday removed.', self::flashAfter($c));
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM holidays WHERE id = ?', [$hid]));
        $this->assertSame(1, (int)self::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'holiday_removed' AND entity_id = ?", [$hid]));
    }

    public function testAuditLogFiltersAndPaging(): void
    {
        $c = self::signedIn('admin@test.local');
        $html = $c->get('/admin/audit-logs&action=release_recorded')->body;
        $this->assertStringContainsString('Released ₱5,000.00', $html);
        $this->assertStringNotContainsString('Created EduKabataan Test Program', $html);
        $this->assertStringContainsString('Created EduKabataan Test Program', $c->get('/admin/audit-logs&q=EduKabataan')->body);
        $this->assertSame(200, $c->get('/admin/audit-logs&page=99&date_from=2020-01-01&date_to=2099-12-31&user_id=1&entity_type=applications')->status);
    }

    public function testNotificationsListAndMarkRead(): void
    {
        $c = self::signedIn('admin@test.local');
        $this->assertStringContainsString('APP-TEST-0002 was submitted.', $c->get('/admin/notifications')->body);
        $c->get('/admin/notifications&mark_read=1');
        $this->assertSame(0, (int)self::value('SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND is_read = 0'));
    }

    public function testDashboardCounters(): void
    {
        // The seed: one application Submitted / Qualified / Interviewed, one pending slot request, one claim Submitted.
        $html = self::signedIn('admin@test.local')->get('/admin/dashboard')->body;
        preg_match_all('~kpi-value text-dark">(\d+)</div>\s*<div class="kpi-label">([^<]+)<~', $html, $m);
        $this->assertSame(['Applications to screen' => '1', 'Qualified, no schedule' => '1', 'Interviews today' => '0',
            'Slot requests to confirm' => '1', 'Awaiting a decision' => '1', 'Claims to verify' => '1', 'Verified, to release' => '0',
            'Renewals to review' => '0'], array_combine(array_map('html_entity_decode', $m[2]), $m[1]));
    }
}
