-- ============================================================
-- SK EDUKABATAAN: EDUCATIONAL ASSISTANCE FOR COLLEGE STUDENTS — Barangay Estefania
-- Empty schema + reference data. Structure matches docs/PROCESS.md and
-- database/sk_scholarship.sql (all migrations up to 2026_09_28_restructure.sql).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- ACCOUNTS ----------
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NULL,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    last_login DATETIME NULL,
    failed_attempts TINYINT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_email (email),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-time e-mail codes for registration and password reset (core/Otp.php).
CREATE TABLE email_otp_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    purpose ENUM('registration','password_reset') NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
    resend_available_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_purpose (user_id, purpose),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- REFERENCE LISTS ----------
CREATE TABLE puroks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purok_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Autocomplete suggestions for Step 2 (school and course names are stored as text).
CREATE TABLE schools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(200) NOT NULL,
    address VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(200) NOT NULL,
    course_code VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- PROGRAMS (B1) ----------
CREATE TABLE scholarship_programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(150) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    amount_per_semester DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash released to each scholar per semester',
    min_gpa DECIMAL(4,2) NULL COMMENT 'Minimum GWA (1.00 highest - 5.00); NULL = no limit',
    max_family_income DECIMAL(12,2) NULL,
    max_age INT NULL,
    slots_available INT DEFAULT 0,
    application_start DATE NOT NULL,
    application_end DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1 COMMENT '1 = open for applications (inside the dates)',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ACADEMIC YEARS (Releasing of budget → Academic years) ----------
-- Every academic-year list comes from here. Exactly one is current: new scholars start in its 1st Semester.
CREATE TABLE academic_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(20) NOT NULL COMMENT 'YYYY-YYYY, second year = first + 1',
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    current_flag TINYINT(1) GENERATED ALWAYS AS (IF(is_current = 1, 1, NULL)) STORED COMMENT 'At most one current year (UNIQUE)',
    created_by INT UNSIGNED NULL COMMENT 'NULL = backfilled by the migration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ay_label (label),
    UNIQUE KEY uniq_ay_current (current_flag),
    CONSTRAINT fk_ay_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- APPLICANT PROFILE (A2) ----------
CREATE TABLE applicants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE applicants_personal_information (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT UNSIGNED NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(10) NULL,
    date_of_birth DATE NOT NULL,
    complete_address TEXT NOT NULL,
    purok_id INT UNSIGNED NULL,
    contact_number VARCHAR(20) NOT NULL,
    height_cm DECIMAL(5,2) NULL,
    weight_kg DECIMAL(5,2) NULL,
    blood_type VARCHAR(5) NULL,
    religion VARCHAR(80) NULL,
    citizenship VARCHAR(50) DEFAULT 'Filipino',
    sex ENUM('Female','Male') NULL COMMENT 'Optional; analytics only',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    FOREIGN KEY (purok_id) REFERENCES puroks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE education_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT UNSIGNED NOT NULL,
    school_name VARCHAR(200) NOT NULL,
    year_level ENUM('1st Year','2nd Year','3rd Year','4th Year','5th Year','Other') NOT NULL,
    year_level_other VARCHAR(50) NULL,
    course_name VARCHAR(200) NOT NULL,
    major VARCHAR(200) NULL,
    general_average DECIMAL(4,2) NULL COMMENT 'GWA, 1.00 (highest) to 5.00',
    previous_scholarship TINYINT(1) DEFAULT 0,
    previous_scholarship_details VARCHAR(255) NULL,
    current_scholarship TINYINT(1) DEFAULT 0,
    current_scholarship_details VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE family_background (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT UNSIGNED NOT NULL UNIQUE,
    father_name VARCHAR(150) NULL,
    father_age INT UNSIGNED NULL,
    father_occupation VARCHAR(150) NULL,
    father_address TEXT NULL,
    father_contact VARCHAR(20) NULL,
    father_income DECIMAL(12,2) DEFAULT 0,
    father_status ENUM('Available','Deceased','Unknown','Not Applicable') DEFAULT 'Available',
    mother_name VARCHAR(150) NULL,
    mother_age INT UNSIGNED NULL,
    mother_occupation VARCHAR(150) NULL,
    mother_address TEXT NULL,
    mother_contact VARCHAR(20) NULL,
    mother_income DECIMAL(12,2) DEFAULT 0,
    mother_status ENUM('Available','Deceased','Unknown','Not Applicable') DEFAULT 'Available',
    total_family_income DECIMAL(12,2) DEFAULT 0,
    family_size INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- APPLICATIONS (A2-A6, B2-B5) ----------
CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_code VARCHAR(30) UNIQUE NOT NULL,
    applicant_id INT UNSIGNED NOT NULL,
    program_id INT UNSIGNED NOT NULL,
    status ENUM('Draft','Submitted','Returned','Qualified','Interview Scheduled','Interviewed',
                'Approved','Waitlisted','Rejected') NOT NULL DEFAULT 'Draft',
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL COMMENT 'Last screening decision (B2)',
    reviewed_by INT UNSIGNED NULL,
    qualified_at DATETIME NULL COMMENT 'When screening qualified it (analytics: reached Qualified)',
    decided_at DATETIME NULL COMMENT 'Final decision (B5) or rejection',
    decided_by INT UNSIGNED NULL,
    decision_remarks TEXT NULL COMMENT 'Rejection reason / decision remarks',
    waitlisted_at DATETIME NULL COMMENT 'When it was waitlisted (analytics: reached Waitlisted)',
    review_remarks TEXT NULL COMMENT 'Items to correct when Returned',
    review_remarks_at DATETIME NULL COMMENT 'When it was last Returned',
    review_remarks_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    flagged_fields TEXT NULL COMMENT 'JSON list of fields/documents flagged when returned',
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    FOREIGN KEY (decided_by) REFERENCES users(id),
    FOREIGN KEY (review_remarks_by) REFERENCES users(id),
    UNIQUE KEY uniq_app_per_program (applicant_id, program_id),
    INDEX idx_status (status),
    INDEX idx_program (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    document_type ENUM('Certificate of Residency','Form 138 / Grades','Enrollment Form','School ID','2x2 Photo') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED NULL,
    mime_type VARCHAR(100) NULL,
    status ENUM('Not Submitted','Submitted','Verified','Invalid') NOT NULL DEFAULT 'Submitted',
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    remarks TEXT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Newest version of this type for the applicant',
    superseded_at DATETIME NULL,
    invalid_reason TEXT NULL,
    invalidated_by INT UNSIGNED NULL,
    invalidated_at DATETIME NULL,
    file_removed_at DATETIME NULL COMMENT 'File deleted because it was flagged invalid; the row is kept',
    side ENUM('Front','Back') NULL DEFAULT NULL COMMENT 'Front/Back picture; NULL = one file with the whole document',
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_app (application_id),
    INDEX idx_status (status),
    INDEX idx_doc_current (document_type, is_current),
    INDEX idx_doc_app_type (application_id, document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- INTERVIEWS (B3-B4) ----------
CREATE TABLE interview_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    interview_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(200) NOT NULL,
    max_slots INT UNSIGNED DEFAULT 30 COMMENT 'Capacity',
    status ENUM('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_date (interview_date),
    INDEX idx_program (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per scheduling attempt; the application's latest row is its current assignment.
CREATE TABLE interview_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL,
    attendance ENUM('Scheduled','Attended','No-show') NOT NULL DEFAULT 'Scheduled',
    reschedule_count TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Earlier no-shows of this application when this row was created (1 = this is the one reschedule)',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reminder_sent_at DATETIME NULL COMMENT 'When the interview reminder was sent (NULL = not yet / reset after reschedule)',
    FOREIGN KEY (schedule_id) REFERENCES interview_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_schedule (schedule_id),
    INDEX idx_application (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rubric maximums live in INTERVIEW_RUBRIC (config/constants.php): 40 / 30 / 20 / 10.
CREATE TABLE interview_evaluations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL UNIQUE,
    financial_need_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    academic_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    communication_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    community_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    total_score DECIMAL(6,2) NOT NULL DEFAULT 0,
    remarks TEXT NULL,
    interviewer_id INT UNSIGNED NULL,
    evaluated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES interview_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (interviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Old → new value of key applicant fields (GWA, school, course, year level, income); shown as "History".
CREATE TABLE record_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    applicant_id INT UNSIGNED NULL,
    field VARCHAR(64) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_by INT UNSIGNED NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_history_record (table_name, record_id),
    INDEX idx_history_applicant (applicant_id, changed_at),
    CONSTRAINT fk_history_changed_by FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dates never suggested for interviews (Settings → Holidays).
CREATE TABLE holidays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_holiday_date (holiday_date),
    CONSTRAINT fk_holidays_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- An applicant's pick of a suggested interview slot; an admin confirms (assign) or declines it.
CREATE TABLE slot_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    schedule_id INT UNSIGNED NULL COMMENT 'Existing Open schedule picked (NULL = a free block)',
    proposed_date DATE NOT NULL,
    proposed_start TIME NOT NULL,
    proposed_end TIME NOT NULL,
    status ENUM('Pending','Confirmed','Declined','Cancelled') NOT NULL DEFAULT 'Pending',
    decision_note VARCHAR(255) NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slot_app_status (application_id, status),
    INDEX idx_slot_status (status),
    CONSTRAINT fk_slot_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_slot_schedule FOREIGN KEY (schedule_id) REFERENCES interview_schedules(id) ON DELETE SET NULL,
    CONSTRAINT fk_slot_decided_by FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SCHOLARS, SEMESTERS, CLAIMS, RENEWALS (A7-A9, B7-B10) ----------
CREATE TABLE scholars (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scholar_code VARCHAR(30) UNIQUE NOT NULL,
    applicant_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL UNIQUE,
    program_id INT UNSIGNED NOT NULL,
    approval_date DATE NOT NULL,
    status ENUM('Active','Ended') NOT NULL DEFAULT 'Active',
    end_reason ENUM('Graduated','Dropped out','Failed grade requirement','Did not renew') NULL,
    ended_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id),
    FOREIGN KEY (application_id) REFERENCES applications(id),
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholar_terms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scholar_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester ENUM('1st Semester','2nd Semester') NOT NULL,
    opened_via ENUM('approval','renewal') NOT NULL,
    claim_status ENUM('Waiting for Requirements','Submitted','Resubmit','Verified','Released') NOT NULL DEFAULT 'Waiting for Requirements',
    gwa DECIMAL(4,2) NULL COMMENT 'GWA read from the Grades by the admin when verifying the claim',
    remarks TEXT NULL COMMENT 'Admin remarks when asking to resubmit',
    submitted_at DATETIME NULL COMMENT 'Last time the scholar submitted the claim',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_term (scholar_id, academic_year, semester),
    INDEX idx_claim_status (claim_status),
    CONSTRAINT fk_terms_scholar FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    CONSTRAINT fk_terms_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE term_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    term_id INT UNSIGNED NOT NULL,
    purpose ENUM('claim','renewal') NOT NULL,
    type ENUM('School ID','Grades','Enrollment Form') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED NULL,
    mime_type VARCHAR(100) NULL,
    status ENUM('Submitted','Resubmit','Verified','Invalid') NOT NULL DEFAULT 'Submitted',
    remarks TEXT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_current TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Newest version of this type for the applicant',
    superseded_at DATETIME NULL,
    invalid_reason TEXT NULL,
    invalidated_by INT UNSIGNED NULL,
    invalidated_at DATETIME NULL,
    file_removed_at DATETIME NULL COMMENT 'File deleted because it was flagged invalid; the row is kept',
    side ENUM('Front','Back') NULL DEFAULT NULL COMMENT 'Front/Back picture; NULL = one file with the whole document',
    INDEX idx_term_doc (term_id, purpose, type),
    INDEX idx_term_doc_current (type, is_current),
    CONSTRAINT fk_term_documents_term FOREIGN KEY (term_id) REFERENCES scholar_terms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin-opened submission windows: 'release' = cash claim of this semester, 'renewal' = renewal INTO it.
CREATE TABLE term_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester ENUM('1st Semester','2nd Semester') NOT NULL,
    purpose ENUM('release','renewal') NOT NULL,
    opens_at DATETIME NOT NULL,
    submission_deadline DATETIME NOT NULL,
    planned_date DATE NULL COMMENT 'Planned release date (release) or semester start (renewal); information only',
    status ENUM('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open',
    notes TEXT NULL,
    deadline_reminder_sent_at DATETIME NULL COMMENT 'When the 48 h deadline reminder was sent',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_term_schedule (program_id, academic_year, semester, purpose),
    INDEX idx_deadline (status, submission_deadline),
    CONSTRAINT fk_term_schedules_program FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    CONSTRAINT fk_term_schedules_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE renewal_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scholar_id INT UNSIGNED NOT NULL,
    from_term_id INT UNSIGNED NULL COMMENT 'Semester the request was made from (NULL only for migrated history)',
    academic_year VARCHAR(20) NOT NULL COMMENT 'Target semester',
    semester ENUM('1st Semester','2nd Semester') NOT NULL,
    status ENUM('Submitted','Resubmit','Approved') NOT NULL DEFAULT 'Submitted',
    remarks TEXT NULL,
    submitted_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    year_level ENUM('1st Year','2nd Year','3rd Year','4th Year','5th Year','Other') NULL DEFAULT NULL COMMENT 'Year level for the target semester',
    year_level_other VARCHAR(50) NULL DEFAULT NULL,
    UNIQUE KEY uniq_renewal_from_term (from_term_id),
    UNIQUE KEY uniq_renewal_target (scholar_id, academic_year, semester),
    INDEX idx_renewal_status (status),
    CONSTRAINT fk_renewals_scholar FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    CONSTRAINT fk_renewals_from_term FOREIGN KEY (from_term_id) REFERENCES scholar_terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_renewals_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One release per semester (term_id unique).
CREATE TABLE scholarship_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_code VARCHAR(30) UNIQUE NOT NULL,
    scholar_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    release_date DATE NOT NULL,
    payment_method ENUM('Cash','GCash','Bank','Check') NOT NULL DEFAULT 'Cash',
    reference_number VARCHAR(100) NULL,
    staff_id INT UNSIGNED NULL COMMENT 'Admin who released it',
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_release_term (term_id),
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id),
    CONSTRAINT fk_releases_term FOREIGN KEY (term_id) REFERENCES scholar_terms(id),
    INDEX idx_scholar (scholar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- BUDGETS (B6) ----------
-- released is always re-summed from scholarship_releases (Budget::syncReleased).
CREATE TABLE budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    total_budget DECIMAL(14,2) NOT NULL,
    released DECIMAL(14,2) DEFAULT 0,
    remaining DECIMAL(14,2) GENERATED ALWAYS AS (total_budget - released) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    UNIQUE KEY uniq_budget (program_id, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- NOTIFICATIONS, AUDIT, SETTINGS ----------
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    email_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string','int','bool','json') DEFAULT 'string',
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- REFERENCE DATA
-- ============================================================
INSERT INTO roles (role_name, description) VALUES
('admin', 'SK Administrator - full access'),
('reviewer', 'Screening, interviews and decisions'),
('treasurer', 'Budget, claims, releases and renewals'),
('applicant', 'College student applicant / scholar');

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('email_notifications', '1', 'bool', 'Enable email notifications'),
('interview_reminder_hours', '24', 'int', 'Hours before interview to send reminder'),
('daily_digest_enabled', '1', 'bool', 'Send the daily interview digest'),
('daily_digest_sent_on', '', 'string', 'Last day the daily interview digest was sent'),
('admin_event_emails', '0', 'bool', 'Also e-mail admins about new submissions'),
('interview_suggestion_window_days', '60', 'int', 'Days after the application end date that interview suggestions may use');

-- Puroks / subdivisions of Barangay Estefania (address step)
INSERT INTO `puroks` VALUES (1,'Arao'),(2,'Arabay 1'),(3,'Arabay 2'),(4,'Bagong Silang'),(5,'Bethany Court'),(6,'Buena Royale'),(7,'Camelot Residences'),(8,'Camelot Village'),(9,'Camingawan Proper'),(10,'Capitol Hills Subdivision'),(11,'Celine Homes Subdivision'),(12,'City Ville Subdivision'),(13,'Country Homes Subdivision Phase 1'),(14,'Country Homes Subdivision Phase 2'),(15,'Country Homes Subdivision Phase 3'),(16,'East Homes 1'),(17,'East Homes 2'),(18,'East Homes 3'),(19,'Elsa'),(20,'Escuerdo'),(21,'Estefania Proper'),(22,'Flora'),(23,'Fortune Towne - B'),(24,'Fortune Towne Subdivision'),(25,'Goldah'),(26,'Glenwood Residences'),(27,'Greensville 1 Subdivision'),(28,'Greensville 4 Subdivision'),(29,'Jesusa Heights Subdivision'),(30,'Kaburihan'),(31,'Kasoy'),(32,'La Herencia'),(33,'Lopues Village'),(34,'Luisville Subdivision'),(35,'Mayang'),(36,'Meadows of Camelot'),(37,'Pag-asa'),(38,'Paho 1'),(39,'Paho 2'),(40,'Paraiso'),(41,'Pedring'),(42,'Pequiño'),(43,'Providence Negros'),(44,'Sagrado 2'),(45,'Sambag'),(46,'Sunshine Valley Subdivision'),(47,'The Palisades'),(48,'Villa Alexandra 1'),(49,'Villa Alexandra 2'),(50,'Villa Angeles'),(51,'Villa Estefania'),(52,'Villa Felicidad'),(53,'Villa Soledad'),(54,'Villa Villeta'),(55,'Villamar');

-- School and course suggestions (Step 2 autocomplete)
INSERT INTO `schools` VALUES (1,'Carlos Hilado Memorial State University','Talisay City, Negros Occidental',1,'2026-09-15 05:50:39'),(2,'University of Negros Occidental - Recoletos','Bacolod City',1,'2026-09-15 05:50:39'),(3,'University of St. La Salle','Bacolod City',1,'2026-09-15 05:50:39'),(4,'Central Philippine Adventist College','Bacolod City',1,'2026-09-15 05:50:39'),(5,'STI West Negros University','Bacolod City',1,'2026-09-15 05:50:39'),(6,'Bacolod City College','Bacolod City',1,'2026-09-15 05:50:39'),(7,'Negros Oriental State University','Dumaguete City',1,'2026-09-15 05:50:39'),(8,'CHMSU - Binalbagan Campus','Binalbagan, Negros Occidental',1,'2026-09-15 05:50:39'),(9,'Carlos Hilado Memorial State University - Fortune Towne Campus','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(10,'Carlos Hilado Memorial State University - Alijis Campus','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(11,'Negros Occidental Language and Information Technology Center (NOLITC)','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(12,'Colegio San Agustin - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(13,'La Consolacion College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(14,'Our Lady of Mercy College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(15,'Sacred Heart Seminary School of Philosophy','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(16,'West Negros University','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(17,'National University Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(18,'Riverside College','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(19,'John B. Lacson Colleges Foundation - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(20,'VMA Global College','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(21,'AMA Computer College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(22,'ABE International Business College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(23,'College of Arts & Sciences of Asia & the Pacific - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(24,'Mapua Malayan Digital College - Learning Hub Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(25,'Bacolod Christian College of Negros','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(26,'Convention Baptist Bible College','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00');
INSERT INTO `courses` VALUES (1,'Bachelor of Science in Information Technology','BSIT',1,'2026-09-15 05:50:39'),(2,'Bachelor of Science in Computer Science','BSCS',1,'2026-09-15 05:50:39'),(3,'Bachelor of Science in Accountancy','BSA',1,'2026-09-15 05:50:39'),(4,'Bachelor of Science in Business Administration','BSBA',1,'2026-09-15 05:50:39'),(5,'Bachelor of Science in Civil Engineering','BSCE',1,'2026-09-15 05:50:39'),(6,'Bachelor of Science in Nursing','BSN',1,'2026-09-15 05:50:39'),(7,'Bachelor of Elementary Education','BEED',1,'2026-09-15 05:50:39'),(8,'Bachelor of Secondary Education','BSED',1,'2026-09-15 05:50:39'),(9,'Bachelor of Arts in Communication','ABCOM',1,'2026-09-15 05:50:39'),(10,'Bachelor of Science in Criminology','BSCrim',1,'2026-09-15 05:50:39');

SET FOREIGN_KEY_CHECKS = 1;
