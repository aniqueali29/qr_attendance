-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2025 at 05:47 AM
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
-- Database: `qr_attendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year`, `start_date`, `end_date`, `is_current`, `created_at`) VALUES
(1, 2025, '2024-09-01', '2025-08-31', 1, '2025-10-11 11:16:08');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `timestamp` datetime NOT NULL,
  `status` enum('Check-in','Present','Absent') NOT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `session_duration` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admission_year` int(11) DEFAULT NULL,
  `current_year` int(11) DEFAULT NULL,
  `shift` varchar(20) DEFAULT NULL,
  `program` varchar(10) DEFAULT NULL,
  `is_graduated` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `student_name`, `timestamp`, `status`, `check_in_time`, `check_out_time`, `session_duration`, `created_at`, `updated_at`, `admission_year`, `current_year`, `shift`, `program`, `is_graduated`, `notes`) VALUES
(19, '24-ESWT-01', 'Anique Ali', '2025-10-16 11:01:07', 'Present', '2025-10-16 11:01:07', '2025-10-16 11:01:51', 0, '2025-10-16 06:01:07', '2025-10-16 09:31:09', 2024, NULL, 'Evening', 'SWT', 0, ''),
(20, '23-SWT-02', 'Naheed Akhter', '2024-01-15 09:00:00', 'Present', '2024-01-15 09:00:00', '2024-01-15 17:00:00', NULL, '2025-10-16 20:41:20', '2025-10-16 20:41:20', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(21, '23-SWT-02', 'Naheed Akhter', '2024-01-16 09:00:00', 'Present', '2024-01-16 09:00:00', '2024-01-16 17:00:00', NULL, '2025-10-16 20:41:20', '2025-10-16 20:41:20', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(22, '23-SWT-02', 'Naheed Akhter', '2024-01-17 09:00:00', 'Absent', NULL, NULL, NULL, '2025-10-16 20:41:20', '2025-10-16 20:41:20', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(23, '23-SWT-02', 'Naheed Akhter', '2024-01-18 09:00:00', 'Present', '2024-01-18 09:00:00', '2024-01-18 17:00:00', NULL, '2025-10-16 20:41:20', '2025-10-16 20:41:20', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(24, '23-SWT-02', 'Naheed Akhter', '2024-01-19 09:00:00', 'Present', '2024-01-19 09:00:00', '2024-01-19 17:00:00', NULL, '2025-10-16 20:41:20', '2025-10-16 20:41:20', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(25, '23-SWT-02', 'Naheed Akhter', '2024-12-01 09:00:00', 'Present', '2024-12-01 09:00:00', '2024-12-01 17:00:00', NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(26, '23-SWT-02', 'Naheed Akhter', '2024-12-02 09:00:00', 'Present', '2024-12-02 09:00:00', '2024-12-02 17:00:00', NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(27, '23-SWT-02', 'Naheed Akhter', '2024-12-03 09:00:00', 'Absent', NULL, NULL, NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(28, '23-SWT-02', 'Naheed Akhter', '2024-12-04 09:00:00', 'Present', '2024-12-04 09:00:00', '2024-12-04 17:00:00', NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(29, '23-SWT-02', 'Naheed Akhter', '2024-12-05 09:00:00', 'Present', '2024-12-05 09:00:00', '2024-12-05 17:00:00', NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(30, '23-SWT-02', 'Naheed Akhter', '2024-12-06 09:00:00', 'Absent', NULL, NULL, NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(31, '23-SWT-02', 'Naheed Akhter', '2024-12-09 09:00:00', 'Present', '2024-12-09 09:00:00', '2024-12-09 17:00:00', NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL),
(32, '23-SWT-02', 'Naheed Akhter', '2024-12-10 09:00:00', 'Present', '2024-12-10 09:00:00', '2024-12-10 17:00:00', NULL, '2025-10-16 20:41:45', '2025-10-16 20:41:45', 2023, NULL, 'Evening', 'SWT', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `check_in_sessions`
--

CREATE TABLE `check_in_sessions` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `last_activity` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `check_in_sessions`
--

INSERT INTO `check_in_sessions` (`id`, `student_id`, `student_name`, `check_in_time`, `last_activity`, `is_active`, `created_at`) VALUES
(8, '24-ESWT-01', 'Anique Ali', '2025-10-16 11:01:07', '2025-10-16 11:01:51', 0, '2025-10-16 06:01:07');

-- --------------------------------------------------------

--
-- Table structure for table `import_logs`
--

CREATE TABLE `import_logs` (
  `id` int(11) NOT NULL,
  `import_type` varchar(50) NOT NULL,
  `total_records` int(11) NOT NULL,
  `successful_records` int(11) NOT NULL,
  `failed_records` int(11) NOT NULL,
  `error_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_years` int(11) DEFAULT 4,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `code`, `name`, `description`, `duration_years`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'SWT', 'Software Technology', 'D.A.E', 3, 1, '2025-10-11 11:16:08', '2025-10-11 12:00:56'),
(4, 'CIT', 'Computer Information Technology', 'D.A.E', 3, 1, '2025-10-11 11:16:08', '2025-10-14 22:08:59'),
(23, 'ESWT', 'Software Technology Evening', 'D.A.E', 3, 1, '2025-10-13 07:42:13', '2025-10-16 05:25:38'),
(24, 'ECIT', 'Computer Information Technology Evening', 'D.A.E', 3, 1, '2025-10-13 07:42:27', '2025-10-16 05:25:43');

-- --------------------------------------------------------

--
-- Stand-in structure for view `program_stats`
-- (See below for the actual view)
--
CREATE TABLE `program_stats` (
`id` int(11)
,`code` varchar(10)
,`name` varchar(100)
,`is_active` tinyint(1)
,`total_students` bigint(21)
,`total_sections` bigint(21)
,`total_capacity` decimal(32,0)
,`avg_attendance` decimal(32,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `qr_data` text NOT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `student_id`, `qr_data`, `qr_image_path`, `generated_at`, `is_active`, `created_by`) VALUES
(35, '22-SWT-01', '22-SWT-01', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_22-SWT-01_1760186456.png', '2025-10-11 12:40:56', 1, 10),
(36, '22-SWT-02', '22-SWT-02', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_22-SWT-02_1760210056.png', '2025-10-11 19:14:16', 1, 10),
(37, '22-ESWT-02', '22-ESWT-02', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_22-ESWT-02_1760217877.png', '2025-10-11 21:24:37', 1, 10),
(38, '25-SWT-26', '25-SWT-26', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_25-SWT-26_1760429175.png', '2025-10-14 08:06:15', 1, 10),
(39, '25-SWT-03', '25-SWT-03', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_25-SWT-03_1760429238.png', '2025-10-14 08:07:18', 1, 10),
(40, '24-ESWT-01', '24-ESWT-01', 'C:\\xampp\\htdocs\\qr_attendance\\public/assets/img/qr_codes/qr_24-ESWT-01_1760444369.png', '2025-10-14 12:19:29', 1, 10),
(75, '25-SWT-595', '{\"student_id\":\"25-SWT-595\",\"name\":\"Sample Student 1\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(76, '25-SWT-596', '{\"student_id\":\"25-SWT-596\",\"name\":\"Sample Student 2\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(77, '25-CIT-597', '{\"student_id\":\"25-CIT-597\",\"name\":\"Sample Student 3\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(78, '25-CIT-598', '{\"student_id\":\"25-CIT-598\",\"name\":\"Sample Student 4\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(79, '25-SWT-599', '{\"student_id\":\"25-SWT-599\",\"name\":\"Sample Student 1\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(80, '25-SWT-600', '{\"student_id\":\"25-SWT-600\",\"name\":\"Sample Student 2\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(81, '25-CIT-601', '{\"student_id\":\"25-CIT-601\",\"name\":\"Sample Student 3\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10),
(82, '25-CIT-602', '{\"student_id\":\"25-CIT-602\",\"name\":\"Sample Student 4\",\"timestamp\":1760605851,\"type\":\"attendance\"}', '', '2025-10-16 09:10:51', 1, 10);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `year_level` enum('1st','2nd','3rd') NOT NULL,
  `section_name` varchar(10) NOT NULL,
  `shift` enum('Morning','Evening') NOT NULL,
  `capacity` int(11) DEFAULT 40,
  `current_students` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `program_id`, `year_level`, `section_name`, `shift`, `capacity`, `current_students`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, '1st', 'A', 'Morning', 41, 1, 1, '2025-10-11 22:15:27', '2025-10-16 09:31:34'),
(2, 1, '1st', 'B', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(3, 1, '1st', 'A', 'Evening', 41, 0, 1, '2025-10-11 22:15:27', '2025-10-13 07:57:56'),
(4, 1, '1st', 'B', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(5, 1, '2nd', 'A', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(6, 1, '2nd', 'B', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(7, 1, '2nd', 'A', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(8, 1, '2nd', 'B', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(9, 1, '3rd', 'A', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(10, 1, '3rd', 'B', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(11, 1, '3rd', 'A', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(12, 1, '3rd', 'B', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(13, 4, '1st', 'A', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(14, 4, '1st', 'B', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(15, 4, '1st', 'A', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(16, 4, '1st', 'B', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(17, 4, '2nd', 'A', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(18, 4, '2nd', 'B', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(19, 4, '2nd', 'A', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(20, 4, '2nd', 'B', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(21, 4, '3rd', 'A', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(22, 4, '3rd', 'B', 'Morning', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(23, 4, '3rd', 'A', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(24, 4, '3rd', 'B', 'Evening', 40, 0, 1, '2025-10-11 22:15:27', '2025-10-11 23:33:31'),
(25, 4, '1st', 'C', 'Evening', 40, 0, 1, '2025-10-14 20:49:08', '2025-10-14 20:49:08');

-- --------------------------------------------------------

--
-- Stand-in structure for view `section_stats`
-- (See below for the actual view)
--
CREATE TABLE `section_stats` (
`id` int(11)
,`section_name` varchar(10)
,`program_code` varchar(10)
,`program_name` varchar(100)
,`year_level` enum('1st','2nd','3rd')
,`shift` enum('Morning','Evening')
,`capacity` int(11)
,`current_students` bigint(21)
,`capacity_utilization` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`, `created_at`) VALUES
('1009eaa0ba3f893abc280f5c2dfa6a95a216dcca221d579f4f500f48681dfd94', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 22:55:35', '2025-10-12 22:38:33'),
('20a4d33533a9db29f3087eb8da315e8d56f25e99ba49fdee4228dbbc679c6203', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 15:29:23', '2025-10-12 15:28:53'),
('2e26cd1f54a55dd4bad475cc751bb7fa10fd10191ba107a00f19515fcaec6971', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 22:11:03', '2025-10-12 22:11:03'),
('35fe7dc19cd7872696b8c02b717750f38d3ccd8b0bb4ecd07bfde33990fb78e4', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 11:06:22', '2025-10-13 10:36:07'),
('4f18301bf687475ca144da8eab48e993341f3e747ac0c945164ab64ad13bd09f', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-11 12:49:36', '2025-10-11 12:23:26'),
('546a2ca0b5167208898d8aadeb96d136536e15dffec974224a1acc54bd286cb7', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-14 08:43:51', '2025-10-14 07:43:44'),
('5947eeafdd104f8a5202330f048b5513057c356de24abcf0f3c25d5f6736af19', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 19:57:58', '2025-10-13 19:00:00'),
('60d52610d3e9bf09293a779847cc491e3f803faf8976f17c2ddc2732c1ab1705', 10, 'unknown', 'unknown', NULL, '2025-10-12 22:36:02', '2025-10-12 22:36:02'),
('677e95ccfcf14eef4c79921fa151f05eee95b7208e1c6173154cf77a73212256', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 19:25:27', '2025-10-13 19:25:22'),
('785e481c6f218a0f2f7f2aae4c7da9ae192e028fa9abf34d49516089384d374d', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 09:23:12', '2025-10-13 07:56:42'),
('7d496ba5331e080bb8a350c567398b747d88384635793edca9c9a0560d14648c', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-11 23:40:19', '2025-10-11 22:11:08'),
('8738fc0a27babb8b534f0178e5dcb71648c93f60550c5a6492c8cc06b324fd0a', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-16 20:48:22', '2025-10-16 20:48:11'),
('89b6a2dbf3afaf56b7047b19c6461dbe65767e492daefe00cc7a3727a73da3dc', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 21:56:04', '2025-10-13 19:59:02'),
('944ad316e85cd701a0b93a965c668c229a27dd8aea7751b4c94e4aaf455fc733', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-16 00:38:28', '2025-10-15 21:31:29'),
('99465cca2614829aedf0479097b73652ea741c8f8185c7290a30b2bdfcb7ae36', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 22:11:00', '2025-10-12 22:11:00'),
('9d98c32cf1e5d2dcb7c6a865b931812397821adec81e9b4600401185575ad7e2', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 21:31:28', '2025-10-13 19:26:02'),
('9dc581cbe33106df88412e23a3f1c8532a8864f112d94edd52515b38598b525a', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 20:40:16', '2025-10-12 20:07:11'),
('afbf18251a0cfc75f381db412b5fdae90e5fb2e1023575f9ec8b96969f9aba9b', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-16 09:32:29', '2025-10-16 08:53:07'),
('b88f3096ec9cd331741fd0c13aff83b608d6a6afca94f3c69176f1f836b57493', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', NULL, '2025-10-11 20:27:44', '2025-10-11 20:27:43'),
('bb98d3e9a016c08abbe7e6cd188ab51c802f2d9a7636c08d31500aedc73c2440', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-13 07:54:33', '2025-10-13 05:54:36'),
('dd302bfe01e2d09824d73a3d10eecb9c9694ba62a22711460917626c0788541a', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-11 21:21:27', '2025-10-11 21:15:07'),
('df4fb47ae33b880ee836f2ac518b530d5b1af1110bcf008f9f35a976e888d5da', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 21:37:48', '2025-10-12 20:48:24'),
('df8bba17c4d6f1fc0e30ada663c2d31e7340cd228d3727465726a0ecd48ccbaf', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 20:07:03', '2025-10-12 19:35:34'),
('f0c763b4a0a63fa47ad976095f96dc41335c663996458669cb25116e6dfc6384', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-11 12:22:14', '2025-10-11 12:04:16'),
('f2fda114688050d36908dc8e7a2a7ca6ae5bc83d357c43a0f409469abf4430dd', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 22:10:46', '2025-10-12 22:10:31'),
('f8dc1addbb044e8f2ccf59376ef84b237f9e8c6db7207766236e16d1f1325540', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-11 21:31:42', '2025-10-11 21:27:24'),
('f94a0a16f0b071c77edb77d08401c5373f61f46848a19676caedcf3a1134a146', 10, 'unknown', 'unknown', NULL, '2025-10-12 22:37:59', '2025-10-12 22:37:59'),
('fb2db220e3a9cf7a6b04ae7bbc7358263e8364b904b489da7a9a96c365c71787', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-12 22:14:28', '2025-10-12 22:11:04');

-- --------------------------------------------------------

--
-- Table structure for table `shift_timings`
--

CREATE TABLE `shift_timings` (
  `id` int(11) NOT NULL,
  `shift_name` enum('Morning','Evening') NOT NULL,
  `checkin_start` time NOT NULL,
  `checkin_end` time NOT NULL,
  `class_end` time NOT NULL,
  `minimum_duration_minutes` int(11) DEFAULT 120,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_timings`
--

INSERT INTO `shift_timings` (`id`, `shift_name`, `checkin_start`, `checkin_end`, `class_end`, `minimum_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Morning', '09:00:00', '11:00:00', '13:40:00', 120, 1, '2025-10-11 11:16:08', '2025-10-11 11:16:08'),
(2, 'Evening', '15:00:00', '16:00:00', '18:00:00', 120, 1, '2025-10-11 11:16:08', '2025-10-11 11:16:08');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `roll_number` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admission_year` int(11) DEFAULT NULL,
  `current_year` int(11) DEFAULT 1,
  `shift` enum('Morning','Evening') DEFAULT 'Morning',
  `program` varchar(50) DEFAULT NULL,
  `last_year_update` date DEFAULT NULL,
  `is_graduated` tinyint(1) DEFAULT 0,
  `year_level` enum('1st','2nd','3rd') DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `roll_prefix` varchar(20) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `attendance_percentage` decimal(5,2) DEFAULT 0.00,
  `username` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `roll_number`, `name`, `email`, `phone`, `password`, `user_id`, `is_active`, `created_at`, `updated_at`, `admission_year`, `current_year`, `shift`, `program`, `last_year_update`, `is_graduated`, `year_level`, `section`, `roll_prefix`, `section_id`, `attendance_percentage`, `username`) VALUES
(141, '24-ESWT-01', '24-ESWT-01', 'Anique Ali', 'aniqueali000@gmail.com', '+923010020668', '24-ESWT-01', NULL, 1, '2025-10-16 05:57:41', '2025-10-16 05:57:41', 2024, 1, 'Evening', 'SWT', NULL, 0, '2nd', 'A', NULL, NULL, 0.00, '24-ESWT-01'),
(150, '23-SWT-02', '23-SWT-02', 'Naheed Akhter', 'aniquecode@gmail.com', '+50703010020668', '23-SWT-02', NULL, 1, '2025-10-16 19:01:14', '2025-10-16 20:41:54', 2023, 1, 'Morning', 'SWT', NULL, 0, '3rd', 'A', NULL, NULL, 75.50, '23-SWT-02');

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_stats`
-- (See below for the actual view)
--
CREATE TABLE `student_stats` (
`student_id` varchar(50)
,`name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`program` varchar(50)
,`shift` enum('Morning','Evening')
,`year_level` enum('1st','2nd','3rd')
,`section` varchar(10)
,`program_name` varchar(100)
,`capacity` int(11)
,`total_attendance` bigint(21)
,`present_count` decimal(22,0)
,`attendance_percentage` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` int(11) NOT NULL,
  `sync_type` enum('push_to_web','pull_from_web','bidirectional') NOT NULL,
  `status` enum('success','failed','partial') NOT NULL,
  `records_processed` int(11) DEFAULT 0,
  `records_failed` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `sync_duration` decimal(10,3) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` varchar(50) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `validation_rules`, `last_updated`, `updated_by`, `created_at`) VALUES
(1, 'morning_checkin_start', '09:00', 'time', 'shift_timings', 'Morning shift check-in start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(2, 'morning_checkin_end', '12:00', 'time', 'shift_timings', 'Morning shift check-in end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(3, 'morning_checkout_start', '12:00', 'time', 'shift_timings', 'Morning shift check-out start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(4, 'morning_checkout_end', '13:40', 'time', 'shift_timings', 'Morning shift check-out end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(5, 'morning_class_end', '13:40', 'time', 'shift_timings', 'Morning shift class end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(6, 'evening_checkin_start', '09:00', 'time', 'shift_timings', 'Evening shift check-in start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(7, 'evening_checkin_end', '12:00', 'time', 'shift_timings', 'Evening shift check-in end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(8, 'evening_checkout_start', '09:00', 'time', 'shift_timings', 'Evening shift check-out start time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(9, 'evening_checkout_end', '14:00', 'time', 'shift_timings', 'Evening shift check-out end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(10, 'evening_class_end', '14:00', 'time', 'shift_timings', 'Evening shift class end time', '{\"required\":true,\"type\":\"time\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(11, 'minimum_duration_minutes', '130', 'integer', 'system_config', 'Minimum duration in minutes for attendance', '{\"required\":true,\"min\":30,\"max\":480}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(12, 'sync_interval_seconds', '30', 'integer', 'system_config', 'Automatic sync interval in seconds', '{\"required\":true,\"min\":10,\"max\":300}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(13, 'timezone', 'Asia/Karachi', 'string', 'system_config', 'System timezone', '{\"required\":true,\"options\":[\"Asia\\/Karachi\",\"UTC\",\"America\\/New_York\",\"Europe\\/London\"]}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(14, 'academic_year_start_month', '9', 'integer', 'system_config', 'Academic year start month (1-12)', '{\"required\":true,\"min\":1,\"max\":12}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(15, 'auto_absent_morning_hour', '11', 'integer', 'system_config', 'Hour to mark morning shift absent (24h format)', '{\"required\":true,\"min\":8,\"max\":16}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(16, 'auto_absent_evening_hour', '17', 'integer', 'system_config', 'Hour to mark evening shift absent (24h format)', '{\"required\":true,\"min\":14,\"max\":20}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(17, 'website_url', 'http://localhost/qr_attendance/public', 'url', 'integration', 'Base URL of the web application', '{\"required\":true,\"type\":\"url\"}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(18, 'api_endpoint_attendance', '/api/api_attendance.php', 'string', 'integration', 'Attendance API endpoint', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(19, 'api_endpoint_checkin', '/api/checkin_api.php', 'string', 'integration', 'Check-in API endpoint', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(20, 'api_endpoint_dashboard', '/api/dashboard_api.php', 'string', 'integration', 'Dashboard API endpoint', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(21, 'api_key', 'attendance_2025_xyz789_secure', 'string', 'integration', 'API authentication key', '{\"required\":true,\"min_length\":10}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(22, 'api_timeout_seconds', '30', 'integer', 'integration', 'API request timeout in seconds', '{\"required\":true,\"min\":5,\"max\":120}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(23, 'debug_mode', 'true', 'boolean', 'advanced', 'Enable debug mode for development', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(24, 'log_errors', 'true', 'boolean', 'advanced', 'Enable error logging', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(25, 'show_errors', 'true', 'boolean', 'advanced', 'Show errors in development mode', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(26, 'session_timeout_seconds', '3600', 'integer', 'advanced', 'Session timeout seconds', '{\"required\":true,\"min\":300,\"max\":86400}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(27, 'max_login_attempts', '5', 'integer', 'advanced', 'Max login attempts', '{\"required\":true,\"min\":3,\"max\":10}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(28, 'login_lockout_minutes', '15', 'integer', 'advanced', 'Login lockout minutes', '{\"required\":true,\"min\":5,\"max\":60}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(29, 'password_min_length', '8', 'integer', 'advanced', 'Password min length', '{\"required\":true,\"min\":6,\"max\":32}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(30, 'max_sync_records', '1000', 'integer', 'advanced', 'Max sync records', '{\"required\":true,\"min\":100,\"max\":10000}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(31, 'api_rate_limit', '100', 'integer', 'advanced', 'Api rate limit', '{\"required\":true,\"min\":10,\"max\":1000}', '2025-10-16 09:31:42', 'admin', '2025-10-12 00:09:58'),
(56, 'qr_code_size', '200', 'integer', 'qr_code', 'Qr code size', '{\"required\":true,\"min\":100,\"max\":500}', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(57, 'qr_code_margin', '10', 'integer', 'qr_code', 'Qr code margin', '{\"required\":true,\"min\":0,\"max\":50}', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(58, 'max_file_size_mb', '5', 'integer', 'file_upload', 'Max file size mb', '{\"required\":true,\"min\":1,\"max\":100}', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(59, 'smtp_host', 'smtp.gmail.com', 'string', 'email', 'Smtp host', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(60, 'smtp_port', '587', 'integer', 'email', 'Smtp port', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(61, 'smtp_from_email', 'noreply@example.com', 'email', 'email', 'Smtp from email', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(62, 'smtp_from_name', 'QR Attendance System', 'string', 'email', 'Smtp from name', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-12 00:29:17'),
(96, 'qr_code_path', 'assets/img/qr_codes/', 'string', 'qr_code', 'Directory to store QR code images', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(98, 'allowed_extensions', 'csv,json,xlsx', 'string', 'file_upload', 'Comma-separated list of allowed file extensions', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(101, 'smtp_username', '', 'string', 'email', 'SMTP authentication username', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(102, 'smtp_password', '', 'string', 'email', 'SMTP authentication password', '[]', '2025-10-13 15:44:27', 'admin', '2025-10-13 08:03:02'),
(1725, 'globalSearchInput', '', 'string', 'general', 'System setting', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-15 21:16:01'),
(1726, 'searchAll', 'all', 'string', 'general', 'System setting', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-15 21:16:01'),
(1727, 'searchStudents', 'students', 'string', 'general', 'System setting', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-15 21:16:01'),
(1728, 'searchPrograms', 'programs', 'string', 'general', 'System setting', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-15 21:16:01'),
(1729, 'searchAttendance', 'attendance', 'string', 'general', 'System setting', '[]', '2025-10-16 09:31:42', 'admin', '2025-10-15 21:16:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','student','teacher') NOT NULL DEFAULT 'student',
  `student_id` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `student_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(10, 'admin', 'aniquecodes@gmail.com', '$2y$10$uzcDa0cHVM014FnZlgTIGeqg.LKPZHTWJolarBF.F8asMD0dRBHF2', 'admin', NULL, 1, '2025-10-16 20:48:11', '2025-10-11 12:02:11', '2025-10-16 20:48:11');

-- --------------------------------------------------------

--
-- Table structure for table `year_progression_log`
--

CREATE TABLE `year_progression_log` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `old_year` int(11) NOT NULL,
  `new_year` int(11) NOT NULL,
  `progression_date` date NOT NULL,
  `progression_type` enum('automatic','manual') DEFAULT 'automatic',
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `program_stats`
--
DROP TABLE IF EXISTS `program_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `program_stats`  AS SELECT `p`.`id` AS `id`, `p`.`code` AS `code`, `p`.`name` AS `name`, `p`.`is_active` AS `is_active`, count(distinct `s`.`student_id`) AS `total_students`, count(distinct `sec`.`id`) AS `total_sections`, sum(`sec`.`capacity`) AS `total_capacity`, avg(`stats`.`attendance_percentage`) AS `avg_attendance` FROM (((`programs` `p` left join `sections` `sec` on(`p`.`id` = `sec`.`program_id` and `sec`.`is_active` = 1)) left join `students` `s` on(`s`.`section_id` = `sec`.`id`)) left join `student_stats` `stats` on(`s`.`student_id` = `stats`.`student_id`)) GROUP BY `p`.`id`, `p`.`code`, `p`.`name`, `p`.`is_active` ;

-- --------------------------------------------------------

--
-- Structure for view `section_stats`
--
DROP TABLE IF EXISTS `section_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `section_stats`  AS SELECT `sec`.`id` AS `id`, `sec`.`section_name` AS `section_name`, `p`.`code` AS `program_code`, `p`.`name` AS `program_name`, `sec`.`year_level` AS `year_level`, `sec`.`shift` AS `shift`, `sec`.`capacity` AS `capacity`, count(`s`.`student_id`) AS `current_students`, round(count(`s`.`student_id`) / `sec`.`capacity` * 100,2) AS `capacity_utilization` FROM ((`sections` `sec` join `programs` `p` on(`sec`.`program_id` = `p`.`id`)) left join `students` `s` on(`s`.`section_id` = `sec`.`id`)) WHERE `sec`.`is_active` = 1 GROUP BY `sec`.`id`, `sec`.`section_name`, `p`.`code`, `p`.`name`, `sec`.`year_level`, `sec`.`shift`, `sec`.`capacity` ;

-- --------------------------------------------------------

--
-- Structure for view `student_stats`
--
DROP TABLE IF EXISTS `student_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_stats`  AS SELECT `s`.`student_id` AS `student_id`, `s`.`name` AS `name`, `s`.`email` AS `email`, `s`.`phone` AS `phone`, `s`.`program` AS `program`, `s`.`shift` AS `shift`, `s`.`year_level` AS `year_level`, `s`.`section` AS `section`, `p`.`name` AS `program_name`, `sec`.`capacity` AS `capacity`, count(`a`.`id`) AS `total_attendance`, sum(case when `a`.`status` = 'Present' then 1 else 0 end) AS `present_count`, CASE WHEN count(`a`.`id`) > 0 THEN round(sum(case when `a`.`status` = 'Present' then 1 else 0 end) / count(`a`.`id`) * 100,2) ELSE 0 END AS `attendance_percentage` FROM (((`students` `s` left join `programs` `p` on(`s`.`program` = `p`.`code`)) left join `sections` `sec` on(`s`.`section_id` = `sec`.`id`)) left join `attendance` `a` on(`s`.`student_id` = `a`.`student_id`)) GROUP BY `s`.`student_id`, `s`.`name`, `s`.`email`, `s`.`phone`, `s`.`program`, `s`.`shift`, `s`.`year_level`, `s`.`section`, `p`.`name`, `sec`.`capacity` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_is_current` (`is_current`),
  ADD KEY `idx_academic_current` (`is_current`,`year`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_attendance_student_timestamp` (`student_id`,`timestamp`),
  ADD KEY `idx_check_in_time` (`check_in_time`),
  ADD KEY `idx_check_out_time` (`check_out_time`),
  ADD KEY `idx_attendance_program_shift` (`program`,`shift`,`timestamp`),
  ADD KEY `idx_attendance_checkin_checkout` (`student_id`,`check_in_time`,`check_out_time`),
  ADD KEY `idx_attendance_years` (`admission_year`,`current_year`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_attendance_timestamp_status` (`timestamp`,`status`),
  ADD KEY `idx_attendance_student_date_status` (`student_id`,`timestamp`,`status`);

--
-- Indexes for table `check_in_sessions`
--
ALTER TABLE `check_in_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_session` (`student_id`,`is_active`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_check_in_time` (`check_in_time`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_checkin_active` (`is_active`,`student_id`,`check_in_time`),
  ADD KEY `idx_checkin_activity` (`last_activity`,`is_active`);

--
-- Indexes for table `import_logs`
--
ALTER TABLE `import_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_import_type_date` (`import_type`,`created_at`),
  ADD KEY `idx_import_created` (`created_at`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_programs_active_name` (`is_active`,`name`(50));

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_qr_student_active` (`student_id`,`is_active`,`generated_at`),
  ADD KEY `idx_qr_generated` (`generated_at`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section` (`program_id`,`year_level`,`section_name`,`shift`),
  ADD KEY `idx_program_year` (`program_id`,`year_level`),
  ADD KEY `idx_shift` (`shift`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_sections_filter` (`program_id`,`year_level`,`shift`,`is_active`),
  ADD KEY `idx_sections_capacity` (`program_id`,`is_active`,`capacity`,`current_students`),
  ADD KEY `idx_sections_year_shift` (`year_level`,`shift`,`is_active`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`),
  ADD KEY `idx_sessions_cleanup` (`last_activity`,`user_id`);

--
-- Indexes for table `shift_timings`
--
ALTER TABLE `shift_timings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_shift` (`shift_name`),
  ADD KEY `idx_shift_name` (`shift_name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_students_admission_year` (`admission_year`),
  ADD KEY `idx_students_current_year` (`current_year`),
  ADD KEY `idx_students_shift` (`shift`),
  ADD KEY `idx_students_program` (`program`),
  ADD KEY `idx_students_is_graduated` (`is_graduated`),
  ADD KEY `idx_program` (`program`),
  ADD KEY `idx_shift` (`shift`),
  ADD KEY `idx_year_level` (`year_level`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_admission_year` (`admission_year`),
  ADD KEY `idx_section_id` (`section_id`),
  ADD KEY `idx_students_program_shift_year` (`program`,`shift`,`year_level`),
  ADD KEY `idx_last_year_update` (`last_year_update`),
  ADD KEY `idx_roll_prefix` (`roll_prefix`),
  ADD KEY `idx_students_section_active` (`section_id`,`is_active`,`year_level`),
  ADD KEY `idx_students_name` (`name`(50)),
  ADD KEY `idx_students_email` (`email`),
  ADD KEY `idx_students_roll` (`roll_number`,`is_active`);

--
-- Indexes for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_type` (`sync_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_sync_type_status` (`sync_type`,`status`,`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_settings_category_key` (`category`,`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `year_progression_log`
--
ALTER TABLE `year_progression_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_progression_date` (`progression_date`),
  ADD KEY `idx_progression_student_date` (`student_id`,`progression_date`,`old_year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `check_in_sessions`
--
ALTER TABLE `check_in_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `import_logs`
--
ALTER TABLE `import_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `shift_timings`
--
ALTER TABLE `shift_timings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1730;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `year_progression_log`
--
ALTER TABLE `year_progression_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `qr_codes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `year_progression_log`
--
ALTER TABLE `year_progression_log`
  ADD CONSTRAINT `year_progression_log_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
