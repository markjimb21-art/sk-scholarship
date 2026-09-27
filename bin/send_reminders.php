<?php
declare(strict_types=1);

/**
 * Interview reminder e-mails (docs/PROCESS.md §8) — run from the command line only (Windows Task Scheduler /
 * cron), e.g. every hour:
 *
 *     php bin/send_reminders.php
 *
 * Sends one reminder per interview attempt that is still waiting (attendance Scheduled) on a schedule that is
 * not cancelled, when the interview starts within the next `interview_reminder_hours` hours (setting; 0 = off).
 *
 * Idempotent: each row is CLAIMED (reminder_sent_at set) before its notification is sent, and only unclaimed rows
 * are picked up, so re-running the script — or two overlapping runs — never sends the same reminder twice.
 * Interview::updateSchedule() clears reminder_sent_at when the date, time or venue changes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// Same bootstrap as public/index.php, minus sessions, headers and the web exception handler.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Notification.php';
require_once __DIR__ . '/../app/models/Interview.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function out(string $message): void
{
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
}

try {
    $pdo = Database::conn();
    $now = date('Y-m-d H:i:s');

    $setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'interview_reminder_hours'")->fetchColumn();
    $hours = ($setting === false || $setting === null || $setting === '') ? 24 : max(0, (int)$setting);
    if ($hours === 0) {
        out('Interview reminders are turned off (interview_reminder_hours = 0).');
        exit(0);
    }

    // Window bounds come from PHP (Asia/Manila) rather than MySQL NOW(), whose timezone may differ.
    $until = date('Y-m-d H:i:s', strtotime($now) + $hours * 3600);
    $stmt = $pdo->prepare(
        "SELECT ia.id, ia.application_id, ia.reschedule_count, s.interview_date, s.start_time, s.end_time, s.venue,
                a.application_code, ap.user_id
         FROM interview_assignments ia
         JOIN interview_schedules s ON s.id = ia.schedule_id
         JOIN applications a ON a.id = ia.application_id
         JOIN applicants ap ON ap.id = a.applicant_id
         WHERE ia.attendance = 'Scheduled'
           AND a.status = 'Interview Scheduled'
           AND s.status <> 'Cancelled'
           AND ia.reminder_sent_at IS NULL
           AND TIMESTAMP(s.interview_date, s.start_time) > ?
           AND TIMESTAMP(s.interview_date, s.start_time) <= ?
         ORDER BY s.interview_date, s.start_time"
    );
    $stmt->execute([$now, $until]);

    $claim   = $pdo->prepare("UPDATE interview_assignments SET reminder_sent_at = ? WHERE id = ? AND reminder_sent_at IS NULL");
    $release = $pdo->prepare("UPDATE interview_assignments SET reminder_sent_at = NULL WHERE id = ?");
    $sent = 0;
    foreach ($stmt->fetchAll() as $row) {
        $id = (int)$row['id'];
        $claim->execute([$now, $id]);
        if ($claim->rowCount() !== 1) continue;   // another run already took it

        $message = 'Reminder: your scholarship interview is on ' . Interview::describe($row) . '.'
            . "\nBring a valid ID and arrive 15 minutes early. Application code: {$row['application_code']}."
            . ((int)$row['reschedule_count'] ? "\nThis is your rescheduled interview — missing it ends your application." : '');
        try {
            Notification::send((int)$row['user_id'], 'Interview reminder', $message, 'info', url('applicant/dashboard'), sendEmail: true);
            AuditLog::write('interview_reminder_sent', 'interview_assignments', $id,
                "Interview reminder sent for {$row['interview_date']} {$row['start_time']}");
            $sent++;
        } catch (Throwable $e) {
            $release->execute([$id]);   // let the next run retry
            out("FAILED reminder for interview #$id: " . $e->getMessage());
        }
    }
    out("Interview reminders sent: $sent (window: next $hours hour(s)).");
    exit(0);
} catch (Throwable $e) {
    error_log('send_reminders failed: ' . $e->getMessage());
    out('ERROR: ' . $e->getMessage());
    exit(1);
}
