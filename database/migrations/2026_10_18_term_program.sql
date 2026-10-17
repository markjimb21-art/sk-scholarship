-- Migration: a semester belongs to a program (docs/CHANGE_PLAN_5.md §3) — returning scholars renew into the new
-- academic year's program, so a scholar's program can change from one year to the next.
--
-- scholar_terms.program_id     the program this semester belongs to: its budget, claim windows and seat. Backfilled
--                              from scholars.program_id, which is what every query used until now.
-- scholar_terms.opened_via     + 'continuation': the 2nd Semester opens by itself when the 1st Semester's cash is
--                              released (decision D3 — no renewal between the two semesters of one academic year).
-- renewal_requests.program_id  the program a renewal goes INTO (the owner of the renewal window). Backfilled from
--                              scholars.program_id: until now every renewal stayed in the scholar's program.
-- scholars.program_id          unchanged, now documented as the ORIGIN program (the one that first approved the scholar).
-- 2nd Semesters opened         Active scholars whose 1st Semester is already Released with nothing after it get their
--                              2nd Semester (continuation), since they can no longer renew into it (decision D11).
-- uniq_program_year            at most one program per academic year (decision D1).
--
-- New columns are added last, so existing dump rows keep their column order.
--
-- BACK UP THE DATABASE FIRST. Safe to run once. Run this check before; every row it returns is an academic year with
-- more than one program, which the last statement refuses — change one program's academic year first:
--   SELECT academic_year, GROUP_CONCAT(program_name SEPARATOR ' | ') AS programs
--   FROM scholarship_programs GROUP BY academic_year HAVING COUNT(*) > 1;

ALTER TABLE `scholar_terms`
  ADD COLUMN `program_id` INT UNSIGNED NULL;

UPDATE `scholar_terms` t JOIN `scholars` s ON s.id = t.scholar_id SET t.program_id = s.program_id;

ALTER TABLE `scholar_terms`
  MODIFY `program_id` INT UNSIGNED NOT NULL COMMENT 'Program this semester belongs to: its budget, claim windows and seat',
  MODIFY `opened_via` ENUM('approval','renewal','continuation') NOT NULL COMMENT 'continuation = 2nd Semester opened by the 1st Semester release',
  ADD INDEX `idx_terms_program_year` (`program_id`, `academic_year`),
  ADD CONSTRAINT `fk_terms_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`);

ALTER TABLE `renewal_requests`
  ADD COLUMN `program_id` INT UNSIGNED NULL;

UPDATE `renewal_requests` rr JOIN `scholars` s ON s.id = rr.scholar_id SET rr.program_id = s.program_id;

ALTER TABLE `renewal_requests`
  MODIFY `program_id` INT UNSIGNED NOT NULL COMMENT 'Program the renewal goes INTO (owner of the renewal window)',
  ADD INDEX `idx_renewals_program` (`program_id`, `status`),
  ADD CONSTRAINT `fk_renewals_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`);

ALTER TABLE `scholars`
  MODIFY `program_id` INT UNSIGNED NOT NULL
    COMMENT 'Origin program: the one that first approved the scholar. Current program = program of the latest semester';

INSERT INTO `scholar_terms` (scholar_id, program_id, academic_year, semester, opened_via, claim_status)
SELECT t.scholar_id, t.program_id, t.academic_year, '2nd Semester', 'continuation', 'Waiting for Requirements'
FROM `scholar_terms` t JOIN `scholars` s ON s.id = t.scholar_id
WHERE s.status = 'Active' AND t.semester = '1st Semester' AND t.claim_status = 'Released'
  AND NOT EXISTS (SELECT 1 FROM `scholar_terms` l WHERE l.scholar_id = t.scholar_id
                  AND (l.academic_year > t.academic_year OR (l.academic_year = t.academic_year AND l.semester > t.semester)))
  AND NOT EXISTS (SELECT 1 FROM `renewal_requests` rr WHERE rr.from_term_id = t.id);

ALTER TABLE `scholarship_programs`
  ADD UNIQUE KEY `uniq_program_year` (`academic_year`);
