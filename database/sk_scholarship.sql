-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2026 at 05:32 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sk_scholarship`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_records`
--

CREATE TABLE `academic_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `scholar_id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `year_level` varchar(30) NOT NULL,
  `course` varchar(200) DEFAULT NULL,
  `school` varchar(200) DEFAULT NULL,
  `gpa` decimal(4,2) DEFAULT NULL,
  `academic_standing` enum('Excellent','Very Good','Good','Satisfactory','Passing','Failing','Probationary') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_records`
--

INSERT INTO `academic_records` (`id`, `scholar_id`, `academic_year`, `semester`, `year_level`, `course`, `school`, `gpa`, `academic_standing`, `remarks`, `recorded_by`, `recorded_at`) VALUES
(1, 1, '2025-2026', '1st Semester', '1st Year', '313', 'Carlos Hilado Memorial State University', 1.00, 'Excellent', 'very good', 2, '2026-09-16 15:07:53');

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `application_code` varchar(30) DEFAULT NULL,
  `is_resident` tinyint(1) DEFAULT 0,
  `residency_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`id`, `user_id`, `application_code`, `is_resident`, `residency_verified`, `created_at`, `updated_at`) VALUES
(1, 1, 'APP-2026-18B6E35A', 0, 0, '2026-09-14 17:50:28', '2026-09-14 17:50:28'),
(2, 2, 'APP-2026-F0E00F30', 0, 0, '2026-09-16 10:13:03', '2026-09-16 10:13:03'),
(8, 13, 'APP-2026-0148BB05', 0, 0, '2026-09-25 11:29:38', '2026-09-25 11:29:38');

-- --------------------------------------------------------

--
-- Table structure for table `applicants_personal_information`
--

CREATE TABLE `applicants_personal_information` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `age` int(10) UNSIGNED DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Separated','Annulled') DEFAULT 'Single',
  `complete_address` text NOT NULL,
  `purok_id` int(10) UNSIGNED DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `religion` varchar(80) DEFAULT NULL,
  `citizenship` varchar(50) DEFAULT 'Filipino',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants_personal_information`
--

INSERT INTO `applicants_personal_information` (`id`, `applicant_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `date_of_birth`, `age`, `civil_status`, `complete_address`, `purok_id`, `contact_number`, `email`, `height_cm`, `weight_kg`, `blood_type`, `religion`, `citizenship`, `updated_at`) VALUES
(1, 1, '123', 'adf', 'adf', 'adf', '2002-05-21', 24, 'Single', 'aadf', 12, 'adsffad', 'markjimb21@gmail.com', 123.00, 123.00, '123', '123', 'Filipino', '2026-09-16 07:36:08'),
(17, 8, 'Juan', '', 'Dela Cruz', 'Jr.', '2001-01-01', 25, 'Single', 'Kung Saan Si Rene', 48, '09123456789', 'cscmjb1@gmail.com', 0.20, 0.30, '', '', 'Filipino', '2026-09-25 12:18:23');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_code` varchar(30) NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `status` enum('Draft','Submitted','Under Initial Review','Incomplete','Documents Under Review','Documents Verified','For Interview','Interview Scheduled','Interview Completed','For Final Evaluation','Approved','Rejected','Waitlisted') DEFAULT 'Draft',
  `eligibility_score` decimal(8,2) DEFAULT NULL,
  `eligibility_notes` text DEFAULT NULL COMMENT 'Failed / unchecked eligibility criteria, one per line (NULL = all criteria met or not yet evaluated)',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decided_by` int(10) UNSIGNED DEFAULT NULL,
  `decision_remarks` text DEFAULT NULL,
  `review_remarks` text DEFAULT NULL COMMENT 'Reason/remarks entered by staff when marking the application Incomplete',
  `review_remarks_at` datetime DEFAULT NULL COMMENT 'When the application was last marked Incomplete',
  `review_remarks_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User (admin/staff) who last marked the application Incomplete',
  `admin_override` tinyint(1) DEFAULT 0,
  `override_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `application_code`, `applicant_id`, `program_id`, `status`, `eligibility_score`, `submitted_at`, `reviewed_at`, `reviewed_by`, `decided_at`, `decided_by`, `decision_remarks`, `review_remarks`, `review_remarks_at`, `review_remarks_by`, `admin_override`, `override_reason`, `created_at`, `updated_at`) VALUES
(1, 'APP-2026-B44B7C11', 1, 1, 'Rejected', NULL, '2026-09-16 21:24:35', NULL, NULL, '2026-09-25 16:03:25', 2, 'asdf', NULL, NULL, NULL, 1, 'adf', '2026-09-16 07:04:38', '2026-09-25 08:03:25'),
(10, 'APP-2026-1FABFEF3', 8, 1, 'Rejected', NULL, '2026-09-25 20:46:51', NULL, NULL, '2026-09-25 23:23:18', 2, 'Wala kanin bossing?', 'Please upload your real profile picture', '2026-09-25 20:45:20', 2, 0, NULL, '2026-09-25 11:31:09', '2026-09-25 15:23:18');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'register', 'users', 1, 'New applicant registered: markjimb21@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 17:50:28'),
(2, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 17:50:55'),
(3, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-15 04:42:19'),
(4, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-15 04:55:17'),
(5, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-15 04:55:48'),
(6, 1, 'application_step_saved', 'applicants', 1, 'Saved step: personal', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-15 04:59:01'),
(7, 1, 'application_step_saved', 'applicants', 1, 'Saved step: education', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-15 04:59:37'),
(8, 1, 'application_step_saved', 'applicants', 1, 'Saved step: family', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-15 05:01:13'),
(9, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:39:52'),
(10, 1, 'application_step_saved', 'applicants', 1, 'Saved step: personal', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:04:38'),
(11, 1, 'application_step_saved', 'applicants', 1, 'Saved step: personal', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:36:08'),
(12, 1, 'application_submitted', 'applications', 1, 'Application submitted', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:36:20'),
(13, 1, 'document_uploaded', 'documents', NULL, 'Uploaded Profile Picture for application #APP-2026-B44B7C11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:58:02'),
(14, 1, 'document_uploaded', 'documents', NULL, 'Uploaded Form 138 for application #APP-2026-B44B7C11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:58:50'),
(15, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:12:19'),
(16, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:12:28'),
(17, NULL, 'register', 'users', 2, 'New applicant registered: admin@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:13:03'),
(18, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:17:35'),
(19, 2, 'application_status_changed', 'applications', 1, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:18:27'),
(20, 2, 'document_rejected', 'documents', 3, 'Document Form 138 set to Rejected', '{\"status\":\"Submitted\"}', '{\"status\":\"Rejected\",\"remarks\":\"asdfad\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:20:27'),
(21, 2, 'document_verified', 'documents', 1, 'Document Profile Picture set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"adfdf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:20:37'),
(22, 2, 'logout', 'users', 2, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:20:43'),
(23, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:20:49'),
(24, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:21:17'),
(25, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 10:21:49'),
(26, 2, 'schedule_created', 'interview_schedules', 1, 'Created interview schedule for 2026-09-18', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:09:39'),
(27, 2, 'logout', 'users', 2, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:17:23'),
(28, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:17:58'),
(29, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:18:53'),
(30, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:18:59'),
(31, 2, 'schedule_updated', 'interview_schedules', 1, 'Updated schedule', '{\"id\":1,\"program_id\":1,\"interview_date\":\"2026-09-18\",\"start_time\":\"09:00:00\",\"end_time\":\"10:00:00\",\"venue\":\"Barangay Estefania\",\"interviewer\":\"SK committee\",\"max_slots\":50,\"status\":\"Open\",\"remarks\":\"afasf\",\"created_by\":2,\"created_at\":\"2026-09-16 20:09:39\",\"assigned_count\":0,\"program_name\":\"SK College Scholarship Program 2026\",\"academic_year\":\"2025-2026\"}', '{\"interview_date\":\"2026-09-18\",\"start_time\":\"09:00:00\",\"end_time\":\"10:00:00\",\"venue\":\"Barangay Estefania\",\"interviewer\":\"SK committee\",\"max_slots\":50,\"status\":\"Open\",\"remarks\":\"afasf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:21:22'),
(32, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:22:46'),
(33, 1, 'application_submitted', 'applications', 1, 'Application submitted', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:23:32'),
(34, 2, 'application_status_changed', 'applications', 1, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:23:40'),
(35, 2, 'admin_override', 'applications', 1, 'Admin override enabled', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:31:09'),
(36, 2, 'document_rejected', 'documents', 3, 'Document Form 138 set to Rejected', '{\"status\":\"Rejected\"}', '{\"status\":\"Rejected\",\"remarks\":\"asdfasdf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:31:52'),
(37, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 12:54:29'),
(38, 2, 'application_status_changed', 'applications', 1, 'Status changed to Incomplete', '{\"status\":\"Under Initial Review\"}', '{\"status\":\"Incomplete\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 13:23:56'),
(39, 1, 'application_submitted', 'applications', 1, 'Application submitted', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 13:24:35'),
(40, 2, 'application_status_changed', 'applications', 1, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 13:31:16'),
(41, 1, 'document_uploaded', 'documents', NULL, 'Uploaded Form 138 for application #APP-2026-B44B7C11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:15:23'),
(42, 1, 'document_uploaded', 'documents', NULL, 'Uploaded School ID for application #APP-2026-B44B7C11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:15:43'),
(43, 1, 'document_uploaded', 'documents', NULL, 'Uploaded Certificate of Residency for application #APP-2026-B44B7C11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:16:04'),
(44, 1, 'document_uploaded', 'documents', NULL, 'Uploaded Enrollment Form for application #APP-2026-B44B7C11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:16:11'),
(45, 2, 'document_verified', 'documents', 3, 'Document Form 138 set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:16:33'),
(46, 2, 'document_verified', 'documents', 5, 'Document School ID set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:16:41'),
(47, 2, 'document_verified', 'documents', 2, 'Document Certificate of Residency set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:16:51'),
(48, 2, 'document_verified', 'documents', 4, 'Document Enrollment Form set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:16:58'),
(49, 2, 'application_status_changed', 'applications', 1, 'Status changed to Documents Verified', '{\"status\":\"Under Initial Review\"}', '{\"status\":\"Documents Verified\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:17:00'),
(50, 2, 'application_status_changed', 'applications', 1, 'Status changed to For Interview', '{\"status\":\"Documents Verified\"}', '{\"status\":\"For Interview\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:18:42'),
(51, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:19:14'),
(52, 2, 'schedule_cancelled', 'interview_schedules', 1, 'Cancelled schedule', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:26:20'),
(53, 2, 'schedule_created', 'interview_schedules', 2, 'Created interview schedule for 2026-09-17', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:26:56'),
(54, 2, 'application_status_changed', 'applications', 1, 'Status changed to Interview Scheduled', '{\"status\":\"For Interview\"}', '{\"status\":\"Interview Scheduled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:27:03'),
(55, 2, 'interview_assigned', 'interview_assignments', 1, 'Assigned applicant to schedule #2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:27:03'),
(56, 2, 'evaluation_saved', 'interview_evaluations', 1, 'Evaluation saved, total=50', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:28:29'),
(57, 2, 'application_status_changed', 'applications', 1, 'Status changed to For Final Evaluation', '{\"status\":\"Interview Scheduled\"}', '{\"status\":\"For Final Evaluation\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:28:29'),
(58, 2, 'scholar_created', 'scholars', 1, 'Scholar created from application #APP-2026-B44B7C11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:59:21'),
(59, 2, 'application_approved', 'applications', 1, 'Application approved', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:59:21'),
(60, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 14:59:36'),
(61, 2, 'scholar_status_changed', 'scholars', 1, 'Status: Active → Active', '{\"status\":\"Active\"}', '{\"status\":\"Active\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 15:00:12'),
(62, 2, 'scholar_status_changed', 'scholars', 1, 'Status: Active → Active', '{\"status\":\"Active\"}', '{\"status\":\"Active\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 15:00:16'),
(63, 2, 'scholar_status_changed', 'scholars', 1, 'Status: Active → Active', '{\"status\":\"Active\"}', '{\"status\":\"Active\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 15:00:38'),
(64, 2, 'scholar_status_changed', 'scholars', 1, 'Status: Active → Active', '{\"status\":\"Active\"}', '{\"status\":\"Active\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 15:00:51'),
(65, 2, 'academic_record_created', 'academic_records', 1, 'Added academic record for scholar #1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 15:07:53'),
(66, 2, 'scholarship_released', 'scholarship_releases', 1, 'Released ₱3,000.00 to scholar #1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 15:08:40'),
(67, 2, 'renewal_created', 'scholarship_renewals', 1, 'Renewal record for scholar #1 2026-2027', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 15:10:49'),
(68, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 16:19:47'),
(69, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 01:12:47'),
(70, 2, 'report_generated', 'reports', NULL, 'Generated Scholarship Applicant List as pdf', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 01:13:14'),
(71, 2, 'report_generated', 'reports', NULL, 'Generated Scholarship Applicant List as csv', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 01:13:31'),
(72, 2, 'report_generated', 'reports', NULL, 'Generated Scholarship Applicant List as csv', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 01:13:37'),
(73, NULL, 'register', 'users', 3, 'New applicant registered: jimrodrigo21@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 02:43:22'),
(74, NULL, 'login', 'users', 3, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-17 02:43:41'),
(75, NULL, 'login', 'users', 3, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-21 05:16:04'),
(76, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-21 05:16:56'),
(77, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 04:10:50'),
(78, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 04:11:43'),
(79, 1, 'document_viewed', 'documents', 1, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 04:11:57'),
(80, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 04:13:29'),
(81, 2, 'report_generated', 'reports', NULL, 'Generated Scholarship Applicant List as pdf', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 04:28:06'),
(82, NULL, 'register', 'users', 4, 'New applicant registered: jimrodrigo21@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:55:39'),
(83, NULL, 'registration_otp_sent', 'users', 4, 'OTP sent', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:55:43'),
(84, NULL, 'registration_otp_sent', 'users', 4, 'OTP sent', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:58:25'),
(85, NULL, 'registration_otp_sent', 'users', 4, 'OTP sent', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 12:19:12'),
(86, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:05:40'),
(87, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:05:46'),
(88, NULL, 'registration_otp_email_failed', 'users', 4, 'OTP generated, but e-mail delivery failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:05:56'),
(89, NULL, 'registration_otp_email_failed', 'users', 4, 'OTP generated, but e-mail delivery failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:07:23'),
(90, NULL, 'register', 'users', 5, 'New applicant registered: markjimb@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:07:49'),
(91, NULL, 'registration_otp_email_failed', 'users', 5, 'OTP generated, but e-mail delivery failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:07:53'),
(92, NULL, 'registration_otp_email_failed', 'users', 5, 'OTP generated, but e-mail delivery failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:09:23'),
(93, NULL, 'registration_otp_email_failed', 'users', 5, 'OTP generated, but e-mail delivery failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:12:40'),
(94, NULL, 'registration_otp_email_failed', 'users', 5, 'OTP generated, but e-mail delivery failed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:16:02'),
(95, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:16:39'),
(96, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:17:01'),
(97, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:57:22'),
(98, NULL, 'registration_otp_sent', 'users', 5, 'OTP generated and e-mailed successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:57:43'),
(99, NULL, 'registration_otp_verified', 'users', 5, 'E-mail verified', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:58:21'),
(100, NULL, 'login', 'users', 5, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 03:58:26'),
(101, NULL, 'application_step_saved', 'applicants', 5, 'Saved step: education', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 04:00:34'),
(102, NULL, 'login', 'users', 5, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 06:49:08'),
(103, NULL, 'login', 'users', 5, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 02:27:39'),
(104, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 02:37:47'),
(105, 2, 'schedule_created', 'interview_schedules', 3, 'Created interview schedule for 2026-09-26', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 02:38:31'),
(106, NULL, 'logout', 'users', 5, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:07:50'),
(107, NULL, 'password_reset_otp_sent', 'users', 1, 'OTP generated and e-mailed successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:08:42'),
(108, NULL, 'password_reset_completed', 'users', 1, 'Password reset via OTP', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:09:36'),
(109, NULL, 'password_changed_email_sent', 'users', 1, 'Password-changed confirmation e-mailed', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:09:42'),
(110, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:09:49'),
(111, 2, 'user_deleted', 'users', 4, 'Deleted user jimrodrigo21@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:12:59'),
(112, 2, 'report_generated', 'reports', NULL, 'Generated Waitlisted Applicants as pdf', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:15:27'),
(113, 2, 'report_generated', 'reports', NULL, 'Generated Waitlisted Applicants as pdf', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:15:39'),
(114, 1, 'document_viewed', 'documents', 3, 'Viewed Form 138', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:17:38'),
(115, 1, 'document_viewed', 'documents', 5, 'Viewed School ID', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:17:44'),
(116, 1, 'document_viewed', 'documents', 2, 'Viewed Certificate of Residency', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:17:54'),
(117, 1, 'document_viewed', 'documents', 4, 'Viewed Enrollment Form', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:18:04'),
(118, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:43:23'),
(119, 2, 'budget_created', 'budgets', 2, 'Budget created for program #1 (2026-2027)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:45:30'),
(120, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:46:43'),
(121, NULL, 'login', 'users', 5, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 04:46:54'),
(122, NULL, 'logout', 'users', 5, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:00:46'),
(123, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:00:56'),
(124, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:05:29'),
(125, NULL, 'login', 'users', 5, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:05:38'),
(126, NULL, 'application_step_saved', 'applicants', 5, 'Saved step: personal', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:07:09'),
(127, NULL, 'application_step_saved', 'applicants', 5, 'Saved step: family', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:08:00'),
(128, NULL, 'application_submitted', 'applications', 2, 'Application submitted', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:09:39'),
(129, 2, 'application_status_changed', 'applications', 2, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:10:58'),
(130, 2, 'application_status_changed', 'applications', 2, 'Status changed to Incomplete', '{\"status\":\"Under Initial Review\"}', '{\"status\":\"Incomplete\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:11:36'),
(131, NULL, 'application_submitted', 'applications', 2, 'Application submitted', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:14:47'),
(132, 2, 'document_viewed', 'documents', 1, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:16:07'),
(133, 2, 'application_status_changed', 'applications', 1, 'Status changed to For Interview', '{\"status\":\"Approved\"}', '{\"status\":\"For Interview\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:18:15'),
(134, 2, 'interview_unassigned', 'interview_assignments', 1, 'Removed assignment', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:18:23'),
(135, 2, 'application_status_changed', 'applications', 1, 'Status changed to Interview Scheduled', '{\"status\":\"For Interview\"}', '{\"status\":\"Interview Scheduled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:18:46'),
(136, 2, 'interview_assigned', 'interview_assignments', 2, 'Assigned applicant to schedule #2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:18:51'),
(137, 2, 'application_status_changed', 'applications', 1, 'Status changed to For Interview', '{\"status\":\"Interview Scheduled\"}', '{\"status\":\"For Interview\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:18:57'),
(138, 2, 'interview_unassigned', 'interview_assignments', 2, 'Removed assignment', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 05:19:01'),
(150, 2, 'application_status_changed', 'applications', 2, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:12:17'),
(151, 2, 'application_marked_incomplete', 'applications', 2, 'Application marked incomplete: Submit your documents.', '{\"status\":\"Under Initial Review\"}', '{\"status\":\"Incomplete\",\"remarks\":\"Submit your documents.\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:12:59'),
(152, NULL, 'document_uploaded', 'documents', 6, 'Uploaded Profile Picture for application #APP-2026-C8745C91', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:25:59'),
(153, NULL, 'document_uploaded', 'documents', 8, 'Uploaded Form 138 for application #APP-2026-C8745C91', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:26:00'),
(154, NULL, 'document_uploaded', 'documents', 10, 'Uploaded School ID for application #APP-2026-C8745C91', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:26:01'),
(155, NULL, 'document_uploaded', 'documents', 7, 'Uploaded Certificate of Residency for application #APP-2026-C8745C91', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:26:03'),
(156, NULL, 'document_uploaded', 'documents', 9, 'Uploaded Enrollment Form for application #APP-2026-C8745C91', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:26:04'),
(157, NULL, 'document_viewed', 'documents', 7, 'Viewed Certificate of Residency', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:26:05'),
(158, NULL, 'application_resubmitted', 'applications', 2, 'Application resubmitted after incomplete status', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:26:41'),
(159, 2, 'document_viewed', 'documents', 6, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:27:51'),
(160, 2, 'document_viewed', 'documents', 8, 'Viewed Form 138', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:27:57'),
(161, 2, 'document_viewed', 'documents', 7, 'Viewed Certificate of Residency', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:28:00'),
(162, 2, 'application_status_changed', 'applications', 2, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:29:10'),
(163, 2, 'document_verified', 'documents', 6, 'Document Profile Picture set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:29:33'),
(164, 2, 'document_verified', 'documents', 8, 'Document Form 138 set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:32:33'),
(165, 2, 'document_downloaded', 'documents', 10, 'Downloaded School ID', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:35:39'),
(166, 2, 'document_verified', 'documents', 10, 'Document School ID set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:37:26'),
(167, NULL, 'login', 'users', 8, 'User logged in', NULL, NULL, '::1', 'curl/8.18.0', '2026-09-25 06:46:49'),
(168, NULL, 'document_verified', 'documents', 16, 'Document Profile Picture set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":null}', '::1', 'curl/8.18.0', '2026-09-25 06:46:49'),
(170, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:49:04'),
(171, 2, 'document_verified', 'documents', 7, 'Document Certificate of Residency set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:49:16'),
(172, 2, 'document_verified', 'documents', 9, 'Document Enrollment Form set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:49:32'),
(173, 2, 'application_status_changed', 'applications', 2, 'Status changed to Documents Verified', '{\"status\":\"Under Initial Review\"}', '{\"status\":\"Documents Verified\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:50:21'),
(174, 2, 'application_status_changed', 'applications', 2, 'Status changed to Documents Verified', '{\"status\":\"Documents Verified\"}', '{\"status\":\"Documents Verified\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:50:24'),
(175, NULL, 'document_viewed', 'documents', 6, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:51:05'),
(176, 2, 'application_status_changed', 'applications', 2, 'Status changed to For Interview', '{\"status\":\"Documents Verified\"}', '{\"status\":\"For Interview\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:55:42'),
(177, 2, 'application_status_changed', 'applications', 2, 'Status changed to Interview Scheduled', '{\"status\":\"For Interview\"}', '{\"status\":\"Interview Scheduled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:57:35'),
(178, 2, 'interview_assigned', 'interview_assignments', 3, 'Assigned applicant to schedule #3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:57:41'),
(179, 2, 'application_status_changed', 'applications', 1, 'Status changed to Interview Scheduled', '{\"status\":\"For Interview\"}', '{\"status\":\"Interview Scheduled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:57:48'),
(180, 2, 'interview_assigned', 'interview_assignments', 4, 'Assigned applicant to schedule #3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 06:57:53'),
(181, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 07:48:03'),
(182, 2, 'logout', 'users', 2, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 07:48:13'),
(183, NULL, 'login', 'users', 5, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 07:48:35'),
(184, NULL, 'logout', 'users', 5, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 08:01:48'),
(185, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 08:02:41'),
(186, 2, 'application_status_changed', 'applications', 1, 'Status changed to Rejected', '{\"status\":\"Interview Scheduled\"}', '{\"status\":\"Rejected\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 08:03:25'),
(187, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 08:03:51'),
(188, NULL, 'login', 'users', 5, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 08:03:58'),
(189, 2, 'application_status_changed', 'applications', 2, 'Status changed to Rejected', '{\"status\":\"Interview Scheduled\"}', '{\"status\":\"Rejected\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 08:04:14'),
(190, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 09:50:23'),
(191, 2, 'user_deleted', 'users', 5, 'Deleted user markjimb@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 09:55:16'),
(192, 2, 'user_updated', 'users', 1, 'Updated user markjimb21@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 09:56:14'),
(193, 2, 'user_created', 'users', 10, 'Created user staff@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 09:57:06'),
(194, 10, 'login', 'users', 10, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 09:57:25'),
(195, 2, 'user_created', 'users', 11, 'Created user official@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:00:10'),
(196, 10, 'logout', 'users', 10, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:00:15'),
(197, 11, 'login', 'users', 11, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:00:28'),
(198, 11, 'report_generated', 'reports', NULL, 'Generated Waitlisted Applicants as pdf', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:00:37'),
(199, 11, 'access_denied', NULL, NULL, 'Denied GET admin/academic-records as official', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:01:06'),
(200, 2, 'user_created', 'users', 12, 'Created user dummy@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:21:07'),
(201, 11, 'logout', 'users', 11, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:22:57'),
(202, NULL, 'registration_otp_sent', 'users', 12, 'OTP generated and e-mailed successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 10:23:21'),
(203, 2, 'user_deleted', 'users', 12, 'Deleted user dummy@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:27:14'),
(204, 2, 'user_status_changed', 'users', 1, 'Deactivated user markjimb21@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:27:52'),
(205, 2, 'user_status_changed', 'users', 1, 'Activated user markjimb21@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:28:20'),
(206, 1, 'login', 'users', 1, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:28:24'),
(207, 1, 'logout', 'users', 1, 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:28:58'),
(208, NULL, 'register', 'users', 13, 'New applicant registered: cscmjb1@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:29:38'),
(209, NULL, 'registration_otp_sent', 'users', 13, 'OTP generated and e-mailed successfully', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:29:44'),
(210, NULL, 'registration_otp_verified', 'users', 13, 'E-mail verified', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:30:23'),
(211, 13, 'login', 'users', 13, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 11:30:52'),
(212, 13, 'document_uploaded', 'documents', 43, 'Uploaded Profile Picture for application #APP-2026-1FABFEF3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:23:02'),
(213, 13, 'document_viewed', 'documents', 43, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:23:05'),
(214, 13, 'document_uploaded', 'documents', 45, 'Uploaded Form 138 for application #APP-2026-1FABFEF3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:36:45');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(215, 13, 'document_uploaded', 'documents', 47, 'Uploaded School ID for application #APP-2026-1FABFEF3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:36:48'),
(216, 13, 'document_uploaded', 'documents', 44, 'Uploaded Certificate of Residency for application #APP-2026-1FABFEF3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:36:50'),
(217, 13, 'document_uploaded', 'documents', 46, 'Uploaded Enrollment Form for application #APP-2026-1FABFEF3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:36:50'),
(218, 13, 'application_submitted', 'applications', 10, 'Application submitted', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:36:56'),
(219, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:40:07'),
(220, 2, 'document_viewed', 'documents', 43, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:41:02'),
(221, 2, 'document_rejected', 'documents', 43, 'Document Profile Picture set to Rejected', '{\"status\":\"Submitted\"}', '{\"status\":\"Rejected\",\"remarks\":\"provide your real picture\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:41:25'),
(222, 2, 'document_rejected', 'documents', 43, 'Document Profile Picture set to Rejected', '{\"status\":\"Rejected\"}', '{\"status\":\"Rejected\",\"remarks\":\"provide your real picture\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:41:30'),
(223, 2, 'document_resubmission_required', 'documents', 43, 'Document Profile Picture set to Resubmission Required', '{\"status\":\"Rejected\"}', '{\"status\":\"Resubmission Required\",\"remarks\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:41:47'),
(224, 13, 'document_viewed', 'documents', 43, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:42:32'),
(225, 2, 'application_status_changed', 'applications', 10, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:43:38'),
(226, 2, 'application_marked_incomplete', 'applications', 10, 'Application marked incomplete: Please upload your real profile picture', '{\"status\":\"Under Initial Review\"}', '{\"status\":\"Incomplete\",\"remarks\":\"Please upload your real profile picture\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:45:20'),
(227, 13, 'document_viewed', 'documents', 43, 'Viewed Profile Picture', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:45:57'),
(228, 13, 'document_uploaded', 'documents', 43, 'Uploaded Profile Picture for application #APP-2026-1FABFEF3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:46:07'),
(229, 13, 'application_resubmitted', 'applications', 10, 'Application resubmitted after incomplete status', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:46:51'),
(230, 2, 'application_status_changed', 'applications', 10, 'Status changed to Under Initial Review', '{\"status\":\"Submitted\"}', '{\"status\":\"Under Initial Review\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 12:51:59'),
(231, 2, 'document_verified', 'documents', 43, 'Document Profile Picture set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 13:19:56'),
(232, 2, 'document_verified', 'documents', 44, 'Document Certificate of Residency set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 13:19:56'),
(233, 2, 'document_verified', 'documents', 45, 'Document Form 138 set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 13:19:56'),
(234, 2, 'document_verified', 'documents', 46, 'Document Enrollment Form set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 13:19:56'),
(235, 2, 'document_verified', 'documents', 47, 'Document School ID set to Verified', '{\"status\":\"Submitted\"}', '{\"status\":\"Verified\",\"remarks\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 13:19:56'),
(236, 2, 'documents_verify_all', 'applications', 10, 'Verified 5 document(s) in bulk', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 13:19:56'),
(237, 2, 'login', 'users', 2, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 15:10:45'),
(238, 2, 'application_status_changed', 'applications', 10, 'Status changed to Documents Verified', '{\"status\":\"Under Initial Review\"}', '{\"status\":\"Documents Verified\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 15:11:07'),
(239, 13, 'login', 'users', 13, 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 15:16:04'),
(240, 2, 'application_status_changed', 'applications', 10, 'Status changed to Waitlisted', '{\"status\":\"Documents Verified\"}', '{\"status\":\"Waitlisted\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 15:21:33'),
(241, 2, 'application_status_changed', 'applications', 10, 'Status changed to Rejected', '{\"status\":\"Waitlisted\"}', '{\"status\":\"Rejected\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 15:23:18');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `city`, `province`, `created_at`) VALUES
(1, 'Barangay Estefania', 'Bacolod City', 'Negros Occidental', '2026-09-14 17:49:44');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `total_budget` decimal(14,2) NOT NULL,
  `allocated` decimal(14,2) DEFAULT 0.00,
  `released` decimal(14,2) DEFAULT 0.00,
  `remaining` decimal(14,2) GENERATED ALWAYS AS (`total_budget` - `released`) STORED,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `program_id`, `academic_year`, `total_budget`, `allocated`, `released`, `notes`, `created_at`) VALUES
(1, 1, '2025-2026', 500000.00, 0.00, 3000.00, NULL, '2026-09-16 16:05:05'),
(2, 1, '2026-2027', 300000.00, 3000.00, 0.00, NULL, '2026-09-25 04:45:30');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_name` varchar(200) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `course_code`, `is_active`, `created_at`) VALUES
(1, 'Bachelor of Science in Information Technology', 'BSIT', 1, '2026-09-15 05:50:39'),
(2, 'Bachelor of Science in Computer Science', 'BSCS', 1, '2026-09-15 05:50:39'),
(3, 'Bachelor of Science in Accountancy', 'BSA', 1, '2026-09-15 05:50:39'),
(4, 'Bachelor of Science in Business Administration', 'BSBA', 1, '2026-09-15 05:50:39'),
(5, 'Bachelor of Science in Civil Engineering', 'BSCE', 1, '2026-09-15 05:50:39'),
(6, 'Bachelor of Science in Nursing', 'BSN', 1, '2026-09-15 05:50:39'),
(7, 'Bachelor of Elementary Education', 'BEED', 1, '2026-09-15 05:50:39'),
(8, 'Bachelor of Secondary Education', 'BSED', 1, '2026-09-15 05:50:39'),
(9, 'Bachelor of Arts in Communication', 'ABCOM', 1, '2026-09-15 05:50:39'),
(10, 'Bachelor of Science in Criminology', 'BSCrim', 1, '2026-09-15 05:50:39');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('Profile Picture','Certificate of Residency','Form 138','Enrollment Form','School ID') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `status` enum('Not Submitted','Submitted','Under Review','Verified','Rejected','Resubmission Required') DEFAULT 'Submitted',
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `application_id`, `document_type`, `file_path`, `original_filename`, `file_size`, `mime_type`, `status`, `uploaded_at`, `reviewed_at`, `reviewed_by`, `remarks`) VALUES
(1, 1, 'Profile Picture', 'uploads/documents/1/Profile_Picture_1789545482.jpg', 'Messenger_creation_CF0C390E-F22E-41DA-A137-B65DA075CC4A.jpeg', 40547, 'image/jpeg', 'Verified', '2026-09-16 15:58:02', '2026-09-16 18:20:37', 2, 'adfdf'),
(2, 1, 'Certificate of Residency', 'uploads/documents/1/Certificate_of_Residency_1789568164.jpg', 'Screenshot_20250819-213241.jpg', 672809, 'image/jpeg', 'Verified', '2026-09-16 22:16:04', '2026-09-16 22:16:51', 2, ''),
(3, 1, 'Form 138', 'uploads/documents/1/Form_138_1789568123.jpg', 'FB_IMG_1759373623430.jpg', 32898, 'image/jpeg', 'Verified', '2026-09-16 22:15:23', '2026-09-16 22:16:33', 2, ''),
(4, 1, 'Enrollment Form', 'uploads/documents/1/Enrollment_Form_1789568171.jpg', 'Messenger_creation_CF0C390E-F22E-41DA-A137-B65DA075CC4A.jpeg', 40547, 'image/jpeg', 'Verified', '2026-09-16 22:16:11', '2026-09-16 22:16:58', 2, ''),
(5, 1, 'School ID', 'uploads/documents/1/School_ID_1789568143.jpg', 'd727289d-ecd1-47e3-ae77-56572b46a9cd.jpg', 167494, 'image/jpeg', 'Verified', '2026-09-16 22:15:43', '2026-09-16 22:16:41', 2, ''),
(43, 10, 'Profile Picture', 'storage/documents/10/c4c20c3c49477f04a7fef07bfcbea3e9.png', '1000067256.png', 1510666, 'image/png', 'Verified', '2026-09-25 20:46:07', '2026-09-25 21:19:55', 2, NULL),
(44, 10, 'Certificate of Residency', 'storage/documents/10/297d1f241faec92b0af1cf32fa62d358.png', 'contact.png', 113324, 'image/png', 'Verified', '2026-09-25 20:36:50', '2026-09-25 21:19:56', 2, NULL),
(45, 10, 'Form 138', 'storage/documents/10/5912f4c4ca4a742d7cd604339b50393a.png', 'seo.png', 325507, 'image/png', 'Verified', '2026-09-25 20:36:45', '2026-09-25 21:19:56', 2, NULL),
(46, 10, 'Enrollment Form', 'storage/documents/10/a11f8f9c09a131b74d448ce742ba2e36.png', 'responsive.png', 302908, 'image/png', 'Verified', '2026-09-25 20:36:50', '2026-09-25 21:19:56', 2, NULL),
(47, 10, 'School ID', 'storage/documents/10/0e9e22fb96f2ee65c4decc222a1551ea.png', 'og-image.png', 196222, 'image/png', 'Verified', '2026-09-25 20:36:48', '2026-09-25 21:19:56', 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `education_records`
--

CREATE TABLE `education_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `school_id` int(10) UNSIGNED DEFAULT NULL,
  `school_name` varchar(200) NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year','Other') NOT NULL,
  `year_level_other` varchar(50) DEFAULT NULL,
  `course_id` int(10) UNSIGNED DEFAULT NULL,
  `course_name` varchar(200) NOT NULL,
  `general_average` decimal(4,2) DEFAULT NULL COMMENT 'Optional GWA, 1.00 (highest) to 5.00',
  `previous_scholarship` tinyint(1) DEFAULT 0,
  `previous_scholarship_details` varchar(255) DEFAULT NULL,
  `current_scholarship` tinyint(1) DEFAULT 0,
  `current_scholarship_details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `education_records`
--

INSERT INTO `education_records` (`id`, `applicant_id`, `school_id`, `school_name`, `year_level`, `year_level_other`, `course_id`, `course_name`, `previous_scholarship`, `previous_scholarship_details`, `current_scholarship`, `current_scholarship_details`, `created_at`) VALUES
(1, 1, NULL, 'Carlos Hilado Memorial State University', '2nd Year', '', NULL, '313', 0, '', 0, '', '2026-09-15 04:59:37'),
(51, 8, NULL, 'Bacolod City College', '1st Year', '', NULL, 'Bachelor of Science in Information Technology', 0, '', 0, '', '2026-09-25 12:36:08');

-- --------------------------------------------------------

--
-- Table structure for table `email_otp_codes`
--

CREATE TABLE `email_otp_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `purpose` enum('registration','password_reset') NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `resend_available_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_otp_codes`
--

INSERT INTO `email_otp_codes` (`id`, `user_id`, `purpose`, `otp_hash`, `expires_at`, `attempts`, `max_attempts`, `resend_available_at`, `verified_at`, `created_at`) VALUES
(11, 1, 'password_reset', '$2y$10$XvYppHp2ZjnuYBuRiO9S6uKKt0fMlbEg3QcgK6iavezCGQF7SgYRG', '2026-09-25 12:18:34', 0, 5, '2026-09-25 12:09:34', '2026-09-25 12:09:17', '2026-09-25 04:08:34'),
(13, 13, 'registration', '$2y$10$5RIRqZs0Ir/A70bo1xrh0O42F2uy9JQB05w3cbkKWlh9c4dkFA7h.', '2026-09-25 19:39:38', 0, 5, '2026-09-25 19:30:38', '2026-09-25 19:30:23', '2026-09-25 11:29:38');

-- --------------------------------------------------------

--
-- Table structure for table `family_background`
--

CREATE TABLE `family_background` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `father_age` int(10) UNSIGNED DEFAULT NULL,
  `father_occupation` varchar(150) DEFAULT NULL,
  `father_address` text DEFAULT NULL,
  `father_contact` varchar(20) DEFAULT NULL,
  `father_income` decimal(12,2) DEFAULT 0.00,
  `father_status` enum('Available','Deceased','Unknown','Not Applicable') DEFAULT 'Available',
  `mother_name` varchar(150) DEFAULT NULL,
  `mother_age` int(10) UNSIGNED DEFAULT NULL,
  `mother_occupation` varchar(150) DEFAULT NULL,
  `mother_address` text DEFAULT NULL,
  `mother_contact` varchar(20) DEFAULT NULL,
  `mother_income` decimal(12,2) DEFAULT 0.00,
  `mother_status` enum('Available','Deceased','Unknown','Not Applicable') DEFAULT 'Available',
  `combined_income` decimal(12,2) DEFAULT 0.00,
  `total_family_income` decimal(12,2) DEFAULT 0.00,
  `family_size` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `family_background`
--

INSERT INTO `family_background` (`id`, `applicant_id`, `father_name`, `father_age`, `father_occupation`, `father_address`, `father_contact`, `father_income`, `father_status`, `mother_name`, `mother_age`, `mother_occupation`, `mother_address`, `mother_contact`, `mother_income`, `mother_status`, `combined_income`, `total_family_income`, `family_size`, `updated_at`) VALUES
(1, 1, '13123', 123, '12313', '12313', '123', 123.00, 'Available', '123', 13, 'qwe', '12313', '123', 123.00, 'Available', 246.00, 246.00, 6, '2026-09-15 05:01:13'),
(14, 8, 'Pedro Penduko', 50, 'Tambay', 'Purok Langis Barangay Banago Bacolod City Negros Occidental', '09123456789', 5000.00, 'Available', 'Margie De Guzman', 48, 'Housewife', 'Murcia, Allegria', '09208865485', 6000.00, 'Available', 11000.00, 11000.00, 4, '2026-09-25 12:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `interview_assignments`
--

CREATE TABLE `interview_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `assignment_status` enum('For Interview','Scheduled','Confirmed','Completed','No Show','Rescheduled','Cancelled') DEFAULT 'Scheduled',
  `instructions` text DEFAULT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL COMMENT 'When the interview reminder was sent (NULL = not yet / reset after reschedule)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interview_assignments`
--

INSERT INTO `interview_assignments` (`id`, `schedule_id`, `application_id`, `assignment_status`, `instructions`, `assigned_by`, `assigned_at`, `confirmed_at`) VALUES
(4, 3, 1, 'Scheduled', NULL, 2, '2026-09-25 06:57:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `interview_evaluations`
--

CREATE TABLE `interview_evaluations` (
  `id` int(10) UNSIGNED NOT NULL,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `financial_need_score` decimal(5,2) DEFAULT 0.00,
  `academic_score` decimal(5,2) DEFAULT 0.00,
  `motivation_score` decimal(5,2) DEFAULT 0.00,
  `community_score` decimal(5,2) DEFAULT 0.00,
  `purpose_score` decimal(5,2) DEFAULT 0.00,
  `overall_score` decimal(5,2) DEFAULT 0.00,
  `total_score` decimal(6,2) DEFAULT 0.00,
  `max_possible_score` decimal(6,2) DEFAULT 100.00,
  `rating` decimal(5,2) DEFAULT NULL,
  `result` enum('Recommended','Recommended with Conditions','For Further Review','Not Recommended','No Show','Rescheduled') DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `interview_remarks` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `interviewer_id` int(10) UNSIGNED DEFAULT NULL,
  `evaluated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_schedules`
--

CREATE TABLE `interview_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `interview_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `venue` varchar(200) NOT NULL,
  `interviewer` varchar(255) NOT NULL,
  `max_slots` int(10) UNSIGNED DEFAULT 30,
  `status` enum('Open','Full','Cancelled','Completed') DEFAULT 'Open',
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interview_schedules`
--

INSERT INTO `interview_schedules` (`id`, `program_id`, `interview_date`, `start_time`, `end_time`, `venue`, `interviewer`, `max_slots`, `status`, `remarks`, `created_by`, `created_at`) VALUES
(1, 1, '2026-09-18', '09:00:00', '10:00:00', 'Barangay Estefania', 'SK committee', 50, 'Cancelled', '123', 2, '2026-09-16 12:09:39'),
(2, 1, '2026-09-17', '10:00:00', '12:00:00', 'Barangay Estefania', 'SK committee', 30, 'Open', 'asdad', 2, '2026-09-16 14:26:56'),
(3, 1, '2026-09-26', '09:00:00', '11:00:00', 'Barangay Estefania Hall', 'SK committee', 30, 'Open', 'asdjifhi', 2, '2026-09-25 02:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `email_sent`, `created_at`) VALUES
(1, 1, 'Application Submitted', 'Your scholarship application has been submitted and is now under initial review.', 'success', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 0, '2026-09-16 07:36:20'),
(2, 1, 'Application Under Review', 'Your application is now being reviewed by the SK office.', 'info', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 0, '2026-09-16 10:18:27'),
(3, 1, 'Document Rejected ❌', 'Your Form 138 has been marked as: Rejected. Remarks: asdfad', 'danger', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-16 10:20:27'),
(4, 1, 'Document Verified ✅', 'Your Profile Picture has been marked as: Verified. Remarks: adfdf', 'success', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-16 10:20:37'),
(5, 1, 'Application Submitted', 'Your scholarship application has been submitted and is now under initial review.', 'success', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 0, '2026-09-16 12:23:32'),
(6, 1, 'Application Under Review', 'Your application is now being reviewed by the SK office.', 'info', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 0, '2026-09-16 12:23:40'),
(7, 1, 'Document Rejected ❌', 'Your Form 138 has been marked as: Rejected. Remarks: asdfasdf', 'danger', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-16 12:31:52'),
(8, 1, 'Application Submitted', 'Your scholarship application has been submitted and is now under initial review.', 'success', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 0, '2026-09-16 13:24:35'),
(9, 1, 'Application Under Review', 'Your application is now being reviewed by the SK office.', 'info', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 0, '2026-09-16 13:31:16'),
(10, 1, 'Document Verified ✅', 'Your Form 138 has been marked as: Verified.', 'success', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-16 14:16:33'),
(11, 1, 'Document Verified ✅', 'Your School ID has been marked as: Verified.', 'success', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-16 14:16:41'),
(12, 1, 'Document Verified ✅', 'Your Certificate of Residency has been marked as: Verified.', 'success', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-16 14:16:51'),
(13, 1, 'Document Verified ✅', 'Your Enrollment Form has been marked as: Verified.', 'success', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-16 14:16:58'),
(14, 1, 'Eligible for Interview', 'You are now eligible for an interview. Please wait for scheduling.', 'success', 'http://localhost/sk-scholarship/public/applicant/interview', 0, 0, '2026-09-16 14:18:42'),
(15, 1, 'Interview Scheduled 📅', 'Your interview has been scheduled on 2026-09-17 at 10:00 AM at Barangay Estefania. Interviewer: SK committee.', 'success', 'http://localhost/sk-scholarship/public/applicant/interview', 0, 0, '2026-09-16 14:27:03'),
(16, 1, 'Interview Result Recorded', 'Your interview has been evaluated. Please wait for the final scholarship decision.', 'info', 'http://localhost/sk-scholarship/public/applicant/interview', 0, 0, '2026-09-16 14:28:29'),
(17, 1, 'Congratulations — Scholarship Approved! 🎉', 'Your scholarship application has been approved. Welcome, SK Scholar!', 'success', 'http://localhost/sk-scholarship/public/applicant/scholarship', 0, 0, '2026-09-16 14:59:21'),
(18, 1, 'Scholarship Status: Active', 'Your scholar status has been updated to: Active', 'success', 'http://localhost/sk-scholarship/public/applicant/scholarship', 0, 0, '2026-09-16 15:00:12'),
(19, 1, 'Scholarship Status: Active', 'Your scholar status has been updated to: Active', 'success', 'http://localhost/sk-scholarship/public/applicant/scholarship', 0, 0, '2026-09-16 15:00:16'),
(20, 1, 'Scholarship Status: Active', 'Your scholar status has been updated to: Active', 'success', 'http://localhost/sk-scholarship/public/applicant/scholarship', 0, 0, '2026-09-16 15:00:38'),
(21, 1, 'Scholarship Status: Active', 'Your scholar status has been updated to: Active', 'success', 'http://localhost/sk-scholarship/public/applicant/scholarship', 0, 0, '2026-09-16 15:00:51'),
(22, 1, 'Scholarship Released 💵', '₱3,000.00 was released to you for 2025-2026 1st Semester.', 'success', 'http://localhost/sk-scholarship/public/applicant/scholarship', 0, 0, '2026-09-16 15:08:40'),
(30, 1, 'Interview Assignment Removed', 'Your interview assignment has been removed. You will be notified when a new schedule is set.', 'warning', 'http://localhost/sk-scholarship/public/applicant/interview', 0, 1, '2026-09-25 05:18:15'),
(31, 1, 'Interview Scheduled 📅', 'Your interview has been scheduled on 2026-09-17 at 10:00 AM at Barangay Estefania. Interviewer: SK committee.', 'success', 'http://localhost/sk-scholarship/public/applicant/interview', 0, 1, '2026-09-25 05:18:46'),
(32, 1, 'Interview Assignment Removed', 'Your interview assignment has been removed. You will be notified when a new schedule is set.', 'warning', 'http://localhost/sk-scholarship/public/applicant/interview', 0, 1, '2026-09-25 05:18:57'),
(51, 1, 'Interview Scheduled 📅', 'Your interview has been scheduled on 2026-09-26 at 9:00 AM at Barangay Estefania Hall. Interviewer: SK committee.', 'success', 'http://localhost/sk-scholarship/public/applicant/interview', 0, 1, '2026-09-25 06:57:48'),
(52, 1, 'Application Not Approved', 'We regret to inform you that your scholarship application was not approved. asdf', 'danger', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 1, '2026-09-25 08:03:25'),
(54, 13, 'Account Created', 'Your SK Scholarship account has been created. Verify your e-mail to activate it.', 'info', NULL, 0, 0, '2026-09-25 11:29:38'),
(55, 13, 'Email Verified', 'Your e-mail has been verified. Your SK Scholarship account is now active.', 'success', 'http://localhost/sk-scholarship/public/login', 0, 0, '2026-09-25 11:30:23'),
(56, 13, 'Application Submitted', 'Your scholarship application has been submitted and is now under initial review.', 'success', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 1, '2026-09-25 12:36:56'),
(57, 13, 'Document Rejected ❌', 'Your Profile Picture has been marked as: Rejected. Remarks: provide your real picture', 'danger', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 1, '2026-09-25 12:41:25'),
(58, 13, 'Document Rejected ❌', 'Your Profile Picture has been marked as: Rejected. Remarks: provide your real picture', 'danger', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 0, '2026-09-25 12:41:30'),
(59, 13, 'Resubmission Required ⚠️', 'Your Profile Picture has been marked as: Resubmission Required.', 'warning', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 1, '2026-09-25 12:41:47'),
(60, 13, 'Application Under Review', 'Your application is now being reviewed by the SK office.', 'info', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 1, '2026-09-25 12:43:38'),
(61, 13, 'Application Marked Incomplete', 'Your scholarship application has been marked incomplete. Please review the remarks and update your application. Remarks: Please upload your real profile picture', 'warning', 'http://localhost/sk-scholarship/public/applicant/application', 0, 1, '2026-09-25 12:45:20'),
(62, 13, 'Application Resubmitted', 'Your application has been resubmitted and is now awaiting review by the SK office.', 'success', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 1, '2026-09-25 12:46:51'),
(63, 13, 'Application Under Review', 'Your application is now being reviewed by the SK office.', 'info', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 1, '2026-09-25 12:51:59'),
(64, 13, 'Documents Verified ✅', '5 of your submitted document(s) have been verified by the SK office.', 'success', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 1, '2026-09-25 13:19:56'),
(65, 13, 'Documents Verified', 'All your submitted documents have been verified by the SK office.', 'success', 'http://localhost/sk-scholarship/public/applicant/documents', 0, 1, '2026-09-25 15:11:07'),
(66, 13, 'Waitlisted', 'You have been placed on the waitlist. We will notify you if a slot opens.', 'warning', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 1, '2026-09-25 15:21:33'),
(67, 13, 'Application Not Approved', 'We regret to inform you that your scholarship application was not approved. Wala kanin bossing?', 'danger', 'http://localhost/sk-scholarship/public/applicant/dashboard', 0, 1, '2026-09-25 15:23:18');

-- --------------------------------------------------------

--
-- Table structure for table `puroks`
--

CREATE TABLE `puroks` (
  `id` int(10) UNSIGNED NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `purok_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `puroks`
--

INSERT INTO `puroks` (`id`, `barangay_id`, `purok_name`) VALUES
(1, 1, 'Arao'),
(2, 1, 'Arabay 1'),
(3, 1, 'Arabay 2'),
(4, 1, 'Bagong Silang'),
(5, 1, 'Bethany Court'),
(6, 1, 'Buena Royale'),
(7, 1, 'Camelot Residences'),
(8, 1, 'Camelot Village'),
(9, 1, 'Camingawan Proper'),
(10, 1, 'Capitol Hills Subdivision'),
(11, 1, 'Celine Homes Subdivision'),
(12, 1, 'City Ville Subdivision'),
(13, 1, 'Country Homes Subdivision Phase 1'),
(14, 1, 'Country Homes Subdivision Phase 2'),
(15, 1, 'Country Homes Subdivision Phase 3'),
(16, 1, 'East Homes 1'),
(17, 1, 'East Homes 2'),
(18, 1, 'East Homes 3'),
(19, 1, 'Elsa'),
(20, 1, 'Escuerdo'),
(21, 1, 'Estefania Proper'),
(22, 1, 'Flora'),
(23, 1, 'Fortune Towne - B'),
(24, 1, 'Fortune Towne Subdivision'),
(25, 1, 'Goldah'),
(26, 1, 'Glenwood Residences'),
(27, 1, 'Greensville 1 Subdivision'),
(28, 1, 'Greensville 4 Subdivision'),
(29, 1, 'Jesusa Heights Subdivision'),
(30, 1, 'Kaburihan'),
(31, 1, 'Kasoy'),
(32, 1, 'La Herencia'),
(33, 1, 'Lopues Village'),
(34, 1, 'Luisville Subdivision'),
(35, 1, 'Mayang'),
(36, 1, 'Meadows of Camelot'),
(37, 1, 'Pag-asa'),
(38, 1, 'Paho 1'),
(39, 1, 'Paho 2'),
(40, 1, 'Paraiso'),
(41, 1, 'Pedring'),
(42, 1, 'Pequiño'),
(43, 1, 'Providence Negros'),
(44, 1, 'Sagrado 2'),
(45, 1, 'Sambag'),
(46, 1, 'Sunshine Valley Subdivision'),
(47, 1, 'The Palisades'),
(48, 1, 'Villa Alexandra 1'),
(49, 1, 'Villa Alexandra 2'),
(50, 1, 'Villa Angeles'),
(51, 1, 'Villa Estefania'),
(52, 1, 'Villa Felicidad'),
(53, 1, 'Villa Soledad'),
(54, 1, 'Villa Villeta'),
(55, 1, 'Villamar');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `created_at`) VALUES
(1, 'admin', 'SK Administrator - full access', '2026-09-14 17:49:44'),
(2, 'staff', 'SK Staff - limited administrative access', '2026-09-14 17:49:44'),
(3, 'official', 'SK Official / Decision Maker - view analytics', '2026-09-14 17:49:44'),
(4, 'applicant', 'College student applicant', '2026-09-14 17:49:44');

-- --------------------------------------------------------

--
-- Table structure for table `scholars`
--

CREATE TABLE `scholars` (
  `id` int(10) UNSIGNED NOT NULL,
  `scholar_code` varchar(30) NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `approval_date` date NOT NULL,
  `status` enum('Active','On Probation','Suspended','Graduated','Withdrawn','Disqualified') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholars`
--

INSERT INTO `scholars` (`id`, `scholar_code`, `applicant_id`, `application_id`, `program_id`, `approval_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SCH-2026-04E325E5', 1, 1, 1, '2026-09-16', 'Active', '2026-09-16 14:59:21', '2026-09-16 15:00:51');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_programs`
--

CREATE TABLE `scholarship_programs` (
  `id` int(10) UNSIGNED NOT NULL,
  `program_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `min_gpa` decimal(4,2) DEFAULT NULL,
  `max_family_income` decimal(12,2) DEFAULT NULL,
  `max_age` int(11) DEFAULT NULL,
  `citizenship_required` varchar(50) DEFAULT 'Filipino',
  `required_residency` varchar(100) DEFAULT 'Barangay Estefania',
  `budget_amount` decimal(14,2) DEFAULT 0.00,
  `slots_available` int(11) DEFAULT 0,
  `application_start` date NOT NULL,
  `application_end` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholarship_programs`
--

INSERT INTO `scholarship_programs` (`id`, `program_name`, `description`, `academic_year`, `semester`, `min_gpa`, `max_family_income`, `max_age`, `citizenship_required`, `required_residency`, `budget_amount`, `slots_available`, `application_start`, `application_end`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SK College Scholarship Program 2026', 'Financial assistance program for college students residing in Barangay Estefania.', '2025-2026', '1st Semester', 2.50, 25000.00, 30, 'Filipino', 'Barangay Estefania', 500000.00, 100, '2026-09-15', '2026-11-14', 1, 1, '2026-09-15 05:45:02', '2026-09-15 05:45:02');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_releases`
--

CREATE TABLE `scholarship_releases` (
  `id` int(10) UNSIGNED NOT NULL,
  `release_code` varchar(30) NOT NULL,
  `scholar_id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `release_date` date NOT NULL,
  `payment_method` enum('Cash','Check','Bank Transfer','GCash','Other') DEFAULT 'Cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `recipient_confirmed` tinyint(1) DEFAULT 0,
  `confirmed_at` datetime DEFAULT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholarship_releases`
--

INSERT INTO `scholarship_releases` (`id`, `release_code`, `scholar_id`, `academic_year`, `semester`, `amount`, `release_date`, `payment_method`, `reference_number`, `recipient_confirmed`, `confirmed_at`, `staff_id`, `remarks`, `created_at`) VALUES
(1, 'REL-2026-3ACA9BCA', 1, '2025-2026', '1st Semester', 3000.00, '2026-09-16', 'Cash', '', 1, NULL, 2, 'pagbutihan mo', '2026-09-16 15:08:40');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_renewals`
--

CREATE TABLE `scholarship_renewals` (
  `id` int(10) UNSIGNED NOT NULL,
  `scholar_id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `gpa` decimal(4,2) DEFAULT NULL,
  `residency_verified` tinyint(1) DEFAULT 0,
  `enrollment_verified` tinyint(1) DEFAULT 0,
  `documents_complete` tinyint(1) DEFAULT 0,
  `status` enum('Eligible for Renewal','For Review','Not Eligible','Renewed','Renewal Denied') DEFAULT 'For Review',
  `remarks` text DEFAULT NULL,
  `evaluated_by` int(10) UNSIGNED DEFAULT NULL,
  `evaluated_at` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL COMMENT 'When the "renewal still under review" reminder was sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholarship_renewals`
--

INSERT INTO `scholarship_renewals` (`id`, `scholar_id`, `academic_year`, `semester`, `gpa`, `residency_verified`, `enrollment_verified`, `documents_complete`, `status`, `remarks`, `evaluated_by`, `evaluated_at`, `created_at`) VALUES
(1, 1, '2026-2027', '2nd Semester', 1.00, 1, 0, 0, 'Eligible for Renewal', '', 2, '2026-09-16 23:10:49', '2026-09-16 15:10:49');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(10) UNSIGNED NOT NULL,
  `school_name` varchar(200) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `school_name`, `address`, `is_active`, `created_at`) VALUES
(1, 'Carlos Hilado Memorial State University', 'Talisay City, Negros Occidental', 1, '2026-09-15 05:50:39'),
(2, 'University of Negros Occidental - Recoletos', 'Bacolod City', 1, '2026-09-15 05:50:39'),
(3, 'University of St. La Salle', 'Bacolod City', 1, '2026-09-15 05:50:39'),
(4, 'Central Philippine Adventist College', 'Bacolod City', 1, '2026-09-15 05:50:39'),
(5, 'STI West Negros University', 'Bacolod City', 1, '2026-09-15 05:50:39'),
(6, 'Bacolod City College', 'Bacolod City', 1, '2026-09-15 05:50:39'),
(7, 'Negros Oriental State University', 'Dumaguete City', 1, '2026-09-15 05:50:39'),
(8, 'CHMSU - Binalbagan Campus', 'Binalbagan, Negros Occidental', 1, '2026-09-15 05:50:39'),
(9, 'Carlos Hilado Memorial State University - Fortune Towne Campus', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(10, 'Carlos Hilado Memorial State University - Alijis Campus', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(11, 'Negros Occidental Language and Information Technology Center (NOLITC)', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(12, 'Colegio San Agustin - Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(13, 'La Consolacion College - Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(14, 'Our Lady of Mercy College - Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(15, 'Sacred Heart Seminary School of Philosophy', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(16, 'West Negros University', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(17, 'National University Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(18, 'Riverside College', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(19, 'John B. Lacson Colleges Foundation - Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(20, 'VMA Global College', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(21, 'AMA Computer College - Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(22, 'ABE International Business College - Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(23, 'College of Arts & Sciences of Asia & the Pacific - Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(24, 'Mapua Malayan Digital College - Learning Hub Bacolod', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(25, 'Bacolod Christian College of Negros', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00'),
(26, 'Convention Baptist Bible College', 'Bacolod City, Negros Occidental', 1, '2026-09-24 16:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','int','bool','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'site_name', 'SK Scholarship MIS - Barangay Estefania', 'string', 'Site title', '2026-09-14 17:49:44'),
(2, 'barangay_name', 'Barangay Estefania', 'string', 'Barangay name', '2026-09-14 17:49:44'),
(3, 'max_file_size_mb', '5', 'int', 'Maximum upload size in MB', '2026-09-14 17:49:44'),
(4, 'allowed_doc_types', 'pdf,jpg,jpeg,png', 'string', 'Allowed document file types', '2026-09-14 17:49:44'),
(5, 'email_notifications', '1', 'bool', 'Enable email notifications', '2026-09-14 17:49:44'),
(6, 'interview_reminder_hours', '24', 'int', 'Hours before interview to send reminder', '2026-09-14 17:49:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `verify_token` varchar(64) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` tinyint(4) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `password_hash`, `contact_number`, `profile_picture`, `is_active`, `email_verified`, `verify_token`, `reset_token`, `reset_expires`, `last_login`, `failed_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 4, 'Mark Jim Balintaculo', 'markjimb21@gmail.com', '$2y$10$S/ejb4lZh4Yx4JCEu0Q5BeLi1NgIUj4uPNxFogPgDbpveYeTzqtWO', '', NULL, 1, 1, NULL, NULL, NULL, '2026-09-25 19:28:24', 0, NULL, '2026-09-14 17:50:28', '2026-09-25 11:28:24'),
(2, 1, 'Admin', 'admin@gmail.com', '$2y$10$LMuceB1iratZxXk/2yasXOBzEn6URZMvoPfnf.1NpS.xKGzyJISVq', NULL, NULL, 1, 1, NULL, NULL, NULL, '2026-09-25 23:10:45', 0, NULL, '2026-09-16 10:13:03', '2026-09-25 15:10:45'),
(10, 2, 'staff', 'staff@gmail.com', '$2y$10$9b7Je8LDKW7BV2MLWjl1u.OC1CT/9ZA9wA.NRceony5O0bMKwCT6i', '09123767812', NULL, 1, 0, NULL, NULL, NULL, '2026-09-25 17:57:25', 0, NULL, '2026-09-25 09:57:06', '2026-09-25 09:57:25'),
(11, 3, 'Official', 'official@gmail.com', '$2y$10$tYBw0N.0Tl0.B5JXGUAi8eOghiN1ihHDcwog12q7upHWvDJiNEdre', '12312312312', NULL, 1, 0, NULL, NULL, NULL, '2026-09-25 18:00:28', 0, NULL, '2026-09-25 10:00:10', '2026-09-25 10:00:28'),
(13, 4, 'Juan Dela Cruz', 'cscmjb1@gmail.com', '$2y$10$Suw.sz2xK8PXcE9CLB6jQepKPQ3Wgys8L5HwHaYMmYCgC3O2qYIM2', NULL, NULL, 1, 1, NULL, NULL, NULL, '2026-09-25 23:16:04', 0, NULL, '2026-09-25 11:29:38', '2026-09-25 15:16:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_records`
--
ALTER TABLE `academic_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_scholar_year` (`scholar_id`,`academic_year`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `application_code` (`application_code`),
  ADD KEY `idx_appcode` (`application_code`);

--
-- Indexes for table `applicants_personal_information`
--
ALTER TABLE `applicants_personal_information`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `applicant_id` (`applicant_id`),
  ADD KEY `purok_id` (`purok_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_code` (`application_code`),
  ADD UNIQUE KEY `uniq_app_per_program` (`applicant_id`,`program_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `decided_by` (`decided_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_program` (`program_id`),
  ADD KEY `fk_applications_review_remarks_by` (`review_remarks_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_budget` (`program_id`,`academic_year`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_doc_per_app` (`application_id`,`document_type`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_app` (`application_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `education_records`
--
ALTER TABLE `education_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `email_otp_codes`
--
ALTER TABLE `email_otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_purpose` (`user_id`,`purpose`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `family_background`
--
ALTER TABLE `family_background`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `interview_assignments`
--
ALTER TABLE `interview_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `idx_schedule` (`schedule_id`);

--
-- Indexes for table `interview_evaluations`
--
ALTER TABLE `interview_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assignment_id` (`assignment_id`),
  ADD KEY `interviewer_id` (`interviewer_id`);

--
-- Indexes for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`interview_date`),
  ADD KEY `idx_program` (`program_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `puroks`
--
ALTER TABLE `puroks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_barangay` (`barangay_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `scholars`
--
ALTER TABLE `scholars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `scholar_code` (`scholar_code`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `scholarship_programs`
--
ALTER TABLE `scholarship_programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_year` (`academic_year`);

--
-- Indexes for table `scholarship_releases`
--
ALTER TABLE `scholarship_releases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `release_code` (`release_code`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `idx_scholar` (`scholar_id`),
  ADD KEY `idx_year` (`academic_year`);

--
-- Indexes for table `scholarship_renewals`
--
ALTER TABLE `scholarship_renewals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_renewal` (`scholar_id`,`academic_year`,`semester`),
  ADD KEY `evaluated_by` (`evaluated_by`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_records`
--
ALTER TABLE `academic_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `applicants_personal_information`
--
ALTER TABLE `applicants_personal_information`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=242;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `education_records`
--
ALTER TABLE `education_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `email_otp_codes`
--
ALTER TABLE `email_otp_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `family_background`
--
ALTER TABLE `family_background`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `interview_assignments`
--
ALTER TABLE `interview_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `interview_evaluations`
--
ALTER TABLE `interview_evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `puroks`
--
ALTER TABLE `puroks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `scholars`
--
ALTER TABLE `scholars`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `scholarship_programs`
--
ALTER TABLE `scholarship_programs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `scholarship_releases`
--
ALTER TABLE `scholarship_releases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `scholarship_renewals`
--
ALTER TABLE `scholarship_renewals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_records`
--
ALTER TABLE `academic_records`
  ADD CONSTRAINT `academic_records_ibfk_1` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `academic_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `applicants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applicants_personal_information`
--
ALTER TABLE `applicants_personal_information`
  ADD CONSTRAINT `applicants_personal_information_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applicants_personal_information_ibfk_2` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`id`);

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`),
  ADD CONSTRAINT `applications_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `applications_ibfk_4` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_applications_review_remarks_by` FOREIGN KEY (`review_remarks_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `education_records`
--
ALTER TABLE `education_records`
  ADD CONSTRAINT `education_records_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `education_records_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `education_records_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

--
-- Constraints for table `email_otp_codes`
--
ALTER TABLE `email_otp_codes`
  ADD CONSTRAINT `email_otp_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `family_background`
--
ALTER TABLE `family_background`
  ADD CONSTRAINT `family_background_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interview_assignments`
--
ALTER TABLE `interview_assignments`
  ADD CONSTRAINT `interview_assignments_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `interview_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interview_assignments_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interview_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `interview_evaluations`
--
ALTER TABLE `interview_evaluations`
  ADD CONSTRAINT `interview_evaluations_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `interview_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interview_evaluations_ibfk_2` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD CONSTRAINT `interview_schedules_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`),
  ADD CONSTRAINT `interview_schedules_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `puroks`
--
ALTER TABLE `puroks`
  ADD CONSTRAINT `puroks_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);

--
-- Constraints for table `scholars`
--
ALTER TABLE `scholars`
  ADD CONSTRAINT `scholars_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`),
  ADD CONSTRAINT `scholars_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  ADD CONSTRAINT `scholars_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`);

--
-- Constraints for table `scholarship_releases`
--
ALTER TABLE `scholarship_releases`
  ADD CONSTRAINT `scholarship_releases_ibfk_1` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scholarship_releases_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `scholarship_renewals`
--
ALTER TABLE `scholarship_renewals`
  ADD CONSTRAINT `scholarship_renewals_ibfk_1` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scholarship_renewals_ibfk_2` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
