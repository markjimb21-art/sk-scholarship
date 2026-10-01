-- Migration: interview slot suggestions, holidays and applicant slot requests (docs/PROCESS.md §3a).
--
-- holidays        Dates never suggested for interviews (admins edit them under Settings).
-- slot_requests   An applicant (Qualified, or after a first no-show) picks one suggested slot: an existing Open schedule
--                 (schedule_id) or a free 2-hour block (proposed_date/start/end). It stays Pending until an admin confirms
--                 it (the existing 'assign' transition moves the application to Interview Scheduled) or declines it.
-- interview_suggestion_window_days   How many days after the application end date suggestions may go (was fixed at 60).
--
-- Safe to run once: new tables + one setting row; existing data is not touched.

CREATE TABLE `holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_date` DATE NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_holiday_date` (`holiday_date`),
  KEY `fk_holidays_created_by` (`created_by`),
  CONSTRAINT `fk_holidays_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `slot_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `schedule_id` INT UNSIGNED NULL COMMENT 'Existing Open schedule picked (NULL = a free block)',
  `proposed_date` DATE NOT NULL,
  `proposed_start` TIME NOT NULL,
  `proposed_end` TIME NOT NULL,
  `status` ENUM('Pending','Confirmed','Declined','Cancelled') NOT NULL DEFAULT 'Pending',
  `decision_note` VARCHAR(255) NULL,
  `decided_by` INT UNSIGNED NULL,
  `decided_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slot_app_status` (`application_id`,`status`),
  KEY `idx_slot_status` (`status`),
  KEY `fk_slot_schedule` (`schedule_id`),
  KEY `fk_slot_decided_by` (`decided_by`),
  CONSTRAINT `fk_slot_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_slot_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `interview_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_slot_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
  ('interview_suggestion_window_days', '60', 'int', 'Days after the application end date that interview suggestions may use');
