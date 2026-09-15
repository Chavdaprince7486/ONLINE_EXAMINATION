-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2026 at 10:53 PM
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
  `profile_photo` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `password`, `profile_photo`, `status`, `last_login`, `created_at`) VALUES
(1, 'Chavda Prince', 'chavdaprince7486@gmail.com', '$2y$10$y2vxMoiQjBO38mSPUjSf..cerJl2Vy06ojibgi8SXNwtcuVsIPhiK', 'admin_1_a6ae06b9adee484d.jpg', 'Active', '2026-09-16 02:21:51', '2026-09-13 13:57:24');

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
(60, 3, 20, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-13 19:17:31'),
(61, 4, 1, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-14 19:23:22'),
(62, 4, 2, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(63, 4, 3, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(64, 4, 4, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(65, 4, 5, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(66, 4, 6, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(67, 4, 7, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(68, 4, 8, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(69, 4, 9, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(70, 4, 10, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(71, 4, 11, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(72, 4, 12, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(73, 4, 13, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(74, 4, 14, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(75, 4, 15, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(76, 4, 16, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(77, 4, 17, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(78, 4, 18, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(79, 4, 19, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(80, 4, 20, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 09:47:21'),
(81, 5, 20, 'A', 'Answered', '2026-09-15 20:09:35', 1, 1.00, '2026-09-15 14:39:51'),
(82, 5, 19, 'C', 'Answered', '2026-09-15 20:09:37', 0, 0.00, '2026-09-15 14:39:37'),
(83, 5, 18, 'D', 'Answered', '2026-09-15 20:09:39', 0, 0.00, '2026-09-15 14:39:39'),
(84, 5, 13, 'A', 'Answered & Marked for Review', '2026-09-15 20:09:42', 0, 0.00, '2026-09-15 14:39:42'),
(85, 5, 12, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 14:39:42'),
(86, 5, 11, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 14:39:46'),
(87, 5, 10, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 14:39:47'),
(88, 5, 9, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 14:39:51'),
(89, 5, 7, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 14:39:51'),
(90, 5, 6, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 14:39:51'),
(91, 6, 20, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:15'),
(92, 6, 19, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(93, 6, 18, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(94, 6, 13, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(95, 6, 12, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(96, 6, 11, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(97, 6, 10, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(98, 6, 9, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(99, 6, 7, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(100, 6, 6, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 16:52:19'),
(101, 7, 21, 'B', 'Answered', '2026-09-16 00:39:55', 1, 1.00, '2026-09-15 19:14:30'),
(102, 7, 22, 'D', 'Answered', '2026-09-16 00:39:58', 0, 0.00, '2026-09-15 19:09:58'),
(103, 7, 23, NULL, 'Marked for Review', NULL, 0, 0.00, '2026-09-15 19:10:00'),
(104, 7, 24, 'D', 'Answered & Marked for Review', '2026-09-16 00:40:03', 0, 0.00, '2026-09-15 19:10:03'),
(105, 7, 25, 'C', 'Answered', '2026-09-16 00:40:06', 0, 0.00, '2026-09-15 19:10:06'),
(106, 7, 26, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:10:06'),
(107, 7, 27, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:10:07'),
(108, 7, 28, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:10:09'),
(109, 7, 29, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:10:09'),
(110, 7, 30, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:10:09'),
(111, 8, 21, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:14'),
(112, 8, 22, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:16'),
(113, 8, 23, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19'),
(114, 8, 24, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19'),
(115, 8, 25, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19'),
(116, 8, 26, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19'),
(117, 8, 27, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19'),
(118, 8, 28, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19'),
(119, 8, 29, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19'),
(120, 8, 30, NULL, 'Not Answered', NULL, 0, 0.00, '2026-09-15 19:40:19');

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
(1, 1, 1, 'Police Constale', 'pojrpjgrepagae', 'Practice', 30, 20, 20.00, 10.00, 0, 0.00, 0, NULL, NULL, 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(2, 1, 1, 'Police Constale', 'fgsg', 'Practice', 60, 10, 10.00, 3.00, 0, 0.00, 0, NULL, NULL, 'Active', '2026-09-15 14:34:49', '2026-09-15 14:34:49'),
(4, 1, 1, 'BCA Sem 1 C#', 'efewfrwf', 'Live', 60, 10, 10.00, 3.00, 0, 0.00, 1, '2026-09-15 00:24:00', '2026-09-17 00:24:00', 'Running', '2026-09-15 18:55:44', '2026-09-15 18:55:44');

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
(3, 1, 1, '2026-09-14 00:47:28', '2026-09-13 21:47:28', '2026-09-14 00:47:31', '2026-09-14 00:47:31', 'Submitted', 0.00, 0.00),
(4, 1, 1, '2026-09-15 00:53:22', '2026-09-14 21:53:22', '2026-09-15 15:17:21', '2026-09-15 15:17:21', 'Auto Submitted', 0.00, 0.00),
(5, 1, 2, '2026-09-15 20:09:27', '2026-09-15 17:39:27', '2026-09-15 20:09:51', '2026-09-15 20:09:51', 'Submitted', 1.00, 10.00),
(6, 1, 2, '2026-09-15 22:22:15', '2026-09-15 19:52:15', '2026-09-15 22:22:19', '2026-09-15 22:22:19', 'Submitted', 0.00, 0.00),
(7, 1, 4, '2026-09-16 00:26:05', '2026-09-15 21:56:05', '2026-09-16 00:44:30', '2026-09-16 00:44:30', 'Submitted', 1.00, 10.00),
(8, 1, 4, '2026-09-16 01:10:14', '2026-09-15 22:40:14', '2026-09-16 01:10:19', '2026-09-16 01:10:19', 'Submitted', 0.00, 0.00);

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
(1, 20, 20),
(2, 20, 1),
(2, 19, 2),
(2, 18, 3),
(2, 13, 4),
(2, 12, 5),
(2, 11, 6),
(2, 10, 7),
(2, 9, 8),
(2, 7, 9),
(2, 6, 10),
(4, 21, 1),
(4, 22, 2),
(4, 23, 3),
(4, 24, 4),
(4, 25, 5),
(4, 26, 6),
(4, 27, 7),
(4, 28, 8),
(4, 29, 9),
(4, 30, 10);

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

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `recipient_type`, `recipient_id`, `title`, `message`, `notification_type`, `reference_type`, `reference_id`, `is_read`, `created_at`, `read_at`) VALUES
(1, 'Student', 1, 'Notification for testing', 'djdvwbdw', 'system', NULL, NULL, 1, '2026-09-15 14:11:42', '2026-09-15 20:17:37'),
(2, 'Teacher', 1, 'Notification for testing', 'djdvwbdw', 'system', NULL, NULL, 1, '2026-09-15 14:11:42', '2026-09-15 19:42:23'),
(3, 'Teacher', 2, 'Notification for testing', 'djdvwbdw', 'system', NULL, NULL, 0, '2026-09-15 14:11:42', NULL),
(4, 'Student', 1, 'New Practice Exam Available', 'A new exam \"Police Constale\" is now available on ExamSphere. Please check the exam section for details.', 'exam', 'exam', 2, 1, '2026-09-15 14:34:49', '2026-09-15 20:17:25');

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
(20, 1, NULL, 1, 'MCQ', 'નર્મદા નદીનો ઉદ્ગમ ક્યાંથી થાય છે?', NULL, 'અમરકંટક', 'ગિરનાર', 'સપુતારા', 'અરવલ્લી', 'A', NULL, 1.00, 0.00, NULL, 'Medium', 'Active', '2026-09-13 17:45:40', '2026-09-13 17:45:40'),
(21, 1, NULL, 1, 'MCQ', 'C# માં પ્રોગ્રામ શરૂ કરવા માટે કઈ પદ્ધતિનો ઉપયોગ થાય છે?', NULL, 'Start()', 'Main()', 'Run()', 'Begin()', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(22, 1, NULL, 1, 'MCQ', 'C# માં integer value store કરવા માટે કયો data type વપરાય છે?', NULL, 'string', 'double', 'int', 'bool', 'C', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(23, 1, NULL, 1, 'MCQ', 'C# માં text store કરવા માટે કયો data type વપરાય છે?', NULL, 'char', 'string', 'int', 'float', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(24, 1, NULL, 1, 'MCQ', 'C# માં સાચું અથવા ખોટું દર્શાવવા માટે કયો data type વપરાય છે?', NULL, 'bool', 'int', 'string', 'decimal', 'A', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(25, 1, NULL, 1, 'MCQ', 'C# માં single-line comment માટે કયો symbol વપરાય છે?', NULL, '/*', '//', '#', '--', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(26, 1, NULL, 1, 'MCQ', 'C# માં class બનાવવા માટે કયો keyword વપરાય છે?', NULL, 'object', 'class', 'struct', 'new', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(27, 1, NULL, 1, 'MCQ', 'C# માં object બનાવવા માટે સામાન્ય રીતે કયો keyword વપરાય છે?', NULL, 'create', 'object', 'new', 'make', 'C', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(28, 1, NULL, 1, 'MCQ', 'C# માં બે integer values નો સરવાળો કરવા માટે કયો operator વપરાય છે?', NULL, '-', '+', '*', '/', 'B', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(29, 1, NULL, 1, 'MCQ', 'C# માં loop માટે નીચેમાંથી કયો keyword વપરાય છે?', NULL, 'repeat', 'loop', 'for', 'again', 'C', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44'),
(30, 1, NULL, 1, 'MCQ', 'C# કઈ programming language family સાથે સંબંધિત છે?', NULL, 'C-family', 'HTML', 'SQL', 'XML', 'A', NULL, 1.00, 0.00, NULL, 'Easy', 'Active', '2026-09-15 18:55:44', '2026-09-15 18:55:44');

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
(3, 3, 1, 1, 20, 0, 0, 0, 20, 20.00, 0.00, 0.00, 'F', 'Fail', '2026-09-13 19:17:31'),
(4, 4, 1, 1, 20, 0, 0, 0, 20, 20.00, 0.00, 0.00, 'F', 'Fail', '2026-09-15 09:47:21'),
(5, 5, 1, 2, 10, 4, 1, 3, 6, 10.00, 1.00, 10.00, 'F', 'Fail', '2026-09-15 14:39:51'),
(6, 6, 1, 2, 10, 0, 0, 0, 10, 10.00, 0.00, 0.00, 'F', 'Fail', '2026-09-15 16:52:19'),
(7, 7, 1, 4, 10, 4, 1, 3, 6, 10.00, 1.00, 10.00, 'F', 'Fail', '2026-09-15 19:14:30'),
(8, 8, 1, 4, 10, 0, 0, 0, 10, 10.00, 0.00, 0.00, 'F', 'Fail', '2026-09-15 19:40:19');

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
(1, 'STU00001', 'Chavda Prince', 'chavdaprince7485@gmail.com', '9088987654', 'Male', '2021-09-09', 'cdscsdcdsuytd', 'dccdc', 'cdscsd', '545544', '$2y$10$asOEEb66jkTl50oDJ5HXvef4OHGGCvtNKUd3.CTTPi6UDS/oFYXeC', 'STU00001_1_ba5c2098b7f8240d.jpg', 'Yes', 'Active', '2026-09-16 02:04:50', '2026-09-13 14:04:39'),
(2, 'STU00002', 'Chavda Prince', 'chavdaprince7488@gmail.com', '6556476543', 'Male', '2021-09-10', 'dfvfvcsvrre', 'dsfsdfsd', 'sdsvv', '999990', '$2y$10$A3PjbU1q4bS6HQaIY/752.UGCqW4ANk2rTsbYV7bGlt/n/3Kx60V2', 'STU00002_2_d98ea3b3d06c8f07.jpg', 'Yes', 'Active', '2026-09-16 02:22:16', '2026-09-15 16:56:26');

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

--
-- Dumping data for table `study_materials`
--

INSERT INTO `study_materials` (`id`, `subject_id`, `teacher_id`, `title`, `description`, `file_path`, `access_type`, `status`, `uploaded_at`) VALUES
(1, 1, 1, 'Cyber Security', 'skjbkdabca', 'uploads/materials/material_3f3d3bd3b6ccbd7093a793ffbb9c2f05.pdf', 'Public', 'Active', '2026-09-15 06:22:32'),
(2, 1, 1, 'IMP Cyber', 'xadxsa', 'uploads/materials/material_5cc4078395ee715a0a63887a291ed906.pdf', 'Subscription Only', 'Active', '2026-09-15 06:28:12'),
(3, 1, NULL, 'main imp topics', 'dewfwfr', 'material_f034e0c10dc70e58c33b.pdf', 'Public', 'Active', '2026-09-15 13:41:37');

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
(1, 8, 'Maths', '3', 'cwjdhcjdc', 'Active', '2026-09-13 13:58:28');

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

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `student_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 1, 4, '2026-09-15', '2026-10-14', 'Active', '2026-09-15 06:12:46'),
(2, 2, 3, '2026-09-15', '2027-03-14', 'Active', '2026-09-15 20:52:00');

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
(1, '1 Month', 1, 99.00, 'Flexible one-month access.', 'Important study materials; subscription-enabled live exams', 'Inactive', '2026-09-13 13:52:11'),
(2, '3 Months', 3, 249.00, 'A practical semester preparation plan.', 'Important study materials; subscription-enabled live exams', 'Active', '2026-09-13 13:52:11'),
(3, '6 Months', 6, 449.00, 'Best value for continuous preparation.', 'Important study materials; subscription-enabled live exams', 'Active', '2026-09-13 13:52:11'),
(4, '1 Month Trial', 1, 0.00, 'This Is For Testing', NULL, 'Active', '2026-09-15 05:45:08');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_requests`
--

CREATE TABLE `subscription_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `request_type` enum('Trial','Paid') NOT NULL DEFAULT 'Paid',
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_requests`
--

INSERT INTO `subscription_requests` (`id`, `student_id`, `plan_id`, `amount`, `request_type`, `payment_screenshot`, `status`, `admin_id`, `admin_note`, `submitted_at`, `reviewed_at`) VALUES
(1, 2, 3, 449.00, 'Paid', 'uploads/subscription_requests/payment_2_3_20260915225131_73fabcb2f9222e30.jpg', 'Approved', 1, 'Payment verified and subscription activated.', '2026-09-15 20:51:31', '2026-09-16 02:22:00');

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
(1, 'TCH00001', 'Vipul Baldha', 'vipulbaldha@gmail.com', '1231234321', '1231234321', 'Male', '2008-10-13', 'PHD', '15', 'ugyftdsrdrdtyugiop', '1789321349_6681235c80.jpg', '$2y$10$R37QB93bX9GtoZPlmtCanOp4rPgQd03736tGld0kgjV6yEDF1UPkG', 'Active', '2026-09-16 02:03:54', '2026-09-13 17:42:29'),
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

-- --------------------------------------------------------

--
-- Table structure for table `website_settings`
--

CREATE TABLE `website_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(120) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `website_settings`
--

INSERT INTO `website_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'ExamSphere', '2026-09-15 18:05:24'),
(2, 'site_tagline', 'Smart • Secure • Success', '2026-09-15 18:05:24'),
(3, 'seo_title', 'ExamSphere | Smart Online Examination Platform', '2026-09-15 18:05:24'),
(4, 'seo_description', 'ExamSphere is a modern online examination platform for practice exams, live exams, results and study materials.', '2026-09-15 18:05:24'),
(5, 'seo_keywords', 'online exam, practice exam, live exam, study materials, ExamSphere', '2026-09-15 18:05:24'),
(6, 'primary_color', '#5d4037', '2026-09-15 18:05:24'),
(7, 'accent_color', '#556b2f', '2026-09-15 18:05:24'),
(8, 'background_color', '#f5f5dc', '2026-09-15 18:05:24'),
(9, 'card_color', '#fffdf8', '2026-09-15 18:05:24'),
(10, 'hero_kicker', 'SMART • SECURE • INSTANT', '2026-09-15 18:05:24'),
(11, 'hero_title_1', 'Make every exam', '2026-09-15 18:05:24'),
(12, 'hero_title_2', 'your next success.', '2026-09-15 18:05:24'),
(13, 'hero_description', 'ExamSphere brings unlimited practice, scheduled live exams, instant results and study materials into one elegant learning platform.', '2026-09-15 18:05:24'),
(14, 'hero_primary_text', 'Start practising', '2026-09-15 18:05:24'),
(15, 'hero_primary_url', 'auth/register.php', '2026-09-15 18:05:24'),
(16, 'hero_secondary_text', 'How it works', '2026-09-15 18:05:24'),
(17, 'hero_secondary_url', '#how-it-works', '2026-09-15 18:05:24'),
(18, 'hero_trust_1', 'Free practice access', '2026-09-15 18:05:24'),
(19, 'hero_trust_2', 'Instant evaluation', '2026-09-15 18:05:24'),
(20, 'why_kicker', 'WHY EXAMSPHERE', '2026-09-15 18:05:24'),
(21, 'why_title', 'Everything you need to learn with confidence.', '2026-09-15 18:05:24'),
(22, 'why_description', 'A focused platform for students, teachers and administrators—designed to stay simple, secure and easy to use.', '2026-09-15 18:05:24'),
(23, 'feature_1_title', 'Unlimited practice', '2026-09-15 18:05:24'),
(24, 'feature_1_text', 'Take practice exams as many times as you need, without a subscription.', '2026-09-15 18:05:24'),
(25, 'feature_2_title', 'Live scheduled exams', '2026-09-15 18:05:24'),
(26, 'feature_2_text', 'Join upcoming tests with clear schedule, eligibility and access details.', '2026-09-15 18:05:24'),
(27, 'feature_3_title', 'Instant results', '2026-09-15 18:05:24'),
(28, 'feature_3_text', 'Receive score, percentage, grade and pass/fail status immediately.', '2026-09-15 18:05:24'),
(29, 'feature_4_title', 'Study materials', '2026-09-15 18:05:24'),
(30, 'feature_4_text', 'Keep essential notes and preparation material within easy reach.', '2026-09-15 18:05:24'),
(31, 'feature_5_title', 'Performance tracking', '2026-09-15 18:05:24'),
(32, 'feature_5_text', 'See your history, subject performance and leaderboard progress.', '2026-09-15 18:05:24'),
(33, 'feature_6_title', 'Secure experience', '2026-09-15 18:05:24'),
(34, 'feature_6_text', 'Role-based access and carefully managed exam attempts protect your work.', '2026-09-15 18:05:24'),
(35, 'process_kicker', 'HOW IT WORKS', '2026-09-15 18:05:24'),
(36, 'process_title', 'A clear path from registration to result.', '2026-09-15 18:05:24'),
(37, 'process_description', 'Get started in minutes, practise freely, then unlock additional learning benefits whenever you need them.', '2026-09-15 18:05:24'),
(38, 'process_step_1_title', 'Register', '2026-09-15 18:05:24'),
(39, 'process_step_1_text', 'Create your student account securely.', '2026-09-15 18:05:24'),
(40, 'process_step_2_title', 'Practice', '2026-09-15 18:05:24'),
(41, 'process_step_2_text', 'Build confidence with free practice exams.', '2026-09-15 18:05:24'),
(42, 'process_step_3_title', 'Unlock access', '2026-09-15 18:05:24'),
(43, 'process_step_3_text', 'Subscribe or pay only when required.', '2026-09-15 18:05:24'),
(44, 'process_step_4_title', 'Get results', '2026-09-15 18:05:24'),
(45, 'process_step_4_text', 'Review your instant result and progress.', '2026-09-15 18:05:24'),
(46, 'contact_kicker', 'READY TO BEGIN?', '2026-09-15 18:05:24'),
(47, 'contact_title', 'Your next achievement can start today.', '2026-09-15 18:05:24'),
(48, 'contact_description', 'Create your student account, discover your target examination category and start building your preparation.', '2026-09-15 18:05:24'),
(49, 'contact_point_1', 'Practice exams', '2026-09-15 18:05:24'),
(50, 'contact_point_2', 'Live exams', '2026-09-15 18:05:24'),
(51, 'contact_point_3', 'Study materials', '2026-09-15 18:05:24'),
(52, 'contact_primary_text', 'Create account', '2026-09-15 18:05:24'),
(53, 'contact_primary_url', 'auth/register.php', '2026-09-15 18:05:24'),
(54, 'contact_login_text', 'Already have an account?', '2026-09-15 18:05:24'),
(55, 'contact_email', 'support@examsphere.local', '2026-09-15 18:05:24'),
(56, 'contact_phone', '+91 00000 00000', '2026-09-15 18:05:24'),
(57, 'contact_address', 'Gujarat, India', '2026-09-15 18:05:24'),
(58, 'footer_description', 'A modern online examination platform for focused learning and clear results.', '2026-09-15 18:05:24'),
(59, 'footer_copyright', 'ExamSphere. All rights reserved.', '2026-09-15 18:05:24'),
(60, 'social_facebook', '', '2026-09-15 18:05:24'),
(61, 'social_instagram', '', '2026-09-15 18:05:24'),
(62, 'social_youtube', '', '2026-09-15 18:05:24'),
(63, 'social_linkedin', '', '2026-09-15 18:05:24'),
(64, 'social_whatsapp', '', '2026-09-15 18:05:24'),
(65, 'show_how_it_works', '0', '2026-09-15 18:39:54'),
(66, 'show_why_choose', '0', '2026-09-15 18:39:54'),
(67, 'show_categories', '1', '2026-09-15 18:05:24'),
(68, 'show_practice_exams', '1', '2026-09-15 18:05:24'),
(69, 'show_live_exams', '1', '2026-09-15 18:05:24'),
(70, 'show_plans', '1', '2026-09-15 18:05:24'),
(71, 'show_materials', '1', '2026-09-15 18:05:24'),
(72, 'show_faq', '1', '2026-09-15 18:05:24'),
(73, 'show_contact', '1', '2026-09-15 18:05:24'),
(74, 'show_footer', '1', '2026-09-15 18:05:24'),
(75, 'faq_1_q', 'Are practice exams free?', '2026-09-15 18:05:24'),
(76, 'faq_1_a', 'Active practice exams can be taken by registered students without requiring a subscription. Access rules are always controlled by the actual exam.', '2026-09-15 18:05:24'),
(77, 'faq_2_q', 'When do I need a subscription?', '2026-09-15 18:05:24'),
(78, 'faq_2_a', 'A subscription is required only for features or resources configured as subscription-only, such as protected study materials or eligible live exams.', '2026-09-15 18:05:24'),
(79, 'faq_3_q', 'Are all live exams free?', '2026-09-15 18:05:24'),
(80, 'faq_3_a', 'No. Each live exam can have its own access rules. The exam may require an active subscription, an exam fee, or provide free access.', '2026-09-15 18:05:24'),
(81, 'faq_4_q', 'How are exam results calculated?', '2026-09-15 18:05:24'),
(82, 'faq_4_a', 'After submission, eligible objective questions are evaluated by the examination system and the resulting score and performance data are saved.', '2026-09-15 18:05:24'),
(83, 'faq_5_q', 'Can I review my previous attempts?', '2026-09-15 18:05:24'),
(84, 'faq_5_a', 'Completed examination attempts and available results are stored in your student account so you can review your previous performance.', '2026-09-15 18:05:24'),
(85, 'faq_6_q', 'How do study materials work?', '2026-09-15 18:05:24'),
(86, 'faq_6_a', 'Published materials can be public or subscription-only. Access is checked by the student system before protected resources are delivered.', '2026-09-15 18:05:24'),
(87, 'site_logo', 'uploads/site/logo_88cfde4d1152984828783f01.png', '2026-09-15 18:06:45');

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
  ADD KEY `idx_notification_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_notifications_recipient_read` (`recipient_type`,`recipient_id`,`is_read`,`created_at`),
  ADD KEY `idx_notifications_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_notifications_duplicate` (`recipient_type`,`recipient_id`,`reference_type`,`reference_id`),
  ADD KEY `idx_notifications_created` (`created_at`);

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
-- Indexes for table `subscription_requests`
--
ALTER TABLE `subscription_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subscription_request_plan` (`plan_id`),
  ADD KEY `fk_subscription_request_admin` (`admin_id`),
  ADD KEY `idx_subscription_request_student` (`student_id`,`status`,`submitted_at`),
  ADD KEY `idx_subscription_request_status` (`status`,`submitted_at`);

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
-- Indexes for table `website_settings`
--
ALTER TABLE `website_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_website_settings_key` (`setting_key`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `live_exam_payments`
--
ALTER TABLE `live_exam_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `study_materials`
--
ALTER TABLE `study_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subscription_requests`
--
ALTER TABLE `subscription_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT for table `website_settings`
--
ALTER TABLE `website_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=261;

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
-- Constraints for table `subscription_requests`
--
ALTER TABLE `subscription_requests`
  ADD CONSTRAINT `fk_subscription_request_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_subscription_request_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`),
  ADD CONSTRAINT `fk_subscription_request_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `fk_topic_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
