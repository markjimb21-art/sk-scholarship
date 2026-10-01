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
 * 3. Daily digest (first run of each day, Settings toggle): "Your interview is today" to each applicant interviewed today,
 *    and one list of today's interviews to the admins (idempotent via system_settings.daily_digest_sent_on).
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

    // Daily digest (Settings → "Daily interview digest"): on the FIRST run of each day, every applicant interviewed today
    // gets "Your interview is today", and the admins get one list of today's interviews. Idempotent: the day is claimed in
    // system_settings.daily_digest_sent_on before anything is sent; a failure gives the day back so the next run retries.
    $today = date('Y-m-d');
    if (setting('daily_digest_enabled', '1') !== '1') {
        out('Daily digest is turned off in Settings.');
    } else {
        $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description)
                       VALUES ('daily_digest_sent_on', '', 'string', 'Last day the daily interview digest was sent')")->execute();
        $previous = (string)setting('daily_digest_sent_on', '');
        $claimDay = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'daily_digest_sent_on' AND setting_value = ?");
        $claimDay->execute([$today, $previous]);
        if ($previous === $today || $claimDay->rowCount() !== 1) {
            out('Daily digest already sent today.');
        } else {
            try {
                $day = Interview::day($today);
                $applicants = 0;
                $lines = [];
                foreach ($day as $s) {
                    $time = date('g:i A', strtotime($s['start_time'])) . '–' . date('g:i A', strtotime($s['end_time']));
                    $names = [];
                    foreach ($s['attempts'] as $at) {
                        $names[] = $at['full_name'] . ($at['attendance'] === 'Scheduled' ? '' : " ({$at['attendance']})");
                        if ($at['attendance'] !== 'Scheduled' || $at['app_status'] !== 'Interview Scheduled') continue;
                        Notification::send((int)$at['user_id'], 'Your interview is today',
                            "Your scholarship interview is today, $time at {$s['venue']}.\nApplication code: {$at['application_code']}. "
                            . 'Bring a valid ID and arrive 15 minutes early.'
                            . ((int)$at['reschedule_count'] ? "\nThis is your rescheduled interview — missing it ends your application." : ''),
                            'info', url('applicant/dashboard'), sendEmail: true);
                        $applicants++;
                    }
                    $lines[] = "• $time at {$s['venue']} ({$s['program_name']}): " . ($names ? implode(', ', $names) : 'nobody assigned')
                        . " — {$s['unmarked']} not yet marked";
                }
                if ($day) {
                    Notification::sendToAdmins('Interviews today: ' . count($day) . ' schedule(s)',
                        "Interviews on " . date('l, F j, Y') . ":\n" . implode("\n", $lines), 'info', url('admin/dashboard'), sendEmail: true);
                }
                AuditLog::write('daily_digest_sent', 'system_settings', null,
                    "Daily digest for $today: " . count($day) . " schedule(s), $applicants applicant(s) notified");
                out("Daily digest sent: " . count($day) . " schedule(s), $applicants applicant(s).");
            } catch (Throwable $e) {
                $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'daily_digest_sent_on'")->execute([$previous]);
                out('FAILED daily digest: ' . $e->getMessage());
            }
        }
    }
    exit(0);
} catch (Throwable $e) {
    error_log('send_reminders failed: ' . $e->getMessage());
    out('ERROR: ' . $e->getMessage());
    exit(1);
}
