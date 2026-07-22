-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Jul 22, 2026 at 01:48 PM
-- Server version: 8.0.40
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rotary_attendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int UNSIGNED NOT NULL,
  `member_id` int UNSIGNED DEFAULT NULL,
  `username` varchar(80) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Super Admin','Secretary','President','Attendance Officer') NOT NULL DEFAULT 'Secretary',
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `member_id`, `username`, `email`, `password_hash`, `role`, `last_login`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'admin', 'ko.otieno@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', '2026-07-17 11:12:12', 1, '2026-07-10 09:00:43', '2026-07-17 08:12:12');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint UNSIGNED NOT NULL,
  `admin_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_table` varchar(80) DEFAULT NULL,
  `target_id` int UNSIGNED DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `admin_id`, `action`, `target_table`, `target_id`, `description`, `ip_address`, `created_at`) VALUES
(1, NULL, 'CHECKIN_GUEST', 'guests', 1, NULL, '::1', '2026-07-10 09:52:28'),
(2, NULL, 'CHECKIN_MEMBER', 'member_attendance', 1, NULL, '::1', '2026-07-10 09:53:55'),
(3, NULL, 'CHECKIN_VISITOR', 'visiting_rotarians', 1, NULL, '::1', '2026-07-10 10:09:54'),
(4, NULL, 'CHECKIN_MEMBER', 'member_attendance', 2, NULL, '::1', '2026-07-10 11:49:15'),
(5, NULL, 'CHECKIN_GUEST', 'guests', 2, NULL, '::1', '2026-07-10 15:43:57'),
(6, NULL, 'CHECKIN_GUEST', 'guests', 3, NULL, '::1', '2026-07-10 15:56:48'),
(7, NULL, 'CHECKIN_MEMBER', 'member_attendance', 3, NULL, '::1', '2026-07-13 05:43:57'),
(8, NULL, 'CHECKIN_MEMBER', 'member_attendance', 4, NULL, '::1', '2026-07-13 05:48:43'),
(9, NULL, 'CHECKIN_GUEST', 'guests', 4, NULL, '::1', '2026-07-13 05:50:12'),
(10, NULL, 'CHECKIN_GUEST', 'guests', 5, NULL, '::1', '2026-07-13 05:52:21'),
(11, NULL, 'CHECKIN_GUEST', 'guests', 6, NULL, '::1', '2026-07-13 05:54:52'),
(12, NULL, 'CHECKIN_GUEST', 'guests', 7, NULL, '::1', '2026-07-17 08:04:46'),
(13, NULL, 'CHECKIN_GUEST', 'guests', 8, NULL, '::1', '2026-07-17 08:17:50');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int UNSIGNED NOT NULL,
  `certificate_no` varchar(80) NOT NULL,
  `meeting_id` int UNSIGNED NOT NULL,
  `attendee_type` enum('Member','Visiting Rotarian','Guest') NOT NULL,
  `attendee_ref_id` int UNSIGNED NOT NULL,
  `recipient_name` varchar(200) NOT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `file_path` varchar(255) DEFAULT NULL,
  `email_sent` tinyint(1) NOT NULL DEFAULT '0',
  `email_sent_at` datetime DEFAULT NULL,
  `email_error` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `certificate_no`, `meeting_id`, `attendee_type`, `attendee_ref_id`, `recipient_name`, `recipient_email`, `issued_at`, `file_path`, `email_sent`, `email_sent_at`, `email_error`) VALUES
(12, 'RCUHCERT-2026-0001', 3, 'Guest', 7, 'dkjsfds fsfdsaf', 'ko.otieno@gmail.com', '2026-07-17 11:04:41', '/Users/kennedyotieno/Dev/html/www/rotary-attendance/config/../certificates/RCUHCERT-2026-0001.pdf', 1, '2026-07-17 11:04:46', NULL),
(13, 'RCUHCERT-2026-0002', 3, 'Guest', 8, 'fddsg dfsgfd', 'ko.otieno@pesaflow.com', '2026-07-17 11:17:45', '/Users/kennedyotieno/Dev/html/www/rotary-attendance/config/../certificates/RCUHCERT-2026-0002.pdf', 1, '2026-07-17 11:17:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int UNSIGNED NOT NULL,
  `club_name` varchar(150) NOT NULL,
  `district` varchar(50) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Nigeria',
  `is_host_club` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`id`, `club_name`, `district`, `city`, `country`, `is_host_club`, `created_at`, `updated_at`) VALUES
(1, 'Rotary Club of Nairobi Upperhill', 'District 9215', 'Nairobi', 'Kenya', 1, '2026-07-10 09:20:46', '2026-07-10 09:20:46'),
(2, 'RC Langata', NULL, NULL, 'Nigeria', 0, '2026-07-10 10:09:50', '2026-07-10 10:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `id` int UNSIGNED NOT NULL,
  `meeting_id` int UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `host_member_id` int UNSIGNED DEFAULT NULL,
  `check_in_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `certificate_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `guests`
--

INSERT INTO `guests` (`id`, `meeting_id`, `first_name`, `last_name`, `email`, `phone`, `organization`, `host_member_id`, `check_in_time`, `is_late`, `certificate_id`) VALUES
(7, 3, 'dkjsfds', 'fsfdsaf', 'ko.otieno@gmail.com', '', '', 2, '2026-07-17 11:04:41', 0, 12),
(8, 3, 'fddsg', 'dfsgfd', 'ko.otieno@pesaflow.com', '', '', 1, '2026-07-17 11:17:45', 0, 13);

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` int UNSIGNED NOT NULL,
  `club_id` int UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `meeting_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `qr_token` varchar(100) NOT NULL,
  `qr_expires_at` datetime DEFAULT NULL,
  `status` enum('Scheduled','Open','Closed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `club_id`, `title`, `meeting_date`, `start_time`, `end_time`, `venue`, `theme`, `qr_token`, `qr_expires_at`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 1, 'Test Meeting', '2026-07-17', '09:30:00', '21:00:00', 'Ngong Hills Hotel', 'Initial Meeting', 'cba586cc5419543addc6945a77f68c60', '2026-07-19 09:30:00', 'Open', 1, '2026-07-17 08:03:31', '2026-07-17 08:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_summary`
--

CREATE TABLE `meeting_summary` (
  `id` int UNSIGNED NOT NULL,
  `meeting_id` int UNSIGNED NOT NULL,
  `total_members_present` int UNSIGNED NOT NULL DEFAULT '0',
  `total_late_members` int UNSIGNED NOT NULL DEFAULT '0',
  `total_visiting_rotarians` int UNSIGNED NOT NULL DEFAULT '0',
  `total_guests` int UNSIGNED NOT NULL DEFAULT '0',
  `total_attendees` int UNSIGNED NOT NULL DEFAULT '0',
  `total_certificates_sent` int UNSIGNED NOT NULL DEFAULT '0',
  `report_generated_at` datetime DEFAULT NULL,
  `generated_by` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `meeting_summary`
--

INSERT INTO `meeting_summary` (`id`, `meeting_id`, `total_members_present`, `total_late_members`, `total_visiting_rotarians`, `total_guests`, `total_attendees`, `total_certificates_sent`, `report_generated_at`, `generated_by`) VALUES
(12, 3, 0, 0, 0, 2, 2, 2, '2026-07-17 11:17:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int UNSIGNED NOT NULL,
  `club_id` int UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `rotary_id` varchar(50) DEFAULT NULL,
  `role` enum('Member','President','Secretary','Treasurer','Sergeant-at-Arms','SpeakerSecretary','Past President','Other') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'Member',
  `photo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `club_id`, `first_name`, `last_name`, `email`, `phone`, `rotary_id`, `role`, `photo_url`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Kennedy', 'Otieno', 'ko.otieno@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-10 09:20:52', '2026-07-16 12:42:57'),
(2, 1, 'Eric', 'Mwanzia', 'muthendu@gmail.com', NULL, NULL, 'Treasurer', NULL, 1, '2026-07-10 11:32:12', '2026-07-16 12:30:26'),
(3, 1, 'Edgar', 'Hezekiah Amadi', 'edgaramadi@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:20:47', '2026-07-16 12:40:55'),
(4, 1, 'Samuel', 'Juma', 'elsirme@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:22:03', '2026-07-16 12:41:02'),
(5, 1, 'Faith', 'Kanaga', 'kanaga.fg@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:23:07', '2026-07-16 12:41:48'),
(6, 1, 'Nancy', 'Laura Khisa', 'nancy.khisa@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:24:01', '2026-07-16 12:42:04'),
(7, 1, 'Sarah', 'Wanjiku Kiarie', 'sarahkiarie@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:24:41', '2026-07-16 12:41:10'),
(8, 1, 'Caroline', 'Kisia', 'drcarolinekisia@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:25:35', '2026-07-16 12:41:56'),
(9, 1, 'Patricia', 'Wairimu Kiwanuka', 'pwkiwanuka@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:26:18', '2026-07-16 12:41:20'),
(10, 1, 'Mariella', 'Makotsi', 'makotsi@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:27:00', '2026-07-16 12:42:11'),
(11, 1, 'Jacqueline', 'Mapesa', 'jacquemapesa@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:28:05', '2026-07-16 12:28:05'),
(12, 1, 'Betty', 'Nyatuga Mbicha', 'betty.nyatuga@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:28:48', '2026-07-16 12:41:39'),
(13, 1, 'Zainab', 'Mishi Mshangamwe', 'zmshangamwe@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:29:37', '2026-07-16 12:29:37'),
(14, 1, 'Sylvia', 'Mwaura Mukubi', 'wambuimwauras@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:31:05', '2026-07-16 12:31:05'),
(15, 1, 'Njoki', 'Ndegwa', 'njokindegwa@outlook.com', NULL, NULL, 'President', NULL, 1, '2026-07-16 12:36:26', '2026-07-16 12:36:26'),
(16, 1, 'Catherine', 'Njau', 'Knjauwambui@gmail.com', NULL, NULL, 'Secretary', NULL, 1, '2026-07-16 12:37:02', '2026-07-16 12:37:02'),
(17, 1, 'Syed', 'Saadur Rahman', 'saadrahman@rotary9215.org', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:37:54', '2026-07-16 12:37:54'),
(18, 1, 'Dela', 'Edna Wilbey', 'delawilbey48@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:38:45', '2026-07-16 12:38:45'),
(19, 1, 'Anne', 'Gicharu', 'angicharu123@gmail.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:39:39', '2026-07-16 12:39:39'),
(20, 1, 'Doreen', 'Murugi', 'd_njeru@yahoo.com', NULL, NULL, 'Member', NULL, 1, '2026-07-16 12:40:11', '2026-07-16 12:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `member_attendance`
--

CREATE TABLE `member_attendance` (
  `id` int UNSIGNED NOT NULL,
  `meeting_id` int UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `check_in_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `remarks` varchar(255) DEFAULT NULL,
  `certificate_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visiting_rotarians`
--

CREATE TABLE `visiting_rotarians` (
  `id` int UNSIGNED NOT NULL,
  `meeting_id` int UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `rotary_id` varchar(50) DEFAULT NULL,
  `home_club_id` int UNSIGNED DEFAULT NULL,
  `home_club_name` varchar(150) NOT NULL,
  `district` varchar(50) DEFAULT NULL,
  `role_in_club` varchar(100) DEFAULT NULL,
  `check_in_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_late` tinyint(1) NOT NULL DEFAULT '0',
  `certificate_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_admin_member` (`member_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_admin` (`admin_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_no` (`certificate_no`),
  ADD KEY `fk_cert_meeting` (`meeting_id`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_guest_meeting` (`meeting_id`,`email`),
  ADD KEY `fk_guests_host` (`host_member_id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_token` (`qr_token`),
  ADD KEY `fk_meetings_club` (`club_id`);

--
-- Indexes for table `meeting_summary`
--
ALTER TABLE `meeting_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meeting_id` (`meeting_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `rotary_id` (`rotary_id`),
  ADD KEY `fk_members_club` (`club_id`);

--
-- Indexes for table `member_attendance`
--
ALTER TABLE `member_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_member_meeting` (`meeting_id`,`member_id`),
  ADD KEY `fk_ma_member` (`member_id`);

--
-- Indexes for table `visiting_rotarians`
--
ALTER TABLE `visiting_rotarians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_visitor_meeting` (`meeting_id`,`email`),
  ADD KEY `fk_vr_home_club` (`home_club_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `meeting_summary`
--
ALTER TABLE `meeting_summary`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `member_attendance`
--
ALTER TABLE `member_attendance`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `visiting_rotarians`
--
ALTER TABLE `visiting_rotarians`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD CONSTRAINT `fk_admin_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `fk_cert_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `guests`
--
ALTER TABLE `guests`
  ADD CONSTRAINT `fk_guests_host` FOREIGN KEY (`host_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_guests_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meetings`
--
ALTER TABLE `meetings`
  ADD CONSTRAINT `fk_meetings_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `meeting_summary`
--
ALTER TABLE `meeting_summary`
  ADD CONSTRAINT `fk_ms_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `member_attendance`
--
ALTER TABLE `member_attendance`
  ADD CONSTRAINT `fk_ma_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ma_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visiting_rotarians`
--
ALTER TABLE `visiting_rotarians`
  ADD CONSTRAINT `fk_vr_home_club` FOREIGN KEY (`home_club_id`) REFERENCES `clubs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vr_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
