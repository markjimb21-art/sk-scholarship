<?php
declare(strict_types=1);

/**
 * Scheduled reminders — run from the command line only (Windows Task Scheduler / cron), e.g. every hour:
 *
 *     php bin/send_reminders.php
 *
 * 1. Interview reminders: every interview_assignments row with status Scheduled/Confirmed, on a schedule that is
 *    not Cancelled, whose start (interview_date + start_time) falls within the next `interview_reminder_hours`
 *    hours (system setting; 0 disables these reminders).
 * 2. Renewal reminders: Active scholars with a renewal record still "For Review".
 *
 * Idempotent: each row is CLAIMED (reminder_sent_at set) before its notification is sent, and only unclaimed rows
 * are picked up, so re-running the script — or two overlapping runs — never sends the same reminder twice.
 * Interview::updateSchedule / updateAssignmentStatus clear reminder_sent_at when an interview is rescheduled.
 * See README.md ("Scheduled reminders") for scheduling instructions.
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

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function out(string $message): void
{
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
}

/** Claim a row (set reminder_sent_at if still NULL). True only for the run that claimed it. */
function claim(PDO $pdo, string $table, int $id, string $now): bool
{
    $stmt = $pdo->prepare("UPDATE $table SET reminder_sent_at = ? WHERE id = ? AND reminder_sent_at IS NULL");
    $stmt->execute([$now, $id]);
    return $stmt->rowCount() === 1;
}

/** Undo a claim when sending failed, so the next run retries. */
function release(PDO $pdo, string $table, int $id): void
{
    $pdo->prepare("UPDATE $table SET reminder_sent_at = NULL WHERE id = ?")->execute([$id]);
}

function sendInterviewReminders(PDO $pdo, string $now): int
{
    $setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'interview_reminder_hours'")->fetchColumn();
    $hours = ($setting === false || $setting === null || $setting === '') ? 24 : max(0, (int)$setting);
    if ($hours === 0) {
        out('Interview reminders disabled (interview_reminder_hours = 0).');
        return 0;
    }

    // Window bounds are computed in PHP (Asia/Manila) rather than with MySQL NOW(), whose timezone may differ.
    $until = date('Y-m-d H:i:s', strtotime($now) + $hours * 3600);
    $stmt = $pdo->prepare(
        "SELECT ia.id, ia.instructions, s.interview_date, s.start_time, s.end_time, s.venue, s.interviewer,
                s.remarks AS schedule_remarks, a.application_code, ap.user_id
         FROM interview_assignments ia
         JOIN interview_schedules s ON s.id = ia.schedule_id
         JOIN applications a ON a.id = ia.application_id
         JOIN applicants ap ON ap.id = a.applicant_id
         WHERE ia.assignment_status IN ('Scheduled','Confirmed')
           AND s.status <> 'Cancelled'
           AND ia.reminder_sent_at IS NULL
           AND TIMESTAMP(s.interview_date, s.start_time) > ?
           AND TIMESTAMP(s.interview_date, s.start_time) <= ?
         ORDER BY s.interview_date, s.start_time"
    );
    $stmt->execute([$now, $until]);

    $sent = 0;
    foreach ($stmt->fetchAll() as $row) {
        $id = (int)$row['id'];
        if (!claim($pdo, 'interview_assignments', $id, $now)) continue; // another run got it

        $when = date('l, F j, Y', strtotime($row['interview_date'])) . ' from '
              . date('g:i A', strtotime($row['start_time'])) . ' to ' . date('g:i A', strtotime($row['end_time']));
        $lines = [
            "This is a reminder of your scholarship interview on $when.",
            'Venue: ' . $row['venue'],
        ];
        if (!empty($row['interviewer']))      $lines[] = 'Interviewer: ' . $row['interviewer'];
        if (!empty($row['instructions']))     $lines[] = 'Instructions: ' . $row['instructions'];
        if (!empty($row['schedule_remarks'])) $lines[] = 'Notes: ' . $row['schedule_remarks'];
        $lines[] = 'Please arrive on time and bring a valid ID. Application code: ' . $row['application_code'] . '.';

        try {
            Notification::send((int)$row['user_id'], 'Interview Reminder 📅', implode("\n", $lines),
                'info', url('applicant/interview'), sendEmail: true);
            AuditLog::write('interview_reminder_sent', 'interview_assignments', $id,
                "Interview reminder sent for {$row['interview_date']} {$row['start_time']}");
            $sent++;
        } catch (Throwable $e) {
            release($pdo, 'interview_assignments', $id);
            out("FAILED interview reminder for assignment #$id: " . $e->getMessage());
        }
    }
    out("Interview reminders sent: $sent (window: next $hours hour(s)).");
    return $sent;
}

function sendRenewalReminders(PDO $pdo, string $now): int
{
    $stmt = $pdo->query(
        "SELECT r.id, r.academic_year, r.semester, s.scholar_code, ap.user_id
         FROM scholarship_renewals r
         JOIN scholars s ON s.id = r.scholar_id
         JOIN applicants ap ON ap.id = s.applicant_id
         WHERE r.status = 'For Review' AND s.status = 'Active' AND r.reminder_sent_at IS NULL
         ORDER BY r.id"
    );

    $sent = 0;
    foreach ($stmt->fetchAll() as $row) {
        $id = (int)$row['id'];
        if (!claim($pdo, 'scholarship_renewals', $id, $now)) continue;

        $term = trim($row['academic_year'] . ' ' . ($row['semester'] ?? ''));
        $message = "Your scholarship renewal for $term is still under review by the SK office. "
                 . 'Please make sure your latest grades, enrollment form and certificate of residency have been submitted '
                 . "so your renewal can be completed. Scholar ID: {$row['scholar_code']}.";
        try {
            Notification::send((int)$row['user_id'], 'Renewal Reminder', $message,
                'warning', url('applicant/scholarship'), sendEmail: true);
            AuditLog::write('renewal_reminder_sent', 'scholarship_renewals', $id, "Renewal reminder sent for $term");
            $sent++;
        } catch (Throwable $e) {
            release($pdo, 'scholarship_renewals', $id);
            out("FAILED renewal reminder for renewal #$id: " . $e->getMessage());
        }
    }
    out("Renewal reminders sent: $sent.");
    return $sent;
}

try {
    $pdo = Database::conn();
    $now = date('Y-m-d H:i:s');
    sendInterviewReminders($pdo, $now);
    sendRenewalReminders($pdo, $now);
    exit(0);
} catch (Throwable $e) {
    error_log('send_reminders failed: ' . $e->getMessage());
    out('ERROR: ' . $e->getMessage());
    exit(1);
}
