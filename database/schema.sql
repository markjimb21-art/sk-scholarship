-- ============================================================
-- SK SCHOLARSHIP MANAGEMENT INFORMATION SYSTEM
-- Barangay Estefania
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- ROLES & USERS ----------
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (role_name, description) VALUES
('admin', 'SK Administrator - full access'),
('staff', 'SK Staff - limited administrative access'),
('official', 'SK Official / Decision Maker - view analytics'),
('applicant', 'College student applicant');

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NULL,
    profile_picture VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    verify_token VARCHAR(64) NULL,
    reset_token VARCHAR(64) NULL,
    reset_expires DATETIME NULL,
    last_login DATETIME NULL,
    failed_attempts TINYINT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_email (email),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- GEOGRAPHY ----------
CREATE TABLE barangays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO barangays (name, city, province) VALUES
('Barangay Estefania', 'Bacolod City', 'Negros Occidental');

CREATE TABLE puroks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barangay_id INT UNSIGNED NOT NULL,
    purok_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    INDEX idx_barangay (barangay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ACADEMIC REFERENCE ----------
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

-- ---------- SCHOLARSHIP PROGRAMS ----------
CREATE TABLE scholarship_programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NULL,
    min_gpa DECIMAL(4,2) NULL,
    max_family_income DECIMAL(12,2) NULL,
    max_age INT NULL,
    citizenship_required VARCHAR(50) DEFAULT 'Filipino',
    required_residency VARCHAR(100) DEFAULT 'Barangay Estefania',
    budget_amount DECIMAL(14,2) DEFAULT 0,
    slots_available INT DEFAULT 0,
    application_start DATE NOT NULL,
    application_end DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- APPLICANTS ----------
CREATE TABLE applicants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    application_code VARCHAR(30) UNIQUE,
    is_resident TINYINT(1) DEFAULT 0,
    residency_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_appcode (application_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE applicants_personal_information (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT UNSIGNED NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(10) NULL,
    date_of_birth DATE NOT NULL,
    age INT UNSIGNED NULL,
    civil_status ENUM('Single','Married','Widowed','Separated','Annulled') DEFAULT 'Single',
    complete_address TEXT NOT NULL,
    purok_id INT UNSIGNED NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    height_cm DECIMAL(5,2) NULL,
    weight_kg DECIMAL(5,2) NULL,
    blood_type VARCHAR(5) NULL,
    religion VARCHAR(80) NULL,
    citizenship VARCHAR(50) DEFAULT 'Filipino',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    FOREIGN KEY (purok_id) REFERENCES puroks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE education_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NULL,
    school_name VARCHAR(200) NOT NULL,
    year_level ENUM('1st Year','2nd Year','3rd Year','4th Year','5th Year','Other') NOT NULL,
    year_level_other VARCHAR(50) NULL,
    course_id INT UNSIGNED NULL,
    course_name VARCHAR(200) NOT NULL,
    previous_scholarship TINYINT(1) DEFAULT 0,
    previous_scholarship_details VARCHAR(255) NULL,
    current_scholarship TINYINT(1) DEFAULT 0,
    current_scholarship_details VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
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
    combined_income DECIMAL(12,2) DEFAULT 0,
    total_family_income DECIMAL(12,2) DEFAULT 0,
    family_size INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- APPLICATIONS ----------
CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_code VARCHAR(30) UNIQUE NOT NULL,
    applicant_id INT UNSIGNED NOT NULL,
    program_id INT UNSIGNED NOT NULL,
    status ENUM(
        'Draft','Submitted','Under Initial Review','Incomplete',
        'Documents Under Review','Documents Verified','For Interview',
        'Interview Scheduled','Interview Completed','For Final Evaluation',
        'Approved','Rejected','Waitlisted'
    ) DEFAULT 'Draft',
    eligibility_score DECIMAL(8,2) NULL,
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decided_by INT UNSIGNED NULL,
    decision_remarks TEXT NULL,
    admin_override TINYINT(1) DEFAULT 0,
    override_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    FOREIGN KEY (decided_by) REFERENCES users(id),
    UNIQUE KEY uniq_app_per_program (applicant_id, program_id),
    INDEX idx_status (status),
    INDEX idx_program (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- DOCUMENTS ----------
CREATE TABLE documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    document_type ENUM('Profile Picture','Certificate of Residency','Form 138','Enrollment Form','School ID') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED NULL,
    mime_type VARCHAR(100) NULL,
    status ENUM('Not Submitted','Submitted','Under Review','Verified','Rejected','Resubmission Required') DEFAULT 'Submitted',
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    remarks TEXT NULL,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_app (application_id),
    INDEX idx_status (status),
    UNIQUE KEY uniq_doc_per_app (application_id, document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- INTERVIEW SCHEDULES ----------
CREATE TABLE interview_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    interview_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(200) NOT NULL,
    interviewer VARCHAR(255) NOT NULL,
    max_slots INT UNSIGNED DEFAULT 30,
    status ENUM('Open','Full','Cancelled','Completed') DEFAULT 'Open',
    remarks TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_date (interview_date),
    INDEX idx_program (program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE interview_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL UNIQUE,
    assignment_status ENUM('For Interview','Scheduled','Confirmed','Completed','No Show','Rescheduled','Cancelled') DEFAULT 'Scheduled',
    instructions TEXT NULL,
    assigned_by INT UNSIGNED NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    FOREIGN KEY (schedule_id) REFERENCES interview_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    INDEX idx_schedule (schedule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE interview_evaluations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL UNIQUE,
    financial_need_score DECIMAL(5,2) DEFAULT 0,
    academic_score DECIMAL(5,2) DEFAULT 0,
    motivation_score DECIMAL(5,2) DEFAULT 0,
    community_score DECIMAL(5,2) DEFAULT 0,
    purpose_score DECIMAL(5,2) DEFAULT 0,
    overall_score DECIMAL(5,2) DEFAULT 0,
    total_score DECIMAL(6,2) DEFAULT 0,
    max_possible_score DECIMAL(6,2) DEFAULT 100,
    rating DECIMAL(5,2) NULL,
    result ENUM('Recommended','Recommended with Conditions','For Further Review','Not Recommended','No Show','Rescheduled') NULL,
    recommendation TEXT NULL,
    interview_remarks TEXT NULL,
    additional_notes TEXT NULL,
    interviewer_id INT UNSIGNED NULL,
    evaluated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES interview_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (interviewer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SCHOLARS ----------
CREATE TABLE scholars (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scholar_code VARCHAR(30) UNIQUE NOT NULL,
    applicant_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL UNIQUE,
    program_id INT UNSIGNED NOT NULL,
    approval_date DATE NOT NULL,
    status ENUM('Active','On Probation','Suspended','Graduated','Withdrawn','Disqualified') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id),
    FOREIGN KEY (application_id) REFERENCES applications(id),
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE academic_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scholar_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    year_level VARCHAR(30) NOT NULL,
    course VARCHAR(200) NULL,
    school VARCHAR(200) NULL,
    gpa DECIMAL(4,2) NULL,
    academic_standing ENUM('Excellent','Very Good','Good','Satisfactory','Passing','Failing','Probationary') NULL,
    remarks TEXT NULL,
    recorded_by INT UNSIGNED NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_scholar_year (scholar_id, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_code VARCHAR(30) UNIQUE NOT NULL,
    scholar_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    release_date DATE NOT NULL,
    payment_method ENUM('Cash','Check','Bank Transfer','GCash','Other') DEFAULT 'Cash',
    reference_number VARCHAR(100) NULL,
    recipient_confirmed TINYINT(1) DEFAULT 0,
    confirmed_at DATETIME NULL,
    staff_id INT UNSIGNED NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id),
    INDEX idx_scholar (scholar_id),
    INDEX idx_year (academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scholarship_renewals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scholar_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NULL,
    gpa DECIMAL(4,2) NULL,
    residency_verified TINYINT(1) DEFAULT 0,
    enrollment_verified TINYINT(1) DEFAULT 0,
    documents_complete TINYINT(1) DEFAULT 0,
    status ENUM('Eligible for Renewal','For Review','Not Eligible','Renewed','Renewal Denied') DEFAULT 'For Review',
    remarks TEXT NULL,
    evaluated_by INT UNSIGNED NULL,
    evaluated_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scholar_id) REFERENCES scholars(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluated_by) REFERENCES users(id),
    UNIQUE KEY uniq_renewal (scholar_id, academic_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- BUDGETS ----------
CREATE TABLE budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    program_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    total_budget DECIMAL(14,2) NOT NULL,
    allocated DECIMAL(14,2) DEFAULT 0,
    released DECIMAL(14,2) DEFAULT 0,
    remaining DECIMAL(14,2) GENERATED ALWAYS AS (total_budget - released) STORED,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES scholarship_programs(id),
    UNIQUE KEY uniq_budget (program_id, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- NOTIFICATIONS ----------
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

-- ---------- AUDIT LOGS ----------
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

-- ---------- SYSTEM SETTINGS ----------
CREATE TABLE system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('string','int','bool','json') DEFAULT 'string',
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'SK Scholarship MIS - Barangay Estefania', 'string', 'Site title'),
('barangay_name', 'Barangay Estefania', 'string', 'Barangay name'),
('max_file_size_mb', '5', 'int', 'Maximum upload size in MB'),
('allowed_doc_types', 'pdf,jpg,jpeg,png', 'string', 'Allowed document file types'),
('email_notifications', '1', 'bool', 'Enable email notifications'),
('interview_reminder_hours', '24', 'int', 'Hours before interview to send reminder');

SET FOREIGN_KEY_CHECKS = 1;