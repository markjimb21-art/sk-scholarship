<?php
declare(strict_types=1);

/**
 * Reminder e-mails (docs/PROCESS.md §8) — run from the command line only (Windows Task Scheduler / cron), e.g. every hour:
 *
 *     php bin/send_reminders.php
 *
 * 1. Interview reminders: one per interview attempt that is still waiting (attendance Scheduled) on a schedule that is
 *    not cancelled, when the interview starts within the next `interview_reminder_hours` hours (setting; 0 = off).
 * 2. Deadline reminders: one per open claim/renewal window whose deadline is within TERM_SCHEDULE_REMINDER_HOURS (48),
 *    sent to every scholar of that window who hasn't submitted yet.
 *
 * Idempotent: each row is CLAIMED (reminder_sent_at / deadline_reminder_sent_at set) before its notification is sent,
 * and only unclaimed rows are picked up, so re-running the script — or two overlapping runs — never sends the same
 * reminder twice. Interview::updateSchedule() and TermSchedule::save() clear the column when the time/deadline changes.
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
require_once __DIR__ . '/../app/models/Program.php';
require_once __DIR__ . '/../app/models/Budget.php';
require_once __DIR__ . '/../app/models/Term.php';
require_once __DIR__ . '/../app/models/TermSchedule.php';

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
    // interview_reminder_hours = 0 turns interview reminders off (deadline reminders below still run).
    $stmt->execute($hours === 0 ? [$now, $now] : [$now, $until]);

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
    out($hours === 0 ? 'Interview reminders are turned off (interview_reminder_hours = 0).'
                     : "Interview reminders sent: $sent (window: next $hours hour(s)).");

    // Claim (release) and renewal windows: one reminder TERM_SCHEDULE_REMINDER_HOURS before the deadline to every
    // scholar who hasn't submitted yet. The window is claimed (deadline_reminder_sent_at) before sending, so re-runs and
    // overlapping runs never repeat it; editing the deadline clears it (TermSchedule::save).
    $claimSchedule   = $pdo->prepare("UPDATE term_schedules SET deadline_reminder_sent_at = ? WHERE id = ? AND deadline_reminder_sent_at IS NULL");
    $releaseSchedule = $pdo->prepare("UPDATE term_schedules SET deadline_reminder_sent_at = NULL WHERE id = ?");
    $windows = 0;
    $scholars = 0;
    foreach (TermSchedule::dueForReminder($now, TERM_SCHEDULE_REMINDER_HOURS) as $row) {
        $claimSchedule->execute([$now, (int)$row['id']]);
        if ($claimSchedule->rowCount() !== 1) continue;
        try {
            $scholars += TermSchedule::notifyAffected($row, 'reminder');
            $windows++;
        } catch (Throwable $e) {
            $releaseSchedule->execute([(int)$row['id']]);
            out("FAILED deadline reminder for window #{$row['id']}: " . $e->getMessage());
        }
    }
    out("Deadline reminders sent: $windows window(s), $scholars scholar(s) (deadline within " . TERM_SCHEDULE_REMINDER_HOURS . ' hours).');
    exit(0);
} catch (Throwable $e) {
    error_log('send_reminders failed: ' . $e->getMessage());
    out('ERROR: ' . $e->getMessage());
    exit(1);
}
