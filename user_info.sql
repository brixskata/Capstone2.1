-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2025 at 02:21 PM
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
-- Database: `ordering_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `user_info`
--

CREATE TABLE `user_info` (
  `user_info_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gcash_number` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_info`
--

INSERT INTO `user_info` (`user_info_id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `gcash_number`, `profile_picture`, `date_created`, `date_updated`) VALUES
(4, 2, 'System', 'Admin', 'admin@example.com', '09123456789', NULL, 'uploads/default.png', '2025-09-03 11:34:15', '2025-09-04 05:26:28'),
(5, 3, 'Marion Brix', 'Quiling', 'brixquils16@gmail.com', '09612351251', '09514971216', 'uploads/profile_3_1761016294_68f6f9e68bb32.jpg', '2025-09-04 04:31:55', '2025-10-30 12:26:22'),
(7, 4, 'Marion Brix Quiling', '', 'superadmin@mikemadz.com', '09514971216', NULL, 'profile_4_1758110938.jpg', '2025-09-04 13:23:31', '2025-09-17 12:08:58'),
(8, 5, NULL, NULL, 'testadmin@gmail.com', NULL, NULL, NULL, '2025-09-05 11:49:59', '2025-09-05 11:49:59'),
(9, 6, NULL, NULL, 'inventory@gmail.com', NULL, NULL, NULL, '2025-09-08 13:04:23', '2025-09-08 13:04:23'),
(10, 7, NULL, NULL, 'admin3@gmail.com', NULL, NULL, NULL, '2025-09-08 13:05:16', '2025-09-08 13:05:16'),
(11, 8, NULL, NULL, 'sales@gmail.com', NULL, NULL, NULL, '2025-09-08 13:28:52', '2025-09-08 13:28:52'),
(12, 9, 'GIAN', 'CARMEN', 'giansteven58@gmail.com', '09213197822', '09517891233', 'uploads/profile_9_1758035256_68c97d38429a6.jpg', '2025-09-15 14:28:22', '2025-10-30 13:18:14'),
(13, 10, 'Kaycee', 'Gallaza', 'kreatives09@gmail.com', '0921314193', NULL, 'uploads/profile_10_1758123186_68cad4b29a052.jpg', '2025-09-17 15:32:23', '2025-09-17 15:33:06'),
(14, 11, 'Katrina', 'Catani', 'ntalavera0426@gmail.com', '09213197822', NULL, 'uploads/profile_11_1759838874_68e5029ad845a.jpg', '2025-10-06 14:36:34', '2025-10-07 12:07:54'),
(16, 13, 'Marion Brix', 'Quiling', 'marionquils16@gmail.com', NULL, NULL, NULL, '2025-10-09 12:51:01', '2025-10-09 12:51:01'),
(17, 14, NULL, NULL, 'inventorykervie@gmail.com', NULL, NULL, NULL, '2025-10-09 13:32:35', '2025-10-09 13:32:35'),
(18, 15, NULL, NULL, 'inventory_kervie@gmail.com', NULL, NULL, NULL, '2025-10-09 13:42:10', '2025-10-09 13:42:10'),
(19, 16, NULL, NULL, 'salesadmin_test@gmail.com', NULL, NULL, NULL, '2025-10-12 09:13:01', '2025-10-12 09:13:01'),
(20, 17, NULL, NULL, 'admin5@gmail.com', NULL, NULL, NULL, '2025-10-12 09:16:10', '2025-10-12 09:16:10'),
(21, 18, NULL, NULL, 'monitoring_admin@gmail.com', NULL, NULL, NULL, '2025-10-12 09:28:37', '2025-10-12 09:28:37'),
(22, 19, NULL, NULL, 'monitoring_admin2@gmail.com', NULL, NULL, NULL, '2025-10-13 06:21:21', '2025-10-13 06:21:21'),
(23, 20, 'David', 'Mosqueda', 'davidsonmosqueda07@gmail.com', '09514971216', NULL, 'uploads/profile_20_1761110960_68f86bb006b61.jpg', '2025-10-15 12:37:18', '2025-10-22 05:29:20'),
(24, 21, 'hatchiko', 'chew', 'brixquils@gmail.com', NULL, NULL, NULL, '2025-10-15 12:52:28', '2025-10-15 12:52:28'),
(25, 22, NULL, NULL, 'giancarmen@gmail.com', NULL, NULL, NULL, '2025-10-15 14:51:03', '2025-10-15 14:51:03'),
(26, 23, 'Gian Zayn', '', 'giangiangian@gmail.com', '', NULL, 'profile_23_1760542588.jpg', '2025-10-15 15:17:15', '2025-10-15 15:37:25'),
(27, 24, '', '', 'davidsales@gmail.com', '', NULL, 'profile_24_1760543859.jpg', '2025-10-15 15:39:31', '2025-10-15 15:57:39'),
(28, 25, NULL, NULL, 'kerviemoni@gmail.com', NULL, NULL, 'profile_25_1760586850.jpg', '2025-10-16 03:42:53', '2025-10-16 03:54:10'),
(29, 26, 'Marianne', 'Zoi', 'mariannezoiquiling99@gmail.com', NULL, NULL, NULL, '2025-10-18 14:55:20', '2025-10-18 14:55:20'),
(33, 30, 'Marianne', 'Quiling', 'mariannezoiquiling99@gmail.com', NULL, NULL, NULL, '2025-10-18 15:03:02', '2025-10-18 15:03:02'),
(34, 31, 'Marianne', 'Quiling', 'mariannequiling893@gmail.com', NULL, NULL, NULL, '2025-10-18 15:09:11', '2025-10-18 15:09:11'),
(35, 32, 'Marianne', 'Quiling', 'mariannequiling893@gmail.com', NULL, NULL, NULL, '2025-10-18 15:09:46', '2025-10-18 15:09:46'),
(36, 33, 'Briy', 'Quiling', 'briyquils@gmail.com', NULL, NULL, NULL, '2025-10-19 15:20:02', '2025-10-19 15:20:02'),
(37, 34, 'Briy', 'Quiling', 'briyquils@gmail.com', NULL, NULL, NULL, '2025-10-19 15:24:36', '2025-10-19 15:24:36'),
(38, 35, 'brixsu', 'brixsaa', 'brixquils.1@gmail.com', NULL, NULL, NULL, '2025-10-25 11:19:30', '2025-10-25 11:19:30'),
(39, 36, 'marr', 'brixxxx', 'brixquils.1@gmail.com', NULL, NULL, NULL, '2025-10-29 03:42:01', '2025-10-29 03:42:01'),
(40, 37, 'brixx', 'shaaa', 'brixquils.1@gmail.com', NULL, NULL, NULL, '2025-10-29 03:52:02', '2025-10-29 03:52:02'),
(41, 38, 'Marion Brix', 'Quiling', 'brixquils.1@gmail.com', NULL, NULL, NULL, '2025-10-29 03:55:22', '2025-10-29 03:55:22'),
(42, 39, 'brixxxxx', 'waaah', 'brixquils.1@gmail.com', '09321321321', '09312213333', 'uploads/default.png', '2025-10-30 03:40:36', '2025-10-30 13:13:52'),
(43, 40, 'GIan', 'AZayn', 'nicoletalavera826@gmail.com', NULL, NULL, NULL, '2025-10-30 03:53:32', '2025-10-30 03:53:32'),
(44, 41, 'GIIII', 'ZAYN', 'nicoletalavera826@gmail.com', NULL, NULL, NULL, '2025-10-30 03:55:10', '2025-10-30 03:55:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user_info`
--
ALTER TABLE `user_info`
  ADD PRIMARY KEY (`user_info_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_profile_picture` (`profile_picture`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `user_info`
--
ALTER TABLE `user_info`
  MODIFY `user_info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user_info`
--
ALTER TABLE `user_info`
  ADD CONSTRAINT `user_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
