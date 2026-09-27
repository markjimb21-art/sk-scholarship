-- Migration: track when a scheduled reminder was sent, so bin/send_reminders.php never sends one twice.
--
-- interview_assignments.reminder_sent_at is cleared by the app when an interview is rescheduled
-- (Interview::updateSchedule / updateAssignmentStatus), so the applicant gets a reminder for the new time.
-- scholarship_renewals.reminder_sent_at: one "still For Review" reminder per renewal record.
--
-- Safe to run once: only adds nullable columns, does not touch existing data.

ALTER TABLE `interview_assignments`
  ADD COLUMN `reminder_sent_at` DATETIME NULL
    COMMENT 'When the interview reminder was sent (NULL = not yet / reset after reschedule)'
    AFTER `confirmed_at`;

ALTER TABLE `scholarship_renewals`
  ADD COLUMN `reminder_sent_at` DATETIME NULL
    COMMENT 'When the "renewal still under review" reminder was sent'
    AFTER `evaluated_at`;
