-- SK EduKabataan: Educational Assistance for College Students — full database dump (schema + sample data)
-- Matches docs/PROCESS.md: includes every migration up to the 2026_10_*.sql files.
-- Fresh install:  mysql -u root sk_scholarship < database/sk_scholarship.sql
-- Existing database: do NOT import this; run the files in database/migrations/ instead.

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sk_scholarship
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `applicants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants`
--

LOCK TABLES `applicants` WRITE;
/*!40000 ALTER TABLE `applicants` DISABLE KEYS */;
INSERT INTO `applicants` VALUES (1,1,'2026-09-14 17:50:28','2026-09-14 17:50:28'),(2,2,'2026-09-16 10:13:03','2026-09-16 10:13:03'),(8,13,'2026-09-25 11:29:38','2026-09-25 11:29:38');
/*!40000 ALTER TABLE `applicants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicants_personal_information`
--

DROP TABLE IF EXISTS `applicants_personal_information`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicants_personal_information` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` int(10) unsigned NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `complete_address` text NOT NULL,
  `purok_id` int(10) unsigned DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `religion` varchar(80) DEFAULT NULL,
  `citizenship` varchar(50) DEFAULT 'Filipino',
  `sex` enum('Female','Male') DEFAULT NULL COMMENT 'Optional; analytics only',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `applicant_id` (`applicant_id`),
  KEY `purok_id` (`purok_id`),
  CONSTRAINT `applicants_personal_information_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applicants_personal_information_ibfk_2` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants_personal_information`
--

LOCK TABLES `applicants_personal_information` WRITE;
/*!40000 ALTER TABLE `applicants_personal_information` DISABLE KEYS */;
INSERT INTO `applicants_personal_information` VALUES (1,1,'123','adf','adf','adf','2002-05-21','aadf',12,'adsffad',NULL,NULL,NULL,NULL,'Filipino',NULL,'2026-09-16 07:36:08'),(17,8,'Juan','','Dela Cruz','Jr.','2001-01-01','Kung Saan Si Rene',48,'09123456789',NULL,NULL,'O+','ROMAN CATHOLIC','Filipino',NULL,'2026-09-25 12:18:23');
/*!40000 ALTER TABLE `applicants_personal_information` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_code` varchar(30) NOT NULL,
  `applicant_id` int(10) unsigned NOT NULL,
  `program_id` int(10) unsigned NOT NULL,
  `status` enum('Draft','Submitted','Returned','Qualified','Interview Scheduled','Interviewed','Approved','Waitlisted','Rejected') NOT NULL DEFAULT 'Draft',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `qualified_at` datetime DEFAULT NULL COMMENT 'When screening qualified it (analytics: reached Qualified)',
  `decided_at` datetime DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decision_remarks` text DEFAULT NULL,
  `waitlisted_at` datetime DEFAULT NULL COMMENT 'When it was waitlisted (analytics: reached Waitlisted)',
  `review_remarks` text DEFAULT NULL COMMENT 'Reason/remarks entered by staff when marking the application Incomplete',
  `review_remarks_at` datetime DEFAULT NULL COMMENT 'When the application was last marked Incomplete',
  `review_remarks_by` int(10) unsigned DEFAULT NULL COMMENT 'User (admin/staff) who last marked the application Incomplete',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `flagged_fields` text DEFAULT NULL COMMENT 'JSON list of fields/documents flagged when returned',
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_code` (`application_code`),
  UNIQUE KEY `uniq_app_per_program` (`applicant_id`,`program_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `decided_by` (`decided_by`),
  KEY `idx_status` (`status`),
  KEY `idx_program` (`program_id`),
  KEY `fk_applications_review_remarks_by` (`review_remarks_by`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`),
  CONSTRAINT `applications_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `applications_ibfk_4` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_applications_review_remarks_by` FOREIGN KEY (`review_remarks_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applications`
--

LOCK TABLES `applications` WRITE;
/*!40000 ALTER TABLE `applications` DISABLE KEYS */;
INSERT INTO `applications` VALUES (1,'APP-2026-B44B7C11',1,1,'Approved','2026-09-16 21:24:35',NULL,NULL,'2026-09-16 21:24:35','2026-09-25 16:03:25',2,'asdf',NULL,NULL,NULL,NULL,'2026-09-16 07:04:38','2026-09-25 08:03:25',NULL),(10,'APP-2026-1FABFEF3',8,1,'Rejected','2026-09-25 20:46:51',NULL,NULL,NULL,'2026-09-25 23:23:18',2,'Wala kanin bossing?',NULL,'Please upload your real profile picture','2026-09-25 20:45:20',2,'2026-09-25 11:31:09','2026-09-25 15:23:18',NULL);
/*!40000 ALTER TABLE `applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=242 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,'register','users',1,'New applicant registered: markjimb21@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-14 17:50:28'),(2,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-14 17:50:55'),(3,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-15 04:42:19'),(4,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-15 04:55:17'),(5,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-15 04:55:48'),(6,1,'application_step_saved','applicants',1,'Saved step: personal',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-15 04:59:01'),(7,1,'application_step_saved','applicants',1,'Saved step: education',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-15 04:59:37'),(8,1,'application_step_saved','applicants',1,'Saved step: family',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-15 05:01:13'),(9,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 06:39:52'),(10,1,'application_step_saved','applicants',1,'Saved step: personal',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 07:04:38'),(11,1,'application_step_saved','applicants',1,'Saved step: personal',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 07:36:08'),(12,1,'application_submitted','applications',1,'Application submitted',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 07:36:20'),(13,1,'document_uploaded','documents',NULL,'Uploaded Profile Picture for application #APP-2026-B44B7C11',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 07:58:02'),(14,1,'document_uploaded','documents',NULL,'Uploaded Form 138 for application #APP-2026-B44B7C11',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 07:58:50'),(15,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:12:19'),(16,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:12:28'),(17,NULL,'register','users',2,'New applicant registered: admin@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:13:03'),(18,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:17:35'),(19,2,'application_status_changed','applications',1,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:18:27'),(20,2,'document_rejected','documents',3,'Document Form 138 set to Rejected','{\"status\":\"Submitted\"}','{\"status\":\"Rejected\",\"remarks\":\"asdfad\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:20:27'),(21,2,'document_verified','documents',1,'Document Profile Picture set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"adfdf\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:20:37'),(22,2,'logout','users',2,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:20:43'),(23,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:20:49'),(24,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:21:17'),(25,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 10:21:49'),(26,2,'schedule_created','interview_schedules',1,'Created interview schedule for 2026-09-18',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:09:39'),(27,2,'logout','users',2,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:17:23'),(28,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:17:58'),(29,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:18:53'),(30,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:18:59'),(31,2,'schedule_updated','interview_schedules',1,'Updated schedule','{\"id\":1,\"program_id\":1,\"interview_date\":\"2026-09-18\",\"start_time\":\"09:00:00\",\"end_time\":\"10:00:00\",\"venue\":\"Barangay Estefania\",\"interviewer\":\"SK committee\",\"max_slots\":50,\"status\":\"Open\",\"remarks\":\"afasf\",\"created_by\":2,\"created_at\":\"2026-09-16 20:09:39\",\"assigned_count\":0,\"program_name\":\"SK College Scholarship Program 2026\",\"academic_year\":\"2025-2026\"}','{\"interview_date\":\"2026-09-18\",\"start_time\":\"09:00:00\",\"end_time\":\"10:00:00\",\"venue\":\"Barangay Estefania\",\"interviewer\":\"SK committee\",\"max_slots\":50,\"status\":\"Open\",\"remarks\":\"afasf\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:21:22'),(32,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:22:46'),(33,1,'application_submitted','applications',1,'Application submitted',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:23:32'),(34,2,'application_status_changed','applications',1,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:23:40'),(35,2,'admin_override','applications',1,'Admin override enabled',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:31:09'),(36,2,'document_rejected','documents',3,'Document Form 138 set to Rejected','{\"status\":\"Rejected\"}','{\"status\":\"Rejected\",\"remarks\":\"asdfasdf\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:31:52'),(37,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 12:54:29'),(38,2,'application_status_changed','applications',1,'Status changed to Incomplete','{\"status\":\"Under Initial Review\"}','{\"status\":\"Incomplete\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 13:23:56'),(39,1,'application_submitted','applications',1,'Application submitted',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 13:24:35'),(40,2,'application_status_changed','applications',1,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 13:31:16'),(41,1,'document_uploaded','documents',NULL,'Uploaded Form 138 for application #APP-2026-B44B7C11',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:15:23'),(42,1,'document_uploaded','documents',NULL,'Uploaded School ID for application #APP-2026-B44B7C11',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:15:43'),(43,1,'document_uploaded','documents',NULL,'Uploaded Certificate of Residency for application #APP-2026-B44B7C11',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:16:04'),(44,1,'document_uploaded','documents',NULL,'Uploaded Enrollment Form for application #APP-2026-B44B7C11',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:16:11'),(45,2,'document_verified','documents',3,'Document Form 138 set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:16:33'),(46,2,'document_verified','documents',5,'Document School ID set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:16:41'),(47,2,'document_verified','documents',2,'Document Certificate of Residency set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:16:51'),(48,2,'document_verified','documents',4,'Document Enrollment Form set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:16:58'),(49,2,'application_status_changed','applications',1,'Status changed to Documents Verified','{\"status\":\"Under Initial Review\"}','{\"status\":\"Documents Verified\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:17:00'),(50,2,'application_status_changed','applications',1,'Status changed to For Interview','{\"status\":\"Documents Verified\"}','{\"status\":\"For Interview\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:18:42'),(51,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:19:14'),(52,2,'schedule_cancelled','interview_schedules',1,'Cancelled schedule',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:26:20'),(53,2,'schedule_created','interview_schedules',2,'Created interview schedule for 2026-09-17',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:26:56'),(54,2,'application_status_changed','applications',1,'Status changed to Interview Scheduled','{\"status\":\"For Interview\"}','{\"status\":\"Interview Scheduled\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:27:03'),(55,2,'interview_assigned','interview_assignments',1,'Assigned applicant to schedule #2',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:27:03'),(56,2,'evaluation_saved','interview_evaluations',1,'Evaluation saved, total=50',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:28:29'),(57,2,'application_status_changed','applications',1,'Status changed to For Final Evaluation','{\"status\":\"Interview Scheduled\"}','{\"status\":\"For Final Evaluation\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:28:29'),(58,2,'scholar_created','scholars',1,'Scholar created from application #APP-2026-B44B7C11',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:59:21'),(59,2,'application_approved','applications',1,'Application approved',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:59:21'),(60,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 14:59:36'),(61,2,'scholar_status_changed','scholars',1,'Status: Active → Active','{\"status\":\"Active\"}','{\"status\":\"Active\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 15:00:12'),(62,2,'scholar_status_changed','scholars',1,'Status: Active → Active','{\"status\":\"Active\"}','{\"status\":\"Active\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 15:00:16'),(63,2,'scholar_status_changed','scholars',1,'Status: Active → Active','{\"status\":\"Active\"}','{\"status\":\"Active\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 15:00:38'),(64,2,'scholar_status_changed','scholars',1,'Status: Active → Active','{\"status\":\"Active\"}','{\"status\":\"Active\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 15:00:51'),(65,2,'academic_record_created','academic_records',1,'Added academic record for scholar #1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 15:07:53'),(66,2,'scholarship_released','scholarship_releases',1,'Released ₱3,000.00 to scholar #1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 15:08:40'),(67,2,'renewal_created','scholarship_renewals',1,'Renewal record for scholar #1 2026-2027',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 15:10:49'),(68,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-16 16:19:47'),(69,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-17 01:12:47'),(70,2,'report_generated','reports',NULL,'Generated Scholarship Applicant List as pdf',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-17 01:13:14'),(71,2,'report_generated','reports',NULL,'Generated Scholarship Applicant List as csv',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-17 01:13:31'),(72,2,'report_generated','reports',NULL,'Generated Scholarship Applicant List as csv',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-17 01:13:37'),(73,NULL,'register','users',3,'New applicant registered: jimrodrigo21@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-17 02:43:22'),(74,NULL,'login','users',3,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-17 02:43:41'),(75,NULL,'login','users',3,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 05:16:04'),(76,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 05:16:56'),(77,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 04:10:50'),(78,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 04:11:43'),(79,1,'document_viewed','documents',1,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 04:11:57'),(80,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 04:13:29'),(81,2,'report_generated','reports',NULL,'Generated Scholarship Applicant List as pdf',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 04:28:06'),(82,NULL,'register','users',4,'New applicant registered: jimrodrigo21@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 10:55:39'),(83,NULL,'registration_otp_sent','users',4,'OTP sent',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 10:55:43'),(84,NULL,'registration_otp_sent','users',4,'OTP sent',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 10:58:25'),(85,NULL,'registration_otp_sent','users',4,'OTP sent',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-22 12:19:12'),(86,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:05:40'),(87,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:05:46'),(88,NULL,'registration_otp_email_failed','users',4,'OTP generated, but e-mail delivery failed',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:05:56'),(89,NULL,'registration_otp_email_failed','users',4,'OTP generated, but e-mail delivery failed',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:07:23'),(90,NULL,'register','users',5,'New applicant registered: markjimb@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:07:49'),(91,NULL,'registration_otp_email_failed','users',5,'OTP generated, but e-mail delivery failed',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:07:53'),(92,NULL,'registration_otp_email_failed','users',5,'OTP generated, but e-mail delivery failed',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:09:23'),(93,NULL,'registration_otp_email_failed','users',5,'OTP generated, but e-mail delivery failed',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:12:40'),(94,NULL,'registration_otp_email_failed','users',5,'OTP generated, but e-mail delivery failed',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:16:02'),(95,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:16:39'),(96,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:17:01'),(97,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:57:22'),(98,NULL,'registration_otp_sent','users',5,'OTP generated and e-mailed successfully',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:57:43'),(99,NULL,'registration_otp_verified','users',5,'E-mail verified',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:58:21'),(100,NULL,'login','users',5,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 03:58:26'),(101,NULL,'application_step_saved','applicants',5,'Saved step: education',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 04:00:34'),(102,NULL,'login','users',5,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-23 06:49:08'),(103,NULL,'login','users',5,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 02:27:39'),(104,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 02:37:47'),(105,2,'schedule_created','interview_schedules',3,'Created interview schedule for 2026-09-26',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 02:38:31'),(106,NULL,'logout','users',5,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:07:50'),(107,NULL,'password_reset_otp_sent','users',1,'OTP generated and e-mailed successfully',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:08:42'),(108,NULL,'password_reset_completed','users',1,'Password reset via OTP',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:09:36'),(109,NULL,'password_changed_email_sent','users',1,'Password-changed confirmation e-mailed',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:09:42'),(110,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:09:49'),(111,2,'user_deleted','users',4,'Deleted user jimrodrigo21@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:12:59'),(112,2,'report_generated','reports',NULL,'Generated Waitlisted Applicants as pdf',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:15:27'),(113,2,'report_generated','reports',NULL,'Generated Waitlisted Applicants as pdf',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:15:39'),(114,1,'document_viewed','documents',3,'Viewed Form 138',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:17:38'),(115,1,'document_viewed','documents',5,'Viewed School ID',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:17:44'),(116,1,'document_viewed','documents',2,'Viewed Certificate of Residency',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:17:54'),(117,1,'document_viewed','documents',4,'Viewed Enrollment Form',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:18:04'),(118,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:43:23'),(119,2,'budget_created','budgets',2,'Budget created for program #1 (2026-2027)',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:45:30'),(120,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:46:43'),(121,NULL,'login','users',5,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 04:46:54'),(122,NULL,'logout','users',5,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:00:46'),(123,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:00:56'),(124,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:05:29'),(125,NULL,'login','users',5,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:05:38'),(126,NULL,'application_step_saved','applicants',5,'Saved step: personal',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:07:09'),(127,NULL,'application_step_saved','applicants',5,'Saved step: family',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:08:00'),(128,NULL,'application_submitted','applications',2,'Application submitted',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:09:39'),(129,2,'application_status_changed','applications',2,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:10:58'),(130,2,'application_status_changed','applications',2,'Status changed to Incomplete','{\"status\":\"Under Initial Review\"}','{\"status\":\"Incomplete\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:11:36'),(131,NULL,'application_submitted','applications',2,'Application submitted',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:14:47'),(132,2,'document_viewed','documents',1,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:16:07'),(133,2,'application_status_changed','applications',1,'Status changed to For Interview','{\"status\":\"Approved\"}','{\"status\":\"For Interview\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:18:15'),(134,2,'interview_unassigned','interview_assignments',1,'Removed assignment',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:18:23'),(135,2,'application_status_changed','applications',1,'Status changed to Interview Scheduled','{\"status\":\"For Interview\"}','{\"status\":\"Interview Scheduled\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:18:46'),(136,2,'interview_assigned','interview_assignments',2,'Assigned applicant to schedule #2',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:18:51'),(137,2,'application_status_changed','applications',1,'Status changed to For Interview','{\"status\":\"Interview Scheduled\"}','{\"status\":\"For Interview\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:18:57'),(138,2,'interview_unassigned','interview_assignments',2,'Removed assignment',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 05:19:01'),(150,2,'application_status_changed','applications',2,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:12:17'),(151,2,'application_marked_incomplete','applications',2,'Application marked incomplete: Submit your documents.','{\"status\":\"Under Initial Review\"}','{\"status\":\"Incomplete\",\"remarks\":\"Submit your documents.\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:12:59'),(152,NULL,'document_uploaded','documents',6,'Uploaded Profile Picture for application #APP-2026-C8745C91',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:25:59'),(153,NULL,'document_uploaded','documents',8,'Uploaded Form 138 for application #APP-2026-C8745C91',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:26:00'),(154,NULL,'document_uploaded','documents',10,'Uploaded School ID for application #APP-2026-C8745C91',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:26:01'),(155,NULL,'document_uploaded','documents',7,'Uploaded Certificate of Residency for application #APP-2026-C8745C91',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:26:03'),(156,NULL,'document_uploaded','documents',9,'Uploaded Enrollment Form for application #APP-2026-C8745C91',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:26:04'),(157,NULL,'document_viewed','documents',7,'Viewed Certificate of Residency',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:26:05'),(158,NULL,'application_resubmitted','applications',2,'Application resubmitted after incomplete status',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:26:41'),(159,2,'document_viewed','documents',6,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:27:51'),(160,2,'document_viewed','documents',8,'Viewed Form 138',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:27:57'),(161,2,'document_viewed','documents',7,'Viewed Certificate of Residency',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:28:00'),(162,2,'application_status_changed','applications',2,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:29:10'),(163,2,'document_verified','documents',6,'Document Profile Picture set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:29:33'),(164,2,'document_verified','documents',8,'Document Form 138 set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:32:33'),(165,2,'document_downloaded','documents',10,'Downloaded School ID',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:35:39'),(166,2,'document_verified','documents',10,'Document School ID set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:37:26'),(167,NULL,'login','users',8,'User logged in',NULL,NULL,'::1','curl/8.18.0','2026-09-25 06:46:49'),(168,NULL,'document_verified','documents',16,'Document Profile Picture set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":null}','::1','curl/8.18.0','2026-09-25 06:46:49'),(170,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:49:04'),(171,2,'document_verified','documents',7,'Document Certificate of Residency set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:49:16'),(172,2,'document_verified','documents',9,'Document Enrollment Form set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:49:32'),(173,2,'application_status_changed','applications',2,'Status changed to Documents Verified','{\"status\":\"Under Initial Review\"}','{\"status\":\"Documents Verified\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:50:21'),(174,2,'application_status_changed','applications',2,'Status changed to Documents Verified','{\"status\":\"Documents Verified\"}','{\"status\":\"Documents Verified\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:50:24'),(175,NULL,'document_viewed','documents',6,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:51:05'),(176,2,'application_status_changed','applications',2,'Status changed to For Interview','{\"status\":\"Documents Verified\"}','{\"status\":\"For Interview\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:55:42'),(177,2,'application_status_changed','applications',2,'Status changed to Interview Scheduled','{\"status\":\"For Interview\"}','{\"status\":\"Interview Scheduled\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:57:35'),(178,2,'interview_assigned','interview_assignments',3,'Assigned applicant to schedule #3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:57:41'),(179,2,'application_status_changed','applications',1,'Status changed to Interview Scheduled','{\"status\":\"For Interview\"}','{\"status\":\"Interview Scheduled\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:57:48'),(180,2,'interview_assigned','interview_assignments',4,'Assigned applicant to schedule #3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 06:57:53'),(181,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 07:48:03'),(182,2,'logout','users',2,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 07:48:13'),(183,NULL,'login','users',5,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 07:48:35'),(184,NULL,'logout','users',5,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 08:01:48'),(185,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 08:02:41'),(186,2,'application_status_changed','applications',1,'Status changed to Rejected','{\"status\":\"Interview Scheduled\"}','{\"status\":\"Rejected\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 08:03:25'),(187,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 08:03:51'),(188,NULL,'login','users',5,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 08:03:58'),(189,2,'application_status_changed','applications',2,'Status changed to Rejected','{\"status\":\"Interview Scheduled\"}','{\"status\":\"Rejected\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 08:04:14'),(190,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 09:50:23'),(191,2,'user_deleted','users',5,'Deleted user markjimb@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 09:55:16'),(192,2,'user_updated','users',1,'Updated user markjimb21@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 09:56:14'),(193,2,'user_created','users',10,'Created user staff@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 09:57:06'),(194,10,'login','users',10,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 09:57:25'),(195,2,'user_created','users',11,'Created user official@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:00:10'),(196,10,'logout','users',10,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:00:15'),(197,11,'login','users',11,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:00:28'),(198,11,'report_generated','reports',NULL,'Generated Waitlisted Applicants as pdf',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:00:37'),(199,11,'access_denied',NULL,NULL,'Denied GET admin/academic-records as official',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:01:06'),(200,2,'user_created','users',12,'Created user dummy@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:21:07'),(201,11,'logout','users',11,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:22:57'),(202,NULL,'registration_otp_sent','users',12,'OTP generated and e-mailed successfully',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 10:23:21'),(203,2,'user_deleted','users',12,'Deleted user dummy@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:27:14'),(204,2,'user_status_changed','users',1,'Deactivated user markjimb21@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:27:52'),(205,2,'user_status_changed','users',1,'Activated user markjimb21@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:28:20'),(206,1,'login','users',1,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:28:24'),(207,1,'logout','users',1,'User logged out',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:28:58'),(208,NULL,'register','users',13,'New applicant registered: cscmjb1@gmail.com',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:29:38'),(209,NULL,'registration_otp_sent','users',13,'OTP generated and e-mailed successfully',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:29:44'),(210,NULL,'registration_otp_verified','users',13,'E-mail verified',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:30:23'),(211,13,'login','users',13,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 11:30:52'),(212,13,'document_uploaded','documents',43,'Uploaded Profile Picture for application #APP-2026-1FABFEF3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:23:02'),(213,13,'document_viewed','documents',43,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:23:05'),(214,13,'document_uploaded','documents',45,'Uploaded Form 138 for application #APP-2026-1FABFEF3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:36:45'),(215,13,'document_uploaded','documents',47,'Uploaded School ID for application #APP-2026-1FABFEF3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:36:48'),(216,13,'document_uploaded','documents',44,'Uploaded Certificate of Residency for application #APP-2026-1FABFEF3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:36:50'),(217,13,'document_uploaded','documents',46,'Uploaded Enrollment Form for application #APP-2026-1FABFEF3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:36:50'),(218,13,'application_submitted','applications',10,'Application submitted',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:36:56'),(219,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:40:07'),(220,2,'document_viewed','documents',43,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:41:02'),(221,2,'document_rejected','documents',43,'Document Profile Picture set to Rejected','{\"status\":\"Submitted\"}','{\"status\":\"Rejected\",\"remarks\":\"provide your real picture\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:41:25'),(222,2,'document_rejected','documents',43,'Document Profile Picture set to Rejected','{\"status\":\"Rejected\"}','{\"status\":\"Rejected\",\"remarks\":\"provide your real picture\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:41:30'),(223,2,'document_resubmission_required','documents',43,'Document Profile Picture set to Resubmission Required','{\"status\":\"Rejected\"}','{\"status\":\"Resubmission Required\",\"remarks\":\"\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:41:47'),(224,13,'document_viewed','documents',43,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:42:32'),(225,2,'application_status_changed','applications',10,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:43:38'),(226,2,'application_marked_incomplete','applications',10,'Application marked incomplete: Please upload your real profile picture','{\"status\":\"Under Initial Review\"}','{\"status\":\"Incomplete\",\"remarks\":\"Please upload your real profile picture\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:45:20'),(227,13,'document_viewed','documents',43,'Viewed Profile Picture',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:45:57'),(228,13,'document_uploaded','documents',43,'Uploaded Profile Picture for application #APP-2026-1FABFEF3',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:46:07'),(229,13,'application_resubmitted','applications',10,'Application resubmitted after incomplete status',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:46:51'),(230,2,'application_status_changed','applications',10,'Status changed to Under Initial Review','{\"status\":\"Submitted\"}','{\"status\":\"Under Initial Review\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 12:51:59'),(231,2,'document_verified','documents',43,'Document Profile Picture set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 13:19:56'),(232,2,'document_verified','documents',44,'Document Certificate of Residency set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 13:19:56'),(233,2,'document_verified','documents',45,'Document Form 138 set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 13:19:56'),(234,2,'document_verified','documents',46,'Document Enrollment Form set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 13:19:56'),(235,2,'document_verified','documents',47,'Document School ID set to Verified','{\"status\":\"Submitted\"}','{\"status\":\"Verified\",\"remarks\":null}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 13:19:56'),(236,2,'documents_verify_all','applications',10,'Verified 5 document(s) in bulk',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 13:19:56'),(237,2,'login','users',2,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 15:10:45'),(238,2,'application_status_changed','applications',10,'Status changed to Documents Verified','{\"status\":\"Under Initial Review\"}','{\"status\":\"Documents Verified\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 15:11:07'),(239,13,'login','users',13,'User logged in',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 15:16:04'),(240,2,'application_status_changed','applications',10,'Status changed to Waitlisted','{\"status\":\"Documents Verified\"}','{\"status\":\"Waitlisted\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 15:21:33'),(241,2,'application_status_changed','applications',10,'Status changed to Rejected','{\"status\":\"Waitlisted\"}','{\"status\":\"Rejected\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 15:23:18');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int(10) unsigned NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `total_budget` decimal(14,2) NOT NULL,
  `released` decimal(14,2) DEFAULT 0.00,
  `remaining` decimal(14,2) GENERATED ALWAYS AS (`total_budget` - `released`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_budget` (`program_id`,`academic_year`),
  CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budgets`
--

LOCK TABLES `budgets` WRITE;
/*!40000 ALTER TABLE `budgets` DISABLE KEYS */;
INSERT INTO `budgets` VALUES (1,1,'2025-2026',500000.00,3000.00,497000.00,'2026-09-16 16:05:05'),(2,1,'2026-2027',300000.00,0.00,300000.00,'2026-09-25 04:45:30');
/*!40000 ALTER TABLE `budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_name` varchar(200) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'Bachelor of Science in Information Technology','BSIT',1,'2026-09-15 05:50:39'),(2,'Bachelor of Science in Computer Science','BSCS',1,'2026-09-15 05:50:39'),(3,'Bachelor of Science in Accountancy','BSA',1,'2026-09-15 05:50:39'),(4,'Bachelor of Science in Business Administration','BSBA',1,'2026-09-15 05:50:39'),(5,'Bachelor of Science in Civil Engineering','BSCE',1,'2026-09-15 05:50:39'),(6,'Bachelor of Science in Nursing','BSN',1,'2026-09-15 05:50:39'),(7,'Bachelor of Elementary Education','BEED',1,'2026-09-15 05:50:39'),(8,'Bachelor of Secondary Education','BSED',1,'2026-09-15 05:50:39'),(9,'Bachelor of Arts in Communication','ABCOM',1,'2026-09-15 05:50:39'),(10,'Bachelor of Science in Criminology','BSCrim',1,'2026-09-15 05:50:39');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `document_type` enum('Certificate of Residency','Form 138 / Grades','Enrollment Form','School ID','2x2 Photo') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `status` enum('Not Submitted','Submitted','Verified','Invalid') NOT NULL DEFAULT 'Submitted',
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Newest version of this type for the applicant',
  `superseded_at` datetime DEFAULT NULL,
  `invalid_reason` text DEFAULT NULL,
  `invalidated_by` int(10) unsigned DEFAULT NULL,
  `invalidated_at` datetime DEFAULT NULL,
  `file_removed_at` datetime DEFAULT NULL COMMENT 'File deleted because it was flagged invalid; the row is kept',
  `side` enum('Front','Back') DEFAULT NULL COMMENT 'Front/Back picture; NULL = one file with the whole document',
  PRIMARY KEY (`id`),
  KEY `idx_app` (`application_id`),
  KEY `idx_status` (`status`),
  KEY `idx_doc_current` (`document_type`,`is_current`),
  KEY `idx_doc_app_type` (`application_id`,`document_type`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,1,'2x2 Photo','storage/documents/1/Profile_Picture_1789545482.jpg','Messenger_creation_CF0C390E-F22E-41DA-A137-B65DA075CC4A.jpeg',40547,'image/jpeg','Verified','2026-09-16 15:58:02','adfdf',1,NULL,NULL,NULL,NULL,NULL,NULL),(2,1,'Certificate of Residency','storage/documents/1/Certificate_of_Residency_1789568164.jpg','Screenshot_20250819-213241.jpg',672809,'image/jpeg','Verified','2026-09-16 22:16:04','',1,NULL,NULL,NULL,NULL,NULL,NULL),(3,1,'Form 138 / Grades','storage/documents/1/Form_138_1789568123.jpg','FB_IMG_1759373623430.jpg',32898,'image/jpeg','Verified','2026-09-16 22:15:23','',1,NULL,NULL,NULL,NULL,NULL,NULL),(4,1,'Enrollment Form','storage/documents/1/Enrollment_Form_1789568171.jpg','Messenger_creation_CF0C390E-F22E-41DA-A137-B65DA075CC4A.jpeg',40547,'image/jpeg','Verified','2026-09-16 22:16:11','',1,NULL,NULL,NULL,NULL,NULL,NULL),(5,1,'School ID','storage/documents/1/School_ID_1789568143.jpg','d727289d-ecd1-47e3-ae77-56572b46a9cd.jpg',167494,'image/jpeg','Verified','2026-09-16 22:15:43','',1,NULL,NULL,NULL,NULL,NULL,NULL),(43,10,'2x2 Photo','storage/documents/10/c4c20c3c49477f04a7fef07bfcbea3e9.png','1000067256.png',1510666,'image/png','Verified','2026-09-25 20:46:07',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL),(44,10,'Certificate of Residency','storage/documents/10/297d1f241faec92b0af1cf32fa62d358.png','contact.png',113324,'image/png','Verified','2026-09-25 20:36:50',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL),(45,10,'Form 138 / Grades','storage/documents/10/5912f4c4ca4a742d7cd604339b50393a.png','seo.png',325507,'image/png','Verified','2026-09-25 20:36:45',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL),(46,10,'Enrollment Form','storage/documents/10/a11f8f9c09a131b74d448ce742ba2e36.png','responsive.png',302908,'image/png','Verified','2026-09-25 20:36:50',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL),(47,10,'School ID','storage/documents/10/0e9e22fb96f2ee65c4decc222a1551ea.png','og-image.png',196222,'image/png','Verified','2026-09-25 20:36:48',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `education_records`
--

DROP TABLE IF EXISTS `education_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `education_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` int(10) unsigned NOT NULL,
  `school_name` varchar(200) NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year','Other') NOT NULL,
  `year_level_other` varchar(50) DEFAULT NULL,
  `course_name` varchar(200) NOT NULL,
  `major` varchar(200) DEFAULT NULL COMMENT 'Optional major/specialization within the selected course',
  `general_average` decimal(4,2) DEFAULT NULL COMMENT 'Optional GWA, 1.00 (highest) to 5.00',
  `previous_scholarship` tinyint(1) DEFAULT 0,
  `previous_scholarship_details` varchar(255) DEFAULT NULL,
  `current_scholarship` tinyint(1) DEFAULT 0,
  `current_scholarship_details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `applicant_id` (`applicant_id`),
  CONSTRAINT `education_records_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `education_records`
--

LOCK TABLES `education_records` WRITE;
/*!40000 ALTER TABLE `education_records` DISABLE KEYS */;
INSERT INTO `education_records` VALUES (1,1,'Carlos Hilado Memorial State University','2nd Year','','313',NULL,NULL,0,'',0,'','2026-09-15 04:59:37'),(51,8,'Bacolod City College','1st Year','','Bachelor of Science in Information Technology',NULL,NULL,0,'',0,'','2026-09-25 12:36:08');
/*!40000 ALTER TABLE `education_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_otp_codes`
--

DROP TABLE IF EXISTS `email_otp_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_otp_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `purpose` enum('registration','password_reset') NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `resend_available_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_purpose` (`user_id`,`purpose`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `email_otp_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_otp_codes`
--

LOCK TABLES `email_otp_codes` WRITE;
/*!40000 ALTER TABLE `email_otp_codes` DISABLE KEYS */;
INSERT INTO `email_otp_codes` VALUES (11,1,'password_reset','$2y$10$XvYppHp2ZjnuYBuRiO9S6uKKt0fMlbEg3QcgK6iavezCGQF7SgYRG','2026-09-25 12:18:34',0,5,'2026-09-25 12:09:34','2026-09-25 12:09:17','2026-09-25 04:08:34'),(13,13,'registration','$2y$10$5RIRqZs0Ir/A70bo1xrh0O42F2uy9JQB05w3cbkKWlh9c4dkFA7h.','2026-09-25 19:39:38',0,5,'2026-09-25 19:30:38','2026-09-25 19:30:23','2026-09-25 11:29:38');
/*!40000 ALTER TABLE `email_otp_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_background`
--

DROP TABLE IF EXISTS `family_background`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_background` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` int(10) unsigned NOT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `father_age` int(10) unsigned DEFAULT NULL,
  `father_occupation` varchar(150) DEFAULT NULL,
  `father_address` text DEFAULT NULL,
  `father_contact` varchar(20) DEFAULT NULL,
  `father_income` decimal(12,2) DEFAULT 0.00,
  `father_status` enum('Available','Deceased','Unknown','Not Applicable') DEFAULT 'Available',
  `mother_name` varchar(150) DEFAULT NULL,
  `mother_age` int(10) unsigned DEFAULT NULL,
  `mother_occupation` varchar(150) DEFAULT NULL,
  `mother_address` text DEFAULT NULL,
  `mother_contact` varchar(20) DEFAULT NULL,
  `mother_income` decimal(12,2) DEFAULT 0.00,
  `mother_status` enum('Available','Deceased','Unknown','Not Applicable') DEFAULT 'Available',
  `total_family_income` decimal(12,2) DEFAULT 0.00,
  `family_size` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `applicant_id` (`applicant_id`),
  CONSTRAINT `family_background_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_background`
--

LOCK TABLES `family_background` WRITE;
/*!40000 ALTER TABLE `family_background` DISABLE KEYS */;
INSERT INTO `family_background` VALUES (1,1,'13123',123,'12313','12313','123',123.00,'Available','123',13,'qwe','12313','123',123.00,'Available',246.00,6,'2026-09-15 05:01:13'),(14,8,'Pedro Penduko',50,'Tambay','Purok Langis Barangay Banago Bacolod City Negros Occidental','09123456789',5000.00,'Available','Margie De Guzman',48,'Housewife','Murcia, Allegria','09208865485',6000.00,'Available',11000.00,4,'2026-09-25 12:17:34');
/*!40000 ALTER TABLE `family_background` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `name` varchar(120) NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_holiday_date` (`holiday_date`),
  KEY `fk_holidays_created_by` (`created_by`),
  CONSTRAINT `fk_holidays_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `interview_assignments`
--

DROP TABLE IF EXISTS `interview_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `schedule_id` int(10) unsigned NOT NULL,
  `application_id` int(10) unsigned NOT NULL,
  `attendance` enum('Scheduled','Attended','No-show') NOT NULL DEFAULT 'Scheduled',
  `reschedule_count` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Earlier no-shows of this application when this row was created (1 = this is the one reschedule)',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reminder_sent_at` datetime DEFAULT NULL COMMENT 'When the interview reminder was sent (NULL = not yet / reset after reschedule)',
  PRIMARY KEY (`id`),
  KEY `idx_schedule` (`schedule_id`),
  KEY `idx_application` (`application_id`),
  CONSTRAINT `interview_assignments_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `interview_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `interview_assignments_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `interview_assignments`
--

LOCK TABLES `interview_assignments` WRITE;
/*!40000 ALTER TABLE `interview_assignments` DISABLE KEYS */;
INSERT INTO `interview_assignments` VALUES (4,3,1,'Scheduled',0,'2026-09-25 06:57:48',NULL);
/*!40000 ALTER TABLE `interview_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `interview_evaluations`
--

DROP TABLE IF EXISTS `interview_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_evaluations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `financial_need_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `academic_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `communication_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `community_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `interviewer_id` int(10) unsigned DEFAULT NULL,
  `evaluated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_id` (`assignment_id`),
  KEY `interviewer_id` (`interviewer_id`),
  CONSTRAINT `interview_evaluations_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `interview_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `interview_evaluations_ibfk_2` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `interview_evaluations`
--

LOCK TABLES `interview_evaluations` WRITE;
/*!40000 ALTER TABLE `interview_evaluations` DISABLE KEYS */;
/*!40000 ALTER TABLE `interview_evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `interview_schedules`
--

DROP TABLE IF EXISTS `interview_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interview_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int(10) unsigned NOT NULL,
  `interview_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `venue` varchar(200) NOT NULL,
  `max_slots` int(10) unsigned DEFAULT 30,
  `status` enum('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_date` (`interview_date`),
  KEY `idx_program` (`program_id`),
  CONSTRAINT `interview_schedules_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`),
  CONSTRAINT `interview_schedules_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `interview_schedules`
--

LOCK TABLES `interview_schedules` WRITE;
/*!40000 ALTER TABLE `interview_schedules` DISABLE KEYS */;
INSERT INTO `interview_schedules` VALUES (1,1,'2026-09-18','09:00:00','10:00:00','Barangay Estefania',50,'Cancelled',2,'2026-09-16 12:09:39'),(2,1,'2026-09-17','10:00:00','12:00:00','Barangay Estefania',30,'Open',2,'2026-09-16 14:26:56'),(3,1,'2026-09-26','09:00:00','11:00:00','Barangay Estefania Hall',30,'Open',2,'2026-09-25 02:38:31');
/*!40000 ALTER TABLE `interview_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'Application Submitted','Your scholarship application has been submitted and is now under initial review.','success','http://localhost/sk-scholarship/public/applicant/dashboard',0,0,'2026-09-16 07:36:20'),(2,1,'Application Under Review','Your application is now being reviewed by the SK office.','info','http://localhost/sk-scholarship/public/applicant/dashboard',0,0,'2026-09-16 10:18:27'),(3,1,'Document Rejected ❌','Your Form 138 has been marked as: Rejected. Remarks: asdfad','danger','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-16 10:20:27'),(4,1,'Document Verified ✅','Your Profile Picture has been marked as: Verified. Remarks: adfdf','success','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-16 10:20:37'),(5,1,'Application Submitted','Your scholarship application has been submitted and is now under initial review.','success','http://localhost/sk-scholarship/public/applicant/dashboard',0,0,'2026-09-16 12:23:32'),(6,1,'Application Under Review','Your application is now being reviewed by the SK office.','info','http://localhost/sk-scholarship/public/applicant/dashboard',0,0,'2026-09-16 12:23:40'),(7,1,'Document Rejected ❌','Your Form 138 has been marked as: Rejected. Remarks: asdfasdf','danger','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-16 12:31:52'),(8,1,'Application Submitted','Your scholarship application has been submitted and is now under initial review.','success','http://localhost/sk-scholarship/public/applicant/dashboard',0,0,'2026-09-16 13:24:35'),(9,1,'Application Under Review','Your application is now being reviewed by the SK office.','info','http://localhost/sk-scholarship/public/applicant/dashboard',0,0,'2026-09-16 13:31:16'),(10,1,'Document Verified ✅','Your Form 138 has been marked as: Verified.','success','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-16 14:16:33'),(11,1,'Document Verified ✅','Your School ID has been marked as: Verified.','success','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-16 14:16:41'),(12,1,'Document Verified ✅','Your Certificate of Residency has been marked as: Verified.','success','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-16 14:16:51'),(13,1,'Document Verified ✅','Your Enrollment Form has been marked as: Verified.','success','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-16 14:16:58'),(14,1,'Eligible for Interview','You are now eligible for an interview. Please wait for scheduling.','success','http://localhost/sk-scholarship/public/applicant/interview',0,0,'2026-09-16 14:18:42'),(15,1,'Interview Scheduled 📅','Your interview has been scheduled on 2026-09-17 at 10:00 AM at Barangay Estefania. Interviewer: SK committee.','success','http://localhost/sk-scholarship/public/applicant/interview',0,0,'2026-09-16 14:27:03'),(16,1,'Interview Result Recorded','Your interview has been evaluated. Please wait for the final scholarship decision.','info','http://localhost/sk-scholarship/public/applicant/interview',0,0,'2026-09-16 14:28:29'),(17,1,'Congratulations — Scholarship Approved! 🎉','Your scholarship application has been approved. Welcome, SK Scholar!','success','http://localhost/sk-scholarship/public/applicant/scholarship',0,0,'2026-09-16 14:59:21'),(18,1,'Scholarship Status: Active','Your scholar status has been updated to: Active','success','http://localhost/sk-scholarship/public/applicant/scholarship',0,0,'2026-09-16 15:00:12'),(19,1,'Scholarship Status: Active','Your scholar status has been updated to: Active','success','http://localhost/sk-scholarship/public/applicant/scholarship',0,0,'2026-09-16 15:00:16'),(20,1,'Scholarship Status: Active','Your scholar status has been updated to: Active','success','http://localhost/sk-scholarship/public/applicant/scholarship',0,0,'2026-09-16 15:00:38'),(21,1,'Scholarship Status: Active','Your scholar status has been updated to: Active','success','http://localhost/sk-scholarship/public/applicant/scholarship',0,0,'2026-09-16 15:00:51'),(22,1,'Scholarship Released 💵','₱3,000.00 was released to you for 2025-2026 1st Semester.','success','http://localhost/sk-scholarship/public/applicant/scholarship',0,0,'2026-09-16 15:08:40'),(30,1,'Interview Assignment Removed','Your interview assignment has been removed. You will be notified when a new schedule is set.','warning','http://localhost/sk-scholarship/public/applicant/interview',0,1,'2026-09-25 05:18:15'),(31,1,'Interview Scheduled 📅','Your interview has been scheduled on 2026-09-17 at 10:00 AM at Barangay Estefania. Interviewer: SK committee.','success','http://localhost/sk-scholarship/public/applicant/interview',0,1,'2026-09-25 05:18:46'),(32,1,'Interview Assignment Removed','Your interview assignment has been removed. You will be notified when a new schedule is set.','warning','http://localhost/sk-scholarship/public/applicant/interview',0,1,'2026-09-25 05:18:57'),(51,1,'Interview Scheduled 📅','Your interview has been scheduled on 2026-09-26 at 9:00 AM at Barangay Estefania Hall. Interviewer: SK committee.','success','http://localhost/sk-scholarship/public/applicant/interview',0,1,'2026-09-25 06:57:48'),(52,1,'Application Not Approved','We regret to inform you that your scholarship application was not approved. asdf','danger','http://localhost/sk-scholarship/public/applicant/dashboard',0,1,'2026-09-25 08:03:25'),(54,13,'Account Created','Your SK Scholarship account has been created. Verify your e-mail to activate it.','info',NULL,0,0,'2026-09-25 11:29:38'),(55,13,'Email Verified','Your e-mail has been verified. Your SK Scholarship account is now active.','success','http://localhost/sk-scholarship/public/login',0,0,'2026-09-25 11:30:23'),(56,13,'Application Submitted','Your scholarship application has been submitted and is now under initial review.','success','http://localhost/sk-scholarship/public/applicant/dashboard',0,1,'2026-09-25 12:36:56'),(57,13,'Document Rejected ❌','Your Profile Picture has been marked as: Rejected. Remarks: provide your real picture','danger','http://localhost/sk-scholarship/public/applicant/documents',0,1,'2026-09-25 12:41:25'),(58,13,'Document Rejected ❌','Your Profile Picture has been marked as: Rejected. Remarks: provide your real picture','danger','http://localhost/sk-scholarship/public/applicant/documents',0,0,'2026-09-25 12:41:30'),(59,13,'Resubmission Required ⚠️','Your Profile Picture has been marked as: Resubmission Required.','warning','http://localhost/sk-scholarship/public/applicant/documents',0,1,'2026-09-25 12:41:47'),(60,13,'Application Under Review','Your application is now being reviewed by the SK office.','info','http://localhost/sk-scholarship/public/applicant/dashboard',0,1,'2026-09-25 12:43:38'),(61,13,'Application Marked Incomplete','Your scholarship application has been marked incomplete. Please review the remarks and update your application. Remarks: Please upload your real profile picture','warning','http://localhost/sk-scholarship/public/applicant/application',0,1,'2026-09-25 12:45:20'),(62,13,'Application Resubmitted','Your application has been resubmitted and is now awaiting review by the SK office.','success','http://localhost/sk-scholarship/public/applicant/dashboard',0,1,'2026-09-25 12:46:51'),(63,13,'Application Under Review','Your application is now being reviewed by the SK office.','info','http://localhost/sk-scholarship/public/applicant/dashboard',0,1,'2026-09-25 12:51:59'),(64,13,'Documents Verified ✅','5 of your submitted document(s) have been verified by the SK office.','success','http://localhost/sk-scholarship/public/applicant/documents',0,1,'2026-09-25 13:19:56'),(65,13,'Documents Verified','All your submitted documents have been verified by the SK office.','success','http://localhost/sk-scholarship/public/applicant/documents',0,1,'2026-09-25 15:11:07'),(66,13,'Waitlisted','You have been placed on the waitlist. We will notify you if a slot opens.','warning','http://localhost/sk-scholarship/public/applicant/dashboard',0,1,'2026-09-25 15:21:33'),(67,13,'Application Not Approved','We regret to inform you that your scholarship application was not approved. Wala kanin bossing?','danger','http://localhost/sk-scholarship/public/applicant/dashboard',0,1,'2026-09-25 15:23:18');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `puroks`
--

DROP TABLE IF EXISTS `puroks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `puroks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purok_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `puroks`
--

LOCK TABLES `puroks` WRITE;
/*!40000 ALTER TABLE `puroks` DISABLE KEYS */;
INSERT INTO `puroks` VALUES (1,'Arao'),(2,'Arabay 1'),(3,'Arabay 2'),(4,'Bagong Silang'),(5,'Bethany Court'),(6,'Buena Royale'),(7,'Camelot Residences'),(8,'Camelot Village'),(9,'Camingawan Proper'),(10,'Capitol Hills Subdivision'),(11,'Celine Homes Subdivision'),(12,'City Ville Subdivision'),(13,'Country Homes Subdivision Phase 1'),(14,'Country Homes Subdivision Phase 2'),(15,'Country Homes Subdivision Phase 3'),(16,'East Homes 1'),(17,'East Homes 2'),(18,'East Homes 3'),(19,'Elsa'),(20,'Escuerdo'),(21,'Estefania Proper'),(22,'Flora'),(23,'Fortune Towne - B'),(24,'Fortune Towne Subdivision'),(25,'Goldah'),(26,'Glenwood Residences'),(27,'Greensville 1 Subdivision'),(28,'Greensville 4 Subdivision'),(29,'Jesusa Heights Subdivision'),(30,'Kaburihan'),(31,'Kasoy'),(32,'La Herencia'),(33,'Lopues Village'),(34,'Luisville Subdivision'),(35,'Mayang'),(36,'Meadows of Camelot'),(37,'Pag-asa'),(38,'Paho 1'),(39,'Paho 2'),(40,'Paraiso'),(41,'Pedring'),(42,'Pequiño'),(43,'Providence Negros'),(44,'Sagrado 2'),(45,'Sambag'),(46,'Sunshine Valley Subdivision'),(47,'The Palisades'),(48,'Villa Alexandra 1'),(49,'Villa Alexandra 2'),(50,'Villa Angeles'),(51,'Villa Estefania'),(52,'Villa Felicidad'),(53,'Villa Soledad'),(54,'Villa Villeta'),(55,'Villamar');
/*!40000 ALTER TABLE `puroks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `record_history`
--

DROP TABLE IF EXISTS `record_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `record_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(64) NOT NULL,
  `record_id` int(10) unsigned NOT NULL,
  `applicant_id` int(10) unsigned DEFAULT NULL,
  `field` varchar(64) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_history_record` (`table_name`,`record_id`),
  KEY `idx_history_applicant` (`applicant_id`,`changed_at`),
  KEY `fk_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `record_history`
--

LOCK TABLES `record_history` WRITE;
/*!40000 ALTER TABLE `record_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `record_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `renewal_requests`
--

DROP TABLE IF EXISTS `renewal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `renewal_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scholar_id` int(10) unsigned NOT NULL,
  `from_term_id` int(10) unsigned DEFAULT NULL COMMENT 'Semester the request was made from (NULL only for migrated history)',
  `academic_year` varchar(20) NOT NULL COMMENT 'Target semester',
  `semester` enum('1st Semester','2nd Semester') NOT NULL,
  `status` enum('Submitted','Resubmit','Approved') NOT NULL DEFAULT 'Submitted',
  `remarks` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year','Other') DEFAULT NULL COMMENT 'Year level for the target semester',
  `year_level_other` varchar(50) DEFAULT NULL,
  `program_id` int(10) unsigned NOT NULL COMMENT 'Program the renewal goes INTO (owner of the renewal window)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_renewal_target` (`scholar_id`,`academic_year`,`semester`),
  UNIQUE KEY `uniq_renewal_from_term` (`from_term_id`),
  KEY `idx_renewal_status` (`status`),
  KEY `fk_renewals_reviewed_by` (`reviewed_by`),
  KEY `idx_renewals_program` (`program_id`,`status`),
  CONSTRAINT `fk_renewals_from_term` FOREIGN KEY (`from_term_id`) REFERENCES `scholar_terms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_renewals_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`),
  CONSTRAINT `fk_renewals_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_renewals_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `renewal_requests`
--

LOCK TABLES `renewal_requests` WRITE;
/*!40000 ALTER TABLE `renewal_requests` DISABLE KEYS */;
INSERT INTO `renewal_requests` VALUES (1,1,1,'2026-2027','2nd Semester','Approved',NULL,'2026-09-16 23:10:49',2,'2026-09-16 23:10:49','2026-09-16 15:10:49',NULL,NULL,1);
/*!40000 ALTER TABLE `renewal_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','SK Administrator - full access','2026-09-14 17:49:44'),(4,'applicant','College student applicant','2026-09-14 17:49:44'),(5,'reviewer','Screening, interviews and decisions','2026-10-03 18:07:22'),(6,'treasurer','Budget, claims, releases and renewals','2026-10-03 18:07:22');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholar_terms`
--

DROP TABLE IF EXISTS `scholar_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholar_terms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scholar_id` int(10) unsigned NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL,
  `opened_via` enum('approval','renewal','continuation') NOT NULL COMMENT 'continuation = 2nd Semester opened by the 1st Semester release',
  `claim_status` enum('Waiting for Requirements','Submitted','Resubmit','Verified','Released') NOT NULL DEFAULT 'Waiting for Requirements',
  `gwa` decimal(4,2) DEFAULT NULL COMMENT 'GWA read from the Grades by the admin when verifying the claim',
  `remarks` text DEFAULT NULL COMMENT 'Admin remarks when asking to resubmit',
  `submitted_at` datetime DEFAULT NULL COMMENT 'Last time the scholar submitted the claim',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `program_id` int(10) unsigned NOT NULL COMMENT 'Program this semester belongs to: its budget, claim windows and seat',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term` (`scholar_id`,`academic_year`,`semester`),
  KEY `idx_claim_status` (`claim_status`),
  KEY `fk_terms_reviewed_by` (`reviewed_by`),
  KEY `idx_terms_program_year` (`program_id`,`academic_year`),
  CONSTRAINT `fk_terms_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`),
  CONSTRAINT `fk_terms_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_terms_scholar` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholar_terms`
--

LOCK TABLES `scholar_terms` WRITE;
/*!40000 ALTER TABLE `scholar_terms` DISABLE KEYS */;
INSERT INTO `scholar_terms` VALUES (1,1,'2025-2026','1st Semester','approval','Released',1.00,NULL,'2026-09-16 23:08:40',NULL,'2026-09-16 23:08:40','2026-09-16 15:08:40',1),(3,1,'2026-2027','2nd Semester','renewal','Waiting for Requirements',NULL,NULL,NULL,NULL,NULL,'2026-09-16 15:10:49',1);
/*!40000 ALTER TABLE `scholar_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholars`
--

DROP TABLE IF EXISTS `scholars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholars` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scholar_code` varchar(30) NOT NULL,
  `applicant_id` int(10) unsigned NOT NULL,
  `application_id` int(10) unsigned NOT NULL,
  `program_id` int(10) unsigned NOT NULL COMMENT 'Origin program: the one that first approved the scholar. Current program = program of the latest semester',
  `approval_date` date NOT NULL,
  `status` enum('Active','Ended') NOT NULL DEFAULT 'Active',
  `end_reason` enum('Graduated','Dropped out','Failed grade requirement','Did not renew') DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `scholar_code` (`scholar_code`),
  UNIQUE KEY `application_id` (`application_id`),
  KEY `applicant_id` (`applicant_id`),
  KEY `program_id` (`program_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `scholars_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`),
  CONSTRAINT `scholars_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  CONSTRAINT `scholars_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholars`
--

LOCK TABLES `scholars` WRITE;
/*!40000 ALTER TABLE `scholars` DISABLE KEYS */;
INSERT INTO `scholars` VALUES (1,'SCH-2026-04E325E5',1,1,1,'2026-09-16','Active',NULL,NULL,'2026-09-16 14:59:21','2026-09-16 15:00:51');
/*!40000 ALTER TABLE `scholars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholarship_programs`
--

DROP TABLE IF EXISTS `scholarship_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarship_programs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_name` varchar(150) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `amount_per_semester` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash released to each scholar per semester',
  `min_gpa` decimal(4,2) DEFAULT NULL,
  `max_family_income` decimal(12,2) DEFAULT NULL,
  `max_age` int(11) DEFAULT NULL,
  `slots_available` int(11) DEFAULT 0,
  `application_start` date NOT NULL,
  `application_end` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_program_year` (`academic_year`),
  KEY `idx_active` (`is_active`),
  KEY `idx_year` (`academic_year`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholarship_programs`
--

LOCK TABLES `scholarship_programs` WRITE;
/*!40000 ALTER TABLE `scholarship_programs` DISABLE KEYS */;
INSERT INTO `scholarship_programs` VALUES (1,'SK College Scholarship Program 2026','2025-2026',0.00,2.50,25000.00,30,100,'2026-09-15','2026-11-14',1,1,'2026-09-15 05:45:02','2026-09-15 05:45:02');
/*!40000 ALTER TABLE `scholarship_programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholarship_releases`
--

DROP TABLE IF EXISTS `scholarship_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarship_releases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `release_code` varchar(30) NOT NULL,
  `scholar_id` int(10) unsigned NOT NULL,
  `term_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `release_date` date NOT NULL,
  `payment_method` enum('Cash','GCash','Bank','Check') NOT NULL DEFAULT 'Cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `staff_id` int(10) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `release_code` (`release_code`),
  UNIQUE KEY `uniq_release_term` (`term_id`),
  KEY `staff_id` (`staff_id`),
  KEY `idx_scholar` (`scholar_id`),
  CONSTRAINT `fk_releases_term` FOREIGN KEY (`term_id`) REFERENCES `scholar_terms` (`id`),
  CONSTRAINT `scholarship_releases_ibfk_1` FOREIGN KEY (`scholar_id`) REFERENCES `scholars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `scholarship_releases_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholarship_releases`
--

LOCK TABLES `scholarship_releases` WRITE;
/*!40000 ALTER TABLE `scholarship_releases` DISABLE KEYS */;
INSERT INTO `scholarship_releases` VALUES (1,'REL-2026-3ACA9BCA',1,1,3000.00,'2026-09-16','Cash','',2,'pagbutihan mo','2026-09-16 15:08:40');
/*!40000 ALTER TABLE `scholarship_releases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_name` varchar(200) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schools`
--

LOCK TABLES `schools` WRITE;
/*!40000 ALTER TABLE `schools` DISABLE KEYS */;
INSERT INTO `schools` VALUES (1,'Carlos Hilado Memorial State University','Talisay City, Negros Occidental',1,'2026-09-15 05:50:39'),(2,'University of Negros Occidental - Recoletos','Bacolod City',1,'2026-09-15 05:50:39'),(3,'University of St. La Salle','Bacolod City',1,'2026-09-15 05:50:39'),(4,'Central Philippine Adventist College','Bacolod City',1,'2026-09-15 05:50:39'),(5,'STI West Negros University','Bacolod City',1,'2026-09-15 05:50:39'),(6,'Bacolod City College','Bacolod City',1,'2026-09-15 05:50:39'),(7,'Negros Oriental State University','Dumaguete City',1,'2026-09-15 05:50:39'),(8,'CHMSU - Binalbagan Campus','Binalbagan, Negros Occidental',1,'2026-09-15 05:50:39'),(9,'Carlos Hilado Memorial State University - Fortune Towne Campus','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(10,'Carlos Hilado Memorial State University - Alijis Campus','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(11,'Negros Occidental Language and Information Technology Center (NOLITC)','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(12,'Colegio San Agustin - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(13,'La Consolacion College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(14,'Our Lady of Mercy College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(15,'Sacred Heart Seminary School of Philosophy','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(16,'West Negros University','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(17,'National University Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(18,'Riverside College','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(19,'John B. Lacson Colleges Foundation - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(20,'VMA Global College','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(21,'AMA Computer College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(22,'ABE International Business College - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(23,'College of Arts & Sciences of Asia & the Pacific - Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(24,'Mapua Malayan Digital College - Learning Hub Bacolod','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(25,'Bacolod Christian College of Negros','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00'),(26,'Convention Baptist Bible College','Bacolod City, Negros Occidental',1,'2026-09-24 16:00:00');
/*!40000 ALTER TABLE `schools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slot_requests`
--

DROP TABLE IF EXISTS `slot_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slot_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `schedule_id` int(10) unsigned DEFAULT NULL COMMENT 'Existing Open schedule picked (NULL = a free block)',
  `proposed_date` date NOT NULL,
  `proposed_start` time NOT NULL,
  `proposed_end` time NOT NULL,
  `status` enum('Pending','Confirmed','Declined','Cancelled') NOT NULL DEFAULT 'Pending',
  `decision_note` varchar(255) DEFAULT NULL,
  `decided_by` int(10) unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_slot_app_status` (`application_id`,`status`),
  KEY `idx_slot_status` (`status`),
  KEY `fk_slot_schedule` (`schedule_id`),
  KEY `fk_slot_decided_by` (`decided_by`),
  CONSTRAINT `fk_slot_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_slot_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_slot_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `interview_schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slot_requests`
--

LOCK TABLES `slot_requests` WRITE;
/*!40000 ALTER TABLE `slot_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `slot_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','int','bool','json') DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (5,'email_notifications','1','bool','Enable email notifications','2026-09-14 17:49:44'),(6,'interview_reminder_hours','24','int','Hours before interview to send reminder','2026-09-14 17:49:44'),(7,'daily_digest_enabled','1','bool','Send the daily interview digest','2026-10-03 18:07:22'),(8,'daily_digest_sent_on','','string','Last day the daily interview digest was sent','2026-10-03 18:07:22'),(9,'admin_event_emails','0','bool','Also e-mail admins about new submissions','2026-10-03 18:07:22'),(10,'interview_suggestion_window_days','60','int','Days after the application end date that interview suggestions may use','2026-10-03 18:07:22');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `term_documents`
--

DROP TABLE IF EXISTS `term_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `term_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `term_id` int(10) unsigned NOT NULL,
  `purpose` enum('claim','renewal') NOT NULL,
  `type` enum('School ID','Grades','Enrollment Form') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `status` enum('Submitted','Resubmit','Verified','Invalid') NOT NULL DEFAULT 'Submitted',
  `remarks` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_current` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Newest version of this type for the applicant',
  `superseded_at` datetime DEFAULT NULL,
  `invalid_reason` text DEFAULT NULL,
  `invalidated_by` int(10) unsigned DEFAULT NULL,
  `invalidated_at` datetime DEFAULT NULL,
  `file_removed_at` datetime DEFAULT NULL COMMENT 'File deleted because it was flagged invalid; the row is kept',
  `side` enum('Front','Back') DEFAULT NULL COMMENT 'Front/Back picture; NULL = one file with the whole document',
  PRIMARY KEY (`id`),
  KEY `idx_term_doc` (`term_id`,`purpose`,`type`),
  KEY `idx_term_doc_current` (`type`,`is_current`),
  CONSTRAINT `fk_term_documents_term` FOREIGN KEY (`term_id`) REFERENCES `scholar_terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `term_documents`
--

LOCK TABLES `term_documents` WRITE;
/*!40000 ALTER TABLE `term_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `term_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `term_schedules`
--

DROP TABLE IF EXISTS `term_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `term_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int(10) unsigned NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL,
  `purpose` enum('release','renewal') NOT NULL COMMENT 'release = cash claim of this semester; renewal = renewal INTO this semester',
  `opens_at` datetime NOT NULL,
  `submission_deadline` datetime NOT NULL,
  `planned_date` date DEFAULT NULL COMMENT 'Planned release date (release) or semester start (renewal); information only',
  `status` enum('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open',
  `notes` text DEFAULT NULL,
  `deadline_reminder_sent_at` datetime DEFAULT NULL COMMENT 'When the 48 h deadline reminder was sent (NULL = not yet / reset after a deadline change)',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term_schedule` (`program_id`,`academic_year`,`semester`,`purpose`),
  KEY `idx_deadline` (`status`,`submission_deadline`),
  KEY `fk_term_schedules_created_by` (`created_by`),
  CONSTRAINT `fk_term_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_term_schedules_program` FOREIGN KEY (`program_id`) REFERENCES `scholarship_programs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `term_schedules`
--

LOCK TABLES `term_schedules` WRITE;
/*!40000 ALTER TABLE `term_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `term_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` tinyint(4) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,4,'Mark Jim Balintaculo','markjimb21@gmail.com','$2y$10$S/ejb4lZh4Yx4JCEu0Q5BeLi1NgIUj4uPNxFogPgDbpveYeTzqtWO','',1,1,'2026-09-25 19:28:24',0,NULL,'2026-09-14 17:50:28','2026-09-25 11:28:24'),(2,1,'Admin','admin@gmail.com','$2y$10$LMuceB1iratZxXk/2yasXOBzEn6URZMvoPfnf.1NpS.xKGzyJISVq',NULL,1,1,'2026-09-25 23:10:45',0,NULL,'2026-09-16 10:13:03','2026-09-25 15:10:45'),(10,1,'staff','staff@gmail.com','$2y$10$9b7Je8LDKW7BV2MLWjl1u.OC1CT/9ZA9wA.NRceony5O0bMKwCT6i','09123767812',0,0,'2026-09-25 17:57:25',0,NULL,'2026-09-25 09:57:06','2026-09-27 17:08:47'),(11,1,'Official','official@gmail.com','$2y$10$tYBw0N.0Tl0.B5JXGUAi8eOghiN1ihHDcwog12q7upHWvDJiNEdre','12312312312',0,0,'2026-09-25 18:00:28',0,NULL,'2026-09-25 10:00:10','2026-09-27 17:08:47'),(13,4,'Juan Dela Cruz','cscmjb1@gmail.com','$2y$10$Suw.sz2xK8PXcE9CLB6jQepKPQ3Wgys8L5HwHaYMmYCgC3O2qYIM2',NULL,1,1,'2026-09-25 23:16:04',0,NULL,'2026-09-25 11:29:38','2026-09-25 15:16:04');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
