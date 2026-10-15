-- Migration: an interview schedule can be Closed (docs/PROCESS.md B3).
--
-- interview_schedules.status   new value 'Closed': no more applicants are assigned or moved onto it; attendance and
--                              scores can still be recorded. The admin sets it in Edit schedule; a schedule whose end
--                              time has passed is also shown as Closed (Interview::isClosed) without changing the row.
--
-- Safe to run once. Existing rows keep their status.

ALTER TABLE `interview_schedules`
  MODIFY `status` ENUM('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open';
