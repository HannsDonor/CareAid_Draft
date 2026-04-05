-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 29, 2026 at 08:35 AM
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
-- Database: `careaid_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('senior','admin','health_worker','finance_officer') NOT NULL,
  `account_status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`account_id`, `username`, `password_hash`, `role`, `account_status`, `created_at`) VALUES
(1, 'sample1', '$2y$10$m/K.QDXZoWn9rP0caNCNXOaEZVANEQMG6fXRMri9TM3M9m.fdbTeS', 'senior', 'active', '2026-03-05 10:25:30'),
(2, 'senior1', '$2y$10$yHKMk7lbdAb.432YOPHN1uhoo9HaUpC9j7JEvQoSCgIWjRECvBcG.', 'senior', 'active', '2026-03-09 00:59:18'),
(3, 'admin', '$2y$10$siFOMG5wC45gJcRPtlbvZuZjWo3XWGumBMVukdiaKiMcGIjZcHY3e', 'admin', 'active', '2026-03-09 01:07:21'),
(26, 'testsenior11', '$2y$10$1VatLlow7z4ITaG0aUsdeuL7bupxJAs8i2ssIbCwX2FtSFlXentd6', 'senior', 'active', '2026-03-11 13:52:13'),
(27, 'workingsenior11', '$2y$10$JfYv.RUf2Kj788wCnc0pweVWh4xTjfRXxTrixbn/bvhoJr4UyRPgi', 'senior', 'active', '2026-03-11 13:52:51'),
(28, 'workingsenior211', '$2y$10$ii51K2XjTD2VcLuktAfv0OQZZfexa5ulGMBvqkoiqQ57oiY4Wafum', 'senior', 'active', '2026-03-11 14:25:35'),
(29, 'workingsenior311', '$2y$10$VU4HMs7vivLPwnbZusHHOOJx9/Qd43rBJbu7tBJV1wRG5CKnhFW4K', 'senior', 'active', '2026-03-11 14:26:34'),
(30, 'healthworker1', '$2y$10$tO1I.eMXIsUYYAcvSF7BC.4ar8xUTWEnZqXPb2whFY.DGjcIBvEC2', 'health_worker', 'active', '2026-03-11 15:35:44'),
(31, 'workingsenior411', '$2y$10$q0uLCbvAbjETmZq1QUnePui6A02.KrD5ex427OySMsYy3WL1YW74i', 'senior', 'active', '2026-03-12 09:08:49'),
(32, 'workingsenior511', '$2y$10$UZafsAg4j8mGeaGljEFQJe.13X5Yq34zTwfDnDHmnwAuxfp2pjZyO', 'senior', 'active', '2026-03-13 02:52:28'),
(33, 'workingsenior611', '$2y$10$peCzIpsKh/oLUvx3dPDRa.pFPP5PrEwRHdQBorOHGeqaXKwseJFK2', 'senior', 'active', '2026-03-13 03:07:25'),
(34, 'janedoesmith', '$2y$10$XTyDWAOgk036Yk9JPagNOe6P7wkNK9EKcC851dZurVpl8H4BNjWCy', 'senior', 'active', '2026-03-13 16:11:44');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `admin_account_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `admin_account_id`, `title`, `message`, `posted_at`, `expires_at`, `status`) VALUES
(1, 3, 'Sample Announcement', 'This is just a sample announcement', '2026-03-13 16:26:45', '2026-03-16', 'active'),
(2, 3, 'Sample Announcement', 'This is a sample announcement,', '2026-03-27 05:59:48', '2026-03-31', 'active'),
(3, 3, 'Financial Assistance', 'This is for seniors above 70', '2026-03-27 06:24:25', '2026-03-28', 'active'),
(4, 3, 'Legarda Tulong para sa Senior', 'Mga Kailangan:\r\nSenior ID:\r\nBirth Certificate:\r\nsample need\r\nsample need:\r\nsample need:', '2026-03-27 07:32:56', '2026-03-28', 'active'),
(5, 3, 'BIGAY NI QUIBOLOY', 'REQUIREMENTS SAMPLES', '2026-03-29 06:12:47', '2026-03-31', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_pictures`
--

CREATE TABLE `announcement_pictures` (
  `picture_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `picture_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_pictures`
--

INSERT INTO `announcement_pictures` (`picture_id`, `announcement_id`, `picture_path`, `caption`, `uploaded_at`) VALUES
(1, 1, 'announcement_1_1773419205_8016.png', 'Sample', '2026-03-13 16:26:45'),
(2, 2, 'announcement_2_1774591188_0_5163.png', '', '2026-03-27 05:59:48'),
(3, 2, 'announcement_2_1774591188_1_6565.png', '', '2026-03-27 05:59:48'),
(4, 3, 'announcement_3_1774592665_0_6964.jpg', '', '2026-03-27 06:24:25'),
(5, 4, 'announcement_4_1774596776_0_6176.jpg', 'Sample Caption', '2026-03-27 07:32:56'),
(6, 5, 'announcement_5_1774764767_0_9498.jpg', '', '2026-03-29 06:12:47'),
(7, 5, 'announcement_5_1774764767_1_1952.jpg', '', '2026-03-29 06:12:47'),
(8, 5, 'announcement_5_1774764767_2_4158.jpg', '', '2026-03-29 06:12:47');

-- --------------------------------------------------------

--
-- Table structure for table `assistance_requests`
--

CREATE TABLE `assistance_requests` (
  `request_id` int(11) NOT NULL,
  `senior_id` int(11) NOT NULL,
  `request_type` enum('medical','financial','barangay') NOT NULL,
  `sub_type` varchar(100) DEFAULT NULL,
  `status` enum('pending','accepted','in_progress','completed','cancelled') DEFAULT 'pending',
  `request_date` date NOT NULL,
  `request_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assistance_requests`
--

INSERT INTO `assistance_requests` (`request_id`, `senior_id`, `request_type`, `sub_type`, `status`, `request_date`, `request_time`) VALUES
(1, 11, 'medical', 'House Visit Request', 'pending', '2026-03-14', '11:56:00'),
(2, 11, 'medical', 'House Visit Request', 'pending', '2026-03-15', '10:15:00'),
(3, 11, 'medical', 'Walk In', 'pending', '2026-03-15', '13:38:00'),
(4, 11, 'medical', 'House Visit Request', 'pending', '2026-03-15', '16:30:00'),
(5, 11, 'medical', 'Walk In', 'cancelled', '2026-03-18', '06:00:00'),
(6, 11, 'medical', 'House Visit Request', 'pending', '2026-03-18', '01:03:00'),
(7, 11, 'financial', 'Financial', 'pending', '2026-03-18', '05:53:31'),
(8, 11, 'medical', 'Walk In', 'pending', '2026-03-17', '12:56:00'),
(9, 11, 'medical', 'House Visit Request', 'accepted', '2026-03-29', '16:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `barangay_staff_profile`
--

CREATE TABLE `barangay_staff_profile` (
  `staff_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_path` varchar(255) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_staff_profile`
--

INSERT INTO `barangay_staff_profile` (`staff_id`, `first_name`, `last_name`, `middle_name`, `gender`, `birth_date`, `position`, `contact_number`, `email`, `address`, `profile_path`, `account_id`, `created_at`, `updated_at`) VALUES
(1, 'Health', '1', 'Worker', 'male', '2026-03-28', '', '09123456789', 'sample@gmail.com', 'New Sample Address', 'staff_30_20260315044638_7530.jpg', 30, '2026-03-11 15:35:44', '2026-03-15 03:50:59');

-- --------------------------------------------------------

--
-- Table structure for table `checkups`
--

CREATE TABLE `checkups` (
  `checkup_id` int(11) NOT NULL,
  `senior_id` int(11) NOT NULL,
  `blood_pressure` varchar(10) NOT NULL,
  `blood_sugar` decimal(5,2) NOT NULL,
  `risk_level` enum('Low','Moderate','High') DEFAULT 'Low',
  `checkup_date` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checkups`
--

INSERT INTO `checkups` (`checkup_id`, `senior_id`, `blood_pressure`, `blood_sugar`, `risk_level`, `checkup_date`, `notes`, `heart_rate`) VALUES
(1, 5, '120', 100.00, 'High', '2026-03-11 22:16:52', 'Severe Illness', 180),
(2, 7, '100', 123.00, 'Moderate', '2026-03-11 22:27:34', NULL, 100),
(3, 7, '100', 120.00, 'Moderate', '2026-03-11 22:28:41', 'fsasaf', 75);

-- --------------------------------------------------------

--
-- Table structure for table `financial_programs`
--

CREATE TABLE `financial_programs` (
  `program_id` int(11) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `monthly_amount` decimal(10,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_records`
--

CREATE TABLE `financial_records` (
  `financial_record_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_status` enum('pending','processed','failed') DEFAULT 'processed',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `health_record_id` int(11) NOT NULL,
  `senior_id` int(11) NOT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `risk_level` enum('Low','Moderate','High','Critical') DEFAULT 'Low'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`health_record_id`, `senior_id`, `chronic_conditions`, `notes`, `created_at`, `updated_at`, `risk_level`) VALUES
(6, 6, 'Hypertension', NULL, '2026-03-11 14:25:35', '2026-03-11 14:25:35', 'Moderate'),
(7, 6, 'Osteoporosis', NULL, '2026-03-11 14:25:35', '2026-03-11 14:25:35', 'Moderate'),
(8, 7, 'Kidney Disease', NULL, '2026-03-11 14:26:34', '2026-03-11 14:26:34', 'Critical'),
(9, 7, 'Osteoporosis', NULL, '2026-03-11 14:26:34', '2026-03-11 14:26:34', 'Moderate'),
(10, 8, 'Seasonal Allergies', NULL, '2026-03-12 09:08:49', '2026-03-12 09:08:49', 'Moderate'),
(11, 8, 'Common Cold', NULL, '2026-03-12 09:08:49', '2026-03-12 09:08:49', 'Low'),
(14, 5, 'Common Cold', '', '2026-03-13 01:21:50', '2026-03-13 01:21:50', 'Low'),
(17, 11, 'Type 2 Diabetes', NULL, '2026-03-13 16:11:44', '2026-03-13 16:11:44', 'High');

-- --------------------------------------------------------

--
-- Table structure for table `illnesses`
--

CREATE TABLE `illnesses` (
  `illness_id` int(11) NOT NULL,
  `illness_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `risk_level` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `illnesses`
--

INSERT INTO `illnesses` (`illness_id`, `illness_name`, `category`, `description`, `risk_level`, `created_at`, `updated_at`) VALUES
(1, 'Common Cold', 'Respiratory', 'Minor viral infection; usually resolves with rest.', 1, '2026-03-12 08:20:02', '2026-03-12 08:20:02'),
(2, 'Seasonal Allergies', 'Immune', 'Managed with antihistamines; non-life-threatening.', 2, '2026-03-12 08:20:02', '2026-03-12 08:20:02'),
(3, 'Hypertension', 'Cardiovascular', 'Requires daily medication and monitoring.', 3, '2026-03-12 08:20:02', '2026-03-12 08:20:02'),
(4, 'Type 2 Diabetes', 'Endocrine', 'High risk of complications if not strictly managed.', 4, '2026-03-12 08:20:02', '2026-03-12 08:20:02'),
(5, 'Heart Failure', 'Cardiovascular', 'Critical condition requiring intensive medical care.', 5, '2026-03-12 08:20:02', '2026-03-12 08:20:02');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `notification_type` enum('assistance','system') NOT NULL,
  `assistance_type` enum('medical','financial','barangay') DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `account_id`, `notification_type`, `assistance_type`, `title`, `message`, `status`, `is_deleted`, `created_at`) VALUES
(1, 34, 'assistance', 'medical', 'Medical Assistance Request Submitted', 'Medical request (Walk In) scheduled for March 18, 2026 at 6:00 AM. Status: pending.', 'read', 1, '2026-03-17 16:45:45'),
(2, 3, 'assistance', 'medical', 'Medical Assistance Request Submitted', 'Medical request (House Visit Request) scheduled for March 18, 2026 at 1:03 AM. Status: pending.', 'read', 0, '2026-03-17 17:03:41'),
(3, 30, 'assistance', 'medical', 'Medical Assistance Request Submitted', 'Medical request (House Visit Request) scheduled for March 18, 2026 at 1:03 AM. Status: pending.', 'read', 0, '2026-03-17 17:03:41'),
(4, 34, 'assistance', 'medical', 'Assistance Request Accepted', 'Your medical assistance request has been accepted.', 'read', 1, '2026-03-17 17:24:46'),
(5, 34, 'assistance', 'medical', 'Assistance Request Accepted', 'Your medical assistance request has been accepted.', 'read', 1, '2026-03-17 17:52:10'),
(6, 34, 'assistance', 'medical', 'Assistance Request Rejected', 'Your medical assistance request has been rejected.', 'read', 1, '2026-03-17 18:12:34'),
(7, 34, 'assistance', 'medical', 'Assistance Request Accepted', 'Your medical assistance request has been accepted.', 'read', 1, '2026-03-17 19:07:42'),
(8, 34, 'assistance', 'medical', 'Assistance Request Rejected', 'Your medical assistance request has been rejected.', 'read', 0, '2026-03-17 19:43:17'),
(9, 34, 'assistance', 'medical', 'Assistance Request Rejected', 'Your medical assistance request has been rejected.', 'read', 0, '2026-03-17 19:43:25'),
(10, 34, 'assistance', 'medical', 'Assistance Request Cancelled', 'Your medical assistance request has been cancelled.', 'read', 0, '2026-03-17 19:48:40'),
(11, 3, 'assistance', 'financial', 'Financial Assistance Request Submitted', 'Financial assistance request submitted on March 18, 2026 at 5:53 AM. Status: pending.', 'unread', 0, '2026-03-18 04:53:31'),
(12, 30, 'assistance', 'financial', 'Financial Assistance Request Submitted', 'Financial assistance request submitted on March 18, 2026 at 5:53 AM. Status: pending.', 'read', 0, '2026-03-18 04:53:31'),
(13, 3, 'assistance', 'medical', 'Medical Assistance Request Submitted', 'Medical request (Walk In) scheduled for March 17, 2026 at 12:56 PM. Status: pending.', 'unread', 0, '2026-03-18 04:54:38'),
(14, 30, 'assistance', 'medical', 'Medical Assistance Request Submitted', 'Medical request (Walk In) scheduled for March 17, 2026 at 12:56 PM. Status: pending.', 'read', 0, '2026-03-18 04:54:38'),
(15, 3, 'assistance', 'medical', 'Medical Assistance Request Submitted', 'Medical request (House Visit Request) scheduled for March 29, 2026 at 4:30 PM. Status: pending.', 'unread', 0, '2026-03-29 06:30:18'),
(16, 30, 'assistance', 'medical', 'Medical Assistance Request Submitted', 'Medical request (House Visit Request) scheduled for March 29, 2026 at 4:30 PM. Status: pending.', 'read', 0, '2026-03-29 06:30:18'),
(17, 34, 'assistance', 'medical', 'Assistance Request Accepted', 'Your medical assistance request has been accepted.', 'read', 0, '2026-03-29 06:31:04');

-- --------------------------------------------------------

--
-- Table structure for table `senior_profiles`
--

CREATE TABLE `senior_profiles` (
  `senior_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `profile_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `priority_level` tinyint(4) NOT NULL DEFAULT 3 CHECK (`priority_level` between 1 and 5),
  `is_alive` enum('yes','no') NOT NULL DEFAULT 'yes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `senior_profiles`
--

INSERT INTO `senior_profiles` (`senior_id`, `account_id`, `first_name`, `last_name`, `middle_name`, `birth_date`, `gender`, `address`, `contact_number`, `emergency_contact`, `profile_path`, `created_at`, `priority_level`, `is_alive`) VALUES
(4, 26, 'test senior', '1', '1', '2026-03-27', NULL, 'Sample Address', '09123456789', '09123456789', NULL, '2026-03-11 13:52:13', 1, 'yes'),
(5, 27, 'working senior', '1', '1', '1978-01-12', 'Female', 'Sample Address', '09123456789', '09123456789', 'senior_5_1773301098_2307.jpg', '2026-03-11 13:52:51', 1, 'no'),
(6, 28, 'working senior 2', '1', '1', '2026-03-27', 'Female', 'Sample Address', '09123456789', '09123456789', '1773239135_face_66.jpg', '2026-03-11 14:25:35', 3, 'yes'),
(7, 29, 'working senior 3', '1', '1', '2026-03-27', 'Male', 'Sample Address', '09123456789', '09123456789', '1773239194_CduyKg.jpg', '2026-03-11 14:26:34', 5, 'yes'),
(8, 31, 'working senior 4', '1', '1', '2026-03-27', 'Male', 'Sample Address', '09123456789', '09123456789', '1773306529_face_51.jpg', '2026-03-12 09:08:49', 2, 'yes'),
(9, 32, 'working senior 5', '1', '1', '1982-02-27', 'Male', 'Sample Address', '09123456789', '09123456789', '1773370348_face_67.jpg', '2026-03-13 02:52:28', 1, 'yes'),
(10, 33, 'Working Senior 6', '1', '1', '1966-03-07', 'Male', 'Sample Address', '09123456789', '09123456789', NULL, '2026-03-13 03:07:25', 1, 'yes'),
(11, 34, 'Jane', 'Smith', 'Doe', '1963-01-14', 'Male', 'Sample Address', '09123456789', '09123456789', '1773418304_face_62.jpg', '2026-03-13 16:11:44', 4, 'yes');

-- --------------------------------------------------------

--
-- Table structure for table `senior_program_enrollments`
--

CREATE TABLE `senior_program_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `admin_account_id` (`admin_account_id`);

--
-- Indexes for table `announcement_pictures`
--
ALTER TABLE `announcement_pictures`
  ADD PRIMARY KEY (`picture_id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `assistance_requests`
--
ALTER TABLE `assistance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `senior_id` (`senior_id`);

--
-- Indexes for table `barangay_staff_profile`
--
ALTER TABLE `barangay_staff_profile`
  ADD PRIMARY KEY (`staff_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `checkups`
--
ALTER TABLE `checkups`
  ADD PRIMARY KEY (`checkup_id`),
  ADD KEY `senior_id` (`senior_id`);

--
-- Indexes for table `financial_programs`
--
ALTER TABLE `financial_programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `program_name` (`program_name`);

--
-- Indexes for table `financial_records`
--
ALTER TABLE `financial_records`
  ADD PRIMARY KEY (`financial_record_id`),
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`health_record_id`),
  ADD KEY `profile_id` (`senior_id`);

--
-- Indexes for table `illnesses`
--
ALTER TABLE `illnesses`
  ADD PRIMARY KEY (`illness_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  ADD PRIMARY KEY (`senior_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `senior_program_enrollments`
--
ALTER TABLE `senior_program_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `profile_id` (`profile_id`),
  ADD KEY `program_id` (`program_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `announcement_pictures`
--
ALTER TABLE `announcement_pictures`
  MODIFY `picture_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `assistance_requests`
--
ALTER TABLE `assistance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `barangay_staff_profile`
--
ALTER TABLE `barangay_staff_profile`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `checkups`
--
ALTER TABLE `checkups`
  MODIFY `checkup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `financial_programs`
--
ALTER TABLE `financial_programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `financial_records`
--
ALTER TABLE `financial_records`
  MODIFY `financial_record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `health_record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `illnesses`
--
ALTER TABLE `illnesses`
  MODIFY `illness_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  MODIFY `senior_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `senior_program_enrollments`
--
ALTER TABLE `senior_program_enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `announcement_pictures`
--
ALTER TABLE `announcement_pictures`
  ADD CONSTRAINT `announcement_pictures_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assistance_requests`
--
ALTER TABLE `assistance_requests`
  ADD CONSTRAINT `assistance_requests_ibfk_1` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`senior_id`);

--
-- Constraints for table `barangay_staff_profile`
--
ALTER TABLE `barangay_staff_profile`
  ADD CONSTRAINT `barangay_staff_profile_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`);

--
-- Constraints for table `checkups`
--
ALTER TABLE `checkups`
  ADD CONSTRAINT `checkups_ibfk_1` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`senior_id`);

--
-- Constraints for table `financial_records`
--
ALTER TABLE `financial_records`
  ADD CONSTRAINT `financial_records_ibfk_1` FOREIGN KEY (`profile_id`) REFERENCES `senior_profiles` (`senior_id`),
  ADD CONSTRAINT `financial_records_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `financial_programs` (`program_id`);

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`senior_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`);

--
-- Constraints for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  ADD CONSTRAINT `senior_profiles_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`);

--
-- Constraints for table `senior_program_enrollments`
--
ALTER TABLE `senior_program_enrollments`
  ADD CONSTRAINT `senior_program_enrollments_ibfk_1` FOREIGN KEY (`profile_id`) REFERENCES `senior_profiles` (`senior_id`),
  ADD CONSTRAINT `senior_program_enrollments_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `financial_programs` (`program_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
