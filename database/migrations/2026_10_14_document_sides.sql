-- Migration: front and back pictures of a document (docs/PROCESS.md §4b "Document versions").
--
-- documents        side: 'Front' or 'Back' for a two-sided document — the School ID always, the Form 138 of a 1st Year
--                  applicant. NULL = one file holding the whole document (every other document, college grades, and
--                  every file uploaded before this change, which counts as both sides).
-- term_documents   Same column for the claim/renewal School ID. Grades and Enrollment Form stay one file (NULL).
--
-- Added as the last column so the existing dump rows keep their order. Safe to run once; existing rows get NULL.

ALTER TABLE `documents`
  ADD COLUMN `side` ENUM('Front','Back') NULL DEFAULT NULL COMMENT 'Front/Back picture; NULL = one file with the whole document';

ALTER TABLE `term_documents`
  ADD COLUMN `side` ENUM('Front','Back') NULL DEFAULT NULL COMMENT 'Front/Back picture; NULL = one file with the whole document';
