<?php
declare(strict_types=1);

/**
 * bin/send_reminders.php, run for real (as Task Scheduler would) against the test database: each reminder goes out once,
 * and a second run sends nothing (each row is claimed before its notification is sent).
 */
final class ReminderScriptTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::rebuild();
        $pdo = Database::conn();
        // Sky's interview (assignment 6 on schedule 4) moves to two hours from now → inside the 24-hour reminder window.
        $pdo->exec("UPDATE interview_schedules SET interview_date = DATE(NOW() + INTERVAL 2 HOUR), start_time = TIME(NOW() + INTERVAL 2 HOUR),
                    end_time = TIME(NOW() + INTERVAL 3 HOUR) WHERE id = 4");
        // The open 2nd-semester claim window now closes in 24 hours, and Scott hasn't submitted → one deadline reminder.
        $pdo->exec("UPDATE term_schedules SET submission_deadline = NOW() + INTERVAL 24 HOUR WHERE purpose = 'release' AND semester = '2nd Semester'");
        $pdo->exec("UPDATE scholar_terms SET claim_status = 'Waiting for Requirements', submitted_at = NULL WHERE id = 2");
        $pdo->exec("UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'daily_digest_enabled'");
    }

    public static function tearDownAfterClass(): void
    {
        self::rebuild();
    }

    /** Run the script in its own process with the test environment; returns [exit code, output]. */
    private static function runScript(): array
    {
        $env = array_merge(getenv(), [
            'DB_NAME' => TestDatabase::NAME, 'APP_ENV' => 'testing', 'STORAGE_PATH' => getenv('STORAGE_PATH'),
            'MAIL_HOST' => '127.0.0.1', 'MAIL_PORT' => '9', 'MAIL_USER' => '',
        ]);
        $p = proc_open([PHP_BINARY, BASE_PATH . '/bin/send_reminders.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, $env);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($p), $out];
    }

    public function testEachReminderIsSentOnce(): void
    {
        [$code, $out] = self::runScript();
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Interview reminders sent: 1 (window: next 24 hour(s)).', $out);
        $this->assertStringContainsString('Deadline reminders sent: 1 window(s), 1 scholar(s) (deadline within 48 hours).', $out);
        // The moved interview is in today's digest only if "two hours from now" is still today.
        $today = (int)self::value('SELECT DATE(start_at) = CURDATE() FROM (SELECT TIMESTAMP(interview_date, start_time) AS start_at FROM interview_schedules WHERE id = 4) s');
        $this->assertStringContainsString("Daily digest sent: $today schedule(s), $today applicant(s).", $out);
        $this->assertNotNull(self::value('SELECT reminder_sent_at FROM interview_assignments WHERE id = 6'));
        $this->assertSame(1, (int)self::value("SELECT COUNT(*) FROM notifications WHERE user_id = 8 AND title = 'Interview reminder'"));
        $this->assertSame(1, (int)self::value("SELECT COUNT(*) FROM notifications WHERE user_id = 11 AND title LIKE 'Claim reminder%'"));
        $this->assertSame(date('Y-m-d'), self::value("SELECT setting_value FROM system_settings WHERE setting_key = 'daily_digest_sent_on'"));

        [$code, $again] = self::runScript();
        $this->assertSame(0, $code, $again);
        $this->assertStringContainsString('Interview reminders sent: 0', $again);
        $this->assertStringContainsString('Deadline reminders sent: 0 window(s)', $again);
        $this->assertStringContainsString('Daily digest already sent today.', $again);
        $this->assertSame(1, (int)self::value("SELECT COUNT(*) FROM notifications WHERE user_id = 8 AND title = 'Interview reminder'"));
    }
}
