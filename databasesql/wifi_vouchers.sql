-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 31, 2026 at 03:21 AM
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
-- Database: `wifi_vouchers`
--

-- --------------------------------------------------------

--
-- Table structure for table `redemption_log`
--

CREATE TABLE `redemption_log` (
  `id` int(11) NOT NULL,
  `voucher_code` varchar(64) NOT NULL,
  `minutes` int(11) NOT NULL,
  `date_time` datetime DEFAULT current_timestamp(),
  `source` varchar(32) NOT NULL,
  `status` enum('SUCCESS','FAILED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `minutes` int(11) NOT NULL,
  `status` enum('UNUSED','USED','VOID','EXPIRED') DEFAULT 'UNUSED',
  `date_created` datetime DEFAULT current_timestamp(),
  `date_used` datetime DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `qr_image` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `minutes`, `status`, `date_created`, `date_used`, `expiry_date`, `qr_image`) VALUES
(6, '0AF05743', 23, 'UNUSED', '2026-01-29 17:22:29', NULL, '2026-01-30 00:00:00', '0AF05743.png'),
(7, 'E4FF4093', 21, 'UNUSED', '2026-01-29 17:25:27', NULL, '2026-02-06 00:00:00', 'E4FF4093.png'),
(8, 'B3BFBA2E', 15, 'UNUSED', '2026-01-30 22:38:03', NULL, '2026-01-30 00:00:00', 'B3BFBA2E.png');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `redemption_log`
--
ALTER TABLE `redemption_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_code` (`voucher_code`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `redemption_log`
--
ALTER TABLE `redemption_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `redemption_log`
--
ALTER TABLE `redemption_log`
  ADD CONSTRAINT `redemption_log_ibfk_1` FOREIGN KEY (`voucher_code`) REFERENCES `vouchers` (`code`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
