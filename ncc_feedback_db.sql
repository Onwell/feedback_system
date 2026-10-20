-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2026 at 08:41 AM
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
-- Database: `ncc_feedback_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `ts` datetime NOT NULL DEFAULT current_timestamp(),
  `actor` varchar(120) NOT NULL,
  `action` varchar(80) NOT NULL,
  `ref` varchar(40) DEFAULT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `ts`, `actor`, `action`, `ref`, `details`) VALUES
(1, '2026-10-19 15:15:59', 'Onwell Masaraure', 'Submitted feedback', 'FB-2026-C0001', 'Complaint via user portal'),
(2, '2026-10-19 15:16:35', 'Onwell Masaraure', 'Updated case', 'FB-2026-C0001', 'status → In progress; assignee → Onwell Masaraure'),
(3, '2026-10-19 15:17:57', 'Onwell Masaraure', 'Updated case', 'FB-2026-C0001', 'status → Resolved; assignee → Onwell Masaraure'),
(4, '2026-10-19 15:18:35', 'Onwell Masaraure', 'Submitted feedback', 'FB-2026-K0001', 'Compliment via user portal'),
(6, '2026-10-19 16:19:21', 'Onwell Masaraure', 'Signed in', NULL, 'admin profile · Successful sign-in'),
(7, '2026-10-19 16:19:34', 'Onwell Masaraure', 'Signed out', NULL, 'admin profile · User signed out'),
(8, '2026-10-19 16:19:36', 'Test User', 'Signed in', NULL, 'user profile · Successful sign-in'),
(9, '2026-10-19 16:19:41', 'Test User', 'Signed out', NULL, 'User profile · User signed out'),
(10, '2026-10-19 16:19:48', 'Onwell Masaraure', 'Signed in', NULL, 'admin profile · Successful sign-in'),
(11, '2026-10-19 17:50:05', 'Onwell Masaraure', 'Signed in', NULL, 'admin profile · Successful sign-in'),
(12, '2026-10-19 17:57:58', 'Onwell Masaraure', 'Updated settings', NULL, 'Administrator profile · Portal settings updated'),
(13, '2026-10-19 18:01:42', 'Onwell Masaraure', 'Signed out', NULL, 'admin profile · User signed out'),
(14, '2026-10-19 18:01:48', 'Test User', 'Signed in', NULL, 'user profile · Successful sign-in'),
(15, '2026-10-19 18:11:30', 'Test User', 'Marked notifications read', NULL, 'User profile · Notifications marked as read'),
(16, '2026-10-19 18:12:08', 'Test User', 'Signed out', NULL, 'User profile · User signed out'),
(17, '2026-10-19 18:12:27', 'Onwell Masaraure', 'Signed in', NULL, 'admin profile · Successful sign-in'),
(18, '2026-10-20 08:35:36', 'Test User', 'Signed in', NULL, 'user profile · Successful sign-in'),
(19, '2026-10-20 08:36:38', 'Test User', 'Submitted feedback', 'FB-2026-C0002', 'User profile · Complaint via user portal'),
(20, '2026-10-20 08:37:01', 'Test User', 'Submitted feedback', 'FB-2026-K0002', 'User profile · Compliment via user portal'),
(21, '2026-10-20 08:38:27', 'Test User', 'Signed out', NULL, 'User profile · User signed out'),
(22, '2026-10-20 08:38:56', 'Onwell Masaraure', 'Signed in', NULL, 'admin profile · Successful sign-in'),
(23, '2026-10-20 08:39:19', 'Onwell Masaraure', 'Deleted entries', NULL, 'Administrator profile · 1 feedback record(s) deleted'),
(24, '2026-10-20 08:39:29', 'Onwell Masaraure', 'Deleted entries', NULL, 'Administrator profile · 1 feedback record(s) deleted'),
(25, '2026-10-20 08:39:50', 'Onwell Masaraure', 'Signed out', NULL, 'admin profile · User signed out'),
(26, '2026-10-20 08:39:59', 'Onwell Masaraure', 'Signed in', NULL, 'admin profile · Successful sign-in');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(24) NOT NULL,
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL DEFAULT 'Anonymous',
  `type` enum('Complaint','Enquiry','Compliment','Suggestion') NOT NULL,
  `priority` enum('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `summary` text NOT NULL,
  `channel` varchar(40) NOT NULL DEFAULT 'Website',
  `assignee` varchar(120) NOT NULL DEFAULT 'Unassigned',
  `status` enum('Open','In progress','Resolved') NOT NULL DEFAULT 'Open',
  `due_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `forwarded_to` varchar(120) NOT NULL DEFAULT '',
  `courtesy_response` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `reference`, `submitted_by`, `name`, `type`, `priority`, `summary`, `channel`, `assignee`, `status`, `due_at`, `created_at`, `updated_at`, `forwarded_to`, `courtesy_response`) VALUES
(3, 'FB-2026-C0002', 2, 'Test 3', 'Complaint', 'Medium', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum.', 'Social media', 'Unassigned', 'Open', NULL, '2026-10-20 08:36:38', '2026-10-20 08:36:38', 'Communications', 'Resolved'),
(4, 'FB-2026-K0002', 2, 'Test 4', 'Compliment', 'Medium', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\'s standard dummy text ever since 1966, when designers at Letraset and James Mosley, the librarian at St Bride Printing Library in London, took a 1914 Cicero translation and scrambled it to make dummy text for Letraset\'s Body Type sheets. It has survived not only many decades, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised thanks to these sheets and more recently with desktop publishing software like Aldus PageMaker and Microsoft Word including versions of Lorem Ipsum.', 'Walk-in', 'Unassigned', 'Open', NULL, '2026-10-20 08:37:01', '2026-10-20 08:37:01', 'Communications', 'Reso ved');

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `role` enum('Admin','Officer') NOT NULL DEFAULT 'Officer',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `k` varchar(64) NOT NULL,
  `v` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`k`, `v`) VALUES
('channels', '[\"Email\",\"Website\",\"Phone\",\"Walk-in\",\"Social media\"]'),
('org', 'Feedback Hub');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Onwell Masaraure', 'o.masaraure@gmail.com', '$2y$10$5Qoinc1zt28yqGupAm4H9.6uvQTiG3BH89GM//SCBJGNecVyqQHsu', 'admin', '2026-10-19 14:13:04'),
(2, 'Test User', 'test@ncc.co.zw', '$2y$10$YHxrwitn6E7QG4f0D3fE1uDvt1JSjgtoDQ/ifsOglBCdLqdqZ9OA6', 'user', '2026-10-19 15:31:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ts` (`ts`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `status` (`status`),
  ADD KEY `type` (`type`),
  ADD KEY `priority` (`priority`),
  ADD KEY `assignee` (`assignee`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`k`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
