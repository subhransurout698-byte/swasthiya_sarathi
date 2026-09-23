-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 12:28 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `swasthya_saarthi`
--

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `patient_uid` varchar(30) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `language` varchar(40) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_uid`, `name`, `age`, `gender`, `phone`, `language`, `created_at`) VALUES
(1, 'SS-2026-000001', 'Subhasmita Padhi', 20, 'Female', '9348681221', 'Odia', '2026-09-10 08:59:09'),
(2, 'SS-2026-371163', 'SANKAR MISHRA', 45, 'Male', '', 'Odia', '2026-09-18 15:13:25');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `triage_id` int(11) DEFAULT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `triage_id`, `reviewer_id`, `remarks`, `reviewed_at`) VALUES
(1, 1, 2, 'within 5 days', '2026-09-10 09:01:30'),
(2, 1, 1, 'bh', '2026-09-10 09:02:36');

-- --------------------------------------------------------

--
-- Table structure for table `triage_reports`
--

CREATE TABLE `triage_reports` (
  `id` int(11) NOT NULL,
  `triage_uid` varchar(30) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `priority` enum('HIGH','MEDIUM','LOW') DEFAULT NULL,
  `urgency_flags` text DEFAULT NULL,
  `missing_information` text DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `status` varchar(40) DEFAULT 'WAITING',
  `report_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ocr_text` longtext DEFAULT NULL,
  `prescription_path` varchar(500) DEFAULT NULL,
  `medicine_summary` longtext DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewer_name` varchar(255) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `ocr_medicines` text DEFAULT NULL,
  `ocr_document_path` varchar(500) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `prescription_text` longtext DEFAULT NULL,
  `uploaded_document` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `triage_reports`
--

INSERT INTO `triage_reports` (`id`, `triage_uid`, `patient_id`, `symptoms`, `duration`, `priority`, `urgency_flags`, `missing_information`, `summary`, `status`, `report_path`, `created_at`, `ocr_text`, `prescription_path`, `medicine_summary`, `reviewed_by`, `reviewer_name`, `reviewed_at`, `ocr_medicines`, `ocr_document_path`, `review_notes`, `prescription_text`, `uploaded_document`) VALUES
(1, 'TRI-2026-000001', 1, 'health issue', '', 'LOW', '[]', '[\"When did the symptoms start?\"]', 'Reported symptoms: health issue. Suggested review priority: LOW.', 'REVIEWED', 'uploads/reports/TRI-2026-000001_1789030749_195839.jpg', '2026-09-10 08:59:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'TR-2026-7392D7A9', 2, '--- OCR FROM MEDICAL REPORT ---\r\nThis ticket is valid for 7 days only.\r\nRs:\r\n0,00\r\nOrdy\r\nName of the Patient:\r\nOPD Regd No.\r\nDepartment:\r\nInvestigations\r\nRequired\r\nDISTRICT HEAD QUARTERS HOSPITAL- PURI\r\nSANKAR MISHRA\r\nOPD/1920/111648\r\nPURI\r\nMALE OPD\r\nComplaints of the Patient:\r\nAge 45 YOM OD\r\n-Sex MALE\r\nDate & Time 16/06/2019 09:48:04 AM\r\nFindings of\r\nExamination Findings :\r\nProvisional Diagnosis:\r\n2, F0 tD\r\n2 Aml kane\r\nno qual\r\nAdvice:\r\n- Can Basilaup\r\n-\r\n200\r\nNO\r\nAmetRacle\r\nSignature & Full Name of the Doctor\r\nOSME\r\nCourtesy : M/s. Mindtrack Technologies (P) Ltd., Cuttack\r\n(An ISO 9001:2015 Certified Company, URL: www.mindtrack.co.in)', NULL, '', 'Pending human review.', 'Human reviewer to assess available information.', 'Pending structured review.', 'pending', 'uploads/medical_reports/report_4c19dbd998d36c52795da113936d1890.webp', '2026-09-18 15:13:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'TR-2026-309B92BA', 1, '--- OCR FROM MEDICAL REPORT ---\r\nThis ticket is valid for 7 days only.\r\nRs:\r\n0,00\r\nOrdy\r\nName of the Patient:\r\nOPD Regd No.\r\nDepartment:\r\nInvestigations\r\nRequired\r\nDISTRICT HEAD QUARTERS HOSPITAL- PURI\r\nSANKAR MISHRA\r\nOPD/1920/111648\r\nPURI\r\nMALE OPD\r\nComplaints of the Patient:\r\nAge 45 YOM OD\r\n-Sex MALE\r\nDate & Time 16/06/2019 09:48:04 AM\r\nFindings of\r\nExamination Findings :\r\nProvisional Diagnosis:\r\n2, F0 tD\r\n2 Aml kane\r\nno qual\r\nAdvice:\r\n- Can Basilaup\r\n-\r\n200\r\nNO\r\nAmetRacle\r\nSignature & Full Name of the Doctor\r\nOSME\r\nCourtesy : M/s. Mindtrack Technologies (P) Ltd., Cuttack\r\n(An ISO 9001:2015 Certified Company, URL: www.mindtrack.co.in)', NULL, '', 'Pending human review.', 'Human reviewer to assess available information.', 'Pending structured review.', 'pending', 'uploads/medical_reports/report_3206c489af6b987b4f316d5136c2d28b.webp', '2026-09-19 06:22:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('admin','doctor','health_worker','patient') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `created_at`, `failed_attempts`, `locked_until`, `last_login`, `patient_id`) VALUES
(1, 'System Admin', 'admin@swasthya.local', '$2y$10$RExGicjMSdHdfOriaDCdTeBLNSH28bfZxc.6toV/hrCi/d/bcXY8.', 'admin', '2026-09-10 08:52:06', 0, NULL, '2026-09-21 16:18:14', NULL),
(2, 'Dr. Demo', 'doctor@swasthya.local', '$2y$10$RExGicjMSdHdfOriaDCdTeBLNSH28bfZxc.6toV/hrCi/d/bcXY8.', 'doctor', '2026-09-10 08:52:06', 0, NULL, '2026-09-22 12:35:31', NULL),
(3, 'Health Worker', 'worker@swasthya.local', '$2y$10$RExGicjMSdHdfOriaDCdTeBLNSH28bfZxc.6toV/hrCi/d/bcXY8.', 'health_worker', '2026-09-10 08:52:06', 0, NULL, '2026-09-21 15:45:34', NULL),
(4, 'subhransu rout', 'subhransurout698@gmail.com', '$2y$10$70r.391xP5XrfTdSOoidvOAc6zFGUrpVRur0GRuyoxDL9QdCwHRRi', 'patient', '2026-09-22 06:51:43', 0, NULL, '2026-09-22 15:55:32', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_uid` (`patient_uid`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `triage_id` (`triage_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Indexes for table `triage_reports`
--
ALTER TABLE `triage_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `triage_uid` (`triage_uid`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `patient_id` (`patient_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `triage_reports`
--
ALTER TABLE `triage_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`triage_id`) REFERENCES `triage_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `triage_reports`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

ALTER TABLE `triage_reports`
  ADD CONSTRAINT `triage_reports_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
