-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 13, 2026 at 09:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `online_examination`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `password`, `status`, `last_login`, `created_at`) VALUES
(1, 'Chavda Prince', 'chavdaprince7486@gmail.com', '$2y$10$y2vxMoiQjBO38mSPUjSf..cerJl2Vy06ojibgi8SXNwtcuVsIPhiK', 'Active', '2026-09-13 23:16:31', '2026-09-13 13:57:24');

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `selected_answer` enum('A','B','C','D') DEFAULT NULL,
  `question_status` enum('Not Visited','Not Answered','Answered','Marked for Review','Answered & Marked for Review') NOT NULL DEFAULT 'Not Answered',
  `answered_at` datetime DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `marks_awarded` decimal(6,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `answers`
--

INSERT INTO `answers` (`id`, `attempt_id`, `question_id`, `selected_answer`, `question_status`, `answered_at`, `is_correct`, `marks_awarded`, `updated_at`) VALUES
(1, 1, 1, NULL, 'Marked for Review', NULL, 0, 0.00, '2026-09-13 19:09:25'),
(2, 1, 12, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 18:58:30'),
(3, 1, 2, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:05:42'),
(4, 1, 3, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:09'),
(5, 1, 4, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(6, 1, 5, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(7, 1, 6, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(8, 1, 7, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(9, 1, 8, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(10, 1, 9, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(11, 1, 10, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(12, 1, 11, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(13, 1, 13, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(14, 1, 14, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(15, 1, 15, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(16, 1, 16, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(17, 1, 17, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(18, 1, 18, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(19, 1, 19, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(20, 1, 20, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:12:12'),
(21, 2, 1, 'C', 'Answered & Marked for Review', '2026-09-14 00:44:14', 0, 0.00, '2026-09-13 19:14:14'),
(22, 2, 2, 'D', 'Answered', '2026-09-14 00:44:18', 0, 0.00, '2026-09-13 19:14:18'),
(23, 2, 3, 'D', 'Answered', '2026-09-14 00:44:22', 0, 0.00, '2026-09-13 19:14:22'),
(24, 2, 4, 'B', 'Answered', '2026-09-14 00:44:26', 0, 0.00, '2026-09-13 19:14:26'),
(25, 2, 5, NULL, 'Marked for Review', NULL, 0, 0.00, '2026-09-13 19:14:29'),
(26, 2, 6, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:14:38'),
(27, 2, 7, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:14:40'),
(28, 2, 8, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:14:42'),
(29, 2, 9, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:14:43'),
(30, 2, 10, 'C', 'Answered', '2026-09-14 00:44:46', 0, 0.00, '2026-09-13 19:14:46'),
(31, 2, 11, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:14:47'),
(32, 2, 12, 'A', 'Answered', '2026-09-14 00:44:53', 0, 0.00, '2026-09-13 19:14:53'),
(33, 2, 13, 'B', 'Answered', '2026-09-14 00:45:03', 1, 1.00, '2026-09-13 19:16:19'),
(34, 2, 14, 'D', 'Answered', '2026-09-14 00:45:10', 0, 0.00, '2026-09-13 19:15:10'),
(35, 2, 15, 'B', 'Answered', '2026-09-14 00:45:15', 1, 1.00, '2026-09-13 19:16:19'),
(36, 2, 16, 'C', 'Answered', '2026-09-14 00:45:28', 1, 1.00, '2026-09-13 19:16:19'),
(37, 2, 17, 'B', 'Answered', '2026-09-14 00:45:36', 0, 0.00, '2026-09-13 19:15:36'),
(38, 2, 18, 'B', 'Answered', '2026-09-14 00:45:50', 1, 1.00, '2026-09-13 19:16:19'),
(39, 2, 19, 'A', 'Answered', '2026-09-14 00:46:00', 1, 1.00, '2026-09-13 19:16:19'),
(40, 2, 20, 'B', 'Answered', '2026-09-14 00:46:08', 0, 0.00, '2026-09-13 19:16:08'),
(41, 3, 1, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:29'),
(42, 3, 2, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(43, 3, 3, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(44, 3, 4, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(45, 3, 5, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(46, 3, 6, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(47, 3, 7, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(48, 3, 8, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(49, 3, 9, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(50, 3, 10, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(51, 3, 11, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(52, 3, 12, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(53, 3, 13, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(54, 3, 14, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(55, 3, 15, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(56, 3, 16, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(57, 3, 17, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(58, 3, 18, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(59, 3, 19, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(60, 3, 20, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) NOT NULL DEFAULT 'fa-solid fa-book',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `description`, `icon`, `status`, `created_at`) VALUES
(1, 'UPSC', 'Civil Services and UPSC preparation with focused practice exams.', 'fa-solid fa-building-columns', 'Active', '2026-09-13 13:52:11'),
(2, 'GPSC', 'Gujarat Public Service Commission preparation and practice.', 'fa-solid fa-landmark', 'Active', '2026-09-13 13:52:11'),
(3, 'NEET', 'Medical entrance preparation with structured practice questions.', 'fa-solid fa-user-doctor', 'Active', '2026-09-13 13:52:11'),
(4, 'JEE', 'Engineering entrance preparation with focused practice exams.', 'fa-solid fa-atom', 'Active', '2026-09-13 13:52:11'),
(5, 'Forest', 'Forest and environment related competitive examination preparation.', 'fa-solid fa-tree', 'Active', '2026-09-13 13:52:11'),
(6, 'Railway', 'Railway recruitment examination practice and preparation.', 'fa-solid fa-train', 'Active', '2026-09-13 13:52:11'),
(7, 'SSC', 'Staff Selection Commission examination preparation.', 'fa-solid fa-file-lines', 'Active', '2026-09-13 13:52:11'),
(8, 'Banking', 'Banking and financial sector competitive exam preparation.', 'fa-solid fa-building-columns', 'Active', '2026-09-13 13:52:11'),
(9, 'Police', 'Police recruitment and competitive examination preparation.', 'fa-solid fa-shield-halved', 'Active', '2026-09-13 13:52:11'),
(10, 'Talati', 'Talati and Gujarat government recruitment exam preparation.', 'fa-solid fa-file-signature', 'Active', '2026-09-13 13:52:11'),
(11, 'University', 'University and college examination preparation.', 'fa-solid fa-graduation-cap', 'Active', '2026-09-13 13:52:11'),
(12, 'School', 'School-level examination and academic preparation.', 'fa-solid fa-school', 'Active', '2026-09-13 13:52:11');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `exam_type` enum('Practice','Live') NOT NULL DEFAULT 'Practice',
  `duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `required_question_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `total_marks` decimal(8,2) NOT NULL DEFAULT 0.00,
  `passing_marks` decimal(8,2) NOT NULL DEFAULT 0.00,
  `negative_marking` tinyint(1) NOT NULL DEFAULT 0,
  `exam_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subscription_required` tinyint(1) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` enum('Draft','Scheduled','Live','Completed','Cancelled','Active','Upcoming','Running') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `subject_id`, `teacher_id`, `title`, `description`, `exam_type`, `duration_minutes`, `required_question_count`, `total_marks`, `passing_marks`, `negative_marking`, `exam_fee`, `subscription_required`, `starts_at`, `ends_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Police Constale', 'pojrpjgrepagae', 'Practice', 30, 20, 20.00, 10.00, 0, 0.00, 0, NULL, NULL, 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40');

-- --------------------------------------------------------

--
-- Table structure for table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `exam_id` int(10) UNSIGNED NOT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `server_deadline` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `status` enum('Started','Submitted','Auto Submitted') NOT NULL DEFAULT 'Started',
  `obtained_marks` decimal(8,2) NOT NULL DEFAULT 0.00,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_attempts`
--

INSERT INTO `exam_attempts` (`id`, `student_id`, `exam_id`, `started_at`, `server_deadline`, `submitted_at`, `last_activity_at`, `status`, `obtained_marks`, `percentage`) VALUES
(1, 1, 1, '2026-09-14 00:24:28', '2026-09-13 21:24:28', '2026-09-14 00:42:12', '2026-09-14 00:42:12', 'Submitted', 0.00, 0.00),
(2, 1, 1, '2026-09-14 00:44:05', '2026-09-13 21:44:05', '2026-09-14 00:46:19', '2026-09-14 00:46:19', 'Submitted', 5.00, 25.00),
(3, 1, 1, '2026-09-14 00:47:28', '2026-09-13 21:47:28', '2026-09-14 00:47:31', '2026-09-14 00:47:31', 'Submitted', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `exam_questions`
--

CREATE TABLE `exam_questions` (
  `exam_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `position` smallint(5) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_questions`
--

INSERT INTO `exam_questions` (`exam_id`, `question_id`, `position`) VALUES
(1, 1, 1),
(1, 2, 2),
(1, 3, 3),
(1, 4, 4),
(1, 5, 5),
(1, 6, 6),
(1, 7, 7),
(1, 8, 8),
(1, 9, 9),
(1, 10, 10),
(1, 11, 11),
(1, 12, 12),
(1, 13, 13),
(1, 14, 14),
(1, 15, 15),
(1, 16, 16),
(1, 17, 17),
(1, 18, 18),
(1, 19, 19),
(1, 20, 20);

-- --------------------------------------------------------

--
-- Table structure for table `live_exam_payments`
--

CREATE TABLE `live_exam_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `exam_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `gateway_order_id` varchar(100) DEFAULT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `gateway_signature` varchar(128) DEFAULT NULL,
  `gateway_status` varchar(30) DEFAULT NULL,
  `gateway_method` varchar(30) DEFAULT NULL,
  `gateway_currency` char(3) NOT NULL DEFAULT 'INR',
  `payment_status` enum('Pending','Paid','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
  `payment_method` varchar(30) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `recipient_type` enum('Admin','Teacher','Student') NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `notification_type` varchar(60) NOT NULL,
  `reference_type` varchar(60) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `topic_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by_teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `question_type` enum('MCQ','TrueFalse') NOT NULL DEFAULT 'MCQ',
  `question_text` text NOT NULL,
  `question_image` varchar(255) DEFAULT NULL,
  `option_a` varchar(500) NOT NULL,
  `option_b` varchar(500) NOT NULL,
  `option_c` varchar(500) DEFAULT NULL,
  `option_d` varchar(500) DEFAULT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `explanation` text DEFAULT NULL,
  `marks` decimal(6,2) NOT NULL DEFAULT 1.00,
  `negative_marks` decimal(6,2) NOT NULL DEFAULT 0.00,
  `estimated_time_seconds` smallint(5) UNSIGNED DEFAULT NULL,
  `difficulty` enum('Easy','Medium','Hard') NOT NULL DEFAULT 'Medium',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `subject_id`, `topic_id`, `created_by_teacher_id`, `question_type`, `question_text`, `question_image`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `marks`, `negative_marks`, `estimated_time_seconds`, `difficulty`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 1, 'MCQ', 'ભારતનું બંધારણ ક્યારે અમલમાં આવ્યું?', NULL, '15 ઓગસ્ટ 1947', '26 જાન્યુઆરી 1950', '26 નવેમ્બર 1949', '2 ઓક્ટોબર 1950', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(2, 1, NULL, 1, 'MCQ', 'ભારતના બંધારણના પિતા તરીકે કોને ઓળખવામાં આવે છે?', NULL, 'મહાત્મા ગાંધી', 'જવાહરલાલ નેહરુ', 'ડૉ. બી. આર. આંબેડકર', 'સરદાર પટેલ', 'C', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(3, 1, NULL, 1, 'MCQ', 'ગુજરાતની રાજધાની કઈ છે?', NULL, 'અમદાવાદ', 'સુરત', 'ગાંધીનગર', 'વડોદરા', 'C', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(4, 1, NULL, 1, 'MCQ', 'ગુજરાત રાજ્યની સ્થાપના ક્યારે થઈ?', NULL, '1 મે 1960', '15 ઓગસ્ટ 1947', '26 જાન્યુઆરી 1950', '1 નવેમ્બર 1956', 'A', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(5, 1, NULL, 1, 'MCQ', 'ભારતનું રાષ્ટ્રીય પ્રાણી કયું છે?', NULL, 'સિંહ', 'વાઘ', 'હાથી', 'ચિત્તો', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(6, 1, NULL, 1, 'MCQ', 'ભારતનું રાષ્ટ્રીય પક્ષી કયું છે?', NULL, 'મોર', 'કબૂતર', 'ગરુડ', 'હંસ', 'A', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(7, 1, NULL, 1, 'MCQ', 'ભારતનું રાષ્ટ્રીય ફૂલ કયું છે?', NULL, 'ગુલાબ', 'કમળ', 'સૂર્યમુખી', 'ચંપો', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(8, 1, NULL, 1, 'MCQ', 'સાબરમતી આશ્રમ કયા શહેરમાં આવેલો છે?', NULL, 'રાજકોટ', 'સુરત', 'અમદાવાદ', 'વડોદરા', 'C', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(9, 1, NULL, 1, 'MCQ', 'ગીર રાષ્ટ્રીય ઉદ્યાન શેના માટે પ્રસિદ્ધ છે?', NULL, 'વાઘ', 'એશિયાટિક સિંહ', 'ગેંડો', 'હાથી', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(10, 1, NULL, 1, 'MCQ', 'ભારતના પ્રથમ રાષ્ટ્રપતિ કોણ હતા?', NULL, 'ડૉ. રાજેન્દ્ર પ્રસાદ', 'ડૉ. સર્વપલ્લી રાધાકૃષ્ણન', 'ઝાકિર હુસેન', 'જવાહરલાલ નેહરુ', 'A', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(11, 1, NULL, 1, 'MCQ', 'ભારતના પ્રથમ વડાપ્રધાન કોણ હતા?', NULL, 'સરદાર પટેલ', 'જવાહરલાલ નેહરુ', 'લાલ બહાદુર શાસ્ત્રી', 'રાજેન્દ્ર પ્રસાદ', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(12, 1, NULL, 1, 'MCQ', 'મહાત્મા ગાંધીનો જન્મ કયા શહેરમાં થયો હતો?', NULL, 'અમદાવાદ', 'પોરબંદર', 'રાજકોટ', 'વડોદરા', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(13, 1, NULL, 1, 'MCQ', 'દાંડી કૂચ કયા આંદોલન સાથે સંબંધિત હતી?', NULL, 'ભારત છોડો આંદોલન', 'મીઠા સત્યાગ્રહ', 'અસહકાર આંદોલન', 'સ્વદેશી આંદોલન', 'B', NULL, 1.00, 0.00, NULL, 'Medium', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(14, 1, NULL, 1, 'MCQ', 'ભારત છોડો આંદોલન કયા વર્ષે શરૂ થયું?', NULL, '1930', '1935', '1942', '1947', 'C', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(15, 1, NULL, 1, 'MCQ', 'ભારતનું સર્વોચ્ચ ન્યાયાલય ક્યાં આવેલું છે?', NULL, 'મુંબઈ', 'નવી દિલ્હી', 'અમદાવાદ', 'કોલકાતા', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(16, 1, NULL, 1, 'MCQ', 'ભારતના બંધારણમાં મૂળભૂત અધિકારો કયા ભાગમાં છે?', NULL, 'ભાગ I', 'ભાગ II', 'ભાગ III', 'ભાગ IV', 'C', NULL, 1.00, 0.00, NULL, 'Medium', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(17, 1, NULL, 1, 'MCQ', 'બંધારણનો અનુચ્છેદ 14 કયા અધિકાર સાથે સંબંધિત છે?', NULL, 'સમાનતાનો અધિકાર', 'સ્વતંત્રતાનો અધિકાર', 'ધાર્મિક સ્વતંત્રતા', 'શિક્ષણનો અધિકાર', 'A', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(18, 1, NULL, 1, 'MCQ', 'ભારતમાં મતદાન કરવાની લઘુત્તમ ઉંમર કેટલી છે?', NULL, '16 વર્ષ', '18 વર્ષ', '21 વર્ષ', '25 વર્ષ', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(19, 1, NULL, 1, 'MCQ', 'ગુજરાતની મહત્વપૂર્ણ નદીઓમાંની એક કઈ છે?', NULL, 'નર્મદા', 'યમુના', 'ગંગા', 'કાવેરી', 'A', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(20, 1, NULL, 1, 'MCQ', 'નર્મદા નદીનો ઉદ્ગમ ક્યાંથી થાય છે?', NULL, 'અમરકંટક', 'ગિરનાર', 'સપુતારા', 'અરવલ્લી', 'A', NULL, 1.00, 0.00, NULL, 'Medium', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `exam_id` int(10) UNSIGNED NOT NULL,
  `total_questions` smallint(5) UNSIGNED NOT NULL,
  `attempted_questions` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `correct_answers` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `wrong_answers` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `unanswered_questions` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `total_marks` decimal(8,2) NOT NULL,
  `obtained_marks` decimal(8,2) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `grade` varchar(4) NOT NULL,
  `result_status` enum('Pass','Fail') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`id`, `attempt_id`, `student_id`, `exam_id`, `total_questions`, `attempted_questions`, `correct_answers`, `wrong_answers`, `unanswered_questions`, `total_marks`, `obtained_marks`, `percentage`, `grade`, `result_status`, `created_at`) VALUES
(1, 1, 1, 1, 20, 0, 0, 0, 20, 20.00, 0.00, 0.00, 'F', 'Fail', '2026-09-13 19:12:12'),
(2, 2, 1, 1, 20, 14, 5, 9, 6, 20.00, 5.00, 25.00, 'F', 'Fail', '2026-09-13 19:16:19'),
(3, 3, 1, 1, 20, 0, 0, 0, 20, 20.00, 0.00, 0.00, 'F', 'Fail', '2026-09-13 19:17:31');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_code` varchar(30) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `email_verified` enum('Yes','No') NOT NULL DEFAULT 'No',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_code`, `full_name`, `email`, `mobile`, `gender`, `dob`, `address`, `city`, `state`, `pincode`, `password`, `profile_photo`, `email_verified`, `status`, `last_login`, `created_at`) VALUES
(1, 'STU00001', 'Chavda Prince', 'chavdaprince7485@gmail.com', '9088987654', 'Male', '2021-09-09', 'cdscsdcds', 'dccdc', 'cdscsd', '545544', '$2y$10$asOEEb66jkTl50oDJ5HXvef4OHGGCvtNKUd3.CTTPi6UDS/oFYXeC', NULL, 'Yes', 'Active', '2026-09-14 00:24:13', '2026-09-13 14:04:39');

-- --------------------------------------------------------

--
-- Table structure for table `study_materials`
--

CREATE TABLE `study_materials` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `access_type` enum('Public','Subscription Only') NOT NULL DEFAULT 'Public',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `category_id`, `name`, `code`, `description`, `status`, `created_at`) VALUES
(1, 8, 'Maths', '1', 'cwjdhcjdc', 'Active', '2026-09-13 13:58:28');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Expired','Cancelled') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `subscription_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `gateway_order_id` varchar(100) DEFAULT NULL,
  `gateway_payment_id` varchar(100) DEFAULT NULL,
  `gateway_signature` varchar(128) DEFAULT NULL,
  `gateway_status` varchar(30) DEFAULT NULL,
  `gateway_method` varchar(30) DEFAULT NULL,
  `gateway_currency` char(3) NOT NULL DEFAULT 'INR',
  `payment_status` enum('Pending','Paid','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
  `payment_method` varchar(30) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`id`, `student_id`, `plan_id`, `subscription_id`, `amount`, `reference_no`, `gateway_order_id`, `gateway_payment_id`, `gateway_signature`, `gateway_status`, `gateway_method`, `gateway_currency`, `payment_status`, `payment_method`, `paid_at`, `created_at`) VALUES
(1, 1, 3, NULL, 449.00, 'SUB-20260913160515-0DEBF17DB2', 'order_TbY1XT5ITKFCed', NULL, NULL, 'client_dismissed', NULL, 'INR', 'Cancelled', NULL, NULL, '2026-09-13 14:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `duration_months` tinyint(3) UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `duration_months`, `price`, `description`, `benefits`, `status`, `created_at`) VALUES
(1, '1 Month', 1, 99.00, 'Flexible one-month access.', 'Important study materials; subscription-enabled live exams', 'Active', '2026-09-13 13:52:11'),
(2, '3 Months', 3, 249.00, 'A practical semester preparation plan.', 'Important study materials; subscription-enabled live exams', 'Active', '2026-09-13 13:52:11'),
(3, '6 Months', 6, 449.00, 'Best value for continuous preparation.', 'Important study materials; subscription-enabled live exams', 'Active', '2026-09-13 13:52:11');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(10) UNSIGNED NOT NULL,
  `teacher_code` varchar(30) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `teacher_code`, `full_name`, `email`, `phone`, `mobile`, `gender`, `dob`, `qualification`, `experience`, `address`, `profile_photo`, `password`, `status`, `last_login`, `created_at`) VALUES
(1, 'TCH00001', 'Vipul Baldha', 'vipulbaldha@gmail.com', '1231234321', '1231234321', 'Male', '2008-10-13', 'PHD', '15', 'ugyftdsrdrdtyugiop', '1789321349_6681235c80.jpg', '$2y$10$z9iv5EufYYFa9cl1ML37iOtIz.gzMNQUypvLU3.EeZ9aHv/0GsOoW', 'Active', '2026-09-13 23:13:02', '2026-09-13 17:42:29'),
(2, 'TCH00002', 'dhruvita savaliya', 'dhruvitasavaliya123@gmail.com', '00092982882', '9939883898', 'Female', '2003-09-08', 'PHD', '10', 'efdwlekfj', '1789323521_db0fa5e5cd.jpg', '$2y$10$LOjPX5EE1n1j.HxwGXQbLOyENOulPZJ3nxziwZSoYWPvmd1BsrdvO', 'Active', NULL, '2026-09-13 18:18:41');

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `topics`
--

INSERT INTO `topics` (`id`, `subject_id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Chapter 1', 'kdhjchdckc', 'Active', '2026-09-13 13:59:06', '2026-09-13 13:59:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_answer` (`attempt_id`,`question_id`),
  ADD UNIQUE KEY `uq_answers_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `fk_answer_question` (`question_id`),
  ADD KEY `idx_answer_attempt_status` (`attempt_id`,`question_status`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_exam_subject` (`subject_id`),
  ADD KEY `fk_exam_teacher` (`teacher_id`),
  ADD KEY `idx_exam_type_status` (`exam_type`,`status`),
  ADD KEY `idx_exam_schedule` (`starts_at`);

--
-- Indexes for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attempt_exam` (`exam_id`),
  ADD KEY `idx_attempt_student` (`student_id`,`submitted_at`),
  ADD KEY `idx_attempt_exam` (`student_id`,`exam_id`,`status`),
  ADD KEY `idx_attempt_deadline` (`status`,`server_deadline`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`exam_id`,`question_id`),
  ADD KEY `fk_eq_question` (`question_id`),
  ADD KEY `idx_exam_question_position` (`exam_id`,`position`);

--
-- Indexes for table `live_exam_payments`
--
ALTER TABLE `live_exam_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD UNIQUE KEY `uq_exam_payment` (`student_id`,`exam_id`),
  ADD UNIQUE KEY `uq_live_gateway_order` (`gateway_order_id`),
  ADD UNIQUE KEY `uq_live_gateway_payment` (`gateway_payment_id`),
  ADD KEY `fk_lep_exam` (`exam_id`),
  ADD KEY `idx_live_gateway_status` (`gateway_status`),
  ADD KEY `idx_live_payment_lookup` (`student_id`,`exam_id`,`payment_status`,`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_recipient` (`recipient_type`,`recipient_id`,`is_read`,`created_at`),
  ADD KEY `idx_notification_reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_question_subject` (`subject_id`),
  ADD KEY `fk_question_teacher` (`created_by_teacher_id`),
  ADD KEY `idx_question_topic_status` (`topic_id`,`status`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attempt_id` (`attempt_id`),
  ADD KEY `fk_result_student` (`student_id`),
  ADD KEY `fk_result_exam` (`exam_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_code` (`student_code`);

--
-- Indexes for table `study_materials`
--
ALTER TABLE `study_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_material_subject` (`subject_id`),
  ADD KEY `fk_material_teacher` (`teacher_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_subject_category` (`category_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subscription_plan` (`plan_id`),
  ADD KEY `idx_subscription_access` (`student_id`,`status`,`end_date`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD UNIQUE KEY `gateway_order_id` (`gateway_order_id`),
  ADD UNIQUE KEY `gateway_payment_id` (`gateway_payment_id`),
  ADD KEY `fk_sp_plan` (`plan_id`),
  ADD KEY `fk_sp_subscription` (`subscription_id`),
  ADD KEY `idx_subscription_gateway_status` (`gateway_status`),
  ADD KEY `idx_subscription_payment_status` (`student_id`,`payment_status`,`created_at`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `teacher_code` (`teacher_code`);

--
-- Indexes for table `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_topic_subject_name` (`subject_id`,`name`),
  ADD KEY `idx_topic_subject_status` (`subject_id`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `live_exam_payments`
--
ALTER TABLE `live_exam_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `study_materials`
--
ALTER TABLE `study_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `fk_answer_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `fk_exam_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_exam_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD CONSTRAINT `fk_attempt_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `fk_attempt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD CONSTRAINT `fk_eq_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Constraints for table `live_exam_payments`
--
ALTER TABLE `live_exam_payments`
  ADD CONSTRAINT `fk_lep_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `fk_lep_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_question_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_question_teacher` FOREIGN KEY (`created_by_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_question_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `fk_result_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`),
  ADD CONSTRAINT `fk_result_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  ADD CONSTRAINT `fk_result_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `study_materials`
--
ALTER TABLE `study_materials`
  ADD CONSTRAINT `fk_material_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_material_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subject_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_subscription_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`),
  ADD CONSTRAINT `fk_subscription_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `fk_sp_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`),
  ADD CONSTRAINT `fk_sp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `fk_sp_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `fk_topic_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
