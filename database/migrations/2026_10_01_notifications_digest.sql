-- Migration: daily interview digest + admin notifications (docs/PROCESS.md §8).
--
-- daily_digest_enabled   Settings toggle for the daily digest in bin/send_reminders.php (1 = on).
-- daily_digest_sent_on   Last day the digest was sent (Y-m-d). The script claims the day here before sending,
--                        so re-running it, or two overlapping runs, never sends the digest twice.
-- admin_event_emails     Also e-mail admins about new submissions / no-shows / slot requests (in-app is always on).
--
-- Safe to run once: only adds setting rows (INSERT IGNORE keeps any value already there).

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
  ('daily_digest_enabled', '1', 'bool', 'Send the daily interview digest'),
  ('daily_digest_sent_on', '', 'string', 'Last day the daily interview digest was sent'),
  ('admin_event_emails', '0', 'bool', 'Also e-mail admins about new submissions');
