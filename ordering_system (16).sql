-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 31, 2025 at 02:53 PM
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
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_line` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `geocoded` tinyint(1) DEFAULT 0,
  `geocoded_at` timestamp NULL DEFAULT NULL,
  `locationiq_confidence` decimal(3,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `user_id`, `address_line`, `address_line2`, `city`, `state`, `postal_code`, `country`, `is_default`, `date_created`, `date_updated`, `latitude`, `longitude`, `geocoded`, `geocoded_at`, `locationiq_confidence`) VALUES
(4, 10, 'bagong silang', '', 'Quezon City', 'Metro Manila', '1119', 'Philippines', 1, '2025-09-17 15:35:32', '2025-09-17 15:35:32', NULL, NULL, 0, NULL, NULL),
(6, 11, 'Blumentritt Road, Santa Cruz, Manila, Capital District, Metro Manila, 1014, Philippines', 'Near 7/11', 'Manila', 'Metro Manila', '1014', 'Philippines', 0, '2025-10-07 12:11:21', '2025-10-07 12:11:21', NULL, NULL, 0, NULL, NULL),
(8, 11, 'De La Salle University Manila, 2401, Taft Avenue, Barangay 726, Malate, Manila, Capital District, Metro Manila, 1004, Philippines', 'Near 7/11', 'Manila', 'Metro Manila', '1004', 'Philippines', 1, '2025-10-08 13:22:38', '2025-10-08 13:22:38', NULL, NULL, 0, NULL, NULL),
(9, 11, 'Housing Project of Diocese of Cubao, Antipolo, Rizal, 1870, Philippines', 'Near 7/11', 'Antipolo', 'Rizal', '1870', 'Philippines', 0, '2025-10-08 13:58:15', '2025-10-08 13:58:15', NULL, NULL, 0, NULL, NULL),
(10, 3, 'Santa Mesa, Manila, Capital District, Metro Manila, Philippines', 'Near 7/11', 'Manila', 'Metro Manila', '1121', 'Philippines', 0, '2025-10-08 14:14:25', '2025-10-08 14:14:25', NULL, NULL, 0, NULL, NULL),
(11, 3, 'Tagaytay, Cavite, 4120, Philippines', 'Near Uncle Johns', 'Cavite', 'Cavite', '4120', 'Philippines', 1, '2025-10-08 14:38:21', '2025-10-11 11:48:19', NULL, NULL, 0, NULL, NULL),
(12, 3, 'BF Homes Caloocan, District 1, Caloocan, Northern Manila District, Metro Manila, 1420, Philippines', '', 'Caloocan', '', '1420', 'Philippines', 0, '2025-10-08 15:10:32', '2025-10-08 15:10:32', NULL, NULL, 0, NULL, NULL),
(13, 3, 'Kasunduan Street, Commonwealth, 2nd District, Quezon City, Eastern Manila District, Metro Manila, 1121, Philippines', '', 'Quezon City', '', '1121', 'Philippines', 0, '2025-10-09 13:28:21', '2025-10-09 13:28:21', NULL, NULL, 0, NULL, NULL),
(14, 11, '100 kasunduan extension barangay commonwealth', '', 'Quezon City', 'NCR', '1100', 'Philippines', 0, '2025-10-19 15:39:47', '2025-10-19 15:39:47', NULL, NULL, 0, NULL, NULL),
(15, 11, '100 kasunduan extension barangay commonwealth', '', 'Quezon City', '', '1100', 'Philippines', 0, '2025-10-19 15:44:06', '2025-10-19 15:44:06', NULL, NULL, 0, NULL, NULL),
(16, 20, '1239 Quirino Avenue, Barangay Tambo', '', 'Parañaque', 'NCR', '1700', 'Philippines', 0, '2025-10-22 05:25:54', '2025-10-22 05:25:54', NULL, NULL, 0, NULL, NULL),
(17, 9, '100-c Kasunduan Extension Brgy. Commonwealth Q.c.', 'Katena Hoa Multipurpose', 'QUEZON CITY', 'NCR', '1121', 'Philippines', 0, '2025-10-30 13:47:43', '2025-10-30 13:47:43', NULL, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `adjustment_types`
--

CREATE TABLE `adjustment_types` (
  `adjustment_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adjustment_types`
--

INSERT INTO `adjustment_types` (`adjustment_type_id`, `name`) VALUES
(1, 'Increase'),
(2, 'Decrease'),
(3, 'Correction'),
(4, 'Damaged');

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `notification_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`notification_id`, `type`, `title`, `message`, `data`, `is_read`, `created_at`, `read_at`) VALUES
(1, 'id_verification', 'New ID Verification Request', 'User ID: 3 has submitted ID verification documents.', '{\"user_id\":3,\"id_type\":\"Student ID\"}', 0, '2025-09-17 11:15:12', NULL),
(2, 'id_verification', 'New ID Verification Request', 'User ID: 9 has submitted ID verification documents.', '{\"user_id\":9,\"id_type\":\"Student ID\"}', 0, '2025-09-17 11:57:02', NULL),
(3, 'id_verification', 'New ID Verification Request', 'User ID: 10 has submitted ID verification documents.', '{\"user_id\":10,\"id_type\":\"Student ID\"}', 0, '2025-09-17 15:33:48', NULL),
(4, 'id_verification', 'New ID Verification Request', 'User ID: 4 has submitted ID verification documents.', '{\"user_id\":4,\"id_type\":\"Driver\'s License\"}', 0, '2025-09-27 14:57:26', NULL),
(5, 'id_verification', 'New ID Verification Request', 'User ID: 11 has submitted ID verification documents.', '{\"user_id\":11,\"id_type\":\"National ID\"}', 0, '2025-10-07 12:07:45', NULL),
(6, 'id_verification', 'New ID Verification Request', 'User ID: 20 has submitted ID verification documents.', '{\"user_id\":20,\"id_type\":\"Passport\"}', 0, '2025-10-22 05:17:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `alert_types`
--

CREATE TABLE `alert_types` (
  `alerttype_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alert_types`
--

INSERT INTO `alert_types` (`alerttype_id`, `name`) VALUES
(1, 'Out of Stock'),
(2, 'Low Stock'),
(3, 'Normal Stock');

-- --------------------------------------------------------

--
-- Table structure for table `batch_movements`
--

CREATE TABLE `batch_movements` (
  `movement_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('sale','adjustment','waste','transfer') NOT NULL,
  `quantity` decimal(10,1) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `batch_movements`
--

INSERT INTO `batch_movements` (`movement_id`, `batch_id`, `product_id`, `movement_type`, `quantity`, `reference_type`, `reference_id`, `created_at`, `created_by`, `notes`) VALUES
(6, 7, 5, 'sale', 10.0, 'test', 999, '2025-09-19 02:26:04', 10, 'Test consumption from interface'),
(7, 15, 4, 'sale', 15.0, 'order', 888, '2025-09-19 03:19:27', 10, 'Test order #888'),
(8, 15, 4, 'sale', 25.0, 'order', 777, '2025-09-19 03:19:27', 10, 'FIFO test order #777'),
(9, 13, 7, 'sale', 1.0, 'order', 182, '2025-09-22 02:35:08', 3, 'Order #182 - Customer purchase'),
(10, 20, 9, 'adjustment', 30.0, 'stock_adjustment', NULL, '2025-09-22 05:01:55', 4, 'Stock adjustment: Damaged Items'),
(11, 19, 9, 'adjustment', 1.0, 'stock_adjustment', NULL, '2025-09-22 05:02:22', 4, 'Stock adjustment: Damaged Items'),
(12, 21, 9, 'sale', 1.0, 'order', 183, '2025-09-22 05:08:36', 3, 'Order #183 - Customer purchase'),
(13, 22, 8, 'adjustment', 1.0, 'stock_adjustment', NULL, '2025-09-23 09:22:44', 4, 'Stock adjustment: Damaged Items'),
(14, 23, 8, 'adjustment', 1.0, 'stock_adjustment', NULL, '2025-09-23 09:23:30', 4, 'Stock adjustment: Damaged Items'),
(15, 21, 9, 'adjustment', 29.0, 'stock_adjustment', NULL, '2025-09-23 09:28:15', 4, 'Stock adjustment: Damaged Items'),
(16, 24, 9, 'sale', 2.0, 'order', 184, '2025-09-23 09:33:28', 3, 'Order #184 - Customer purchase'),
(17, 25, 9, 'sale', 2.0, 'order', 185, '2025-09-23 09:34:56', 3, 'Order #185 - Customer purchase'),
(18, 27, 9, 'adjustment', 1.0, 'stock_adjustment', NULL, '2025-09-23 09:58:02', 4, 'Stock adjustment: Damaged Items'),
(19, 27, 9, 'adjustment', 1.0, 'stock_adjustment', NULL, '2025-09-23 09:58:15', 4, 'Stock adjustment: Manual Correction'),
(20, 26, 9, 'adjustment', 2.0, 'stock_adjustment', NULL, '2025-09-23 09:58:37', 4, 'Stock adjustment: Damaged Items'),
(21, 15, 4, 'sale', 1.0, 'order', 189, '2025-09-23 11:30:23', 3, 'Order #189 - Customer purchase'),
(22, 10, 5, 'sale', 1.0, 'order', 190, '2025-09-23 11:31:25', 3, 'Order #190 - Customer purchase'),
(23, 10, 5, 'sale', 2.0, 'order', 191, '2025-09-24 08:45:37', 3, 'Order #191 - Customer purchase'),
(24, 28, 9, 'sale', 1.0, 'order', 191, '2025-09-24 08:45:37', 3, 'Order #191 - Customer purchase'),
(25, 13, 7, 'sale', 1.0, 'order', 191, '2025-09-24 08:45:37', 3, 'Order #191 - Customer purchase'),
(26, 15, 4, 'sale', 1.0, 'order', 191, '2025-09-24 08:45:37', 3, 'Order #191 - Customer purchase'),
(27, 30, 11, 'sale', 1.0, 'order', 192, '2025-09-30 12:34:23', 3, 'Order #192 - Customer purchase'),
(28, 15, 4, 'sale', 8.0, 'order', 192, '2025-09-30 12:34:23', 3, 'Order #192 - Customer purchase'),
(29, 16, 4, 'sale', 2.0, 'order', 192, '2025-09-30 12:34:23', 3, 'Order #192 - Customer purchase'),
(30, 30, 11, 'sale', 1.0, 'order', 193, '2025-09-30 12:51:51', 3, 'Order #193 - Customer purchase'),
(31, 29, 10, 'sale', 1.0, 'order', 194, '2025-09-30 12:59:40', 3, 'Order #194 - Customer purchase'),
(32, 30, 11, 'sale', 1.0, 'order', 195, '2025-09-30 13:02:18', 3, 'Order #195 - Customer purchase'),
(33, 29, 10, 'sale', 2.0, 'order', 196, '2025-10-07 02:39:49', 10, 'Order #196 - Customer purchase'),
(34, 16, 4, 'sale', 3.0, 'order', 199, '2025-10-07 03:13:14', 10, 'Order #199 - Customer purchase'),
(35, 29, 10, 'sale', 4.0, 'order', 199, '2025-10-07 03:13:14', 10, 'Order #199 - Customer purchase'),
(36, 32, 6, 'sale', 4.0, 'order', 200, '2025-10-07 03:18:10', 10, 'Order #200 - Customer purchase'),
(37, 32, 6, 'sale', 2.0, 'order', 201, '2025-10-07 03:23:43', 10, 'Order #201 - Customer purchase'),
(38, 32, 6, 'sale', 2.0, 'order', 202, '2025-10-07 03:31:40', 10, 'Order #202 - Customer purchase'),
(39, 31, 8, 'sale', 12.0, 'order', 203, '2025-10-07 03:36:01', 10, 'Order #203 - Customer purchase'),
(40, 31, 8, 'sale', 3.0, 'order', 204, '2025-10-07 03:55:27', 10, 'Order #204 - Customer purchase'),
(41, 31, 8, 'sale', 4.0, 'order', 205, '2025-10-07 03:56:06', 10, 'Order #205 - Customer purchase'),
(42, 32, 6, 'sale', 2.0, 'order', 206, '2025-10-07 04:03:39', 10, 'Order #206 - Customer purchase'),
(44, 32, 6, 'adjustment', 2.0, 'order_cancellation', 206, '2025-10-07 04:16:38', 4, 'Order #206 cancelled - stock restored'),
(45, 31, 8, 'sale', 2.0, 'order', 207, '2025-10-07 04:23:52', 10, 'Order #207 - Customer purchase'),
(46, 30, 11, 'sale', 3.0, 'order', 208, '2025-10-07 04:29:15', 10, 'Order #208 - Customer purchase'),
(47, 29, 10, 'sale', 2.0, 'order', 209, '2025-10-07 04:33:42', 10, 'Order #209 - Customer purchase'),
(49, 31, 8, 'sale', 22.2, 'order', 210, '2025-10-07 04:39:26', 10, 'Order #210 - Customer purchase'),
(50, 33, 9, 'sale', 2.5, 'order', 211, '2025-10-07 04:43:14', 10, 'Order #211 - Customer purchase'),
(51, 34, 9, 'sale', 3.5, 'order', 212, '2025-10-07 04:43:50', 10, 'Order #212 - Customer purchase'),
(52, 40, 14, 'sale', 10.5, 'order', 213, '2025-10-08 06:08:47', 3, 'Order #213 - Customer purchase'),
(53, 38, 13, 'sale', 1.0, 'order', 214, '2025-10-08 06:14:29', 3, 'Order #214 - Customer purchase'),
(54, 30, 11, 'sale', 3.5, 'order', 215, '2025-10-08 10:23:47', 3, 'Order #215 - Customer purchase'),
(62, 41, 14, 'sale', 1.0, 'order', 223, '2025-10-08 11:01:37', 3, 'Order #223 - Customer purchase'),
(63, 41, 14, 'adjustment', 1.0, 'order_cancellation', 223, '2025-10-08 11:02:01', 4, 'Order #223 cancelled - stock restored to batch B14-20251008-002'),
(64, 30, 11, 'adjustment', 3.5, 'order_cancellation', 215, '2025-10-08 11:15:02', 4, 'Order #215 cancelled - stock restored to batch B11-20250927-001'),
(65, 38, 13, 'adjustment', 1.0, 'order_cancellation', 214, '2025-10-08 11:15:04', 4, 'Order #214 cancelled - stock restored to batch B13-20251008-001'),
(66, 41, 14, 'sale', 1.0, 'order', 224, '2025-10-08 13:23:20', 11, 'Order #224 - Customer purchase'),
(67, 42, 14, 'sale', 1.0, 'order', 224, '2025-10-08 13:23:20', 11, 'Order #224 - Customer purchase'),
(68, 42, 14, 'sale', 1.0, 'order', 225, '2025-10-08 13:34:03', 11, 'Order #225 - Customer purchase'),
(69, 42, 14, 'sale', 1.0, 'order', 226, '2025-10-08 13:36:22', 11, 'Order #226 - Customer purchase'),
(70, 42, 14, 'sale', 1.0, 'order', 227, '2025-10-08 13:42:57', 11, 'Order #227 - Customer purchase'),
(71, 42, 14, 'sale', 1.0, 'order', 228, '2025-10-08 13:58:48', 11, 'Order #228 - Customer purchase'),
(72, 42, 14, 'sale', 1.0, 'order', 229, '2025-10-08 14:11:19', 11, 'Order #229 - Customer purchase'),
(73, 42, 14, 'sale', 1.0, 'order', 230, '2025-10-08 14:12:36', 3, 'Order #230 - Customer purchase'),
(74, 42, 14, 'sale', 2.0, 'order', 231, '2025-10-08 14:31:36', 3, 'Order #231 - Customer purchase'),
(75, 42, 14, 'sale', 1.0, 'order', 232, '2025-10-08 14:50:44', 3, 'Order #232 - Customer purchase'),
(76, 43, 14, 'sale', 1.0, 'order', 233, '2025-10-08 14:53:07', 3, 'Order #233 - Customer purchase'),
(77, 43, 14, 'sale', 1.0, 'order', 234, '2025-10-08 14:58:16', 3, 'Order #234 - Customer purchase'),
(78, 43, 14, 'sale', 1.0, 'order', 235, '2025-10-08 15:02:07', 3, 'Order #235 - Customer purchase'),
(79, 43, 14, 'sale', 1.0, 'order', 236, '2025-10-08 15:07:38', 3, 'Order #236 - Customer purchase'),
(80, 43, 14, 'sale', 1.0, 'order', 237, '2025-10-08 15:10:32', 3, 'Order #237 - Customer purchase'),
(82, 43, 14, 'sale', 45.0, 'order', 238, '2025-10-08 15:14:58', 3, 'Order #238 - Customer purchase'),
(83, 44, 14, 'sale', 1.0, 'order', 239, '2025-10-09 03:54:09', 3, 'Order #239 - Customer purchase'),
(84, 44, 14, 'sale', 1.0, 'order', 240, '2025-10-09 03:55:53', 3, 'Order #240 - Customer purchase'),
(85, 45, 14, 'sale', 1.0, 'order', 241, '2025-10-09 03:56:09', 3, 'Order #241 - Customer purchase'),
(86, 45, 14, 'sale', 1.0, 'order', 242, '2025-10-09 03:56:36', 3, 'Order #242 - Customer purchase'),
(87, 45, 14, 'sale', 8.0, 'order', 243, '2025-10-09 04:28:08', 3, 'Order #243 - Customer purchase'),
(88, 46, 14, 'sale', 1.0, 'order', 244, '2025-10-09 04:29:59', 3, 'Order #244 - Customer purchase'),
(89, 47, 14, 'sale', 1.0, 'order', 244, '2025-10-09 04:29:59', 3, 'Order #244 - Customer purchase'),
(90, 48, 14, 'sale', 1.0, 'order', 245, '2025-10-09 04:31:51', 11, 'Order #245 - Customer purchase'),
(91, 48, 14, 'sale', 1.0, 'order', 246, '2025-10-09 04:34:15', 11, 'Order #246 - Customer purchase'),
(92, 48, 14, 'sale', 1.0, 'order', 247, '2025-10-09 04:38:56', 11, 'Order #247 - Customer purchase'),
(93, 48, 14, 'sale', 1.0, 'order', 248, '2025-10-09 04:53:55', 11, 'Order #248 - Customer purchase'),
(94, 48, 14, 'sale', 1.0, 'order', 249, '2025-10-09 06:31:38', 3, 'Order #249 - Customer purchase'),
(95, 48, 14, 'sale', 2.0, 'order', 250, '2025-10-09 13:28:21', 3, 'Order #250 - Customer purchase'),
(100, 56, 17, 'sale', 1.0, 'order', 264, '2025-10-12 05:52:45', 3, 'Order #264 - Customer purchase'),
(101, 57, 17, 'sale', 1.0, 'order', 265, '2025-10-12 06:07:02', 3, 'Order #265 - Customer purchase'),
(104, 59, 17, 'sale', 1.0, 'order', 268, '2025-10-12 06:12:56', 3, 'Order #268 - Customer purchase'),
(105, 58, 17, 'sale', 1.0, 'order', 269, '2025-10-12 06:13:35', 3, 'Order #269 - Customer purchase'),
(106, 59, 17, 'sale', 1.0, 'order', 270, '2025-10-12 06:17:57', 3, 'Order #270 - Customer purchase'),
(107, 60, 17, 'sale', 1.0, 'order', 271, '2025-10-12 06:20:00', 3, 'Order #271 - Customer purchase'),
(111, 61, 17, 'sale', 1.0, 'order', 275, '2025-10-12 06:22:02', 3, 'Order #275 - Customer purchase'),
(112, 62, 17, 'sale', 2.0, 'order', 276, '2025-10-12 06:22:20', 3, 'Order #276 - Customer purchase'),
(113, 63, 17, 'sale', 1.0, 'order', 277, '2025-10-12 06:23:26', 3, 'Order #277 - Customer purchase'),
(114, 63, 17, 'sale', 1.0, 'order', 277, '2025-10-12 06:23:26', 3, 'Order #277 - Customer purchase'),
(115, 64, 17, 'sale', 1.0, 'order', 278, '2025-10-12 06:23:52', 3, 'Order #278 - Customer purchase'),
(119, 65, 17, 'sale', 1.5, 'order', 282, '2025-10-12 06:33:18', 3, 'Order #282 - Customer purchase'),
(122, 66, 17, 'sale', 1.0, 'order', 285, '2025-10-12 06:39:05', 3, 'Order #285 - Customer purchase'),
(129, 66, 17, 'sale', 1.0, 'order', 292, '2025-10-12 06:47:24', 3, 'Order #292 - Customer purchase'),
(136, 66, 17, 'sale', 1.0, 'order', 299, '2025-10-12 06:56:42', 3, 'Order #299 - Customer purchase'),
(137, 65, 17, 'sale', 1.0, 'order', 299, '2025-10-12 06:56:42', 3, 'Order #299 - Customer purchase'),
(138, 66, 17, 'sale', 1.0, 'order', 300, '2025-10-12 07:00:06', 3, 'Order #300 - Customer purchase'),
(139, 66, 17, 'sale', 1.0, 'order', 301, '2025-10-12 07:08:27', 3, 'Order #301 - Customer purchase'),
(140, 66, 17, 'sale', 0.5, 'order', 302, '2025-10-12 07:08:59', 3, 'Order #302 - Customer purchase'),
(141, 68, 17, 'sale', 1.0, 'order', 303, '2025-10-12 11:29:53', 3, 'Order #303 - Customer purchase'),
(142, 67, 17, 'sale', 1.0, 'order', 303, '2025-10-12 11:29:53', 3, 'Order #303 - Customer purchase'),
(143, 68, 17, 'sale', 1.0, 'order', 304, '2025-10-12 14:46:03', 3, 'Order #304 - Customer purchase'),
(144, 68, 17, 'sale', 0.5, 'order', 305, '2025-10-12 14:50:10', 3, 'Order #305 - Customer purchase'),
(145, 69, 17, 'sale', 1.0, 'order', 306, '2025-10-12 14:58:53', 3, 'Order #306 - Customer purchase'),
(146, 69, 17, 'sale', 1.0, 'order', 307, '2025-10-12 15:06:05', 3, 'Order #307 - Customer purchase'),
(147, 69, 17, 'sale', 1.0, 'order', 308, '2025-10-12 15:51:21', 11, 'Order #308 - Customer purchase'),
(148, 69, 17, 'sale', 1.0, 'order', 309, '2025-10-12 15:52:14', 11, 'Order #309 - Customer purchase'),
(149, 69, 17, 'sale', 1.0, 'order', 310, '2025-10-12 15:55:58', 11, 'Order #310 - Customer purchase'),
(150, 69, 17, 'sale', 1.0, 'order', 311, '2025-10-12 16:02:52', 11, 'Order #311 - Customer purchase'),
(151, 69, 17, 'sale', 1.0, 'order', 311, '2025-10-12 16:02:52', 11, 'Order #311 - Customer purchase'),
(152, 69, 17, 'sale', 1.0, 'order', 312, '2025-10-13 07:15:04', 3, 'Order #312 - Customer purchase'),
(153, 69, 17, 'sale', 1.0, 'order', 313, '2025-10-13 07:51:47', 3, 'Order #313 - Customer purchase'),
(154, 69, 17, 'sale', 1.0, 'order', 314, '2025-10-13 07:52:13', 3, 'Order #314 - Customer purchase'),
(155, 70, 17, 'sale', 1.0, 'order', 315, '2025-10-13 07:52:58', 3, 'Order #315 - Customer purchase'),
(156, 70, 17, 'sale', 1.0, 'order', 316, '2025-10-13 07:54:09', 3, 'Order #316 - Customer purchase'),
(158, 71, 17, 'adjustment', 10.0, 'stock_adjustment', NULL, '2025-10-16 06:06:59', 4, 'Stock adjustment: Supplier Return'),
(159, 70, 17, 'adjustment', 5.0, 'stock_adjustment', NULL, '2025-10-16 06:16:37', 4, 'Stock adjustment: Damaged Items'),
(160, 70, 17, 'adjustment', 10.0, 'stock_adjustment', NULL, '2025-10-16 13:38:17', 4, 'Stock adjustment: Supplier Return'),
(161, 70, 17, 'adjustment', 10.0, 'stock_adjustment', NULL, '2025-10-16 13:50:52', 4, 'Stock adjustment: Supplier Return'),
(162, 70, 17, 'sale', 23.0, 'order', 317, '2025-10-16 15:13:29', 3, 'Order #317 - Customer purchase'),
(163, 72, 17, 'adjustment', 10.0, 'stock_adjustment', NULL, '2025-10-16 15:35:55', 4, 'Stock adjustment: Damaged Items'),
(164, 75, 17, 'sale', 25.0, 'order', 318, '2025-10-17 00:53:03', 3, 'Order #318 - Customer purchase'),
(165, 75, 17, 'sale', 5.0, 'order', 319, '2025-10-17 01:00:27', 3, 'Order #319 - Customer purchase'),
(166, 72, 17, 'sale', 30.0, 'order', 320, '2025-10-17 02:55:50', 9, 'Order #320 - Customer purchase'),
(167, 73, 17, 'sale', 5.0, 'order', 321, '2025-10-17 02:56:31', 9, 'Order #321 - Customer purchase'),
(168, 72, 17, 'adjustment', 10.0, 'stock_adjustment', NULL, '2025-10-18 07:29:09', 4, 'Stock adjustment: Supplier Return'),
(169, 73, 17, 'sale', 10.0, 'order', 322, '2025-10-18 18:06:00', 9, 'Order #322 - Customer purchase'),
(170, 73, 17, 'sale', 3.0, 'order', 323, '2025-10-18 18:24:18', 9, 'Order #323 - Customer purchase'),
(171, 73, 17, 'sale', 1.0, 'order', 324, '2025-10-18 18:52:40', 9, 'Order #324 - Customer purchase'),
(172, 73, 17, 'sale', 1.0, 'order', 325, '2025-10-18 19:03:10', 9, 'Order #325 - Customer purchase'),
(173, 74, 17, 'sale', 1.0, 'order', 326, '2025-10-18 19:05:28', 9, 'Order #326 - Customer purchase'),
(174, 74, 17, 'sale', 1.0, 'order', 327, '2025-10-18 19:07:47', 9, 'Order #327 - Customer purchase'),
(175, 80, 20, 'adjustment', 10.0, 'stock_adjustment', NULL, '2025-10-19 05:40:00', 4, 'Stock adjustment: Supplier Return'),
(176, 81, 20, 'adjustment', 190.0, 'stock_adjustment', NULL, '2025-10-19 05:40:00', 4, 'Stock adjustment: Supplier Return'),
(180, 81, 20, 'adjustment', 100.0, 'stock_adjustment', NULL, '2025-10-19 05:40:44', 4, 'Stock adjustment: Supplier Return'),
(181, 81, 20, 'adjustment', 100.0, 'stock_adjustment', NULL, '2025-10-19 05:41:10', 4, 'Stock adjustment: Supplier Return'),
(182, 85, 17, 'sale', 2.0, 'order', 328, '2025-10-19 08:18:07', 3, 'Order #328 - Customer purchase'),
(183, 74, 17, 'sale', 10.0, 'order', 328, '2025-10-19 08:18:07', 3, 'Order #328 - Customer purchase'),
(184, 79, 20, 'sale', 1.0, 'order', 329, '2025-10-19 08:19:58', 3, 'Order #329 - Customer purchase'),
(185, 81, 20, 'sale', 1.0, 'order', 329, '2025-10-19 08:19:58', 3, 'Order #329 - Customer purchase'),
(186, 82, 20, 'sale', 1.0, 'order', 330, '2025-10-19 08:21:36', 3, 'Order #330 - Customer purchase'),
(187, 81, 20, 'sale', 1.0, 'order', 330, '2025-10-19 08:21:36', 3, 'Order #330 - Customer purchase'),
(188, 74, 17, 'sale', 1.0, 'order', 331, '2025-10-19 08:22:54', 3, 'Order #331 - Customer purchase'),
(189, 74, 17, 'sale', 1.0, 'order', 332, '2025-10-19 08:23:46', 3, 'Order #332 - Customer purchase'),
(190, 81, 20, 'sale', 1.0, 'order', 333, '2025-10-19 08:26:07', 3, 'Order #333 - Customer purchase'),
(191, 82, 20, 'sale', 1.0, 'order', 334, '2025-10-19 08:29:00', 3, 'Order #334 - Customer purchase'),
(192, 81, 20, 'sale', 1.0, 'order', 335, '2025-10-19 08:30:59', 3, 'Order #335 - Customer purchase'),
(193, 81, 20, 'sale', 1.0, 'order', 336, '2025-10-19 08:37:18', 3, 'Order #336 - Customer purchase'),
(194, 81, 20, 'sale', 1.0, 'order', 337, '2025-10-19 08:38:12', 3, 'Order #337 - Customer purchase'),
(195, 74, 17, 'sale', 1.0, 'order', 338, '2025-10-19 08:46:20', 3, 'Order #338 - Customer purchase'),
(196, 81, 20, 'sale', 1.0, 'order', 339, '2025-10-19 08:51:54', 3, 'Order #339 - Customer purchase'),
(197, 82, 20, 'sale', 1.1, 'order', 340, '2025-10-19 08:52:09', 3, 'Order #340 - Customer purchase'),
(198, 81, 20, 'sale', 4.0, 'order', 341, '2025-10-19 08:52:37', 3, 'Order #341 - Customer purchase'),
(199, 81, 20, 'sale', 1.0, 'order', 342, '2025-10-19 08:52:59', 3, 'Order #342 - Customer purchase'),
(200, 81, 20, 'sale', 70.0, 'order', 343, '2025-10-19 13:28:49', 11, 'Order #343 - Customer purchase'),
(201, 81, 20, 'sale', 70.0, 'order', 344, '2025-10-19 13:30:03', 11, 'Order #344 - Customer purchase'),
(202, 94, 28, 'sale', 15.0, 'order', 345, '2025-10-19 14:17:01', 11, 'Order #345 - Customer purchase'),
(203, 92, 22, 'sale', 10.0, 'order', 346, '2025-10-19 14:34:07', 11, 'Order #346 - Customer purchase'),
(204, 100, 27, 'sale', 5.0, 'order', 347, '2025-10-19 15:44:06', 11, 'Order #347 - Customer purchase'),
(205, 95, 26, 'sale', 10.0, 'order', 348, '2025-10-19 15:53:46', 11, 'Order #348 - Customer purchase'),
(206, 90, 32, 'sale', 2.0, 'order', 349, '2025-10-19 15:58:28', 11, 'Order #349 - Customer purchase'),
(207, 88, 21, 'sale', 1.0, 'order', 350, '2025-10-20 00:31:05', 11, 'Order #350 - Customer purchase'),
(208, 94, 28, 'sale', 2.0, 'order', 351, '2025-10-20 05:08:23', 11, 'Order #351 - Customer purchase'),
(209, 104, 28, 'sale', 5.0, 'order', 351, '2025-10-20 05:08:23', 11, 'Order #351 - Customer purchase'),
(210, 92, 22, 'sale', 1.0, 'order', 352, '2025-10-20 05:11:12', 11, 'Order #352 - Customer purchase'),
(211, 103, 22, 'sale', 5.0, 'order', 352, '2025-10-20 05:11:12', 11, 'Order #352 - Customer purchase'),
(212, 87, 24, 'sale', 4.0, 'order', 353, '2025-10-20 13:13:52', 11, 'Order #353 - Customer purchase'),
(213, 104, 28, 'sale', 9.0, 'order', 354, '2025-10-21 01:31:59', 3, 'Order #354 - Customer purchase'),
(214, 87, 24, 'sale', 1.0, 'order', 355, '2025-10-21 01:47:51', 3, 'Order #355 - Customer purchase'),
(215, 88, 21, 'sale', 6.0, 'order', 356, '2025-10-21 03:14:27', 10, 'Order #356 - Customer purchase'),
(216, 86, 25, 'sale', 4.0, 'order', 357, '2025-10-21 03:19:24', 11, 'Order #357 - Customer purchase'),
(217, 103, 22, 'sale', 10.0, 'order', 358, '2025-10-21 16:22:04', 9, 'Order #358 - Customer purchase'),
(218, 105, 22, 'sale', 5.0, 'order', 358, '2025-10-21 16:22:04', 9, 'Order #358 - Customer purchase'),
(219, 101, 23, 'sale', 15.0, 'order', 359, '2025-10-21 17:24:55', 9, 'Order #359 - Customer purchase'),
(220, 98, 20, 'sale', 5.0, 'order', 360, '2025-10-22 03:51:00', 9, 'Order #360 - Customer purchase'),
(221, 105, 22, 'sale', 10.0, 'order', 361, '2025-10-22 05:07:45', 10, 'Order #361 - Customer purchase'),
(222, 105, 22, 'sale', 1.0, 'order', 362, '2025-10-22 05:45:09', 20, 'Order #362 - Customer purchase'),
(223, 91, 29, 'sale', 1.0, 'order', 362, '2025-10-22 05:45:09', 20, 'Order #362 - Customer purchase'),
(224, 98, 20, 'sale', 1.0, 'order', 363, '2025-10-22 05:48:59', 20, 'Order #363 - Customer purchase'),
(225, 86, 25, 'sale', 1.0, 'order', 364, '2025-10-22 05:51:35', 20, 'Order #364 - Customer purchase'),
(226, 102, 25, 'sale', 1.0, 'order', 364, '2025-10-22 05:51:35', 20, 'Order #364 - Customer purchase'),
(227, 87, 24, 'sale', 2.0, 'order', 365, '2025-10-22 05:57:59', 20, 'Order #365 - Customer purchase'),
(228, 74, 17, 'sale', 7.0, 'order', 366, '2025-10-22 06:14:14', 20, 'Order #366 - Customer purchase'),
(229, 74, 17, 'sale', 1.0, 'order', 367, '2025-10-22 07:04:18', 20, 'Order #367 - Customer purchase'),
(230, 74, 17, 'sale', 15.0, 'order', 368, '2025-10-22 07:05:45', 3, 'Order #368 - Customer purchase'),
(231, 87, 24, 'sale', 2.0, 'order', 369, '2025-10-22 12:44:47', 3, 'Order #369 - Customer purchase'),
(232, 108, 28, 'sale', 1.0, 'order', 370, '2025-10-22 12:47:40', 3, 'Order #370 - Customer purchase'),
(233, 108, 28, 'sale', 1.0, 'order', 371, '2025-10-22 12:54:57', 3, 'Order #371 - Customer purchase'),
(234, 109, 28, 'sale', 2.0, 'order', 371, '2025-10-22 12:54:57', 3, 'Order #371 - Customer purchase'),
(235, 109, 28, '', 3.0, 'order_cancellation', 371, '2025-10-22 12:55:06', 4, 'Order cancellation: Customer unable to visit the store'),
(236, 101, 23, '', 15.0, 'order_cancellation', 359, '2025-10-22 12:55:52', 4, 'Order cancellation: Customer unable to visit the store'),
(237, 101, 23, 'sale', 10.0, 'order', 372, '2025-10-22 15:45:07', 10, 'Order #372 - Customer purchase'),
(238, 82, 20, 'sale', 2.0, 'order', 373, '2025-10-22 15:58:50', 3, 'Order #373 - Customer purchase'),
(239, 95, 26, 'sale', 1.0, 'order', 373, '2025-10-22 15:58:50', 3, 'Order #373 - Customer purchase'),
(240, 105, 22, 'sale', 1.0, 'order', 374, '2025-10-24 01:08:23', 3, 'Order #374 - Customer purchase'),
(241, 105, 22, 'sale', 2.0, 'order', 375, '2025-10-24 01:51:44', 3, 'Order #375 - Customer purchase'),
(242, 105, 22, 'sale', 1.0, 'order', 376, '2025-10-24 01:58:02', 3, 'Order #376 - Customer purchase'),
(243, 95, 26, 'sale', 1.0, 'order', 377, '2025-10-24 02:01:00', 3, 'Order #377 - Customer purchase'),
(244, 105, 22, 'sale', 1.0, 'order', 378, '2025-10-24 02:04:21', 3, 'Order #378 - Customer purchase'),
(245, 100, 27, 'sale', 1.0, 'order', 379, '2025-10-24 15:06:14', 3, 'Order #379 - Customer purchase'),
(246, 101, 23, 'sale', 1.0, 'order', 380, '2025-10-24 15:08:26', 3, 'Order #380 - Customer purchase'),
(247, 109, 28, 'sale', 1.0, 'order', 381, '2025-10-25 10:40:13', 3, 'Order #381 - Customer purchase'),
(248, 74, 17, 'sale', 12.0, 'order', 382, '2025-10-25 13:52:42', 3, 'Order #382 - Customer purchase'),
(249, 105, 22, 'sale', 1.0, 'order', 383, '2025-10-25 13:54:42', 3, 'Order #383 - Customer purchase'),
(250, 74, 17, '', 12.0, 'order_cancellation', 382, '2025-10-26 02:48:50', 4, 'Order cancellation: Customer did not pick up order within 3 hours (6.9 hours elapsed)'),
(251, 105, 22, 'sale', 1.0, 'order', 384, '2025-10-26 04:52:53', 10, 'Order #384 - Customer purchase'),
(252, 93, 31, 'sale', 1.0, 'order', 385, '2025-10-26 09:39:04', 3, 'Order #385 - Customer purchase'),
(253, 105, 22, 'sale', 1.0, 'order', 386, '2025-10-26 09:40:28', 3, 'Order #386 - Customer purchase'),
(254, 93, 31, 'sale', 1.0, 'order', 387, '2025-10-26 13:01:29', 3, 'Order #387 - Customer purchase'),
(255, 82, 20, 'sale', 1.0, 'order', 388, '2025-10-26 13:06:22', 3, 'Order #388 - Customer purchase'),
(256, 98, 20, 'sale', 4.0, 'order', 389, '2025-10-30 04:09:14', 3, 'Order #389 - Customer purchase'),
(257, 99, 20, 'sale', 7.0, 'order', 389, '2025-10-30 04:09:14', 3, 'Order #389 - Customer purchase'),
(258, 82, 20, 'sale', 1.0, 'order', 389, '2025-10-30 04:09:14', 3, 'Order #389 - Customer purchase'),
(259, 95, 26, 'sale', 5.0, 'order', 390, '2025-10-30 04:17:19', 3, 'Order #390 - Customer purchase'),
(260, 89, 30, 'sale', 5.0, 'order', 390, '2025-10-30 04:17:19', 3, 'Order #390 - Customer purchase'),
(261, 101, 23, 'sale', 1.0, 'order', 391, '2025-10-30 12:32:42', 3, 'Order #391 - Customer purchase'),
(262, 91, 29, 'sale', 1.0, 'order', 392, '2025-10-30 12:56:16', 3, 'Order #392 - Customer purchase'),
(263, 101, 23, 'sale', 1.0, 'order', 393, '2025-10-30 13:11:14', 3, 'Order #393 - Customer purchase'),
(264, 99, 20, 'sale', 40.0, 'order', 394, '2025-10-30 13:18:50', 9, 'Order #394 - Customer purchase'),
(265, 82, 20, 'sale', 1.0, 'order', 394, '2025-10-30 13:18:50', 9, 'Order #394 - Customer purchase'),
(266, 93, 31, 'sale', 10.0, 'order', 395, '2025-10-30 13:47:52', 9, 'Order #395 - Customer purchase'),
(267, 106, 20, '', 1.0, 'order_cancellation', 388, '2025-10-31 07:56:10', 4, 'Order cancellation: Customer did not pick up order within 3 hours (11.6 hours elapsed)'),
(268, 93, 31, '', 1.0, 'order_cancellation', 385, '2025-10-31 08:05:44', 4, 'Order cancellation: Customer did not pick up order within 3 hours (111.4 hours elapsed)'),
(269, 110, 23, '', 1.0, 'order_cancellation', 393, '2025-10-31 08:06:27', 4, 'Order cancellation: Insufficient Payment'),
(270, 101, 23, 'sale', 1.0, 'order', 396, '2025-10-31 08:12:01', 3, 'Order #396 - Customer purchase'),
(271, 110, 23, '', 1.0, 'order_cancellation', 396, '2025-10-31 08:12:30', 4, 'Order cancellation: Insufficient Payment'),
(272, 111, 25, 'sale', 3.0, 'order', 397, '2025-10-31 08:24:40', 3, 'Order #397 - Customer purchase'),
(273, 100, 27, 'sale', 4.0, 'order', 398, '2025-10-31 11:12:59', 3, 'Order #398 - Customer purchase'),
(274, 100, 27, '', 4.0, 'order_cancellation', 398, '2025-10-31 11:13:23', 4, 'Order cancellation: Customer unable to visit the store'),
(275, 100, 27, 'sale', 4.0, 'order', 399, '2025-10-31 11:13:50', 3, 'Order #399 - Customer purchase'),
(276, 117, 27, 'sale', 10.0, 'order', 400, '2025-10-31 11:22:12', 3, 'Order #400 - Customer purchase'),
(277, 120, 23, 'sale', 20.0, 'order', 401, '2025-10-31 11:46:35', 10, 'Order #401 - Customer purchase'),
(278, 121, 23, 'sale', 20.0, 'order', 401, '2025-10-31 11:46:35', 10, 'Order #401 - Customer purchase'),
(279, 112, 24, 'sale', 10.0, 'order', 402, '2025-10-31 12:27:08', 10, 'Order #402 - Customer purchase'),
(280, 115, 24, 'sale', 10.0, 'order', 402, '2025-10-31 12:27:08', 10, 'Order #402 - Customer purchase');

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_archived` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `is_archived`, `created_at`) VALUES
(1, 'San Gabriel Beef', 0, '2025-07-12 14:00:25'),
(3, 'Zayn Bangus', 1, '2025-07-25 12:31:19'),
(4, 'ANDOKS ', 1, '2025-08-05 15:19:56'),
(5, 'GOODS GOODS', 1, '2025-08-05 15:20:14'),
(6, 'XYZ INC.', 1, '2025-08-05 15:20:31'),
(10, 'Pampanga\'s Best', 1, '2025-09-22 04:48:15'),
(11, 'Tyson', 0, '2025-10-10 11:20:38'),
(12, 'Mega', 0, '2025-10-10 11:26:24'),
(13, 'Beefies', 0, '2025-10-16 06:19:14'),
(14, 'Foremost', 0, '2025-10-19 05:15:20'),
(15, 'King\'s', 0, '2025-10-19 09:44:15'),
(16, 'Sea World', 0, '2025-10-19 09:44:31'),
(17, 'Magnolia', 0, '2025-10-19 09:44:44'),
(18, 'Bounty Fresh', 0, '2025-10-19 09:44:55'),
(19, 'Monterey', 0, '2025-10-19 09:45:10'),
(20, 'CDO', 0, '2025-10-19 09:46:32'),
(21, 'Unbranded', 0, '2025-10-19 09:46:54');

-- --------------------------------------------------------

--
-- Table structure for table `brand_product_stock`
--

CREATE TABLE `brand_product_stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `reorder_point` decimal(10,2) DEFAULT 0.00,
  `average_daily_sales` decimal(10,2) DEFAULT 0.00,
  `movement_type` enum('Fast-Moving','Slow-Moving','Non-Moving') DEFAULT 'Non-Moving',
  `last_calculated` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brand_product_stock`
--

INSERT INTO `brand_product_stock` (`id`, `product_id`, `brand_id`, `reorder_point`, `average_daily_sales`, `movement_type`, `last_calculated`, `created_at`, `updated_at`) VALUES
(1, 17, 13, 51.41, 7.14, 'Slow-Moving', '2025-10-25 21:52:42', '2025-10-17 00:58:40', '2025-10-25 13:52:42'),
(2, 17, 12, 41.69, 5.79, 'Slow-Moving', '2025-10-19 03:03:10', '2025-10-17 00:58:40', '2025-10-18 19:03:10'),
(3, 17, 11, 61.20, 8.50, 'Slow-Moving', '2025-10-19 16:18:07', '2025-10-17 00:58:40', '2025-10-19 08:18:07'),
(25, 20, 13, 1.00, 0.43, 'Non-Moving', '2025-10-30 21:18:50', '2025-10-19 05:15:46', '2025-10-30 13:18:50'),
(27, 20, 14, 158.48, 21.71, 'Fast-Moving', '2025-10-19 21:30:03', '2025-10-19 05:37:01', '2025-10-19 13:30:03'),
(51, 25, 19, 1.00, 0.86, 'Non-Moving', '2025-10-22 13:51:35', '2025-10-19 11:39:11', '2025-10-22 05:51:35'),
(52, 24, 19, 9.29, 1.29, 'Slow-Moving', '2025-10-22 20:44:47', '2025-10-19 11:39:32', '2025-10-22 12:44:47'),
(53, 21, 15, 1.00, 0.00, 'Non-Moving', '2025-10-31 17:43:12', '2025-10-19 11:39:48', '2025-10-31 09:43:12'),
(54, 30, 20, 1.00, 0.71, 'Non-Moving', '2025-10-30 12:17:19', '2025-10-19 11:40:21', '2025-10-30 04:17:19'),
(55, 32, 16, 1.00, 0.29, 'Non-Moving', '2025-10-19 23:58:28', '2025-10-19 11:40:34', '2025-10-19 15:58:28'),
(56, 29, 20, 1.00, 0.14, 'Non-Moving', '2025-10-30 20:56:16', '2025-10-19 11:42:09', '2025-10-30 12:56:16'),
(57, 22, 12, 51.41, 7.14, 'Slow-Moving', '2025-10-26 17:40:28', '2025-10-19 11:42:28', '2025-10-26 09:40:28'),
(58, 31, 16, 12.31, 1.71, 'Slow-Moving', '2025-10-30 21:47:52', '2025-10-19 11:42:42', '2025-10-30 13:47:52'),
(59, 28, 17, 31.90, 4.43, 'Slow-Moving', '2025-10-21 09:31:59', '2025-10-19 11:42:54', '2025-10-21 01:31:59'),
(60, 26, 17, 1.00, 0.86, 'Non-Moving', '2025-10-30 12:17:19', '2025-10-19 11:43:32', '2025-10-30 04:17:19'),
(61, 20, 11, 52.49, 7.29, 'Slow-Moving', '2025-10-30 21:18:50', '2025-10-19 12:14:13', '2025-10-30 13:18:50'),
(65, 27, 15, 9.29, 1.29, 'Slow-Moving', '2025-10-31 19:42:07', '2025-10-19 14:10:49', '2025-10-31 11:42:07'),
(66, 23, 18, 1.00, 0.57, 'Non-Moving', '2025-10-31 16:12:01', '2025-10-19 14:11:33', '2025-10-31 08:12:01'),
(99, 28, 15, 1.00, 0.71, 'Non-Moving', '2025-10-25 18:40:13', '2025-10-22 12:46:54', '2025-10-25 10:40:13'),
(115, 25, 20, 1.00, 0.43, 'Non-Moving', '2025-10-31 16:24:40', '2025-10-25 13:29:57', '2025-10-31 08:24:40'),
(135, 24, 1, 20.59, 2.86, 'Slow-Moving', '2025-10-31 20:27:08', '2025-10-31 08:50:29', '2025-10-31 12:27:08'),
(141, 27, 18, 1.00, 0.00, 'Non-Moving', '2025-10-31 19:16:48', '2025-10-31 11:16:48', '2025-10-31 11:16:48'),
(142, 27, 12, 10.30, 1.43, 'Slow-Moving', '2025-10-31 19:22:12', '2025-10-31 11:20:55', '2025-10-31 11:22:12'),
(144, 27, 13, 1.00, 0.00, 'Non-Moving', '2025-10-31 19:30:00', '2025-10-31 11:30:00', '2025-10-31 11:30:00'),
(146, 23, 11, 41.11, 5.71, 'Slow-Moving', '2025-10-31 19:46:35', '2025-10-31 11:43:56', '2025-10-31 11:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `session_token`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 3, NULL, 0, '2025-10-10 15:04:06', '2025-10-10 15:04:16'),
(3, 3, NULL, 0, '2025-10-10 15:04:23', '2025-10-10 15:05:00'),
(4, 3, NULL, 0, '2025-10-10 15:05:09', '2025-10-10 15:31:39'),
(5, 3, NULL, 0, '2025-10-10 15:31:45', '2025-10-10 15:34:25'),
(6, 3, NULL, 0, '2025-10-10 15:34:47', '2025-10-10 16:06:23'),
(7, 11, NULL, 0, '2025-10-10 15:35:25', '2025-10-10 15:46:06'),
(8, 11, NULL, 0, '2025-10-10 15:46:09', '2025-10-12 03:38:17'),
(9, 3, NULL, 0, '2025-10-10 16:06:32', '2025-10-10 16:14:17'),
(10, 3, NULL, 0, '2025-10-10 16:14:24', '2025-10-11 10:58:17'),
(11, 3, NULL, 0, '2025-10-11 10:58:34', '2025-10-11 12:17:20'),
(12, 3, NULL, 0, '2025-10-11 12:17:26', '2025-10-11 14:54:13'),
(13, 3, NULL, 0, '2025-10-11 14:54:19', '2025-10-11 15:22:27'),
(14, 3, NULL, 0, '2025-10-11 15:22:34', '2025-10-12 03:22:29'),
(15, 3, NULL, 0, '2025-10-12 03:22:39', '2025-10-12 05:32:45'),
(16, 11, NULL, 0, '2025-10-12 03:38:26', '2025-10-12 03:49:48'),
(17, 11, NULL, 0, '2025-10-12 03:50:13', '2025-10-12 04:49:35'),
(18, 11, NULL, 0, '2025-10-12 04:49:42', '2025-10-12 04:58:52'),
(19, 11, NULL, 0, '2025-10-12 04:58:59', '2025-10-12 15:41:59'),
(20, 3, NULL, 0, '2025-10-12 05:32:50', '2025-10-12 05:52:45'),
(21, 3, NULL, 0, '2025-10-12 06:00:01', '2025-10-12 06:01:35'),
(22, 3, NULL, 0, '2025-10-12 06:01:39', '2025-10-12 06:03:51'),
(23, 3, NULL, 0, '2025-10-12 06:03:55', '2025-10-12 06:04:07'),
(24, 3, NULL, 0, '2025-10-12 06:04:12', '2025-10-12 06:07:02'),
(25, 3, NULL, 0, '2025-10-12 06:12:14', '2025-10-12 06:12:56'),
(26, 3, NULL, 0, '2025-10-12 06:13:30', '2025-10-12 06:13:35'),
(27, 3, NULL, 0, '2025-10-12 06:13:55', '2025-10-12 06:14:23'),
(28, 3, NULL, 0, '2025-10-12 06:17:51', '2025-10-12 06:17:57'),
(29, 3, NULL, 0, '2025-10-12 06:19:51', '2025-10-12 06:20:00'),
(30, 3, NULL, 0, '2025-10-12 06:20:55', '2025-10-12 06:22:02'),
(31, 3, NULL, 0, '2025-10-12 06:22:15', '2025-10-12 06:22:20'),
(32, 3, NULL, 0, '2025-10-12 06:23:19', '2025-10-12 06:23:26'),
(33, 3, NULL, 0, '2025-10-12 06:23:47', '2025-10-12 06:23:52'),
(34, 3, NULL, 0, '2025-10-12 06:26:09', '2025-10-12 06:33:18'),
(35, 3, NULL, 0, '2025-10-12 06:33:27', '2025-10-12 06:38:30'),
(36, 3, NULL, 0, '2025-10-12 06:38:34', '2025-10-12 06:39:05'),
(37, 3, NULL, 0, '2025-10-12 06:39:11', '2025-10-12 06:47:24'),
(38, 3, NULL, 0, '2025-10-12 06:47:53', '2025-10-12 06:56:42'),
(39, 3, NULL, 0, '2025-10-12 06:59:58', '2025-10-12 07:00:06'),
(40, 3, NULL, 0, '2025-10-12 07:08:08', '2025-10-12 07:08:27'),
(41, 3, NULL, 0, '2025-10-12 07:08:33', '2025-10-12 07:08:59'),
(42, 3, NULL, 0, '2025-10-12 11:28:17', '2025-10-12 11:29:53'),
(43, 3, NULL, 0, '2025-10-12 14:45:56', '2025-10-12 14:46:03'),
(44, 3, NULL, 0, '2025-10-12 14:50:02', '2025-10-12 14:50:10'),
(45, 3, NULL, 0, '2025-10-12 14:58:44', '2025-10-12 14:58:53'),
(46, 3, NULL, 0, '2025-10-12 15:05:58', '2025-10-12 15:06:05'),
(47, 11, NULL, 0, '2025-10-12 15:51:14', '2025-10-12 15:51:21'),
(48, 11, NULL, 0, '2025-10-12 15:52:07', '2025-10-12 15:52:14'),
(49, 11, NULL, 0, '2025-10-12 15:55:50', '2025-10-12 15:55:58'),
(50, 11, NULL, 0, '2025-10-12 16:00:19', '2025-10-12 16:02:52'),
(51, 11, NULL, 0, '2025-10-12 23:00:21', '2025-10-19 13:27:56'),
(52, 3, NULL, 0, '2025-10-13 06:59:45', '2025-10-13 06:59:50'),
(53, 3, NULL, 0, '2025-10-13 07:01:16', '2025-10-13 07:01:21'),
(54, 3, NULL, 0, '2025-10-13 07:02:22', '2025-10-13 07:02:26'),
(55, 3, NULL, 0, '2025-10-13 07:02:51', '2025-10-13 07:15:04'),
(56, 3, NULL, 0, '2025-10-13 07:18:59', '2025-10-13 07:51:47'),
(57, 3, NULL, 0, '2025-10-13 07:52:09', '2025-10-13 07:52:13'),
(58, 3, NULL, 0, '2025-10-13 07:52:54', '2025-10-13 07:52:58'),
(59, 3, NULL, 0, '2025-10-13 07:54:05', '2025-10-13 07:54:09'),
(60, 3, NULL, 0, '2025-10-16 15:13:06', '2025-10-16 15:13:29'),
(61, 3, NULL, 0, '2025-10-17 00:52:46', '2025-10-17 00:53:03'),
(62, 3, NULL, 0, '2025-10-17 01:00:19', '2025-10-17 01:00:27'),
(63, 9, NULL, 0, '2025-10-17 02:55:13', '2025-10-17 02:55:50'),
(64, 9, NULL, 0, '2025-10-17 02:56:24', '2025-10-17 02:56:31'),
(65, 9, NULL, 0, '2025-10-18 18:05:36', '2025-10-18 18:06:00'),
(66, 9, NULL, 0, '2025-10-18 18:24:11', '2025-10-18 18:24:18'),
(67, 9, NULL, 0, '2025-10-18 18:52:28', '2025-10-18 18:52:40'),
(68, 9, NULL, 0, '2025-10-18 19:02:53', '2025-10-18 19:03:10'),
(69, 9, NULL, 0, '2025-10-18 19:05:00', '2025-10-18 19:05:28'),
(70, 9, NULL, 0, '2025-10-18 19:07:34', '2025-10-18 19:07:47'),
(71, 3, NULL, 0, '2025-10-19 05:34:13', '2025-10-19 05:46:39'),
(72, 3, NULL, 0, '2025-10-19 05:46:41', '2025-10-19 05:46:45'),
(73, 3, NULL, 0, '2025-10-19 05:46:47', '2025-10-19 05:46:53'),
(74, 3, NULL, 0, '2025-10-19 05:56:41', '2025-10-19 05:56:45'),
(75, 3, NULL, 0, '2025-10-19 05:56:47', '2025-10-19 05:56:55'),
(76, 3, NULL, 0, '2025-10-19 06:17:20', '2025-10-19 06:17:33'),
(77, 3, NULL, 0, '2025-10-19 06:17:37', '2025-10-19 06:17:41'),
(78, 3, NULL, 0, '2025-10-19 06:17:43', '2025-10-19 06:18:09'),
(79, 3, NULL, 0, '2025-10-19 06:20:17', '2025-10-19 06:22:23'),
(80, 3, NULL, 0, '2025-10-19 06:22:26', '2025-10-19 06:24:16'),
(81, 3, NULL, 0, '2025-10-19 06:24:18', '2025-10-19 06:26:57'),
(82, 3, NULL, 0, '2025-10-19 06:27:06', '2025-10-19 06:28:08'),
(83, 3, NULL, 0, '2025-10-19 06:28:16', '2025-10-19 06:30:39'),
(84, 3, NULL, 0, '2025-10-19 06:30:42', '2025-10-19 06:30:50'),
(85, 3, NULL, 0, '2025-10-19 06:32:09', '2025-10-19 06:33:13'),
(86, 3, NULL, 0, '2025-10-19 06:33:27', '2025-10-19 06:36:44'),
(87, 3, NULL, 0, '2025-10-19 06:36:49', '2025-10-19 06:44:46'),
(88, 3, NULL, 0, '2025-10-19 06:44:51', '2025-10-19 06:48:50'),
(89, 3, NULL, 0, '2025-10-19 06:48:54', '2025-10-19 06:51:42'),
(90, 3, NULL, 0, '2025-10-19 06:51:45', '2025-10-19 06:58:07'),
(91, 3, NULL, 0, '2025-10-19 06:58:15', '2025-10-19 07:01:02'),
(92, 3, NULL, 0, '2025-10-19 07:01:08', '2025-10-19 07:09:59'),
(93, 3, NULL, 0, '2025-10-19 07:10:04', '2025-10-19 08:18:07'),
(94, 3, NULL, 0, '2025-10-19 08:18:33', '2025-10-19 08:19:58'),
(95, 3, NULL, 0, '2025-10-19 08:20:23', '2025-10-19 08:20:28'),
(96, 3, NULL, 0, '2025-10-19 08:20:54', '2025-10-19 08:21:36'),
(97, 3, NULL, 0, '2025-10-19 08:21:50', '2025-10-19 08:21:57'),
(98, 3, NULL, 0, '2025-10-19 08:22:41', '2025-10-19 08:22:54'),
(99, 3, NULL, 0, '2025-10-19 08:23:08', '2025-10-19 08:23:22'),
(100, 3, NULL, 0, '2025-10-19 08:23:26', '2025-10-19 08:23:46'),
(101, 3, NULL, 0, '2025-10-19 08:23:55', '2025-10-19 08:25:43'),
(102, 3, NULL, 0, '2025-10-19 08:25:49', '2025-10-19 08:26:07'),
(103, 3, NULL, 0, '2025-10-19 08:28:44', '2025-10-19 08:28:48'),
(104, 3, NULL, 0, '2025-10-19 08:28:50', '2025-10-19 08:29:23'),
(105, 3, NULL, 0, '2025-10-19 08:30:01', '2025-10-19 08:31:14'),
(106, 3, NULL, 0, '2025-10-19 08:31:41', '2025-10-19 08:36:13'),
(107, 3, NULL, 0, '2025-10-19 08:37:04', '2025-10-19 08:37:56'),
(108, 3, NULL, 0, '2025-10-19 08:38:03', '2025-10-19 08:38:22'),
(109, 3, NULL, 0, '2025-10-19 08:41:44', '2025-10-19 08:43:56'),
(110, 3, NULL, 0, '2025-10-19 08:44:04', '2025-10-19 08:44:29'),
(111, 3, NULL, 0, '2025-10-19 08:44:58', '2025-10-19 08:45:47'),
(112, 3, NULL, 0, '2025-10-19 08:45:56', '2025-10-19 08:51:39'),
(113, 3, NULL, 0, '2025-10-19 08:51:43', '2025-10-20 05:30:39'),
(114, 11, NULL, 0, '2025-10-19 13:28:33', '2025-10-19 15:29:04'),
(115, 11, NULL, 0, '2025-10-19 15:29:18', '2025-10-20 00:30:24'),
(116, 11, NULL, 0, '2025-10-20 00:30:43', '2025-10-20 01:39:36'),
(117, 9, NULL, 0, '2025-10-20 02:05:50', '2025-10-20 05:29:51'),
(118, 11, NULL, 0, '2025-10-20 05:08:14', '2025-10-20 13:12:28'),
(119, 11, NULL, 0, '2025-10-20 13:13:42', '2025-10-21 03:17:30'),
(120, 3, NULL, 0, '2025-10-21 01:22:11', '2025-10-21 01:31:05'),
(121, 3, NULL, 0, '2025-10-21 01:31:48', '2025-10-21 02:36:20'),
(122, 10, NULL, 0, '2025-10-21 03:14:18', '2025-10-22 05:05:57'),
(123, 11, NULL, 1, '2025-10-21 03:19:16', '2025-10-21 03:19:16'),
(124, 9, NULL, 0, '2025-10-21 16:21:47', '2025-10-22 03:25:49'),
(125, 9, NULL, 0, '2025-10-22 03:39:44', '2025-10-22 03:39:49'),
(126, 9, NULL, 0, '2025-10-22 03:48:11', '2025-10-22 03:48:39'),
(127, 9, NULL, 0, '2025-10-22 03:48:41', '2025-10-22 03:49:42'),
(128, 9, NULL, 0, '2025-10-22 03:49:44', '2025-10-22 03:49:59'),
(129, 9, NULL, 0, '2025-10-22 03:50:36', '2025-10-30 13:17:18'),
(130, 10, NULL, 0, '2025-10-22 05:06:45', '2025-10-22 15:44:21'),
(131, 20, NULL, 0, '2025-10-22 05:11:07', '2025-10-22 05:57:40'),
(132, 20, NULL, 1, '2025-10-22 05:57:50', '2025-10-22 07:04:07'),
(133, 3, NULL, 0, '2025-10-22 07:05:29', '2025-10-22 12:43:57'),
(134, 3, NULL, 0, '2025-10-22 12:44:41', '2025-10-22 12:47:57'),
(135, 3, NULL, 0, '2025-10-22 12:54:51', '2025-10-22 15:55:18'),
(136, 10, NULL, 0, '2025-10-22 15:44:41', '2025-10-22 15:45:07'),
(137, 3, NULL, 0, '2025-10-22 15:56:26', '2025-10-22 15:58:50'),
(138, 3, NULL, 0, '2025-10-22 16:06:05', '2025-10-22 16:06:27'),
(139, 3, NULL, 0, '2025-10-22 16:08:14', '2025-10-23 03:35:48'),
(140, 3, NULL, 0, '2025-10-23 03:35:55', '2025-10-23 03:36:16'),
(141, 3, NULL, 0, '2025-10-23 03:36:37', '2025-10-23 03:51:54'),
(142, 3, NULL, 0, '2025-10-23 03:51:58', '2025-10-23 03:52:08'),
(143, 3, NULL, 0, '2025-10-23 03:52:10', '2025-10-23 03:56:47'),
(144, 3, NULL, 0, '2025-10-23 03:56:49', '2025-10-23 03:59:08'),
(145, 3, NULL, 0, '2025-10-23 04:00:10', '2025-10-24 00:42:42'),
(146, 3, NULL, 0, '2025-10-24 00:42:47', '2025-10-24 01:37:18'),
(147, 3, NULL, 0, '2025-10-24 01:51:31', '2025-10-24 13:00:38'),
(148, 3, NULL, 0, '2025-10-24 14:26:32', '2025-10-25 09:16:00'),
(149, 3, NULL, 0, '2025-10-25 10:40:06', '2025-10-25 11:39:13'),
(150, 3, NULL, 0, '2025-10-25 11:39:17', '2025-10-25 11:39:29'),
(151, 3, NULL, 0, '2025-10-25 11:39:46', '2025-10-25 11:40:58'),
(152, 3, NULL, 0, '2025-10-25 11:41:06', '2025-10-25 11:41:52'),
(153, 3, NULL, 0, '2025-10-25 13:38:55', '2025-10-25 13:52:42'),
(154, 3, NULL, 0, '2025-10-25 13:54:22', '2025-10-26 09:38:42'),
(155, 10, NULL, 0, '2025-10-26 04:52:22', '2025-10-26 04:52:53'),
(156, 3, NULL, 0, '2025-10-26 09:38:56', '2025-10-26 12:24:04'),
(157, 3, NULL, 0, '2025-10-26 12:24:17', '2025-10-26 14:49:44'),
(158, 3, NULL, 0, '2025-10-30 04:08:42', '2025-10-30 11:33:57'),
(159, 3, NULL, 0, '2025-10-30 11:34:01', '2025-10-31 07:56:44'),
(160, 9, NULL, 1, '2025-10-30 13:17:23', '2025-10-30 13:46:48'),
(161, 3, NULL, 0, '2025-10-31 08:11:54', '2025-10-31 11:45:37'),
(162, 10, NULL, 1, '2025-10-31 11:46:16', '2025-10-31 12:26:27');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cartitem_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,1) NOT NULL DEFAULT 1.0,
  `unit` varchar(20) DEFAULT 'kilo',
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `is_archive` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `is_archive`) VALUES
(23, 'BEEF', 0),
(24, 'CHICKEN', 0),
(27, 'SEA FOODS', 0),
(28, 'PORK', 0),
(32, 'Processed Meat', 0);

-- --------------------------------------------------------

--
-- Table structure for table `customer_id_verification`
--

CREATE TABLE `customer_id_verification` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_type` varchar(50) NOT NULL,
  `id_number` varchar(100) NOT NULL,
  `id_front_image` varchar(255) NOT NULL,
  `id_back_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_id_verification`
--

INSERT INTO `customer_id_verification` (`verification_id`, `user_id`, `id_type`, `id_number`, `id_front_image`, `id_back_image`, `status`, `rejection_reason`, `admin_notes`, `verified_by`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 3, 'Student ID', '02000380827', 'uploads/id_verification/front_3_1758107712.jpg', 'uploads/id_verification/back_3_1758107712.jpg', 'approved', NULL, '', 4, '2025-09-17 12:04:02', '2025-09-17 11:15:12', '2025-09-17 12:04:02'),
(2, 9, 'Student ID', '20000000000', 'uploads/id_verification/front_9_1758110222.jpg', 'uploads/id_verification/back_9_1758110222.png', 'approved', NULL, '', 4, '2025-09-17 12:19:13', '2025-09-17 11:57:02', '2025-09-17 12:19:13'),
(3, 10, 'Student ID', '02000380827', 'uploads/id_verification/front_10_1758123228.jpg', 'uploads/id_verification/back_10_1758123228.jpg', 'approved', NULL, '', 4, '2025-09-17 15:34:09', '2025-09-17 15:33:48', '2025-09-17 15:34:09'),
(4, 4, 'Driver\'s License', '2000321312321', 'uploads/id_verification/front_4_1758985046.jpg', 'uploads/id_verification/back_4_1758985046.jpg', 'rejected', '', '', 4, '2025-09-27 14:58:17', '2025-09-27 14:57:26', '2025-09-27 14:58:17'),
(5, 11, 'National ID', '2000321312321', 'uploads/id_verification/front_11_1759838865.jpg', 'uploads/id_verification/back_11_1759838865.jpg', 'approved', NULL, 'nice pic', 4, '2025-10-08 13:21:15', '2025-10-07 12:07:45', '2025-10-08 13:21:15'),
(6, 20, 'Passport', '5675676534', 'uploads/id_verification/front_20_1761110239.jpg', 'uploads/id_verification/back_20_1761110239.jpg', 'approved', NULL, '', 4, '2025-10-22 05:18:01', '2025-10-22 05:17:19', '2025-10-22 05:18:01');

-- --------------------------------------------------------

--
-- Table structure for table `discount_codes`
--

CREATE TABLE `discount_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discount_codes`
--

INSERT INTO `discount_codes` (`id`, `code`, `discount_type`, `discount_value`, `is_active`, `expires_at`) VALUES
(4, 'MIKEMADZ5', 'percent', 5.00, 1, '2026-04-27 21:01:00'),
(6, 'WELCOME2', 'percent', 2.00, 1, '2025-12-25 23:43:00'),
(7, 'SAVEMORE', 'percent', 3.00, 1, '2025-10-27 21:42:00');

-- --------------------------------------------------------

--
-- Table structure for table `discount_code_usage`
--

CREATE TABLE `discount_code_usage` (
  `id` int(11) NOT NULL,
  `discount_code_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discount_code_usage`
--

INSERT INTO `discount_code_usage` (`id`, `discount_code_id`, `user_id`, `order_id`, `discount_amount`, `used_at`) VALUES
(1, 4, 3, 239, 40.00, '2025-10-09 11:54:09'),
(2, 4, 11, 245, 15.00, '2025-10-09 12:31:51'),
(4, 6, 10, 372, 370.00, '2025-10-22 23:45:07'),
(5, 6, 3, 373, 438.00, '2025-10-22 23:58:50'),
(6, 4, 10, 384, 18.50, '2025-10-26 12:52:53');

-- --------------------------------------------------------

--
-- Table structure for table `email_verification`
--

CREATE TABLE `email_verification` (
  `emailverify_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification`
--

INSERT INTO `email_verification` (`emailverify_id`, `user_id`, `otp`, `expires_at`, `verified`, `created_at`) VALUES
(5, 9, '826957', '2025-09-15 16:38:22', 1, '2025-09-15 14:28:22'),
(6, 10, '717024', '2025-09-17 17:42:23', 1, '2025-09-17 15:32:23'),
(7, 11, '669609', '2025-10-06 16:46:34', 1, '2025-10-06 14:36:34'),
(8, 12, '172028', '2025-10-06 16:56:10', 1, '2025-10-06 14:46:10'),
(9, 13, '262850', '2025-10-09 15:01:01', 1, '2025-10-09 12:51:01'),
(10, 20, '096519', '2025-10-15 14:47:18', 1, '2025-10-15 12:37:18'),
(12, 3, '702878', '2025-10-15 15:06:52', 1, '2025-10-15 12:56:52'),
(13, 21, '194650', '2025-10-15 15:07:51', 1, '2025-10-15 12:57:51'),
(15, 26, '780711', '2025-10-18 17:13:02', 1, '2025-10-18 15:03:02'),
(16, 31, '440550', '2025-10-18 17:19:11', 0, '2025-10-18 15:09:11'),
(17, 32, '318139', '2025-10-18 17:19:46', 1, '2025-10-18 15:09:46'),
(21, 33, '379184', '2025-10-19 17:32:58', 0, '2025-10-19 15:22:58'),
(22, 34, '960869', '2025-10-19 17:34:36', 1, '2025-10-19 15:24:36'),
(26, 35, '039661', '2025-10-25 13:30:49', 0, '2025-10-25 11:20:49'),
(28, 36, '948555', '2025-10-29 04:58:16', 0, '2025-10-29 03:48:16'),
(29, 37, '853272', '2025-10-29 05:02:02', 0, '2025-10-29 03:52:02'),
(33, 38, '542311', '2025-10-30 04:49:41', 0, '2025-10-30 03:39:41'),
(34, 39, '868742', '2025-10-30 04:50:37', 1, '2025-10-30 03:40:37'),
(36, 40, '702467', '2025-10-30 05:04:33', 0, '2025-10-30 03:54:33'),
(37, 41, '172201', '2025-10-30 05:05:10', 0, '2025-10-30 03:55:10');

-- --------------------------------------------------------

--
-- Table structure for table `faq_questions`
--

CREATE TABLE `faq_questions` (
  `faq_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text DEFAULT NULL,
  `answered_by` int(11) DEFAULT NULL,
  `status` enum('pending','answered','archived') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `answered_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faq_questions`
--

INSERT INTO `faq_questions` (`faq_id`, `user_id`, `question`, `answer`, `answered_by`, `status`, `created_at`, `answered_at`, `updated_at`) VALUES
(1, 3, 'Working Hours?', '7am to 12 Midnight', 4, 'answered', '2025-10-21 02:37:02', '2025-10-21 02:39:41', '2025-10-21 02:39:41'),
(2, 9, 'Paano po namin malalaman yung information ni Rider at yung location tracking niya?', 'We’ll give you the app name, transaction number, rider’s name, and plate number after we ship the products so you can track the delivery. You can access this information in your profile section. Thank you!', 4, 'answered', '2025-10-21 02:58:22', '2025-10-21 03:03:15', '2025-10-25 11:15:10');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `product_id`) VALUES
(33, 3, 1),
(34, 3, 2),
(67, 3, 4),
(30, 4, 2),
(40, 9, 4),
(59, 10, 6),
(58, 10, 10);

-- --------------------------------------------------------

--
-- Table structure for table `history_action_types`
--

CREATE TABLE `history_action_types` (
  `history_action_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `history_action_types`
--

INSERT INTO `history_action_types` (`history_action_type_id`, `name`) VALUES
(1, 'Login'),
(2, 'Logout'),
(3, 'Create User'),
(4, 'Update User'),
(5, 'Deactivate User'),
(6, 'Add Product'),
(7, 'Update Product'),
(8, 'Archive Product'),
(9, 'Update Category'),
(10, 'Update Supplier'),
(11, 'Restock Product'),
(12, 'Stock Adjustment'),
(13, 'Approve Return'),
(14, 'Reject Return'),
(15, 'Update Order Status'),
(16, 'Process Payment'),
(17, 'Issue Refund'),
(18, 'Generate Report'),
(19, 'Stock Adjustment'),
(20, 'Restocking'),
(21, 'Added Brand'),
(22, 'Added UOM'),
(23, 'Added Category'),
(24, 'Added Supplier'),
(25, 'Deleted Brand'),
(26, 'Deleted UOM'),
(27, 'Deleted Category'),
(28, 'Deleted Supplier'),
(29, 'Login'),
(30, 'Logout'),
(31, 'Product Created'),
(32, 'Product Updated'),
(33, 'Product Deleted'),
(34, 'Order Created'),
(35, 'Order Updated'),
(36, 'System Action'),
(37, 'Stock Adjustment'),
(38, 'Restocking'),
(39, 'Added Brand'),
(40, 'Added UOM'),
(41, 'Added Category'),
(42, 'Added Supplier'),
(43, 'Deleted Brand'),
(44, 'Deleted UOM'),
(45, 'Deleted Category'),
(46, 'Deleted Supplier'),
(47, 'Login'),
(48, 'Logout'),
(49, 'Product Created'),
(50, 'Product Updated'),
(51, 'Product Deleted'),
(52, 'Order Created'),
(53, 'Order Updated'),
(54, 'System Action');

-- --------------------------------------------------------

--
-- Table structure for table `history_logs`
--

CREATE TABLE `history_logs` (
  `historylog_id` int(11) NOT NULL,
  `history_action_type_id` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `history_logs`
--

INSERT INTO `history_logs` (`historylog_id`, `history_action_type_id`, `reference_id`, `reference_type`, `details`, `performed_by`, `performed_at`) VALUES
(1, 24, NULL, NULL, 'Added Supplier: Supplier Name: Marion Brix Quiling, Contact: Marion Brix Quiling', 2, '2025-09-03 20:22:48'),
(2, 28, NULL, NULL, 'Archived Supplier: Supplier ID: 4, Name: Marion Brix Quiling', 2, '2025-09-03 20:23:49'),
(3, 31, NULL, NULL, 'Added Product: Product Name: TRIMMINGS, Cost: ₱250, Markup: 10%, Selling Price: ₱275 (Stock: 0 - to be managed through inventory)', 2, '2025-09-04 14:03:55'),
(4, 31, NULL, NULL, 'Added Product: Product Name: BANGUS, Cost: ₱110, Markup: 20%, Selling Price: ₱132 (Stock: 0 - to be managed through inventory)', 2, '2025-09-04 14:14:52'),
(5, 21, NULL, NULL, 'Added Brand: Brand Name: TEST', 2, '2025-09-04 14:15:37'),
(6, 22, NULL, NULL, 'Added UOM: UOM Name: TEST', 2, '2025-09-04 14:15:52'),
(7, 25, NULL, NULL, 'Deleted Brand: Brand ID: 7, Name: TEST', 2, '2025-09-04 14:16:48'),
(8, 26, NULL, NULL, 'Deleted UOM: UOM ID: 7, Name: TEST', 2, '2025-09-04 14:16:56'),
(9, 21, NULL, NULL, 'Added Brand: Brand Name: TEST', 2, '2025-09-04 14:24:49'),
(10, 25, NULL, NULL, 'Deleted Brand: Brand ID: 8, Name: TEST', 2, '2025-09-04 14:24:52'),
(11, 22, NULL, NULL, 'Added UOM: UOM Name: TEST', 2, '2025-09-04 14:24:56'),
(12, 26, NULL, NULL, 'Deleted UOM: UOM ID: 8, Name: TEST', 2, '2025-09-04 14:24:59'),
(13, 21, NULL, NULL, 'Added Brand: Brand Name: TEST', 2, '2025-09-04 14:27:27'),
(14, 11, NULL, NULL, 'Restocking: Product ID: 2, Quantity: 21, Cost: ₱210', 2, '2025-09-04 20:58:34'),
(15, 12, NULL, NULL, 'Stock Adjustment: Product ID: 2, Type: add, Quantity: 10, Reason: Damaged Items', 2, '2025-09-04 20:58:53'),
(16, 36, NULL, NULL, 'User Permissions Updated: Updated permissions for user: admin1', 4, '2025-09-04 23:15:39'),
(17, 11, NULL, NULL, 'Restocking: Product ID: 1, Quantity: 10, Cost: ₱2220', 4, '2025-09-05 08:51:50'),
(18, 36, NULL, NULL, 'Added Product: Product Name: Marion Brix Quiling, Cost: ₱1, Markup: 1%, Selling Price: ₱1.01 (Stock: 0 - to be managed through inventory)', 4, '2025-09-05 08:57:57'),
(19, 25, NULL, NULL, 'Deleted Brand: Brand ID: 9, Name: TEST', 4, '2025-09-05 09:03:08'),
(20, 26, NULL, NULL, 'Deleted UOM: UOM ID: 4, Name: Liters', 4, '2025-09-05 09:03:15'),
(21, 26, NULL, NULL, 'Deleted UOM: UOM ID: 5, Name: Grams', 4, '2025-09-05 19:44:44'),
(22, 36, NULL, NULL, 'User Created: Username: admin2, Email: testadmin@gmail.com, Role: admin', 4, '2025-09-05 19:49:59'),
(23, 36, NULL, NULL, 'User Permissions Updated: Updated permissions for user: admin2', 4, '2025-09-06 13:41:43'),
(24, 36, NULL, NULL, 'Role Created: Created new role: inventory_admin', 4, '2025-09-06 22:48:01'),
(25, 36, NULL, NULL, 'Role Created: Created new role: sales_admin', 4, '2025-09-06 22:50:46'),
(26, 36, NULL, NULL, 'User Created: Username: inventory_admin_test, Email: inventory@gmail.com, Role: inventory_admin', 4, '2025-09-08 21:04:23'),
(27, 36, NULL, NULL, 'User Created: Username: admin3, Email: admin3@gmail.com, Role: admin', 4, '2025-09-08 21:05:16'),
(28, 36, NULL, NULL, 'User Permissions Updated: Updated permissions for user: inventory_admin_test', 4, '2025-09-08 21:25:34'),
(29, 36, NULL, NULL, 'User Created: Username: sales_admin, Email: sales@gmail.com, Role: sales_admin', 4, '2025-09-08 21:28:52'),
(30, 36, NULL, NULL, 'User Permissions Updated: Updated permissions for user: sales_admin', 4, '2025-09-08 21:29:29'),
(31, 24, NULL, NULL, 'Added Supplier: Supplier Name: TEST TEST TEST, Contact: brixu', 4, '2025-09-09 23:00:23'),
(32, 36, NULL, NULL, 'Product Added: Added new product: TEST (Cost: ₱200, Markup: 20%, Selling Price: ₱240)', 4, '2025-09-09 23:52:32'),
(33, 24, NULL, NULL, 'Added Supplier: Supplier Name: BALIWAG, Contact: JAY GALANG', 4, '2025-09-10 23:19:19'),
(34, 36, NULL, NULL, 'Restocking Status Update: Product: TRIMMINGS, Status: Received', 4, '2025-09-11 00:25:59'),
(35, 36, NULL, NULL, 'Restocking Status Update: Product: TRIMMINGS, Status: Pending', 4, '2025-09-11 00:26:09'),
(36, 36, NULL, NULL, 'Restocking Status Update: Product: TRIMMINGS, Status: Received', 4, '2025-09-11 00:26:10'),
(37, 36, NULL, NULL, 'Restocking Status Update: Product: BANGUS, Status: Received', 4, '2025-09-11 00:26:23'),
(38, 36, NULL, NULL, 'Expiration Date Update: Product: BANGUS, Expiration: 2026-01-01', 4, '2025-09-11 00:45:43'),
(39, 36, NULL, NULL, 'Expiration Date Update: Product: TRIMMINGS, Expiration: 2025-12-05', 4, '2025-09-11 00:46:02'),
(40, 36, NULL, NULL, 'UOM Created: Created new UOM: JAY', 4, '2025-09-11 10:34:40'),
(41, 36, NULL, NULL, 'UOM Deleted: Deleted UOM: JAY', 4, '2025-09-11 10:34:44'),
(42, 36, NULL, NULL, 'Profile Picture Update: Updated profile picture', 4, '2025-09-11 11:07:59'),
(43, 36, NULL, NULL, 'Profile Update: Updated profile information', 4, '2025-09-11 11:08:12'),
(44, 36, NULL, NULL, 'Settings Update: Updated theme preferences', 4, '2025-09-14 22:29:06'),
(45, 36, NULL, NULL, 'Settings Update: Updated theme preferences', 4, '2025-09-14 22:29:08'),
(46, 36, NULL, NULL, 'Settings Update: Updated theme preferences', 4, '2025-09-14 22:29:11'),
(47, 36, NULL, NULL, 'Reorder Point Updated: Product: TEST, New Reorder Point: 5', 4, '2025-09-14 22:32:59'),
(48, 36, NULL, NULL, 'Reorder Point Updated: Product: TEST, New Reorder Point: 10', 4, '2025-09-14 22:33:04'),
(49, 36, NULL, NULL, 'Profile Update: Updated profile information', 4, '2025-09-14 23:10:29'),
(50, 36, NULL, NULL, 'Profile Update: Updated profile information', 4, '2025-09-14 23:13:08'),
(51, 36, NULL, NULL, 'Profile Update: Updated profile information', 4, '2025-09-14 23:13:09'),
(52, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 172 marked as received by customer', 3, '2025-09-16 22:41:28'),
(53, 11, NULL, NULL, 'Restocking: Product: TEST, Quantity: 50, Cost: ₱7500', 4, '2025-09-16 23:09:01'),
(54, 36, NULL, NULL, 'Category Created: Created new category: test', 4, '2025-09-17 13:09:41'),
(55, 36, NULL, NULL, 'Category Deleted: Deleted category: test', 4, '2025-09-17 13:09:44'),
(56, 36, NULL, NULL, 'Product Unarchived: Unarchived product: BANGUS', 4, '2025-09-17 15:25:14'),
(57, 36, NULL, NULL, 'Product Unarchived: Unarchived product: TRIMMINGS', 4, '2025-09-17 15:25:18'),
(58, 36, NULL, NULL, 'Product Unarchived: Unarchived product: Marion Brix Quiling', 4, '2025-09-17 15:25:20'),
(59, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 173 marked as received by customer', 9, '2025-09-17 17:16:04'),
(60, 36, NULL, NULL, 'Profile Picture Update: Updated profile picture', 4, '2025-09-17 20:08:58'),
(61, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 175 marked as received by customer', 10, '2025-09-17 23:39:43'),
(62, 36, NULL, NULL, 'UOM Deleted: Deleted UOM: Pounds', 4, '2025-09-17 23:43:40'),
(63, 36, NULL, NULL, 'Product Added: Added new product: TRIMMINGS (Cost: ₱150, Markup: 20%, Selling Price: ₱180)', 4, '2025-09-17 23:45:01'),
(64, 11, NULL, NULL, 'Restocking: Product: TRIMMINGS, Quantity: 20, Cost: ₱3000', 4, '2025-09-17 23:46:07'),
(65, 36, NULL, NULL, 'Product Added: Added new product: TRIMMINGS (Cost: ₱150, Markup: 20%, Selling Price: ₱180)', 4, '2025-09-17 23:47:02'),
(66, 36, NULL, NULL, 'Category Created: Created new category: kervie', 4, '2025-09-17 23:49:34'),
(67, 36, NULL, NULL, 'Category Deleted: Deleted category: kervie', 4, '2025-09-17 23:49:38'),
(68, 36, NULL, NULL, 'UOM Deleted: Deleted UOM: Boxes', 4, '2025-09-17 23:50:11'),
(69, 36, NULL, NULL, 'UOM Created: Created new UOM: Boxes', 4, '2025-09-17 23:50:16'),
(70, 12, NULL, NULL, 'Stock Adjustment: Product: TRIMMINGS, Type: add, Quantity: 10, Reason: Manual Correction', 4, '2025-09-17 23:51:47'),
(71, 12, NULL, NULL, 'Stock Adjustment: Product: TRIMMINGS, Type: add, Quantity: 10, Reason: Other', 4, '2025-09-19 09:48:44'),
(72, 12, NULL, NULL, 'Stock Adjustment: Product: TRIMMINGS, Type: add, Quantity: 10, Reason: Quality Control', 4, '2025-09-19 09:55:56'),
(73, 11, NULL, NULL, 'Restocking: Product: TRIMMINGS, Quantity: 10, Cost: ₱1500', 4, '2025-09-19 10:36:00'),
(74, 12, NULL, NULL, 'Stock Adjustment: Product: TRIMMINGS, Type: add, Quantity: 10, Reason: Quality Control', 4, '2025-09-19 10:38:21'),
(75, 36, NULL, NULL, 'Product Added: Added new product: HIPON (Cost: ₱120, Markup: 20%, Selling Price: ₱144)', 4, '2025-09-19 11:07:36'),
(76, 11, NULL, NULL, 'Restocking: Product: HIPON, Quantity: 10, Cost: ₱1200', 4, '2025-09-19 11:08:01'),
(77, 11, NULL, NULL, 'Restocking: Product: HIPON, Quantity: 10, Cost: ₱1200', 4, '2025-09-19 11:14:50'),
(78, 36, NULL, NULL, 'Product Added: Added new product: FLANK (Cost: ₱150, Markup: 20%, Selling Price: ₱180)', 4, '2025-09-21 19:51:42'),
(79, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: HIPON', 4, '2025-09-21 20:35:38'),
(80, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Marion Brix Quiling, Product: FLANK', 4, '2025-09-21 21:43:57'),
(81, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: FLANK', 4, '2025-09-21 22:29:37'),
(82, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: TRIMMINGS', 4, '2025-09-21 22:29:46'),
(83, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 182 marked as received by customer', 3, '2025-09-22 11:30:29'),
(84, 12, NULL, NULL, 'Stock Adjustment: Product: HIPON, Type: add, Quantity: 10, Reason: Other', 4, '2025-09-22 11:57:22'),
(85, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: TEST TEST TEST, Product: HIPON', 4, '2025-09-22 12:05:33'),
(86, 36, NULL, NULL, 'Category Created: Created new category: Processed Meat', 4, '2025-09-22 12:47:59'),
(87, 36, NULL, NULL, 'Brand Created: Created new brand: Pampanga\'s Best', 4, '2025-09-22 12:48:15'),
(88, 36, NULL, NULL, 'Product Added: Added new product: Pindang (Cost: ₱180, Markup: 10%, Selling Price: ₱198)', 4, '2025-09-22 12:51:36'),
(89, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: ZAYN GOODS, Product: Pindang', 4, '2025-09-22 12:52:57'),
(90, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: BALIWAG, Product: Pindang', 4, '2025-09-22 12:53:12'),
(91, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 1, Cost: ₱100', 4, '2025-09-22 12:57:34'),
(92, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 30, Cost: ₱3000', 4, '2025-09-22 12:59:56'),
(93, 12, NULL, NULL, 'Stock Adjustment: Product: Pindang, Type: subtract, Quantity: 30, Reason: Damaged Items', 4, '2025-09-22 13:01:55'),
(94, 12, NULL, NULL, 'Stock Adjustment: Product: Pindang, Type: subtract, Quantity: 1, Reason: Damaged Items', 4, '2025-09-22 13:02:22'),
(95, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 30, Cost: ₱3000', 4, '2025-09-22 13:04:22'),
(96, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 183 marked as received by customer', 3, '2025-09-22 13:10:27'),
(97, 11, NULL, NULL, 'Restocking: Product: FLANK, Quantity: 1, Cost: ₱100', 4, '2025-09-23 17:21:21'),
(98, 11, NULL, NULL, 'Restocking: Product: FLANK, Quantity: 1, Cost: ₱100', 4, '2025-09-23 17:22:21'),
(99, 12, NULL, NULL, 'Stock Adjustment: Product: FLANK, Type: subtract, Quantity: 1, Reason: Damaged Items', 4, '2025-09-23 17:22:44'),
(100, 12, NULL, NULL, 'Stock Adjustment: Product: FLANK, Type: subtract, Quantity: 1, Reason: Damaged Items', 4, '2025-09-23 17:23:30'),
(101, 12, NULL, NULL, 'Stock Adjustment: Product: Pindang, Type: subtract, Quantity: 29, Reason: Damaged Items', 4, '2025-09-23 17:28:15'),
(102, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 2, Cost: ₱200', 4, '2025-09-23 17:30:13'),
(103, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 2, Cost: ₱200', 4, '2025-09-23 17:30:54'),
(104, 12, NULL, NULL, 'Stock Adjustment: Product: Pindang, Type: add, Quantity: 2, Reason: Other', 4, '2025-09-23 17:34:21'),
(105, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 2, Cost: ₱200', 4, '2025-09-23 17:57:34'),
(106, 12, NULL, NULL, 'Stock Adjustment: Product: Pindang, Type: subtract, Quantity: 1, Reason: Damaged Items', 4, '2025-09-23 17:58:02'),
(107, 12, NULL, NULL, 'Stock Adjustment: Product: Pindang, Type: subtract, Quantity: 1, Reason: Manual Correction', 4, '2025-09-23 17:58:15'),
(108, 12, NULL, NULL, 'Stock Adjustment: Product: Pindang, Type: subtract, Quantity: 2, Reason: Damaged Items', 4, '2025-09-23 17:58:37'),
(109, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 1, Cost: ₱100', 4, '2025-09-23 22:08:26'),
(110, 36, NULL, NULL, 'Edited Supplier: Supplier ID: 1, Name: SGB Goods, Contact: ', 4, '2025-09-24 16:53:46'),
(111, 36, NULL, NULL, 'Edited Supplier: Supplier ID: 3, Name: ZAYN GOODS, Contact: ', 4, '2025-09-24 16:53:49'),
(112, 36, NULL, NULL, 'Edited Supplier: Supplier ID: 2, Name: SUPPLIER TEST, Contact: ', 4, '2025-09-24 16:53:54'),
(113, 28, NULL, NULL, 'Deleted Supplier: Supplier ID: 2, Name: SUPPLIER TEST', 4, '2025-09-24 16:54:10'),
(114, 36, NULL, NULL, 'Product Added: Added new product: Jowls (Markup Value: ₱8)', 4, '2025-09-24 17:27:15'),
(115, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: ZAYN GOODS, Product: Jowls', 4, '2025-09-24 17:27:53'),
(116, 11, NULL, NULL, 'Restocking: Product: Jowls, Quantity: 10, Cost: ₱1000', 4, '2025-09-24 17:28:42'),
(117, 36, NULL, NULL, 'Product Unarchived: Unarchived product: BANGUS', 4, '2025-09-25 23:22:45'),
(118, 36, NULL, NULL, 'Product Unarchived: Unarchived product: FLANK', 4, '2025-09-25 23:23:21'),
(119, 36, NULL, NULL, 'Edited Product: Product ID: 8, Name: FLANK, Cost: ₱150.00, Markup: 20.00%, Selling Price: ₱180', 4, '2025-09-25 23:23:27'),
(120, 36, NULL, NULL, 'Product Unarchived: Unarchived product: TRIMMINGS', 4, '2025-09-25 23:26:41'),
(121, 36, NULL, NULL, 'Product Unarchived: Unarchived product: FLANK', 4, '2025-09-25 23:35:34'),
(122, 36, NULL, NULL, 'Product Unarchived: Unarchived product: FLANK', 4, '2025-09-25 23:51:57'),
(123, 36, NULL, NULL, 'Settings Update: Updated theme preferences', 4, '2025-09-26 20:40:45'),
(124, 36, NULL, NULL, 'Settings Update: Updated theme preferences', 4, '2025-09-26 20:40:47'),
(125, 36, NULL, NULL, 'Settings Update: Updated theme preferences', 4, '2025-09-26 20:40:50'),
(126, 36, NULL, NULL, 'Settings Update: Updated notification preferences', 4, '2025-09-26 20:40:53'),
(127, 36, NULL, NULL, 'Settings Update: Updated notification preferences', 4, '2025-09-26 20:40:55'),
(128, 36, NULL, NULL, 'Settings Update: Updated notification preferences', 4, '2025-09-26 20:41:00'),
(129, 36, NULL, NULL, 'Settings Update: Updated notification preferences', 4, '2025-09-26 20:41:02'),
(130, 36, NULL, NULL, 'Settings Update: Updated notification preferences', 4, '2025-09-26 20:41:03'),
(131, 36, NULL, NULL, 'Product Added: Added new product: Breast (Markup Value: ₱10)', 4, '2025-09-27 23:29:46'),
(132, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: BALIWAG, Product: Breast', 4, '2025-09-27 23:30:41'),
(133, 11, NULL, NULL, 'Restocking: Product: Breast, Quantity: 9, Cost: ₱1350', 4, '2025-09-27 23:30:59'),
(134, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 190 marked as received by customer', 3, '2025-10-06 23:18:37'),
(135, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 195 marked as received by customer', 3, '2025-10-06 23:21:15'),
(136, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 194 marked as received by customer', 3, '2025-10-06 23:24:56'),
(137, 11, NULL, NULL, 'Restocking: Product: FLANK, Quantity: 100, Cost: ₱10000', 4, '2025-10-07 10:46:47'),
(138, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Marion Brix Quiling, Product: TRIMMINGS', 4, '2025-10-07 10:47:18'),
(139, 11, NULL, NULL, 'Restocking: Product: TRIMMINGS, Quantity: 50, Cost: ₱5000', 4, '2025-10-07 10:47:34'),
(140, 36, NULL, NULL, 'Stock Restored: Order #206 cancelled - stock restored for 1 products', 4, '2025-10-07 12:16:38'),
(141, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 2.5, Cost: ₱250', 4, '2025-10-07 12:41:54'),
(142, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 3.5, Cost: ₱350', 4, '2025-10-07 12:42:10'),
(143, 11, NULL, NULL, 'Restocking: Product: Pindang, Quantity: 2.5, Cost: ₱175', 4, '2025-10-07 13:10:48'),
(144, 36, NULL, NULL, 'Product Added: Added new product: SCRAP (Markup Value: ₱5)', 4, '2025-10-07 13:24:10'),
(145, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: SCRAP', 4, '2025-10-07 13:27:48'),
(146, 11, NULL, NULL, 'Restocking: Product: SCRAP, Quantity: 2.5, Cost: ₱25', 4, '2025-10-07 13:28:21'),
(147, 11, NULL, NULL, 'Restocking: Product: SCRAP, Quantity: 3.5, Cost: ₱52.5', 4, '2025-10-07 13:29:18'),
(148, 36, NULL, NULL, 'Product Added: Added new product: KASIM (Markup Value: ₱10)', 4, '2025-10-08 12:05:17'),
(149, 36, NULL, NULL, 'Product Unarchived: Unarchived product: SCRAP', 4, '2025-10-08 12:14:40'),
(150, 36, NULL, NULL, 'Product Unarchived: Unarchived product: Breast', 4, '2025-10-08 12:14:42'),
(151, 36, NULL, NULL, 'Product Unarchived: Unarchived product: Jowls', 4, '2025-10-08 12:14:44'),
(152, 36, NULL, NULL, 'Product Unarchived: Unarchived product: Pindang', 4, '2025-10-08 12:14:47'),
(153, 36, NULL, NULL, 'Product Unarchived: Unarchived product: FLANK', 4, '2025-10-08 12:14:49'),
(154, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: KASIM', 4, '2025-10-08 12:15:20'),
(155, 11, NULL, NULL, 'Restocking: Product: KASIM, Quantity: 10, Cost: ₱100', 4, '2025-10-08 12:15:57'),
(156, 11, NULL, NULL, 'Restocking: Product: KASIM, Quantity: 10, Cost: ₱1000', 4, '2025-10-08 12:18:27'),
(157, 36, NULL, NULL, 'Product Added: Added new product: Beef Fats (Markup Value: ₱50)', 4, '2025-10-08 13:52:59'),
(158, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Marion Brix Quiling, Product: Beef Fats', 4, '2025-10-08 13:55:47'),
(159, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 10.5, Cost: ₱1102.5', 4, '2025-10-08 13:56:26'),
(160, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 1, Cost: ₱100', 4, '2025-10-08 18:20:02'),
(161, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 193 marked as received by customer', 3, '2025-10-08 18:40:05'),
(162, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 221 marked as received by customer', 3, '2025-10-08 18:57:45'),
(163, 36, NULL, NULL, 'Stock Restored: Order #223 cancelled - stock restored for 1 products', 4, '2025-10-08 19:02:01'),
(164, 36, NULL, NULL, 'Stock Restored: Order #220 cancelled - stock restored for 1 products', 4, '2025-10-08 19:06:40'),
(165, 36, NULL, NULL, 'Stock Restored: Order #219 cancelled - stock restored for 1 products', 4, '2025-10-08 19:06:44'),
(166, 36, NULL, NULL, 'Stock Restored: Order #218 cancelled - stock restored for 1 products', 4, '2025-10-08 19:06:49'),
(167, 36, NULL, NULL, 'Stock Restored: Order #217 cancelled - stock restored for 1 products', 4, '2025-10-08 19:06:51'),
(168, 36, NULL, NULL, 'Stock Restored: Order #216 cancelled - stock restored for 1 products', 4, '2025-10-08 19:14:58'),
(169, 36, NULL, NULL, 'Stock Restored: Order #215 cancelled - stock restored for 1 products', 4, '2025-10-08 19:15:02'),
(170, 36, NULL, NULL, 'Stock Restored: Order #214 cancelled - stock restored for 1 products', 4, '2025-10-08 19:15:04'),
(171, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 222 marked as received by customer', 3, '2025-10-08 19:15:40'),
(172, 36, NULL, NULL, 'Edited Product: Product ID: 9, Name: Pindang, Cost: ₱180.00, Markup: 5.56%, Markup Amount: ₱10.008', 4, '2025-10-08 19:19:19'),
(173, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 10, Cost: ₱1000', 4, '2025-10-08 19:22:04'),
(174, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 192 marked as received by customer', 3, '2025-10-08 19:40:45'),
(175, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 50, Cost: ₱10000', 4, '2025-10-08 22:52:29'),
(176, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: BALIWAG, Product: Beef Fats', 4, '2025-10-08 23:13:42'),
(177, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 2, Cost: ₱200', 4, '2025-10-08 23:14:01'),
(178, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 10, Cost: ₱3500', 4, '2025-10-08 23:15:50'),
(179, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: MIKEMADZ10', 4, '2025-10-09 11:40:13'),
(180, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 1, Cost: ₱100', 4, '2025-10-09 12:06:27'),
(181, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 1, Cost: ₱500', 4, '2025-10-09 12:28:37'),
(182, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 10, Cost: ₱1000', 4, '2025-10-09 12:31:04'),
(183, 36, NULL, NULL, 'Discount Code Created: Created discount code: TEST', 4, '2025-10-09 12:35:12'),
(184, 36, NULL, NULL, 'User Created: Username: inventory_kervie, Email: inventorykervie@gmail.com, Role: inventory_admin', 4, '2025-10-09 21:32:35'),
(185, 36, NULL, NULL, 'User Permissions Updated: Updated permissions for user: inventory_kervie', 4, '2025-10-09 21:38:34'),
(186, 36, NULL, NULL, 'User Created: Username: inventory_kervie2, Email: inventory_kervie@gmail.com, Role: inventory_admin', 4, '2025-10-09 21:42:10'),
(187, 36, NULL, NULL, 'Product Added: Added new product: Chicken Neck (Markup Value: ₱10)', 4, '2025-10-10 14:12:16'),
(188, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: ZAYN GOODS, Product: Chicken Neck', 4, '2025-10-10 14:12:51'),
(189, 11, NULL, NULL, 'Restocking: Product: Chicken Neck, Quantity: 10, Cost: ₱1000', 4, '2025-10-10 14:13:18'),
(190, 11, NULL, NULL, 'Restocking: Product: Chicken Neck, Quantity: 10, Cost: ₱5000', 4, '2025-10-10 14:14:13'),
(191, 11, NULL, NULL, 'Restocking: Product: Chicken Neck, Quantity: 15, Cost: ₱13500', 4, '2025-10-10 15:01:41'),
(192, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 35, Cost: ₱17500', 4, '2025-10-10 15:15:28'),
(193, 36, NULL, NULL, 'Brand Archived: Archived brand: XYZ INC.', 4, '2025-10-10 19:12:53'),
(194, 36, NULL, NULL, 'Brand Archived: Archived brand: Zayn Bangus', 4, '2025-10-10 19:13:04'),
(195, 36, NULL, NULL, 'Brand Archived: Archived brand: GOODS GOODS', 4, '2025-10-10 19:13:08'),
(196, 36, NULL, NULL, 'Brand Archived: Archived brand: ANDOKS ', 4, '2025-10-10 19:13:10'),
(197, 36, NULL, NULL, 'Brand Archived: Archived brand: Pampanga\'s Best', 4, '2025-10-10 19:13:12'),
(198, 36, NULL, NULL, 'Brand Archived: Archived brand: San Gabriel Beef', 4, '2025-10-10 19:13:15'),
(199, 36, NULL, NULL, 'Brand Restored: Restored brand: San Gabriel Beef', 4, '2025-10-10 19:19:42'),
(200, 36, NULL, NULL, 'Brand Archived: Archived brand: San Gabriel Beef', 4, '2025-10-10 19:19:46'),
(201, 36, NULL, NULL, 'Brand Created: Created new brand: Tyson', 4, '2025-10-10 19:20:38'),
(202, 36, NULL, NULL, 'Category Created: Created new category: TRY', 4, '2025-10-10 19:20:51'),
(203, 36, NULL, NULL, 'Category Deleted: Deleted category: TRY', 4, '2025-10-10 19:20:57'),
(204, 36, NULL, NULL, 'Product Added: Added new product: Pork Ribs (Markup Value: ₱30)', 4, '2025-10-10 19:24:06'),
(205, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: Pork Ribs', 4, '2025-10-10 19:24:46'),
(206, 11, NULL, NULL, 'Restocking: Product: Pork Ribs, Quantity: 35, Cost: ₱19425', 4, '2025-10-10 19:25:05'),
(207, 36, NULL, NULL, 'Brand Created: Created new brand: Mega', 4, '2025-10-10 19:26:24'),
(208, 11, NULL, NULL, 'Restocking: Product: Pork Ribs, Quantity: 13, Cost: ₱10400', 4, '2025-10-10 19:26:49'),
(209, 11, NULL, NULL, 'Restocking: Product: Pork Ribs, Quantity: 30, Cost: ₱30000', 4, '2025-10-12 12:52:34'),
(210, 36, NULL, NULL, 'Product Added: Added new product: Forequarter (Markup Value: ₱50)', 4, '2025-10-12 13:23:13'),
(211, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: BALIWAG, Product: Forequarter', 4, '2025-10-12 13:23:44'),
(212, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 1, Cost: ₱100', 4, '2025-10-12 13:24:06'),
(213, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 1, Cost: ₱500', 4, '2025-10-12 13:59:48'),
(214, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 1, Cost: ₱500', 4, '2025-10-12 14:11:05'),
(215, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 2, Cost: ₱1200', 4, '2025-10-12 14:11:22'),
(216, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 1, Cost: ₱100', 4, '2025-10-12 14:19:35'),
(217, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 1, Cost: ₱100', 4, '2025-10-12 14:19:43'),
(218, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 2, Cost: ₱200', 4, '2025-10-12 14:20:40'),
(219, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 2, Cost: ₱2', 4, '2025-10-12 14:22:39'),
(220, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 1, Cost: ₱1', 4, '2025-10-12 14:23:14'),
(221, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 2.5, Cost: ₱250', 4, '2025-10-12 14:25:44'),
(222, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 5.5, Cost: ₱2750', 4, '2025-10-12 14:26:01'),
(223, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 302 marked as received by customer', 3, '2025-10-12 16:02:19'),
(224, 36, NULL, NULL, 'User Deactivated: Username: inventory_kervie2, Email: inventory_kervie@gmail.com', 4, '2025-10-12 17:12:05'),
(225, 36, NULL, NULL, 'User Created: Username: salesadmin_test, Email: salesadmin_test@gmail.com, Role: sales_admin', 4, '2025-10-12 17:13:01'),
(226, 36, NULL, NULL, 'User Created: Username: admin5, Email: admin5@gmail.com, Role: inventory_admin', 4, '2025-10-12 17:16:10'),
(227, 36, NULL, NULL, 'Role Created: Created new role: Monitoring', 4, '2025-10-12 17:28:00'),
(228, 36, NULL, NULL, 'User Created: Username: monitoring_admin, Email: monitoring_admin@gmail.com, Role: Monitoring', 4, '2025-10-12 17:28:37'),
(229, 36, NULL, NULL, 'User Access Updated: Updated access for user: inventory_kervie - Sections: ', 4, '2025-10-12 19:05:27'),
(230, 36, NULL, NULL, 'User Access Updated: Updated access for user: inventory_kervie - Sections: Inventory Management', 4, '2025-10-12 19:14:30'),
(231, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 1, Cost: ₱50', 4, '2025-10-12 19:25:00'),
(232, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 2.5, Cost: ₱250', 4, '2025-10-12 19:27:08'),
(233, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 10, Cost: ₱1000', 4, '2025-10-12 22:58:39'),
(234, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 300 marked as received by customer', 3, '2025-10-12 23:39:22'),
(235, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 249 marked as received by customer', 3, '2025-10-12 23:39:36'),
(236, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 245 marked as received by customer', 11, '2025-10-12 23:42:16'),
(237, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 229 marked as received by customer', 11, '2025-10-12 23:42:19'),
(238, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 228 marked as received by customer', 11, '2025-10-12 23:50:28'),
(239, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 309 marked as received by customer', 11, '2025-10-12 23:55:31'),
(240, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 227 marked as received by customer', 11, '2025-10-12 23:55:35'),
(241, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 226 marked as received by customer', 11, '2025-10-12 23:55:42'),
(242, 36, NULL, NULL, 'User Deactivated: Username: admin1, Email: admin@example.com', 4, '2025-10-13 14:17:30'),
(243, 36, NULL, NULL, 'User Deactivated: Username: admin2, Email: testadmin@gmail.com', 4, '2025-10-13 14:17:44'),
(244, 36, NULL, NULL, 'User Deactivated: Username: admin3, Email: admin3@gmail.com', 4, '2025-10-13 14:17:55'),
(245, 36, NULL, NULL, 'User Reactivated: Username: admin3, Email: admin3@gmail.com', 4, '2025-10-13 14:18:07'),
(246, 36, NULL, NULL, 'User Deactivated: Username: admin3, Email: admin3@gmail.com', 4, '2025-10-13 14:18:17'),
(247, 36, NULL, NULL, 'User Created: Username: monitoring_admin2, Email: monitoring_admin2@gmail.com, Role: Monitoring', 4, '2025-10-13 14:21:21'),
(248, 36, NULL, NULL, 'GCash Settings Update: Updated GCash payment settings', 4, '2025-10-13 15:10:37'),
(249, 36, NULL, NULL, 'GCash Settings Update: Updated GCash payment settings', 4, '2025-10-13 15:14:41'),
(250, 36, NULL, NULL, 'GCash Settings Update: Updated GCash payment settings', 4, '2025-10-13 15:18:51'),
(251, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 50, Cost: ₱50', 4, '2025-10-13 15:52:49'),
(252, 36, NULL, NULL, 'User Deactivated: Username: inventory_admin_test, Email: inventory@gmail.com', 4, '2025-10-15 21:14:22'),
(253, 36, NULL, NULL, 'User Deactivated: Username: admin5, Email: admin5@gmail.com', 4, '2025-10-15 21:14:35'),
(254, 36, NULL, NULL, 'User Deactivated: Username: sales_admin, Email: sales@gmail.com', 4, '2025-10-15 21:14:44'),
(255, 36, NULL, NULL, 'User Deactivated: Username: monitoring_admin2, Email: monitoring_admin2@gmail.com', 4, '2025-10-15 21:15:07'),
(256, 36, NULL, NULL, 'User Reactivated: Username: admin1, Email: admin@example.com', 4, '2025-10-15 21:30:50'),
(257, 36, NULL, NULL, 'User Deactivated: Username: admin1, Email: admin@example.com', 4, '2025-10-15 21:30:52'),
(258, 36, NULL, NULL, 'User Access Updated: Updated access for user: inventory_kervie - Sections: ', 4, '2025-10-15 21:46:43'),
(259, 36, NULL, NULL, 'User Deactivated: Username: inventory_kervie, Email: inventorykervie@gmail.com', 4, '2025-10-15 22:09:25'),
(260, 36, NULL, NULL, 'User Deactivated: Username: salesadmin_test, Email: salesadmin_test@gmail.com', 4, '2025-10-15 22:09:29'),
(261, 36, NULL, NULL, 'User Deactivated: Username: monitoring_admin, Email: monitoring_admin@gmail.com', 4, '2025-10-15 22:09:31'),
(262, 36, NULL, NULL, 'Role Deleted: Deleted role: Monitoring', 4, '2025-10-15 22:19:25'),
(263, 36, NULL, NULL, 'Role Deleted: Deleted role: sales_admin', 4, '2025-10-15 22:20:24'),
(264, 36, NULL, NULL, 'Role Deleted: Deleted role: inventory_admin', 4, '2025-10-15 22:20:29'),
(265, 36, NULL, NULL, 'Role Created: Created new role: inventory_admin', 4, '2025-10-15 22:27:54'),
(266, 36, NULL, NULL, 'Role Permissions Assigned: Assigned permissions to role: inventory_admin - Sections: Inventory Management', 4, '2025-10-15 22:28:10'),
(267, 36, NULL, NULL, 'Role Deleted: Deleted role: inventory_admin', 4, '2025-10-15 22:30:32'),
(268, 36, NULL, NULL, 'Role Created: Created new role: inventory_admin', 4, '2025-10-15 22:30:40'),
(269, 36, NULL, NULL, 'Role Deleted: Deleted role: inventory_admin', 4, '2025-10-15 22:45:38'),
(270, 36, NULL, NULL, 'Role Created: Created new role: inventory_admin', 4, '2025-10-15 22:45:47'),
(271, 36, NULL, NULL, 'Role Permissions Assigned: Assigned permissions to role: inventory_admin - Sections: Inventory Management', 4, '2025-10-15 22:45:51'),
(272, 36, NULL, NULL, 'User Created: Username: Gian_Inventory, Email: giancarmen@gmail.com, Role: inventory_admin', 4, '2025-10-15 22:51:03'),
(273, 36, NULL, NULL, 'User Created: Username: gian_inventory1, Email: giangiangian@gmail.com, Role: inventory_admin', 4, '2025-10-15 23:17:15'),
(274, 36, NULL, NULL, 'Profile Picture Update: Updated profile picture', 23, '2025-10-15 23:36:28'),
(275, 36, NULL, NULL, 'Profile Update: Updated profile information', 23, '2025-10-15 23:37:25'),
(276, 36, NULL, NULL, 'User Deactivated: Username: Gian_Inventory, Email: giancarmen@gmail.com', 4, '2025-10-15 23:38:48'),
(277, 36, NULL, NULL, 'Role Created: Created new role: sales_admin', 4, '2025-10-15 23:39:04'),
(278, 36, NULL, NULL, 'Role Permissions Assigned: Assigned permissions to role: sales_admin - Sections: Sales', 4, '2025-10-15 23:39:07'),
(279, 36, NULL, NULL, 'User Created: Username: david_sales, Email: davidsales@gmail.com, Role: sales_admin', 4, '2025-10-15 23:39:31'),
(280, 36, NULL, NULL, 'Profile Update: Updated profile information', 24, '2025-10-15 23:54:59'),
(281, 36, NULL, NULL, 'Profile Picture Update: Updated profile picture', 24, '2025-10-15 23:57:39'),
(282, 36, NULL, NULL, 'Profile Update: Updated profile information', 24, '2025-10-15 23:57:41'),
(283, 36, NULL, NULL, 'Role Created: Created new role: monitoring_admin', 4, '2025-10-15 23:59:36'),
(284, 36, NULL, NULL, 'Role Permissions Assigned: Assigned permissions to role: monitoring_admin - Sections: Analytics', 4, '2025-10-15 23:59:58'),
(285, 36, NULL, NULL, 'User Created: Username: kervie_monitoring, Email: kerviemoni@gmail.com, Role: monitoring_admin', 4, '2025-10-16 11:42:53'),
(286, 36, NULL, NULL, 'Profile Picture Update: Updated profile picture', 25, '2025-10-16 11:54:10'),
(287, 36, NULL, NULL, 'Password Change: Changed password', 25, '2025-10-16 11:54:43'),
(288, 36, NULL, NULL, 'Role Permissions Assigned: Assigned permissions to role: inventory_admin - Sections: Inventory Management, Product Management', 4, '2025-10-16 12:15:11'),
(289, 36, NULL, NULL, 'Role Permissions Assigned: Assigned permissions to role: inventory_admin - Sections: Inventory Management, Product Management', 4, '2025-10-16 12:15:22'),
(290, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Marion Brix Quiling, Product: Forequarter', 4, '2025-10-16 14:02:38'),
(291, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 10, Cost: ₱1500', 4, '2025-10-16 14:02:54'),
(292, 12, NULL, NULL, 'Stock Adjustment: Product: Forequarter, Type: subtract, Quantity: 10, Reason: Supplier Return', 4, '2025-10-16 14:06:59'),
(293, 12, NULL, NULL, 'Stock Adjustment: Product: Forequarter, Type: subtract, Quantity: 5, Reason: Damaged Items', 4, '2025-10-16 14:16:37'),
(294, 36, NULL, NULL, 'Brand Created: Created new brand: Beefies', 4, '2025-10-16 14:19:14'),
(295, 12, NULL, NULL, 'Stock Adjustment: Product: Forequarter, Type: subtract, Quantity: 10, Reason: Supplier Return', 4, '2025-10-16 21:38:17'),
(296, 12, NULL, NULL, 'Stock Adjustment: Product: Forequarter, Type: subtract, Quantity: 10, Reason: Supplier Return', 4, '2025-10-16 21:50:52'),
(297, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 50, Cost: ₱22500', 4, '2025-10-16 23:33:51'),
(298, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 20, Cost: ₱6000', 4, '2025-10-16 23:34:22'),
(299, 12, NULL, NULL, 'Stock Adjustment: Product: Forequarter, Type: subtract, Quantity: 10, Reason: Damaged Items', 4, '2025-10-16 23:35:55'),
(300, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 50, Cost: ₱15000', 4, '2025-10-17 08:51:45'),
(301, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 30, Cost: ₱16500', 4, '2025-10-17 08:52:33'),
(302, 36, NULL, NULL, 'Reorder Points Recalculated: Recalculated 3 brand-product reorder points', 4, '2025-10-17 09:14:14'),
(303, 36, NULL, NULL, 'Reorder Points Recalculated: Recalculated 3 brand-product reorder points', 4, '2025-10-17 09:14:33'),
(304, 36, NULL, NULL, 'Reorder Points Recalculated: Recalculated 3 brand-product reorder points', 4, '2025-10-17 09:18:29'),
(305, 36, NULL, NULL, 'Reorder Points Recalculated: Recalculated 3 brand-product reorder points', 4, '2025-10-17 09:18:37'),
(306, 36, NULL, NULL, 'Reorder Points Recalculated: Recalculated 3 brand-product reorder points', 4, '2025-10-17 10:24:44'),
(307, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 244 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(308, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 243 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(309, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 241 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(310, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 240 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(311, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 239 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(312, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 238 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(313, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 237 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(314, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 236 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(315, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 235 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(316, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 234 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(317, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 233 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(318, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 232 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(319, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 231 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(320, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 230 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(321, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 185 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(322, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 184 automatically confirmed after 48 hours', 3, '2025-10-17 10:52:43'),
(323, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 312 automatically confirmed after 48 hours', 3, '2025-10-17 10:53:23'),
(324, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 174 automatically confirmed after 48 hours', 9, '2025-10-17 11:11:48'),
(325, 36, NULL, NULL, 'Category Created: Created new category: Processed Foods', 4, '2025-10-18 11:28:50'),
(326, 36, NULL, NULL, 'Category Deleted: Deleted category: Processed Foods', 4, '2025-10-18 12:42:13'),
(327, 36, NULL, NULL, 'Category Archived: Archived category: Processed Meat', 4, '2025-10-18 15:22:24'),
(328, 36, NULL, NULL, 'Category Unarchived: Unarchived category: Processed Meat', 4, '2025-10-18 15:22:27'),
(329, 36, NULL, NULL, 'Category Archived: Archived category: SEA FOODS', 4, '2025-10-18 15:26:19'),
(330, 36, NULL, NULL, 'Category Unarchived: Unarchived category: SEA FOODS', 4, '2025-10-18 15:26:22'),
(331, 36, NULL, NULL, 'Category Archived: Archived category: PORK', 4, '2025-10-18 15:26:26'),
(332, 36, NULL, NULL, 'Category Unarchived: Unarchived category: PORK', 4, '2025-10-18 15:26:29'),
(333, 12, NULL, NULL, 'Stock Adjustment: Product: Forequarter, Type: subtract, Quantity: 10, Reason: Supplier Return', 4, '2025-10-18 15:29:09'),
(334, 36, NULL, NULL, 'Automatic Expiration: Product: TRIMMINGS, Batch: TRIMMINGS-BATCH001, Quantity: 20.0, Expired: 2025-09-10', 0, '2025-10-18 23:59:33'),
(335, 36, NULL, NULL, 'Automatic Expiration: Product: BANGUS, Batch: BANGUS-BATCH001, Quantity: 20.0, Expired: 2025-09-10', 0, '2025-10-18 23:59:33'),
(336, 36, NULL, NULL, 'Automatic Expiration: Product: BANGUS, Batch: BANGUS-BATCH002, Quantity: 15.0, Expired: 2025-09-20', 0, '2025-10-18 23:59:33'),
(337, 36, NULL, NULL, 'Automatic Expiration: Product: Test Expired Product, Batch: B18-20251018-001, Quantity: 50.0, Expired: 2025-10-17', 0, '2025-10-18 23:59:33'),
(338, 36, NULL, NULL, 'Automatic Expiration: Product: Test Expired Product, Batch: B19-20251018-001, Quantity: 50.0, Expired: 2025-10-17', 0, '2025-10-18 23:59:40'),
(339, 36, NULL, NULL, 'Reorder Points Synced: Synchronized 3 reorder points between tables', 4, '2025-10-19 00:48:30'),
(340, 36, NULL, NULL, 'Product Added: Added new product: Beef Sirloin (Markup Value: ₱70)', 4, '2025-10-19 13:12:51'),
(341, 24, NULL, NULL, 'Added Supplier: Supplier Name: Davidson Frozen, Contact: Davidson Mosqueda', 4, '2025-10-19 13:14:44'),
(342, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Davidson Frozen, Product: Beef Sirloin', 4, '2025-10-19 13:14:57'),
(343, 36, NULL, NULL, 'Brand Created: Created new brand: Foremost', 4, '2025-10-19 13:15:20'),
(344, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 54, Cost: ₱27000', 4, '2025-10-19 13:15:46'),
(345, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 1, Cost: ₱600', 4, '2025-10-19 13:33:54'),
(346, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 10, Cost: ₱5000', 4, '2025-10-19 13:37:01'),
(347, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 550, Cost: ₱1100', 4, '2025-10-19 13:37:23'),
(348, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 10, Cost: ₱5000', 4, '2025-10-19 13:38:56'),
(349, 12, NULL, NULL, 'Stock Adjustment: Product: Beef Sirloin, Type: subtract, Quantity: 200, Reason: Supplier Return', 4, '2025-10-19 13:40:00'),
(350, 12, NULL, NULL, 'Stock Adjustment: Product: Beef Sirloin, Type: subtract, Quantity: 100, Reason: Supplier Return', 4, '2025-10-19 13:40:44'),
(351, 12, NULL, NULL, 'Stock Adjustment: Product: Beef Sirloin, Type: subtract, Quantity: 100, Reason: Supplier Return', 4, '2025-10-19 13:41:10'),
(352, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 1, Cost: ₱570', 4, '2025-10-19 13:41:49'),
(353, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 10, Cost: ₱7000', 4, '2025-10-19 13:50:40'),
(354, 11, NULL, NULL, 'Restocking: Product: Forequarter, Quantity: 2, Cost: ₱1000', 4, '2025-10-19 14:16:30'),
(355, 36, NULL, NULL, 'Product Added: Added new product: Chicken Fillet (Markup Value: ₱30)', 4, '2025-10-19 17:23:22'),
(356, 36, NULL, NULL, 'Product Added: Added new product: Chicken Wings (Markup Value: ₱40)', 4, '2025-10-19 17:24:16'),
(357, 36, NULL, NULL, 'Product Added: Added new product: Chicken Skin (Markup Value: ₱50)', 4, '2025-10-19 17:25:58'),
(358, 36, NULL, NULL, 'Product Added: Added new product: Beef Tapa (Markup Value: ₱70)', 4, '2025-10-19 17:27:41'),
(359, 36, NULL, NULL, 'Product Added: Added new product: Beef Shank (Markup Value: ₱60)', 4, '2025-10-19 17:28:33'),
(360, 36, NULL, NULL, 'Product Added: Added new product: Pork Belly (Markup Value: ₱30)', 4, '2025-10-19 17:31:02'),
(361, 36, NULL, NULL, 'Product Added: Added new product: Pork Adobo Cut (Markup Value: ₱70)', 4, '2025-10-19 17:32:22'),
(362, 36, NULL, NULL, 'Product Added: Added new product: Ground Pork (Markup Value: ₱50)', 4, '2025-10-19 17:33:53'),
(363, 36, NULL, NULL, 'Product Added: Added new product: Fiesta Ham (Markup Value: ₱70)', 4, '2025-10-19 17:37:25'),
(364, 36, NULL, NULL, 'Product Added: Added new product: Tocino (Markup Value: ₱30)', 4, '2025-10-19 17:39:02'),
(365, 36, NULL, NULL, 'Product Added: Added new product: Fishball (Markup Value: ₱10)', 4, '2025-10-19 17:42:41'),
(366, 36, NULL, NULL, 'Product Added: Added new product: Tempura (Markup Value: ₱30)', 4, '2025-10-19 17:43:44'),
(367, 36, NULL, NULL, 'Brand Created: Created new brand: King\'s', 4, '2025-10-19 17:44:15'),
(368, 36, NULL, NULL, 'Brand Created: Created new brand: Sea World', 4, '2025-10-19 17:44:31'),
(369, 36, NULL, NULL, 'Brand Created: Created new brand: Magnolia', 4, '2025-10-19 17:44:44'),
(370, 36, NULL, NULL, 'Brand Created: Created new brand: Bounty Fresh', 4, '2025-10-19 17:44:55'),
(371, 36, NULL, NULL, 'Brand Created: Created new brand: Monterey', 4, '2025-10-19 17:45:10'),
(372, 36, NULL, NULL, 'Brand Created: Created new brand: CDO', 4, '2025-10-19 17:46:32'),
(373, 36, NULL, NULL, 'Brand Created: Created new brand: Unbranded', 4, '2025-10-19 17:46:54'),
(374, 24, NULL, NULL, 'Added Supplier: Supplier Name: Andoks, Contact: Kali', 4, '2025-10-19 19:31:56'),
(375, 24, NULL, NULL, 'Added Supplier: Supplier Name: Jay Inc., Contact: JhayJhay', 4, '2025-10-19 19:34:00'),
(376, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Andoks, Product: Chicken Fillet', 4, '2025-10-19 19:34:25'),
(377, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: BALIWAG, Product: Chicken Wings', 4, '2025-10-19 19:34:35'),
(378, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Jay Inc., Product: Ground Pork', 4, '2025-10-19 19:34:42'),
(379, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Jay Inc., Product: Pork Adobo Cut', 4, '2025-10-19 19:34:50'),
(380, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Jay Inc., Product: Pork Belly', 4, '2025-10-19 19:34:55'),
(381, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: ZAYN GOODS, Product: Tocino', 4, '2025-10-19 19:35:12'),
(382, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Davidson Frozen, Product: Tempura', 4, '2025-10-19 19:35:21'),
(383, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Davidson Frozen, Product: Beef Shank', 4, '2025-10-19 19:35:27'),
(384, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Andoks, Product: Beef Sirloin', 4, '2025-10-19 19:35:34'),
(385, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Andoks, Product: Beef Tapa', 4, '2025-10-19 19:35:37'),
(386, 36, NULL, NULL, 'Archived Supplier: Supplier ID: 6, Name: TEST TEST TEST', 4, '2025-10-19 19:35:45'),
(387, 24, NULL, NULL, 'Added Supplier: Supplier Name: Cheraine Goods, Contact: Kervie Kay', 4, '2025-10-19 19:36:50'),
(388, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Cheraine Goods, Product: Fiesta Ham', 4, '2025-10-19 19:37:09'),
(389, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Cheraine Goods, Product: Fishball', 4, '2025-10-19 19:37:24'),
(390, 11, NULL, NULL, 'Restocking: Product: Beef Shank, Quantity: 5, Cost: ₱1280', 4, '2025-10-19 19:39:11'),
(391, 11, NULL, NULL, 'Restocking: Product: Beef Tapa, Quantity: 9, Cost: ₱2970', 4, '2025-10-19 19:39:32'),
(392, 11, NULL, NULL, 'Restocking: Product: Chicken Fillet, Quantity: 7, Cost: ₱1526', 4, '2025-10-19 19:39:48'),
(393, 11, NULL, NULL, 'Restocking: Product: Tocino, Quantity: 10, Cost: ₱1000', 4, '2025-10-19 19:40:21'),
(394, 11, NULL, NULL, 'Restocking: Product: Tempura, Quantity: 12, Cost: ₱1440', 4, '2025-10-19 19:40:34'),
(395, 11, NULL, NULL, 'Restocking: Product: Fiesta Ham, Quantity: 14, Cost: ₱3500', 4, '2025-10-19 19:42:09'),
(396, 11, NULL, NULL, 'Restocking: Product: Chicken Wings, Quantity: 11, Cost: ₱3850', 4, '2025-10-19 19:42:28'),
(397, 11, NULL, NULL, 'Restocking: Product: Fishball, Quantity: 15, Cost: ₱1800', 4, '2025-10-19 19:42:42'),
(398, 11, NULL, NULL, 'Restocking: Product: Ground Pork, Quantity: 17, Cost: ₱3978', 4, '2025-10-19 19:42:54'),
(399, 11, NULL, NULL, 'Restocking: Product: Pork Belly, Quantity: 21, Cost: ₱6720', 4, '2025-10-19 19:43:32'),
(400, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 10, Cost: ₱8000', 4, '2025-10-19 20:14:13'),
(401, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 310 automatically confirmed after 48 hours', 11, '2025-10-19 21:27:59'),
(402, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 225 automatically confirmed after 48 hours', 11, '2025-10-19 21:27:59'),
(403, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 224 automatically confirmed after 48 hours', 11, '2025-10-19 21:27:59'),
(404, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 344 marked as received by customer', 11, '2025-10-19 21:30:31'),
(405, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251019-0001, Supplier: Andoks, Items: 3', 4, '2025-10-19 21:42:54'),
(406, 36, NULL, NULL, 'Restocking Status Update: Product: Beef Sirloin, Status: Cancelled', 4, '2025-10-19 21:56:25'),
(407, 36, NULL, NULL, 'Restocking Status Update: Product: Beef Sirloin, Status: Cancelled', 4, '2025-10-19 21:56:27'),
(408, 36, NULL, NULL, 'Restocking Status Update: Product: Beef Sirloin, Status: Cancelled', 4, '2025-10-19 21:56:30'),
(409, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251019-0004, Supplier: Andoks, Items: 1', 4, '2025-10-19 21:57:29'),
(410, 36, NULL, NULL, 'Restocking Status Update: Product: Beef Sirloin, Status: Received', 4, '2025-10-19 22:00:47'),
(411, 11, NULL, NULL, 'Restocking: Product: Pork Adobo Cut, Quantity: 10, Cost: ₱2350', 4, '2025-10-19 22:10:49'),
(412, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Marion Brix Quiling, Product: Chicken Skin', 4, '2025-10-19 22:11:11'),
(413, 11, NULL, NULL, 'Restocking: Product: Chicken Skin, Quantity: 21, Cost: ₱2835', 4, '2025-10-19 22:11:33'),
(414, 11, NULL, NULL, 'Restocking: Product: Beef Shank, Quantity: 1, Cost: ₱335', 4, '2025-10-19 22:16:01'),
(415, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 345 marked as received by customer', 11, '2025-10-19 22:19:10'),
(416, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 347 marked as received by customer', 11, '2025-10-19 23:52:29'),
(417, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 346 marked as received by customer', 11, '2025-10-19 23:52:32'),
(418, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 348 marked as received by customer', 11, '2025-10-19 23:57:43'),
(419, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251020-0001, Supplier: BALIWAG, Items: 1', 4, '2025-10-20 08:58:43'),
(420, 36, NULL, NULL, 'Restocking Status Update: Product: Chicken Wings, Status: Received, Ordered: 20, Received: 15', 4, '2025-10-20 09:08:09'),
(421, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251020-0002, Supplier: Jay Inc., Items: 1', 4, '2025-10-20 09:54:28'),
(422, 36, NULL, NULL, 'Restocking Status Update: Product: Ground Pork, Status: Received, Ordered: 15, Received: 14', 4, '2025-10-20 09:55:21'),
(423, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 351 marked as received by customer', 11, '2025-10-20 13:09:07'),
(424, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 203 marked as received by customer', 10, '2025-10-20 13:16:50'),
(425, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 209 automatically confirmed after 48 hours', 10, '2025-10-20 13:24:57'),
(426, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 208 automatically confirmed after 48 hours', 10, '2025-10-20 13:24:57'),
(427, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 180 automatically confirmed after 48 hours', 10, '2025-10-20 13:24:57'),
(428, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 207 automatically confirmed after 48 hours', 10, '2025-10-20 13:24:57'),
(429, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 178 automatically confirmed after 48 hours', 10, '2025-10-20 13:24:58'),
(430, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 321 automatically confirmed after 48 hours', 9, '2025-10-20 13:25:39'),
(431, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251020-0003, Supplier: BALIWAG, Items: 1', 4, '2025-10-20 22:21:38'),
(432, 36, NULL, NULL, 'Restocking Status Update: Product: Chicken Wings, Status: Received, Ordered: 50, Received: 45', 4, '2025-10-20 22:22:20'),
(433, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 342 marked as received by customer', 3, '2025-10-21 09:36:27'),
(434, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 322 automatically confirmed after 48 hours', 9, '2025-10-21 11:09:19');
INSERT INTO `history_logs` (`historylog_id`, `history_action_type_id`, `reference_id`, `reference_type`, `details`, `performed_by`, `performed_at`) VALUES
(435, 36, NULL, NULL, 'Role Created: Created new role: crew1', 4, '2025-10-21 23:44:17'),
(436, 36, NULL, NULL, 'Role Deleted: Deleted role: crew1', 4, '2025-10-21 23:49:54'),
(437, 36, NULL, NULL, 'Role Created: Created new role: crew1', 4, '2025-10-21 23:50:21'),
(438, 36, NULL, NULL, 'Role Created: Created new role: crew2', 4, '2025-10-21 23:51:33'),
(439, 36, NULL, NULL, 'Role Deleted: Deleted role: crew1', 4, '2025-10-21 23:52:01'),
(440, 36, NULL, NULL, 'Role Deleted: Deleted role: crew2', 4, '2025-10-21 23:52:08'),
(441, 36, NULL, NULL, 'Role Created: Created new role: crew1', 4, '2025-10-21 23:54:08'),
(442, 36, NULL, NULL, 'Role Deleted: Deleted role: crew1', 4, '2025-10-21 23:54:12'),
(443, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 323 automatically confirmed after 48 hours', 9, '2025-10-22 00:16:29'),
(444, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 5, Cost: ₱4250', 4, '2025-10-22 11:49:33'),
(445, 11, NULL, NULL, 'Restocking: Product: Beef Sirloin, Quantity: 5, Cost: ₱3750', 4, '2025-10-22 11:50:24'),
(446, 11, NULL, NULL, 'Restocking: Product: Ground Pork, Quantity: 2, Cost: ₱500', 4, '2025-10-22 20:46:54'),
(447, 11, NULL, NULL, 'Restocking: Product: Ground Pork, Quantity: 2, Cost: ₱580', 4, '2025-10-22 20:47:22'),
(448, 36, NULL, NULL, 'Manual Pull Out: Product: TEST, Batch: B4-20250919-003, Quantity: 20.0, Reason: Expired', 4, '2025-10-22 21:53:25'),
(449, 36, NULL, NULL, 'Manual Pull Out: Product: TRIMMINGS, Batch: TRIMMINGS-BATCH002, Quantity: 15.0, Reason: Expired', 4, '2025-10-22 21:53:58'),
(450, 36, NULL, NULL, 'Manual Pull Out: Product: TEST, Batch: B4-20250919-002, Quantity: 25.0, Reason: Expired', 4, '2025-10-22 21:56:44'),
(451, 36, NULL, NULL, 'Manual Pull Out: Product: TRIMMINGS, Batch: TRIMMINGS-BATCH003, Quantity: 25.0, Reason: Expired', 4, '2025-10-22 21:56:52'),
(452, 36, NULL, NULL, 'Manual Pull Out: Product: BANGUS, Batch: BANGUS-BATCH003, Quantity: 25.0, Reason: Expired', 4, '2025-10-22 21:58:13'),
(453, 36, NULL, NULL, 'Discount Code Created: Created discount code: WELCOME20', 4, '2025-10-22 23:43:57'),
(454, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 373 marked as received by customer', 3, '2025-10-23 00:01:22'),
(455, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251024-0001, Supplier: Marion Brix Quiling, Items: 1', 4, '2025-10-24 09:25:59'),
(456, 36, NULL, NULL, 'Restocking Status Update: Product: Chicken Skin, Status: Received, Ordered: 15, Received: 15', 4, '2025-10-24 09:26:42'),
(457, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 376 marked as received by customer', 3, '2025-10-24 10:00:26'),
(458, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 377 marked as received by customer', 3, '2025-10-24 10:03:40'),
(459, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 375 marked as received by customer', 3, '2025-10-24 10:03:44'),
(460, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 378 marked as received by customer', 3, '2025-10-25 18:41:03'),
(461, 36, NULL, NULL, 'Unarchived Supplier: Supplier ID: 6, Name: TEST TEST TEST', 4, '2025-10-25 19:01:11'),
(462, 36, NULL, NULL, 'User Deactivated: Username: marquils', 4, '2025-10-25 19:21:33'),
(463, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Cheraine Goods, Product: Beef Shank', 4, '2025-10-25 21:27:21'),
(464, 11, NULL, NULL, 'Restocking: Product: Beef Shank, Quantity: 10, Cost: ₱2500', 4, '2025-10-25 21:29:57'),
(465, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: Jay Inc., Product: Beef Tapa', 4, '2025-10-25 21:31:22'),
(466, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251025-0001, Supplier: Jay Inc., Items: 1', 4, '2025-10-25 21:31:51'),
(467, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251025-0002, Supplier: Andoks, Items: 2', 4, '2025-10-25 21:34:24'),
(468, 36, NULL, NULL, 'Discount Code Created: Created discount code: SAVEMORE', 4, '2025-10-25 21:42:43'),
(469, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-25 21:43:13'),
(470, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 10:54:28'),
(471, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 10:54:59'),
(472, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:01:39'),
(473, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:01:49'),
(474, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:08:42'),
(475, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:08:54'),
(476, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:08:58'),
(477, 36, NULL, NULL, 'Discount Code Deleted: Deleted discount code: TEST', 4, '2025-10-26 11:13:12'),
(478, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:13:24'),
(479, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:15:29'),
(480, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: WELCOME20', 4, '2025-10-26 11:15:33'),
(481, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: MIKEMADZ10', 4, '2025-10-26 11:15:37'),
(482, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:15:52'),
(483, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: SAVEMORE', 4, '2025-10-26 11:16:04'),
(484, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: MIKEMADZ5', 4, '2025-10-26 11:16:13'),
(485, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: MIKEMADZ5', 4, '2025-10-26 11:16:17'),
(486, 36, NULL, NULL, 'Discount Code Updated: Updated discount code: WELCOME2', 4, '2025-10-26 11:16:22'),
(487, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 386 automatically confirmed after 48 hours', 3, '2025-10-29 11:01:36'),
(488, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 390 marked as received by customer', 3, '2025-10-30 12:18:24'),
(489, 36, NULL, NULL, 'Order Received Confirmed: Order ID: 358 automatically confirmed after 48 hours', 9, '2025-10-30 21:17:54'),
(490, 36, NULL, NULL, 'Restocking Status Update: Product: Chicken Fillet, Status: Cancelled', 4, '2025-10-31 16:41:37'),
(491, 36, NULL, NULL, 'Restocking Status Update: Product: Beef Tapa, Status: Cancelled', 4, '2025-10-31 16:41:40'),
(492, 36, NULL, NULL, 'Restocking Status Update: Product: Beef Tapa, Status: Cancelled', 4, '2025-10-31 16:41:43'),
(493, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251031-0001, Supplier: Jay Inc., Items: 1', 4, '2025-10-31 16:41:55'),
(494, 36, NULL, NULL, 'Brand Restored: Restored brand: San Gabriel Beef', 4, '2025-10-31 16:49:25'),
(495, 36, NULL, NULL, 'Restocking Status Update: Product: Beef Tapa, Status: Received, Ordered: 10, Received: 10', 4, '2025-10-31 16:50:29'),
(496, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: Chicken Fillet', 4, '2025-10-31 16:51:41'),
(497, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251031-0002, Supplier: SGB Goods, Items: 1', 4, '2025-10-31 16:58:34'),
(498, 36, NULL, NULL, 'Restocking Status Update: Product: Chicken Fillet, Status: Cancelled', 4, '2025-10-31 16:58:50'),
(499, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251031-0003, Supplier: SGB Goods, Items: 1', 4, '2025-10-31 17:39:39'),
(500, 36, NULL, NULL, 'Restocking Status Update: Product: Chicken Fillet, Status: Cancelled', 4, '2025-10-31 17:40:54'),
(501, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251031-0004, Supplier: Andoks, Items: 1', 4, '2025-10-31 17:41:04'),
(502, 36, NULL, NULL, 'Restocking Status Update: Product: Chicken Fillet, Status: Received, Ordered: 8, Received: 8', 4, '2025-10-31 17:41:29'),
(503, 11, NULL, NULL, 'Restocking: Product: Chicken Fillet, Quantity: 10, Cost: ₱2000', 4, '2025-10-31 17:43:12'),
(504, 11, NULL, NULL, 'Restocking: Product: Beef Tapa, Quantity: 10, Cost: ₱2500', 4, '2025-10-31 17:43:34'),
(505, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: ZAYN GOODS, Product: Beef Shank', 4, '2025-10-31 18:32:57'),
(506, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: ZAYN GOODS, Product: Pork Adobo Cut', 4, '2025-10-31 19:14:27'),
(507, 11, NULL, NULL, 'Restocking: Product: Pork Adobo Cut, Quantity: 10, Cost: ₱2500', 4, '2025-10-31 19:16:48'),
(508, 11, NULL, NULL, 'Restocking: Product: Pork Adobo Cut, Quantity: 20, Cost: ₱6000', 4, '2025-10-31 19:20:55'),
(509, 11, NULL, NULL, 'Restocking: Product: Pork Adobo Cut, Quantity: 10, Cost: ₱3000', 4, '2025-10-31 19:30:00'),
(510, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251031-0005, Supplier: Jay Inc., Items: 1', 4, '2025-10-31 19:41:19'),
(511, 36, NULL, NULL, 'Restocking Status Update: Product: Pork Adobo Cut, Status: Received, Ordered: 20, Received: 20', 4, '2025-10-31 19:42:07'),
(512, 36, NULL, NULL, 'Assigned Product to Supplier: Supplier: SGB Goods, Product: Chicken Skin', 4, '2025-10-31 19:43:16'),
(513, 11, NULL, NULL, 'Restocking: Product: Chicken Skin, Quantity: 20, Cost: ₱3000', 4, '2025-10-31 19:43:56'),
(514, 11, NULL, NULL, 'Restocking: Product: Chicken Skin, Quantity: 20, Cost: ₱5000', 4, '2025-10-31 19:44:45'),
(515, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251031-0006, Supplier: SGB Goods, Items: 1', 4, '2025-10-31 19:52:26'),
(516, 36, NULL, NULL, 'Purchase Order Created: PO: PO-20251031-0007, Supplier: Andoks, Items: 1', 4, '2025-10-31 20:28:03');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_alerts`
--

CREATE TABLE `inventory_alerts` (
  `inventoryalert_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `alerttype_id` int(11) NOT NULL,
  `limit_value` int(11) NOT NULL,
  `current_value` int(11) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `order_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 2, 'Your order has been delivered.', 0, '2024-11-24 16:10:11'),
(2, 10, 179, 'Your order has been cancelled by admin: pangit mo kausap', 0, '2025-09-18 22:36:25'),
(3, 3, 186, 'Your order has been cancelled by admin: error', 0, '2025-09-23 19:44:30'),
(4, 3, 186, 'Your order has been cancelled by admin: error', 0, '2025-09-23 19:44:30'),
(5, 3, 187, 'Your order has been cancelled by admin: s', 0, '2025-09-23 22:01:45'),
(6, 3, 188, 'Your order has been cancelled by admin: s', 0, '2025-09-23 22:01:50'),
(7, 10, 196, 'Your order has been cancelled by admin: did not show', 0, '2025-10-07 10:41:47'),
(8, 10, 205, 'Your order has been cancelled by admin: di nag bayad', 0, '2025-10-07 11:58:21'),
(9, 10, 206, 'Your order has been cancelled by admin: s', 0, '2025-10-07 12:16:38'),
(10, 3, 223, 'Your order has been cancelled by admin: no payment', 0, '2025-10-08 19:02:01'),
(11, 3, 220, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:06:40'),
(12, 3, 219, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:06:44'),
(13, 3, 218, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:06:49'),
(14, 3, 217, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:06:51'),
(15, 3, 216, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:14:58'),
(16, 3, 215, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:15:02'),
(17, 3, 214, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:15:04'),
(18, 3, 250, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:06:56'),
(19, 3, 251, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:07:03'),
(20, 3, 252, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:07:06'),
(21, 3, 253, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:18'),
(22, 3, 254, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:21'),
(23, 11, 255, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:23'),
(24, 11, 256, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:26'),
(25, 11, 257, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:28'),
(26, 11, 258, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:31'),
(27, 11, 259, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:33'),
(28, 11, 260, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:36'),
(29, 3, 298, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:40'),
(30, 3, 299, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:13:50'),
(31, 11, 261, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:09'),
(32, 11, 262, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:13'),
(33, 11, 263, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:44'),
(34, 3, 264, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:47'),
(35, 3, 265, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:50'),
(36, 3, 266, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:52'),
(37, 3, 267, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:55'),
(38, 3, 272, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:14:58'),
(39, 3, 274, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:01'),
(40, 3, 273, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:04'),
(41, 3, 279, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:08'),
(42, 3, 281, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:12'),
(43, 3, 280, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:16'),
(44, 3, 283, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:19'),
(45, 3, 284, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:23'),
(46, 3, 286, 'Your order has been cancelled by admin: 1', 0, '2025-10-12 16:15:28'),
(47, 3, 269, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:29:38'),
(48, 3, 278, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:35:14'),
(49, 3, 287, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:36:17'),
(50, 3, 285, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:47:03'),
(51, 3, 288, 'Your order has been cancelled by admin: 3', 0, '2025-10-12 16:47:06'),
(52, 3, 289, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:48:38'),
(53, 3, 291, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:48:42'),
(54, 3, 294, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:48:46'),
(55, 3, 293, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:48:49'),
(56, 3, 295, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:48:52'),
(57, 3, 297, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:48:54'),
(58, 3, 296, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:48:57'),
(59, 3, 290, 'Your order has been cancelled by admin: 2', 0, '2025-10-12 16:49:00'),
(60, 3, 319, 'Your order has been cancelled by admin: did not show', 0, '2025-10-17 10:25:15'),
(61, 3, 315, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-19 02:25:41'),
(62, 3, 313, 'Your order has been cancelled by admin: error', 0, '2025-10-19 02:26:33'),
(63, 3, 317, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-19 02:46:08'),
(64, 9, 324, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-19 02:53:11'),
(65, 9, 325, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-19 03:03:27'),
(66, 9, 326, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-19 03:05:47'),
(67, 9, 327, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-19 03:08:00'),
(68, 3, 316, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-19 03:10:09'),
(69, 3, 314, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-19 03:12:41'),
(70, 3, 329, 'Your order has been cancelled by admin: correction', 0, '2025-10-19 19:49:56'),
(71, 3, 330, 'Your order has been cancelled by admin: requested', 0, '2025-10-19 19:50:12'),
(72, 3, 340, 'Your order has been cancelled by admin: did not pay', 0, '2025-10-19 22:17:44'),
(73, 3, 331, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-19 22:18:05'),
(74, 3, 332, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-19 22:20:16'),
(75, 3, 333, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-19 22:20:25'),
(76, 3, 337, 'Your order has been cancelled by admin: Customer did not pick up order within 3 hours (9.4 hours elapsed)', 0, '2025-10-20 08:31:35'),
(77, 3, 369, 'Your order has been cancelled by admin: Ride did not show up', 0, '2025-10-22 20:45:08'),
(78, 3, 370, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-22 20:48:09'),
(79, 3, 371, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-22 20:55:06'),
(80, 9, 359, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-22 20:55:52'),
(81, 3, 382, 'Your order has been cancelled by admin: Customer did not pick up order within 3 hours (6.9 hours elapsed)', 0, '2025-10-26 10:48:50'),
(82, 3, 388, 'Your order has been cancelled by admin: Customer did not pick up order within 3 hours (11.6 hours elapsed)', 0, '2025-10-31 15:56:10'),
(83, 3, 385, 'Your order has been cancelled by admin: Customer did not pick up order within 3 hours (111.4 hours elapsed)', 0, '2025-10-31 16:05:44'),
(84, 3, 393, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-31 16:06:27'),
(85, 3, 396, 'Your order has been cancelled by admin: Insufficient Payment', 0, '2025-10-31 16:12:30'),
(86, 3, 398, 'Your order has been cancelled by admin: Customer unable to visit the store', 0, '2025-10-31 19:13:23');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `orders_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `orderstatus_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `total_price` decimal(10,2) NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `delivery_option` varchar(50) NOT NULL DEFAULT 'Pickup',
  `plate_number` varchar(20) DEFAULT NULL,
  `rider_contact_number` varchar(11) DEFAULT NULL,
  `transaction_number` varchar(19) DEFAULT NULL,
  `pickup_ready_at` datetime DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `application_name` varchar(50) DEFAULT NULL,
  `rider_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`orders_id`, `user_id`, `orderstatus_id`, `created_at`, `total_price`, `delivered_at`, `delivery_option`, `plate_number`, `rider_contact_number`, `transaction_number`, `pickup_ready_at`, `address_id`, `application_name`, `rider_name`) VALUES
(158, 45, 0, '2025-07-25 13:13:06', 209.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(159, 45, 0, '2025-07-25 13:23:37', 418.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(160, 45, 0, '2025-07-25 20:32:32', 722.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(161, 45, 0, '2025-07-25 20:33:13', 209.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(162, 45, 0, '2025-07-25 20:33:45', 209.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(163, 46, 0, '2025-08-09 09:31:06', 231.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(164, 46, 0, '2025-08-15 23:08:35', 1339.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(165, 46, 0, '2025-08-16 08:05:25', 184.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(167, 3, 4, '2025-09-06 10:56:28', 814.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(168, 3, 5, '2025-09-06 11:22:33', 407.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(169, 3, 4, '2025-09-06 13:15:21', 539.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(170, 3, 4, '2025-09-06 13:30:45', 132.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(171, 3, 4, '2025-09-06 13:39:41', 264.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(172, 3, 7, '2025-09-08 21:48:14', 275.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(173, 9, 4, '2025-09-16 23:09:49', 240.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(174, 9, 4, '2025-09-17 20:56:27', 960.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(175, 10, 4, '2025-09-17 23:35:58', 240.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(176, 10, 4, '2025-09-17 23:37:31', 2400.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(177, 10, 4, '2025-09-18 22:16:49', 180.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(178, 10, 4, '2025-09-18 22:18:21', 240.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(179, 10, 5, '2025-09-18 22:22:18', 180.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(180, 10, 4, '2025-09-18 22:22:54', 180.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(181, 3, 4, '2025-09-19 11:15:24', 144.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(182, 3, 4, '2025-09-22 10:35:08', 144.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(183, 3, 4, '2025-09-22 13:08:36', 198.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(184, 3, 4, '2025-09-23 17:33:28', 396.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(185, 3, 4, '2025-09-23 17:34:56', 396.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(186, 3, 5, '2025-09-23 19:29:56', 198.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(187, 3, 5, '2025-09-23 19:30:00', 198.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(188, 3, 5, '2025-09-23 19:30:04', 198.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(189, 3, 4, '2025-09-23 19:30:23', 240.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(190, 3, 4, '2025-09-23 19:31:25', 180.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(191, 3, 4, '2025-09-24 16:45:37', 942.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(192, 3, 4, '2025-09-30 20:34:23', 2410.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(193, 3, 4, '2025-09-30 20:51:51', 10.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(194, 3, 4, '2025-09-30 20:59:40', 8.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(195, 3, 4, '2025-09-30 21:02:18', 10.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(196, 10, 5, '2025-10-07 10:39:49', 12.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(199, 10, 4, '2025-10-07 11:13:14', 748.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(200, 10, 4, '2025-10-07 11:18:10', 630.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(201, 10, 4, '2025-10-07 11:23:43', 270.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(202, 10, 4, '2025-10-07 11:31:40', 270.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(203, 10, 4, '2025-10-07 11:36:01', 2070.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(204, 10, 4, '2025-10-07 11:55:27', 540.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(205, 10, 5, '2025-10-07 11:56:06', 630.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(206, 10, 5, '2025-10-07 12:03:39', 270.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(207, 10, 4, '2025-10-07 12:23:52', 270.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(208, 10, 4, '2025-10-07 12:29:15', 25.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(209, 10, 4, '2025-10-07 12:33:42', 12.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(210, 10, 4, '2025-10-07 12:39:26', 3996.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(211, 10, 4, '2025-10-07 12:43:14', 495.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(212, 10, 4, '2025-10-07 12:43:50', 693.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(213, 3, 4, '2025-10-08 14:08:47', 1627.50, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(214, 3, 5, '2025-10-08 14:14:29', 10.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(215, 3, 5, '2025-10-08 18:23:47', 35.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(216, 3, 5, '2025-10-08 18:45:44', 20.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(217, 3, 5, '2025-10-08 18:45:50', 20.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(218, 3, 5, '2025-10-08 18:46:01', 20.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(219, 3, 5, '2025-10-08 18:46:16', 20.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(220, 3, 5, '2025-10-08 18:46:21', 20.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(221, 3, 4, '2025-10-08 18:47:30', 20.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(222, 3, 4, '2025-10-08 19:00:53', 20.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(223, 3, 5, '2025-10-08 19:01:37', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(224, 11, 4, '2025-10-08 21:23:20', 300.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(225, 11, 4, '2025-10-08 21:34:03', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(226, 11, 4, '2025-10-08 21:36:22', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(227, 11, 4, '2025-10-08 21:42:57', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(228, 11, 4, '2025-10-08 21:58:48', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(229, 11, 4, '2025-10-08 22:11:19', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(230, 3, 4, '2025-10-08 22:12:36', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(231, 3, 4, '2025-10-08 22:31:35', 300.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 10, NULL, NULL),
(232, 3, 4, '2025-10-08 22:50:44', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(233, 3, 4, '2025-10-08 22:53:07', 250.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(234, 3, 4, '2025-10-08 22:58:16', 250.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(235, 3, 4, '2025-10-08 23:02:07', 250.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(236, 3, 4, '2025-10-08 23:07:38', 250.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(237, 3, 4, '2025-10-08 23:10:32', 250.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 12, NULL, NULL),
(238, 3, 4, '2025-10-08 23:14:58', 6750.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(239, 3, 4, '2025-10-09 11:54:09', 400.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 10, NULL, NULL),
(240, 3, 4, '2025-10-09 11:55:53', 400.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(241, 3, 4, '2025-10-09 11:56:09', 400.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 10, NULL, NULL),
(242, 3, 4, '2025-10-09 11:56:36', 400.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 12, NULL, NULL),
(243, 3, 4, '2025-10-09 12:28:08', 1200.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(244, 3, 4, '2025-10-09 12:29:59', 300.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(245, 11, 4, '2025-10-09 12:31:51', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 9, NULL, NULL),
(246, 11, 4, '2025-10-09 12:34:15', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(247, 11, 4, '2025-10-09 12:38:56', 100.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(248, 11, 4, '2025-10-09 12:53:55', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(249, 3, 4, '2025-10-09 14:31:38', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(250, 3, 5, '2025-10-09 21:28:21', 300.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(251, 3, 5, '2025-10-11 23:11:54', 2166.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(252, 3, 5, '2025-10-11 23:13:00', 2166.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(253, 3, 5, '2025-10-11 23:22:48', 1415.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(254, 3, 5, '2025-10-12 11:23:12', 1415.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(255, 11, 5, '2025-10-12 11:50:22', 1415.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(256, 11, 5, '2025-10-12 12:39:51', 1415.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(257, 11, 5, '2025-10-12 12:44:05', 1415.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 9, NULL, NULL),
(258, 11, 5, '2025-10-12 12:47:00', 1415.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(259, 11, 5, '2025-10-12 12:49:56', 1415.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(260, 11, 5, '2025-10-12 12:59:06', 1415.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(261, 11, 5, '2025-10-12 13:09:06', 1415.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(262, 11, 5, '2025-10-12 13:11:27', 585.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(263, 11, 5, '2025-10-12 13:16:20', 585.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(264, 3, 5, '2025-10-12 13:52:45', 50.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(265, 3, 5, '2025-10-12 14:07:02', 50.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(266, 3, 5, '2025-10-12 14:12:29', 1200.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(267, 3, 5, '2025-10-12 14:12:42', 1200.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(268, 3, 4, '2025-10-12 14:12:56', 650.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(269, 3, 5, '2025-10-12 14:13:35', 550.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(270, 3, 4, '2025-10-12 14:17:57', 650.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(271, 3, 4, '2025-10-12 14:20:00', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(272, 3, 5, '2025-10-12 14:21:24', 450.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 12, NULL, NULL),
(273, 3, 5, '2025-10-12 14:21:30', 450.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(274, 3, 5, '2025-10-12 14:21:47', 450.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(275, 3, 4, '2025-10-12 14:22:02', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(276, 3, 4, '2025-10-12 14:22:20', 300.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(277, 3, 4, '2025-10-12 14:23:26', 102.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(278, 3, 5, '2025-10-12 14:23:52', 51.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(279, 3, 5, '2025-10-12 14:26:31', 2150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(280, 3, 5, '2025-10-12 14:26:56', 2150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(281, 3, 5, '2025-10-12 14:32:55', 2150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(282, 3, 4, '2025-10-12 14:33:18', 225.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(283, 3, 5, '2025-10-12 14:33:33', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(284, 3, 5, '2025-10-12 14:38:41', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(285, 3, 5, '2025-10-12 14:39:05', 550.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(286, 3, 5, '2025-10-12 14:39:18', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(287, 3, 5, '2025-10-12 14:41:33', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(288, 3, 5, '2025-10-12 14:43:06', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(289, 3, 5, '2025-10-12 14:43:37', 700.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(290, 3, 5, '2025-10-12 14:46:17', 700.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(291, 3, 5, '2025-10-12 14:46:48', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(292, 3, 4, '2025-10-12 14:47:24', 550.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(293, 3, 5, '2025-10-12 14:48:11', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(294, 3, 5, '2025-10-12 14:51:11', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(295, 3, 5, '2025-10-12 14:52:03', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(296, 3, 5, '2025-10-12 14:52:11', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(297, 3, 5, '2025-10-12 14:52:35', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(298, 3, 5, '2025-10-12 14:53:21', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(299, 3, 5, '2025-10-12 14:56:42', 700.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(300, 3, 4, '2025-10-12 15:00:06', 550.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(301, 3, 4, '2025-10-12 15:08:27', 550.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(302, 3, 4, '2025-10-12 15:08:59', 275.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 12, NULL, NULL),
(303, 3, 4, '2025-10-12 19:29:53', 250.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(304, 3, 4, '2025-10-12 22:46:03', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(305, 3, 4, '2025-10-12 22:50:10', 75.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(306, 3, 4, '2025-10-12 22:58:53', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(307, 3, 4, '2025-10-12 23:06:05', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(308, 11, 4, '2025-10-12 23:51:21', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(309, 11, 4, '2025-10-12 23:52:14', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(310, 11, 4, '2025-10-12 23:55:58', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(311, 11, 4, '2025-10-13 00:02:52', 300.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(312, 3, 4, '2025-10-13 15:15:04', 150.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(313, 3, 5, '2025-10-13 15:51:47', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(314, 3, 5, '2025-10-13 15:52:13', 150.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(315, 3, 5, '2025-10-13 15:52:58', 51.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(316, 3, 5, '2025-10-13 15:54:09', 51.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(317, 3, 5, '2025-10-16 23:13:29', 1173.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(318, 3, 4, '2025-10-17 08:53:03', 15000.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(319, 3, 5, '2025-10-17 09:00:27', 3000.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(320, 9, 4, '2025-10-17 10:55:50', 15000.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(321, 9, 4, '2025-10-17 10:56:31', 1750.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(322, 9, 4, '2025-10-19 02:06:00', 3500.00, NULL, 'delivery', 'CBA-2142', NULL, '9878568975685687775', NULL, NULL, NULL, NULL),
(323, 9, 4, '2025-10-19 02:24:18', 1050.00, NULL, 'delivery', 'WQO-231', NULL, '5676512', NULL, NULL, 'Lalamove', 'Gian Zayn Mosqueda'),
(324, 9, 5, '2025-10-19 02:52:40', 350.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(325, 9, 5, '2025-10-19 03:03:10', 350.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(326, 9, 5, '2025-10-19 03:05:28', 350.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(327, 9, 5, '2025-10-19 03:07:47', 350.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(328, 3, 4, '2025-10-19 16:18:07', 4600.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-19 22:58:54', 11, NULL, NULL),
(329, 3, 5, '2025-10-19 16:19:58', 1410.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(330, 3, 5, '2025-10-19 16:21:36', 1410.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(331, 3, 5, '2025-10-19 16:22:54', 1120.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(332, 3, 5, '2025-10-19 16:23:46', 990.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(333, 3, 5, '2025-10-19 16:26:07', 640.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(334, 3, 4, '2025-10-19 16:29:00', 770.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-24 09:58:14', 11, NULL, NULL),
(335, 3, 4, '2025-10-19 16:30:59', 640.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-19 22:57:30', 11, NULL, NULL),
(336, 3, 4, '2025-10-19 16:37:18', 640.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-19 17:07:18', 11, NULL, NULL),
(337, 3, 5, '2025-10-19 16:38:12', 640.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-19 17:08:12', 11, NULL, NULL),
(338, 3, 4, '2025-10-19 16:46:20', 350.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-19 17:16:20', 11, NULL, NULL),
(339, 3, 4, '2025-10-19 16:51:54', 640.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(340, 3, 5, '2025-10-19 16:52:09', 847.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(341, 3, 4, '2025-10-19 16:52:37', 2560.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(342, 3, 4, '2025-10-19 16:52:59', 640.00, NULL, 'delivery', 'ASD-XUQ', NULL, '8797989789879789787', NULL, 11, NULL, NULL),
(343, 11, 4, '2025-10-19 21:28:49', 44800.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 8, NULL, NULL),
(344, 11, 4, '2025-10-19 21:30:03', 44800.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 9, NULL, NULL),
(345, 11, 4, '2025-10-19 22:17:01', 4260.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 9, NULL, NULL),
(346, 11, 4, '2025-10-19 22:34:07', 3900.00, NULL, 'delivery', 'XYZ-791', NULL, '1231231231233213212', NULL, 9, NULL, NULL),
(347, 11, 4, '2025-10-19 23:44:06', 1525.00, NULL, 'delivery', 'XXX-2142', NULL, '9898908908998899898', NULL, 15, NULL, NULL),
(348, 11, 4, '2025-10-19 23:53:46', 3500.00, NULL, 'delivery', 'ACD-4214', NULL, '6565765776576576576', NULL, 9, NULL, NULL),
(349, 11, 4, '2025-10-19 23:58:28', 300.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-19 23:58:51', 8, NULL, NULL),
(350, 11, 4, '2025-10-20 08:31:05', 248.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-20 08:31:19', 8, NULL, NULL),
(351, 11, 4, '2025-10-20 13:08:23', 350.00, NULL, 'delivery', 'EWA-5712', NULL, '9898989898898988988', NULL, 15, NULL, NULL),
(352, 11, 4, '2025-10-20 13:11:12', 240.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-20 13:12:49', 8, NULL, NULL),
(353, 11, 4, '2025-10-20 21:13:52', 1600.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-21 09:35:32', 8, NULL, NULL),
(354, 3, 4, '2025-10-21 09:31:59', 450.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-21 09:35:29', 11, NULL, NULL),
(355, 3, 4, '2025-10-21 09:47:51', 400.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-21 09:48:07', 11, NULL, NULL),
(356, 10, 4, '2025-10-21 11:14:27', 1488.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-21 11:14:33', 4, NULL, NULL),
(357, 11, 4, '2025-10-21 11:19:24', 1580.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-21 11:19:33', 8, NULL, NULL),
(358, 9, 4, '2025-10-22 00:22:04', 5550.00, NULL, 'delivery', 'WQW-712', NULL, '3421354324897895434', NULL, NULL, 'Lalamove', 'Marion Brix Quiling'),
(359, 9, 5, '2025-10-22 01:24:55', 2775.00, NULL, 'pickup', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(360, 9, 4, '2025-10-22 11:51:00', 4100.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 11:51:52', NULL, NULL, NULL),
(361, 10, 4, '2025-10-22 13:07:45', 3700.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 13:07:58', 4, NULL, NULL),
(362, 20, 4, '2025-10-22 13:45:09', 690.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 13:45:25', 16, NULL, NULL),
(363, 20, 4, '2025-10-22 13:48:59', 820.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 13:49:19', 16, NULL, NULL),
(364, 20, 4, '2025-10-22 13:51:35', 790.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 13:51:42', 16, NULL, NULL),
(365, 20, 4, '2025-10-22 13:57:59', 800.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 13:59:06', 16, NULL, NULL),
(366, 20, 4, '2025-10-22 14:14:14', 2450.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 14:14:22', 16, NULL, NULL),
(367, 20, 4, '2025-10-22 15:04:18', 350.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 15:04:24', 16, NULL, NULL),
(368, 3, 4, '2025-10-22 15:05:45', 5250.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 15:05:57', 11, NULL, NULL),
(369, 3, 5, '2025-10-22 20:44:47', 800.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(370, 3, 5, '2025-10-22 20:47:40', 600.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(371, 3, 5, '2025-10-22 20:54:57', 1020.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(372, 10, 4, '2025-10-22 23:45:07', 1480.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-22 23:45:23', 4, NULL, NULL),
(373, 3, 4, '2025-10-22 23:58:50', 1752.00, NULL, 'delivery', 'XYZ-791', NULL, '8656756778878', NULL, 11, 'Lalamove', 'Marianne'),
(374, 3, 4, '2025-10-24 09:08:23', 370.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-24 09:57:31', 11, NULL, NULL),
(375, 3, 4, '2025-10-24 09:51:43', 740.00, NULL, 'delivery', 'ADE-2131', NULL, '213123512', NULL, 13, 'Lalamove', 'Davidson'),
(376, 3, 4, '2025-10-24 09:58:02', 370.00, NULL, 'delivery', 'WWE-2133', NULL, '7456456', NULL, 12, 'Lalamove', 'Kervie'),
(377, 3, 4, '2025-10-24 10:01:00', 350.00, NULL, 'delivery', 'POQ-2130', NULL, '4141124', NULL, 13, 'Lalamove', 'Jay'),
(378, 3, 4, '2025-10-24 10:04:21', 370.00, NULL, 'delivery', 'GDO-2131', NULL, '712312', NULL, 12, 'Lalamove', 'Brix'),
(379, 3, 4, '2025-10-24 23:06:14', 70.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-24 23:22:50', 11, NULL, NULL),
(380, 3, 4, '2025-10-24 23:08:26', 350.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-25 18:40:29', 11, NULL, NULL),
(381, 3, 4, '2025-10-25 18:40:13', 340.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-25 18:40:32', 11, NULL, NULL),
(382, 3, 5, '2025-10-25 21:52:42', 4200.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-25 21:52:55', 11, NULL, NULL),
(383, 3, 2, '2025-10-25 21:54:42', 370.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(384, 10, 4, '2025-10-26 12:52:53', 351.50, NULL, 'pickup', NULL, NULL, NULL, '2025-10-26 12:53:13', 4, NULL, NULL),
(385, 3, 5, '2025-10-26 17:39:04', 130.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-26 17:39:40', 11, NULL, NULL),
(386, 3, 4, '2025-10-26 17:40:28', 370.00, NULL, 'delivery', 'SAZ-1212', '09213197822', '78734123213', NULL, 13, 'Lalamove', 'Gian'),
(387, 3, 2, '2025-10-26 21:01:29', 130.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(388, 3, 5, '2025-10-26 21:06:22', 920.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-30 21:19:06', 11, NULL, NULL),
(389, 3, 2, '2025-10-30 12:09:14', 9940.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(390, 3, 4, '2025-10-30 12:17:19', 2400.00, NULL, 'delivery', 'WQO-232', '09321312321', '5464564512323213335', NULL, 13, 'Lalamove', 'Gian Zayn Mosqueda'),
(391, 3, 2, '2025-10-30 20:32:42', 50.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 12, NULL, NULL),
(392, 3, 2, '2025-10-30 20:56:16', 320.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(393, 3, 5, '2025-10-30 21:11:14', 350.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 10, NULL, NULL),
(394, 9, 2, '2025-10-30 21:18:50', 33720.00, NULL, 'delivery', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(395, 9, 4, '2025-10-30 21:47:52', 1300.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-31 16:24:51', 17, NULL, NULL),
(396, 3, 5, '2025-10-31 16:12:01', 350.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(397, 3, 8, '2025-10-31 16:24:40', 930.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-31 16:24:49', 11, NULL, NULL),
(398, 3, 5, '2025-10-31 19:12:59', 1220.00, NULL, 'pickup', NULL, NULL, NULL, NULL, 11, NULL, NULL),
(399, 3, 4, '2025-10-31 19:13:50', 1220.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-31 19:13:59', 11, NULL, NULL),
(400, 3, 2, '2025-10-31 19:22:12', 3700.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 13, NULL, NULL),
(401, 10, 8, '2025-10-31 19:46:35', 12000.00, NULL, 'pickup', NULL, NULL, NULL, '2025-10-31 20:27:20', 4, NULL, NULL),
(402, 10, 2, '2025-10-31 20:27:08', 6400.00, NULL, 'delivery', NULL, NULL, NULL, NULL, 4, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_cancellations`
--

CREATE TABLE `order_cancellations` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `cancelled_by` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `receipt_path` varchar(255) DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `receipt_uploaded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_cancellations`
--

INSERT INTO `order_cancellations` (`id`, `order_id`, `reason`, `cancelled_by`, `created_at`, `receipt_path`, `receipt_filename`, `receipt_uploaded_at`) VALUES
(1, 179, 'pangit mo kausap', 'superadmin', '2025-09-18 14:36:25', NULL, NULL, NULL),
(2, 186, 'error', 'superadmin', '2025-09-23 11:44:30', NULL, NULL, NULL),
(3, 186, 'error', 'superadmin', '2025-09-23 11:44:30', NULL, NULL, NULL),
(4, 187, 's', 'superadmin', '2025-09-23 14:01:45', NULL, NULL, NULL),
(5, 188, 's', 'superadmin', '2025-09-23 14:01:50', NULL, NULL, NULL),
(6, 196, 'did not show', 'superadmin', '2025-10-07 02:41:47', NULL, NULL, NULL),
(7, 205, 'di nag bayad', 'superadmin', '2025-10-07 03:58:21', NULL, NULL, NULL),
(13, 206, 's', 'superadmin', '2025-10-07 04:16:38', NULL, NULL, NULL),
(14, 223, 'no payment', 'superadmin', '2025-10-08 11:02:01', NULL, NULL, NULL),
(15, 220, 's', 'superadmin', '2025-10-08 11:06:40', NULL, NULL, NULL),
(16, 219, 's', 'superadmin', '2025-10-08 11:06:44', NULL, NULL, NULL),
(17, 218, 's', 'superadmin', '2025-10-08 11:06:49', NULL, NULL, NULL),
(18, 217, 's', 'superadmin', '2025-10-08 11:06:51', NULL, NULL, NULL),
(19, 216, 's', 'superadmin', '2025-10-08 11:14:58', NULL, NULL, NULL),
(20, 215, 's', 'superadmin', '2025-10-08 11:15:02', NULL, NULL, NULL),
(21, 214, 's', 'superadmin', '2025-10-08 11:15:04', NULL, NULL, NULL),
(22, 250, '1', 'superadmin', '2025-10-12 08:06:56', NULL, NULL, NULL),
(23, 251, '1', 'superadmin', '2025-10-12 08:07:03', NULL, NULL, NULL),
(24, 252, '1', 'superadmin', '2025-10-12 08:07:06', NULL, NULL, NULL),
(25, 253, '1', 'superadmin', '2025-10-12 08:13:18', NULL, NULL, NULL),
(26, 254, '1', 'superadmin', '2025-10-12 08:13:21', NULL, NULL, NULL),
(27, 255, '1', 'superadmin', '2025-10-12 08:13:23', NULL, NULL, NULL),
(28, 256, '1', 'superadmin', '2025-10-12 08:13:26', NULL, NULL, NULL),
(29, 257, '1', 'superadmin', '2025-10-12 08:13:28', NULL, NULL, NULL),
(30, 258, '1', 'superadmin', '2025-10-12 08:13:31', NULL, NULL, NULL),
(31, 259, '1', 'superadmin', '2025-10-12 08:13:33', NULL, NULL, NULL),
(32, 260, '1', 'superadmin', '2025-10-12 08:13:36', NULL, NULL, NULL),
(33, 298, '1', 'superadmin', '2025-10-12 08:13:40', NULL, NULL, NULL),
(34, 299, '1', 'superadmin', '2025-10-12 08:13:50', NULL, NULL, NULL),
(35, 261, '1', 'superadmin', '2025-10-12 08:14:09', NULL, NULL, NULL),
(36, 262, '1', 'superadmin', '2025-10-12 08:14:13', NULL, NULL, NULL),
(37, 263, '1', 'superadmin', '2025-10-12 08:14:44', NULL, NULL, NULL),
(38, 264, '1', 'superadmin', '2025-10-12 08:14:47', NULL, NULL, NULL),
(39, 265, '1', 'superadmin', '2025-10-12 08:14:50', NULL, NULL, NULL),
(40, 266, '1', 'superadmin', '2025-10-12 08:14:52', NULL, NULL, NULL),
(41, 267, '1', 'superadmin', '2025-10-12 08:14:55', NULL, NULL, NULL),
(42, 272, '1', 'superadmin', '2025-10-12 08:14:58', NULL, NULL, NULL),
(43, 274, '1', 'superadmin', '2025-10-12 08:15:01', NULL, NULL, NULL),
(44, 273, '1', 'superadmin', '2025-10-12 08:15:04', NULL, NULL, NULL),
(45, 279, '1', 'superadmin', '2025-10-12 08:15:08', NULL, NULL, NULL),
(46, 281, '1', 'superadmin', '2025-10-12 08:15:12', NULL, NULL, NULL),
(47, 280, '1', 'superadmin', '2025-10-12 08:15:16', NULL, NULL, NULL),
(48, 283, '1', 'superadmin', '2025-10-12 08:15:19', NULL, NULL, NULL),
(49, 284, '1', 'superadmin', '2025-10-12 08:15:23', NULL, NULL, NULL),
(50, 286, '1', 'superadmin', '2025-10-12 08:15:28', NULL, NULL, NULL),
(51, 269, '2', 'superadmin', '2025-10-12 08:29:38', NULL, NULL, NULL),
(52, 278, '2', 'superadmin', '2025-10-12 08:35:14', NULL, NULL, NULL),
(53, 287, '2', 'superadmin', '2025-10-12 08:36:17', NULL, NULL, NULL),
(54, 285, '2', 'superadmin', '2025-10-12 08:47:03', NULL, NULL, NULL),
(55, 288, '3', 'superadmin', '2025-10-12 08:47:06', NULL, NULL, NULL),
(56, 289, '2', 'superadmin', '2025-10-12 08:48:38', NULL, NULL, NULL),
(57, 291, '2', 'superadmin', '2025-10-12 08:48:42', NULL, NULL, NULL),
(58, 294, '2', 'superadmin', '2025-10-12 08:48:46', NULL, NULL, NULL),
(59, 293, '2', 'superadmin', '2025-10-12 08:48:49', NULL, NULL, NULL),
(60, 295, '2', 'superadmin', '2025-10-12 08:48:52', NULL, NULL, NULL),
(61, 297, '2', 'superadmin', '2025-10-12 08:48:54', NULL, NULL, NULL),
(62, 296, '2', 'superadmin', '2025-10-12 08:48:57', NULL, NULL, NULL),
(63, 290, '2', 'superadmin', '2025-10-12 08:49:00', NULL, NULL, NULL),
(64, 319, 'did not show', 'superadmin', '2025-10-17 02:25:15', NULL, NULL, NULL),
(65, 315, 'Customer unable to visit the store', 'superadmin', '2025-10-18 18:25:41', NULL, NULL, NULL),
(66, 313, 'error', 'superadmin', '2025-10-18 18:26:33', NULL, NULL, NULL),
(67, 317, 'Insufficient Payment', 'superadmin', '2025-10-18 18:46:08', 'uploads/cancellation_receipts/test_receipt.jpg', 'test_receipt.jpg', '2025-10-18 18:46:08'),
(68, 324, 'Insufficient Payment', 'superadmin', '2025-10-18 18:53:11', 'uploads/cancellation_receipts/test_receipt.jpg', 'test_receipt.jpg', '2025-10-18 18:58:53'),
(69, 325, 'Insufficient Payment', 'superadmin', '2025-10-18 19:03:27', 'uploads/cancellation_receipts/test_receipt.jpg', 'test_receipt.jpg', '2025-10-18 19:03:27'),
(70, 326, 'Insufficient Payment', 'superadmin', '2025-10-18 19:05:47', 'uploads/cancellation_receipts/test_receipt.jpg', 'test_receipt.jpg', '2025-10-18 19:05:47'),
(71, 327, 'Insufficient Payment', 'superadmin', '2025-10-18 19:08:00', 'uploads/cancellation_receipts/test_receipt.jpg', 'test_receipt.jpg', '2025-10-18 19:08:00'),
(72, 316, 'Insufficient Payment', 'superadmin', '2025-10-18 19:10:09', 'uploads/cancellation_receipts/test_receipt.jpg', 'test_receipt.jpg', '2025-10-18 19:10:09'),
(73, 314, 'Insufficient Payment', 'superadmin', '2025-10-18 19:12:41', 'uploads/cancellation_receipts/order_314_2025-10-18_21-12-42.jpg', 'order_314_2025-10-18_21-12-42.jpg', '2025-10-18 19:12:42'),
(74, 329, 'correction', 'superadmin', '2025-10-19 11:49:56', NULL, NULL, NULL),
(75, 330, 'requested', 'superadmin', '2025-10-19 11:50:12', NULL, NULL, NULL),
(76, 340, 'did not pay', 'superadmin', '2025-10-19 14:17:44', NULL, NULL, NULL),
(77, 331, 'Customer unable to visit the store', 'superadmin', '2025-10-19 14:18:05', NULL, NULL, NULL),
(78, 332, 'Customer unable to visit the store', 'superadmin', '2025-10-19 14:20:16', NULL, NULL, NULL),
(79, 333, 'Customer unable to visit the store', 'superadmin', '2025-10-19 14:20:25', NULL, NULL, NULL),
(80, 337, 'Customer did not pick up order within 3 hours (9.4 hours elapsed)', 'superadmin', '2025-10-20 00:31:35', NULL, NULL, NULL),
(81, 369, 'Ride did not show up', 'superadmin', '2025-10-22 12:45:08', NULL, NULL, NULL),
(82, 370, 'Customer unable to visit the store', 'superadmin', '2025-10-22 12:48:09', NULL, NULL, NULL),
(83, 371, 'Customer unable to visit the store', 'superadmin', '2025-10-22 12:55:06', NULL, NULL, NULL),
(84, 359, 'Customer unable to visit the store', 'superadmin', '2025-10-22 12:55:52', NULL, NULL, NULL),
(85, 382, 'Customer did not pick up order within 3 hours (6.9 hours elapsed) - REFUND REQUIRED (Payment: Gcash)', 'superadmin', '2025-10-26 02:48:50', NULL, NULL, NULL),
(86, 388, 'Customer did not pick up order within 3 hours (11.6 hours elapsed) - REFUND REQUIRED (Payment: Gcash)', 'superadmin', '2025-10-31 07:56:10', NULL, NULL, NULL),
(87, 385, 'Customer did not pick up order within 3 hours (111.4 hours elapsed)', 'superadmin', '2025-10-31 08:05:44', NULL, NULL, NULL),
(88, 393, 'Insufficient Payment', 'superadmin', '2025-10-31 08:06:27', NULL, NULL, NULL),
(89, 396, 'Insufficient Payment', 'superadmin', '2025-10-31 08:12:30', 'uploads/cancellation_receipts/order_396_2025-10-31_09-12-31.jpg', 'order_396_2025-10-31_09-12-31.jpg', '2025-10-31 08:12:31'),
(90, 398, 'Customer unable to visit the store', 'superadmin', '2025-10-31 11:13:23', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `orderitems_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,1) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`orderitems_id`, `order_id`, `product_id`, `brand_id`, `batch_id`, `quantity`, `price`) VALUES
(3, 167, 2, NULL, NULL, 2.0, 132.00),
(4, 167, 1, NULL, NULL, 2.0, 275.00),
(5, 168, 2, NULL, NULL, 1.0, 132.00),
(6, 168, 1, NULL, NULL, 1.0, 275.00),
(7, 169, 1, NULL, NULL, 1.0, 275.00),
(8, 169, 2, NULL, NULL, 2.0, 132.00),
(9, 170, 2, NULL, NULL, 1.0, 132.00),
(10, 171, 2, NULL, NULL, 2.0, 132.00),
(11, 172, 1, NULL, NULL, 1.0, 275.00),
(12, 173, 4, NULL, NULL, 1.0, 240.00),
(13, 174, 4, NULL, NULL, 4.0, 240.00),
(14, 175, 4, NULL, NULL, 1.0, 240.00),
(15, 176, 4, NULL, NULL, 10.0, 240.00),
(16, 177, 5, NULL, NULL, 1.0, 180.00),
(17, 178, 4, NULL, NULL, 1.0, 240.00),
(18, 179, 5, NULL, NULL, 1.0, 180.00),
(19, 180, 5, NULL, NULL, 1.0, 180.00),
(20, 181, 7, NULL, NULL, 1.0, 144.00),
(21, 182, 7, NULL, NULL, 1.0, 144.00),
(22, 183, 9, NULL, NULL, 1.0, 198.00),
(23, 184, 9, NULL, NULL, 2.0, 198.00),
(24, 185, 9, NULL, NULL, 2.0, 198.00),
(25, 189, 4, NULL, NULL, 1.0, 240.00),
(26, 190, 5, NULL, NULL, 1.0, 180.00),
(27, 191, 5, NULL, NULL, 2.0, 180.00),
(28, 191, 9, NULL, NULL, 1.0, 198.00),
(29, 191, 7, NULL, NULL, 1.0, 144.00),
(30, 191, 4, NULL, NULL, 1.0, 240.00),
(31, 192, 11, NULL, NULL, 1.0, 10.00),
(32, 192, 4, NULL, NULL, 10.0, 240.00),
(33, 193, 11, NULL, NULL, 1.0, 10.00),
(34, 194, 10, NULL, NULL, 1.0, 8.00),
(35, 195, 11, NULL, NULL, 1.0, 10.00),
(36, 196, 10, NULL, NULL, 2.0, 8.00),
(37, 199, 4, NULL, NULL, 3.0, 240.00),
(38, 199, 10, NULL, NULL, 4.0, 8.00),
(39, 200, 6, NULL, NULL, 4.0, 180.00),
(40, 201, 6, NULL, NULL, 2.0, 180.00),
(41, 202, 6, NULL, NULL, 2.0, 180.00),
(42, 203, 8, NULL, NULL, 12.0, 180.00),
(43, 204, 8, NULL, NULL, 3.0, 180.00),
(44, 205, 8, NULL, NULL, 4.0, 180.00),
(45, 206, 6, NULL, NULL, 2.0, 180.00),
(46, 207, 8, NULL, NULL, 2.0, 180.00),
(47, 208, 11, NULL, NULL, 3.0, 10.00),
(49, 209, 10, NULL, NULL, 1.5, 8.00),
(50, 210, 8, NULL, NULL, 22.2, 180.00),
(51, 211, 9, NULL, NULL, 2.5, 198.00),
(52, 212, 9, NULL, NULL, 3.5, 198.00),
(53, 213, 14, NULL, NULL, 10.5, 155.00),
(54, 214, 13, NULL, NULL, 1.0, 10.00),
(55, 215, 11, NULL, NULL, 3.5, 10.00),
(56, 216, 10, NULL, NULL, 2.5, 8.00),
(57, 217, 10, NULL, NULL, 2.5, 8.00),
(58, 218, 10, NULL, NULL, 2.5, 8.00),
(59, 219, 10, NULL, NULL, 2.5, 8.00),
(60, 220, 10, NULL, NULL, 2.5, 8.00),
(61, 221, 10, NULL, NULL, 2.5, 8.00),
(63, 223, 14, NULL, NULL, 1.0, 150.00),
(64, 224, 14, NULL, NULL, 2.0, 150.00),
(65, 225, 14, NULL, NULL, 1.0, 150.00),
(66, 226, 14, NULL, NULL, 1.0, 150.00),
(67, 227, 14, NULL, NULL, 1.0, 150.00),
(68, 228, 14, NULL, NULL, 1.0, 150.00),
(69, 229, 14, NULL, NULL, 1.0, 150.00),
(70, 230, 14, NULL, NULL, 1.0, 150.00),
(71, 231, 14, NULL, NULL, 2.0, 150.00),
(72, 232, 14, NULL, NULL, 1.0, 150.00),
(73, 233, 14, NULL, NULL, 1.0, 250.00),
(74, 234, 14, NULL, NULL, 1.0, 250.00),
(75, 235, 14, NULL, NULL, 1.0, 250.00),
(76, 236, 14, NULL, NULL, 1.0, 250.00),
(77, 237, 14, NULL, NULL, 1.0, 250.00),
(78, 238, 14, NULL, NULL, 45.0, 150.00),
(79, 239, 14, NULL, NULL, 1.0, 400.00),
(80, 240, 14, NULL, NULL, 1.0, 400.00),
(81, 241, 14, NULL, NULL, 1.0, 400.00),
(82, 242, 14, NULL, NULL, 1.0, 400.00),
(83, 243, 14, NULL, NULL, 8.0, 150.00),
(84, 244, 14, NULL, NULL, 2.0, 150.00),
(85, 245, 14, NULL, NULL, 1.0, 150.00),
(86, 246, 14, NULL, NULL, 1.0, 150.00),
(87, 247, 14, NULL, NULL, 1.0, 150.00),
(88, 248, 14, NULL, NULL, 1.0, 150.00),
(89, 249, 14, NULL, NULL, 1.0, 150.00),
(90, 250, 14, NULL, NULL, 2.0, 150.00),
(95, 264, 17, 12, 56, 1.0, 150.00),
(96, 265, 17, 12, 57, 1.0, 550.00),
(101, 268, 17, 11, 59, 1.0, 650.00),
(102, 269, 17, 12, 58, 1.0, 550.00),
(103, 270, 17, 11, 59, 1.0, 650.00),
(104, 271, 17, 12, 60, 1.0, 150.00),
(108, 275, 17, 12, 61, 1.0, 150.00),
(109, 276, 17, 11, 62, 2.0, 150.00),
(110, 277, 17, 12, 64, 1.0, 51.00),
(111, 277, 17, 12, 63, 1.0, 51.00),
(112, 278, 17, 11, 64, 1.0, 51.00),
(116, 282, 17, 12, 65, 1.5, 150.00),
(121, 285, 17, 11, 66, 1.0, 550.00),
(134, 292, 17, 11, 66, 1.0, 550.00),
(147, 299, 17, 11, 66, 1.0, 550.00),
(148, 299, 17, 12, 65, 1.0, 150.00),
(149, 300, 17, 11, 66, 1.0, 550.00),
(150, 301, 17, 11, 66, 1.0, 550.00),
(151, 302, 17, 11, 66, 0.5, 550.00),
(152, 303, 17, 11, 68, 1.0, 150.00),
(153, 303, 17, 12, 67, 1.0, 100.00),
(154, 304, 17, 11, 68, 1.0, 150.00),
(155, 305, 17, 11, 68, 0.5, 150.00),
(156, 306, 17, 12, 69, 1.0, 150.00),
(157, 307, 17, 12, 69, 1.0, 150.00),
(158, 308, 17, 12, 69, 1.0, 150.00),
(159, 309, 17, 12, 69, 1.0, 150.00),
(160, 310, 17, 12, 69, 1.0, 150.00),
(161, 311, 17, NULL, NULL, 1.0, 150.00),
(162, 311, 17, 12, 69, 1.0, 150.00),
(163, 312, 17, 12, 69, 1.0, 150.00),
(164, 313, 17, 12, 69, 1.0, 150.00),
(165, 314, 17, 12, 69, 1.0, 150.00),
(166, 315, 17, 11, 70, 1.0, 51.00),
(167, 316, 17, 11, 70, 1.0, 51.00),
(168, 317, 17, 11, 70, 23.0, 51.00),
(169, 318, 17, 11, 75, 25.0, 600.00),
(170, 319, 17, 11, 75, 5.0, 600.00),
(171, 320, 17, 13, 74, 30.0, 500.00),
(172, 321, 17, 12, 73, 5.0, 350.00),
(173, 322, 17, 12, 73, 10.0, 350.00),
(174, 323, 17, 12, 73, 3.0, 350.00),
(175, 324, 17, 12, 73, 1.0, 350.00),
(176, 325, 17, 12, 73, 1.0, 350.00),
(177, 326, 17, 13, 74, 1.0, 350.00),
(178, 327, 17, 13, 74, 1.0, 350.00),
(179, 328, 17, 11, 85, 2.0, 550.00),
(180, 328, 17, 13, 74, 10.0, 350.00),
(181, 329, 20, 13, 84, 1.0, 770.00),
(182, 329, 20, 14, 83, 1.0, 640.00),
(183, 330, 20, 13, 84, 1.0, 770.00),
(184, 330, 20, 14, 83, 1.0, 640.00),
(185, 331, 17, 13, 74, 1.0, 350.00),
(186, 332, 17, 13, 74, 1.0, 350.00),
(187, 333, 20, 14, 83, 1.0, 640.00),
(188, 334, 20, 13, 84, 1.0, 770.00),
(189, 335, 20, 14, 83, 1.0, 640.00),
(190, 336, 20, 14, 83, 1.0, 640.00),
(191, 337, 20, 14, 83, 1.0, 640.00),
(192, 338, 17, 13, 74, 1.0, 350.00),
(193, 339, 20, 14, 83, 1.0, 640.00),
(194, 340, 20, 13, 84, 1.1, 770.00),
(195, 341, 20, 14, 83, 4.0, 640.00),
(196, 342, 20, 14, 83, 1.0, 640.00),
(197, 343, 20, 14, 83, 70.0, 640.00),
(198, 344, 20, 14, 83, 70.0, 640.00),
(199, 345, 28, 17, 94, 15.0, 284.00),
(200, 346, 22, 12, 92, 10.0, 390.00),
(201, 266, 14, NULL, NULL, 1.0, 1200.00),
(202, 266, 20, NULL, NULL, 1.0, 1200.00),
(203, 266, 24, NULL, NULL, 1.0, 1200.00),
(204, 266, 25, NULL, NULL, 1.0, 1200.00),
(208, 347, 27, 15, 100, 5.0, 305.00),
(209, 348, 26, 17, 95, 10.0, 350.00),
(210, 349, 32, 16, 90, 2.0, 150.00),
(211, 350, 21, 15, 88, 1.0, 248.00),
(212, 351, 28, 17, 104, 7.0, 50.00),
(213, 352, 22, 12, 103, 6.0, 40.00),
(214, 353, 24, 19, 87, 4.0, 400.00),
(215, 354, 28, 17, 104, 9.0, 50.00),
(216, 355, 24, 19, 87, 1.0, 400.00),
(217, 356, 21, 15, 88, 6.0, 248.00),
(218, 357, 25, 19, 102, 4.0, 395.00),
(219, 358, 22, 12, 105, 15.0, 370.00),
(220, 359, 23, 18, 101, 15.0, 185.00),
(221, 360, 20, 11, 107, 5.0, 820.00),
(222, 361, 22, 12, 105, 10.0, 370.00),
(223, 362, 22, 12, 105, 1.0, 370.00),
(224, 362, 29, 20, 91, 1.0, 320.00),
(225, 363, 20, 11, 107, 1.0, 820.00),
(226, 364, 25, 19, 102, 2.0, 395.00),
(227, 365, 24, 19, 87, 2.0, 400.00),
(228, 366, 17, 13, 74, 7.0, 350.00),
(229, 367, 17, 13, 74, 1.0, 350.00),
(230, 368, 17, 13, 74, 15.0, 350.00),
(231, 369, 24, 19, 87, 2.0, 400.00),
(232, 370, 28, 15, 108, 1.0, 340.00),
(233, 371, 28, 15, 109, 3.0, 340.00),
(234, 372, 23, 18, 101, 10.0, 185.00),
(235, 373, 20, 13, 106, 2.0, 920.00),
(236, 373, 26, 17, 95, 1.0, 350.00),
(237, 374, 22, 12, 105, 1.0, 370.00),
(238, 375, 22, 12, 105, 2.0, 370.00),
(239, 376, 22, 12, 105, 1.0, 370.00),
(240, 377, 26, 17, 95, 1.0, 350.00),
(241, 378, 22, 12, 105, 1.0, 370.00),
(242, 379, 27, 15, 100, 1.0, 305.00),
(243, 380, 23, 18, 110, 1.0, 350.00),
(244, 381, 28, 15, 109, 1.0, 340.00),
(245, 382, 17, 13, 74, 12.0, 350.00),
(246, 383, 22, 12, 105, 1.0, 370.00),
(247, 384, 22, 12, 105, 1.0, 370.00),
(248, 385, 31, 16, 93, 1.0, 130.00),
(249, 386, 22, 12, 105, 1.0, 370.00),
(250, 387, 31, 16, 93, 1.0, 130.00),
(251, 388, 20, 13, 106, 1.0, 920.00),
(252, 389, 20, 11, 107, 11.0, 820.00),
(253, 389, 20, 13, 106, 1.0, 920.00),
(254, 390, 26, 17, 95, 5.0, 350.00),
(255, 390, 30, 20, 89, 5.0, 130.00),
(256, 391, 23, 18, 110, 1.0, 350.00),
(257, 392, 29, 20, 91, 1.0, 320.00),
(258, 393, 23, 18, 110, 1.0, 350.00),
(259, 394, 20, 11, 107, 40.0, 820.00),
(260, 394, 20, 13, 106, 1.0, 920.00),
(261, 395, 31, 16, 93, 10.0, 130.00),
(262, 396, 23, 18, 110, 1.0, 350.00),
(263, 397, 25, 20, 111, 3.0, 310.00),
(264, 398, 27, 15, 100, 4.0, 305.00),
(265, 399, 27, 15, 100, 4.0, 305.00),
(266, 400, 27, 12, 117, 10.0, 370.00),
(267, 401, 23, 11, 121, 40.0, 300.00),
(268, 402, 24, 1, 115, 20.0, 320.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_ratings`
--

CREATE TABLE `order_ratings` (
  `rating_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rating_image` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded rating image',
  `image_uploaded_at` timestamp NULL DEFAULT NULL COMMENT 'When the image was uploaded'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_ratings`
--

INSERT INTO `order_ratings` (`rating_id`, `order_id`, `user_id`, `rating`, `review`, `created_at`, `updated_at`, `rating_image`, `image_uploaded_at`) VALUES
(1, 171, 3, 5, 'angas', '2025-09-17 09:00:06', '2025-09-17 09:00:06', NULL, NULL),
(2, 170, 3, 4, 'solid to', '2025-09-17 09:04:57', '2025-09-17 09:04:57', NULL, NULL),
(3, 173, 9, 5, 'let\'s go!', '2025-09-17 09:16:17', '2025-09-17 09:16:17', NULL, NULL),
(4, 175, 10, 3, 'awit', '2025-09-17 15:39:59', '2025-09-17 15:39:59', NULL, NULL),
(5, 195, 3, 5, 'hello', '2025-10-06 15:58:39', '2025-10-06 15:58:39', NULL, NULL),
(6, 222, 3, 5, '', '2025-10-08 11:46:56', '2025-10-08 11:46:56', NULL, NULL),
(7, 221, 3, 5, '', '2025-10-08 11:48:43', '2025-10-08 11:48:43', NULL, NULL),
(8, 169, 3, 5, '', '2025-10-08 12:02:26', '2025-10-08 12:02:26', NULL, NULL),
(9, 213, 3, 5, 'yown', '2025-10-12 08:00:07', '2025-10-12 08:00:07', NULL, NULL),
(10, 302, 3, 5, 'YEHEY', '2025-10-12 08:02:29', '2025-10-12 08:02:29', NULL, NULL),
(11, 174, 9, 5, 'Responsive Seller', '2025-10-17 03:22:17', '2025-10-17 03:22:17', NULL, NULL),
(12, 343, 11, 5, '', '2025-10-19 13:29:18', '2025-10-19 13:29:18', NULL, NULL),
(13, 344, 11, 5, '', '2025-10-19 13:30:42', '2025-10-19 13:30:42', NULL, NULL),
(14, 345, 11, 5, '', '2025-10-19 14:19:17', '2025-10-19 14:19:17', NULL, NULL),
(15, 347, 11, 4, '', '2025-10-19 15:52:38', '2025-10-19 15:52:38', NULL, NULL),
(16, 346, 11, 4, '', '2025-10-19 15:52:47', '2025-10-19 15:52:47', NULL, NULL),
(17, 348, 11, 3, '', '2025-10-19 15:57:51', '2025-10-19 15:57:51', NULL, NULL),
(18, 311, 11, 5, '', '2025-10-19 16:02:33', '2025-10-19 16:02:33', NULL, NULL),
(19, 349, 11, 5, '', '2025-10-19 16:02:40', '2025-10-19 16:02:40', NULL, NULL),
(20, 341, 3, 3, '', '2025-10-20 01:18:27', '2025-10-20 01:18:27', NULL, NULL),
(21, 320, 9, 4, '', '2025-10-20 02:13:08', '2025-10-20 02:13:08', NULL, NULL),
(22, 351, 11, 3, '', '2025-10-20 05:10:49', '2025-10-20 05:10:49', NULL, NULL),
(23, 354, 3, 5, '', '2025-10-21 01:36:32', '2025-10-21 01:36:32', NULL, NULL),
(24, 342, 3, 4, '', '2025-10-21 01:36:38', '2025-10-21 01:36:38', NULL, NULL),
(25, 339, 3, 4, '', '2025-10-21 01:36:46', '2025-10-21 01:36:46', NULL, NULL),
(26, 338, 3, 4, '', '2025-10-21 01:36:54', '2025-10-21 01:36:54', NULL, NULL),
(27, 212, 10, 5, 'Satisfied Customer here!!', '2025-10-21 03:12:42', '2025-10-21 03:12:42', NULL, NULL),
(28, 356, 10, 4, 'Kids love it! Easy to cook, golden and crunchy after frying. Will definitely order again for baon.', '2025-10-21 03:14:49', '2025-10-21 03:14:49', NULL, NULL),
(29, 355, 3, 5, '', '2025-10-21 03:17:09', '2025-10-21 03:17:09', NULL, NULL),
(30, 357, 11, 4, 'Super tender after simmering for a few hours! The bone marrow melts perfectly, and the soup comes out rich and flavorful. Feels like eating in Tagaytay!', '2025-10-21 03:19:59', '2025-10-21 03:19:59', NULL, NULL),
(31, 322, 9, 4, '', '2025-10-21 16:13:40', '2025-10-21 16:13:40', NULL, NULL),
(32, 323, 9, 4, 'Medyo matigas kapag minadali ang luto, pero sobrang sarap kapag pinakuluan nang matagal.', '2025-10-22 03:28:41', '2025-10-22 06:12:00', NULL, NULL),
(33, 360, 9, 5, 'Malambot, malasa, at perfect pang-steak o ginisa', '2025-10-22 03:54:23', '2025-10-22 06:12:00', NULL, NULL),
(34, 321, 9, 4, 'Thank you Seller!', '2025-10-22 05:05:23', '2025-10-22 05:05:23', NULL, NULL),
(35, 211, 10, 4, '', '2025-10-22 05:06:10', '2025-10-22 05:06:10', NULL, NULL),
(36, 210, 10, 3, '', '2025-10-22 05:06:19', '2025-10-22 05:06:19', NULL, NULL),
(37, 361, 10, 5, 'sobrang crispy!!!', '2025-10-22 05:08:49', '2025-10-22 05:08:49', NULL, NULL),
(38, 362, 20, 4, 'Thank you po!', '2025-10-22 05:48:04', '2025-10-22 05:48:04', NULL, NULL),
(39, 363, 20, 5, 'Sarap pang Steak!!', '2025-10-22 05:49:52', '2025-10-22 05:49:52', NULL, NULL),
(40, 364, 20, 4, 'solid pang bulalo talaga, thank you selleeeeer!!', '2025-10-22 05:52:39', '2025-10-22 05:52:39', NULL, NULL),
(41, 365, 20, 4, '', '2025-10-22 05:59:44', '2025-10-22 05:59:44', NULL, NULL),
(42, 366, 20, 2, '', '2025-10-22 06:19:52', '2025-10-22 06:19:52', NULL, NULL),
(43, 367, 20, 4, '', '2025-10-22 07:04:44', '2025-10-22 07:04:44', NULL, NULL),
(44, 368, 3, 4, 'Thank you MikeMadz!!', '2025-10-22 07:06:31', '2025-10-22 07:06:31', NULL, NULL),
(45, 372, 10, 4, '', '2025-10-22 15:46:29', '2025-10-22 15:46:29', NULL, NULL),
(46, 373, 3, 5, 'solid!!', '2025-10-22 16:02:11', '2025-10-22 16:02:11', NULL, NULL),
(47, 386, 3, 3, '', '2025-10-30 04:09:23', '2025-10-30 04:09:23', NULL, NULL),
(48, 381, 3, 3, '', '2025-10-30 04:09:32', '2025-10-30 04:09:32', NULL, NULL),
(49, 380, 3, 3, '', '2025-10-30 04:09:40', '2025-10-30 04:09:40', NULL, NULL),
(50, 379, 3, 4, '', '2025-10-30 04:09:48', '2025-10-30 04:09:48', NULL, NULL),
(51, 378, 3, 5, '', '2025-10-30 04:09:56', '2025-10-30 04:09:56', NULL, NULL),
(52, 390, 3, 3, 'ty', '2025-10-30 04:21:24', '2025-10-30 04:21:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_status`
--

CREATE TABLE `order_status` (
  `orderstatus_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status`
--

INSERT INTO `order_status` (`orderstatus_id`, `status_name`) VALUES
(1, 'Pending'),
(2, 'To Ship'),
(3, 'Out for delivery'),
(4, 'Completed'),
(5, 'Cancelled'),
(6, 'Return'),
(7, 'Order Received'),
(8, 'Ready for Pick Up');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payments_id` int(11) NOT NULL,
  `orders_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('COD','Bank Transfer','Gcash') NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `proof` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(50) DEFAULT NULL COMMENT 'GCash transaction ID or reference number',
  `paymentstatus_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payments_id`, `orders_id`, `amount`, `method`, `payment_date`, `proof`, `transaction_id`, `paymentstatus_id`) VALUES
(2, 167, 814.00, 'Gcash', '2025-09-06 10:56:28', '68bba2dce6339_beef3.jpg', NULL, 1),
(3, 168, 407.00, 'Gcash', '2025-09-06 11:22:33', '68bba8f9ce35d_7fbcafae-1e22-4925-840a-1cffd842feab.jpg', NULL, 1),
(4, 169, 539.00, 'Gcash', '2025-09-06 13:15:21', '68bbc369e2693_05fb0980-cda5-4a0a-9984-e03795dbf537.jpg', NULL, 1),
(5, 170, 132.00, '', '2025-09-06 13:30:45', NULL, NULL, 1),
(6, 171, 264.00, '', '2025-09-06 13:39:41', NULL, NULL, 1),
(7, 172, 275.00, 'Gcash', '2025-09-08 21:48:14', '68bede9eb4cb1_fd74e6f0-b69e-41c3-bd91-aa149e45ab3c.jpg', NULL, 1),
(8, 173, 240.00, '', '2025-09-16 23:09:49', NULL, NULL, 1),
(9, 174, 960.00, 'Gcash', '2025-09-17 20:56:27', '68caaffba5d1a_aac35451-ebb1-4ee3-8f51-f6ae1de10b57.jpg', NULL, 1),
(10, 175, 240.00, '', '2025-09-17 23:35:58', NULL, NULL, 1),
(11, 176, 2400.00, 'Gcash', '2025-09-17 23:37:31', '68cad5bb710fb_1ee55934-6bdb-45bc-8ebe-26175f8adc34.jpg', NULL, 1),
(12, 177, 180.00, '', '2025-09-18 22:16:49', NULL, NULL, 1),
(13, 178, 240.00, '', '2025-09-18 22:18:21', NULL, NULL, 1),
(14, 179, 180.00, 'Gcash', '2025-09-18 22:22:18', '68cc159a26651_images.jpg', NULL, 1),
(15, 180, 180.00, '', '2025-09-18 22:22:54', NULL, NULL, 1),
(16, 181, 144.00, '', '2025-09-19 11:15:24', NULL, NULL, 1),
(17, 182, 144.00, '', '2025-09-22 10:35:08', NULL, NULL, 1),
(18, 183, 198.00, 'Gcash', '2025-09-22 13:08:36', '68d0d9d475e59_beef1.jpg', NULL, 1),
(19, 184, 396.00, '', '2025-09-23 17:33:28', NULL, NULL, 1),
(20, 185, 396.00, '', '2025-09-23 17:34:56', NULL, NULL, 1),
(21, 186, 198.00, '', '2025-09-23 19:29:56', NULL, NULL, 1),
(22, 187, 198.00, '', '2025-09-23 19:30:00', NULL, NULL, 1),
(23, 188, 198.00, '', '2025-09-23 19:30:04', NULL, NULL, 1),
(24, 189, 240.00, '', '2025-09-23 19:30:23', NULL, NULL, 1),
(25, 190, 180.00, 'Gcash', '2025-09-23 19:31:25', '68d2850d8fc48_30a5bc4e-a799-42d4-a475-951ba7320350.jpg', NULL, 1),
(26, 191, 942.00, '', '2025-09-24 16:45:37', NULL, NULL, 1),
(27, 192, 2410.00, 'Gcash', '2025-09-30 20:34:23', '68dbce4fb2436_QUILING-03-ASSIGNMENT1.png', NULL, 1),
(28, 193, 10.00, 'Gcash', '2025-09-30 20:51:51', '68dbd267e469a_trimmings3.jpg', '123123123123', 1),
(29, 194, 8.00, 'Gcash', '2025-09-30 20:59:40', '68dbd43ca2ba4_trimmings3.jpg', '123123123123', 1),
(30, 195, 10.00, 'Gcash', '2025-09-30 21:02:18', '68dbd4dae6456_trimmings2.jpg', '123123123', 1),
(31, 196, 12.00, '', '2025-10-07 10:39:49', '', '', 1),
(34, 199, 748.00, '', '2025-10-07 11:13:14', '', '', 1),
(35, 200, 630.00, '', '2025-10-07 11:18:10', '', '', 1),
(36, 201, 270.00, '', '2025-10-07 11:23:43', '', '', 1),
(37, 202, 270.00, '', '2025-10-07 11:31:40', '', '', 1),
(38, 203, 2070.00, 'Gcash', '2025-10-07 11:36:01', '68e48aa1b8290_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(39, 204, 540.00, '', '2025-10-07 11:55:27', '', '', 1),
(40, 205, 630.00, '', '2025-10-07 11:56:06', '', '', 1),
(41, 206, 270.00, '', '2025-10-07 12:03:39', '', '', 1),
(42, 207, 270.00, '', '2025-10-07 12:23:52', '', '', 1),
(43, 208, 25.00, '', '2025-10-07 12:29:15', '', '', 1),
(44, 209, 12.00, '', '2025-10-07 12:33:42', '', '', 1),
(45, 210, 3996.00, 'Gcash', '2025-10-07 12:39:26', '68e4997e41d2e_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(46, 211, 495.00, '', '2025-10-07 12:43:14', '', '', 1),
(47, 212, 693.00, '', '2025-10-07 12:43:50', '', '', 1),
(48, 213, 1627.50, '', '2025-10-08 14:08:47', '', '', 1),
(49, 214, 10.00, '', '2025-10-08 14:14:29', '', '', 1),
(50, 215, 35.00, '', '2025-10-08 18:23:47', '', '', 1),
(51, 216, 20.00, '', '2025-10-08 18:45:44', '', '', 1),
(52, 217, 20.00, '', '2025-10-08 18:45:50', '', '', 1),
(53, 218, 20.00, '', '2025-10-08 18:46:01', '', '', 1),
(54, 219, 20.00, '', '2025-10-08 18:46:16', '', '', 1),
(55, 220, 20.00, '', '2025-10-08 18:46:21', '', '', 1),
(56, 221, 20.00, '', '2025-10-08 18:47:30', '', '', 1),
(57, 222, 20.00, '', '2025-10-08 19:00:53', '', '', 1),
(58, 223, 150.00, '', '2025-10-08 19:01:37', '', '', 1),
(59, 224, 300.00, 'Gcash', '2025-10-08 21:23:20', '68e665c8dd057_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1231231231231', 1),
(60, 225, 150.00, 'Gcash', '2025-10-08 21:34:03', '68e6684b2b05c_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111131333451', 1),
(61, 226, 150.00, 'Gcash', '2025-10-08 21:36:22', '68e668d67499c_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1234567891111', 1),
(62, 227, 150.00, '', '2025-10-08 21:42:57', '', '', 1),
(63, 228, 150.00, '', '2025-10-08 21:58:48', '', '', 1),
(64, 229, 150.00, '', '2025-10-08 22:11:19', '', '', 1),
(65, 230, 150.00, '', '2025-10-08 22:12:36', '', '', 1),
(66, 231, 300.00, '', '2025-10-08 22:31:36', '', '', 1),
(67, 232, 150.00, '', '2025-10-08 22:50:44', '', '', 1),
(68, 233, 250.00, '', '2025-10-08 22:53:07', '', '', 1),
(69, 234, 250.00, '', '2025-10-08 22:58:16', '', '', 1),
(70, 235, 250.00, '', '2025-10-08 23:02:07', '', '', 1),
(71, 236, 250.00, '', '2025-10-08 23:07:38', '', '', 1),
(72, 237, 250.00, 'Gcash', '2025-10-08 23:10:32', '68e67ee8cfbad_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1231231231231', 1),
(73, 238, 6750.00, 'Gcash', '2025-10-08 23:14:58', '68e67ff2b798e_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1234567891111', 1),
(74, 239, 400.00, '', '2025-10-09 11:54:09', '', '', 1),
(75, 240, 400.00, '', '2025-10-09 11:55:53', 'C:\\fakepath\\c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(76, 241, 400.00, '', '2025-10-09 11:56:09', '', '', 1),
(77, 242, 400.00, '', '2025-10-09 11:56:36', '', '', 1),
(78, 243, 1200.00, '', '2025-10-09 12:28:08', '', '', 1),
(79, 244, 300.00, '', '2025-10-09 12:29:59', '', '', 1),
(80, 245, 150.00, '', '2025-10-09 12:31:51', '', '', 1),
(81, 246, 150.00, '', '2025-10-09 12:34:15', '', '', 1),
(82, 247, 100.00, '', '2025-10-09 12:38:56', '', '', 1),
(83, 248, 150.00, '', '2025-10-09 12:53:55', '', '', 1),
(84, 249, 150.00, '', '2025-10-09 14:31:38', '', '', 1),
(85, 250, 300.00, '', '2025-10-09 21:28:21', '', '', 1),
(86, 251, 2166.00, 'Gcash', '2025-10-11 23:11:54', '68ea73baced61_images.jpg', '1111111111111', 1),
(87, 252, 2166.00, 'Gcash', '2025-10-11 23:13:00', '68ea73fc6fbb0_neck.jpg', '1111111111111', 1),
(88, 253, 1415.00, '', '2025-10-11 23:22:48', '', '', 1),
(89, 254, 1415.00, '', '2025-10-12 11:23:12', '', '', 1),
(90, 255, 1415.00, '', '2025-10-12 11:50:22', '', '', 1),
(91, 256, 1415.00, '', '2025-10-12 12:39:51', '', '', 1),
(92, 257, 1415.00, '', '2025-10-12 12:44:05', '', '', 1),
(93, 258, 1415.00, 'Gcash', '2025-10-12 12:47:00', '68eb32c4b85d2_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(94, 259, 1415.00, '', '2025-10-12 12:49:56', '', '', 1),
(95, 260, 1415.00, '', '2025-10-12 12:59:06', '', '', 1),
(96, 261, 1415.00, '', '2025-10-12 13:09:06', '', '', 1),
(97, 262, 585.00, '', '2025-10-12 13:11:27', '', '', 1),
(98, 263, 585.00, '', '2025-10-12 13:16:20', '', '', 1),
(99, 264, 50.00, '', '2025-10-12 13:52:45', '', '', 1),
(100, 265, 50.00, '', '2025-10-12 14:07:02', '', '', 1),
(101, 266, 1200.00, '', '2025-10-12 14:12:29', '', '', 1),
(102, 267, 1200.00, '', '2025-10-12 14:12:42', '', '', 1),
(103, 268, 650.00, '', '2025-10-12 14:12:56', '', '', 1),
(104, 269, 550.00, '', '2025-10-12 14:13:35', '', '', 1),
(105, 270, 650.00, '', '2025-10-12 14:17:57', '', '', 1),
(106, 271, 150.00, '', '2025-10-12 14:20:00', '', '', 1),
(107, 272, 450.00, '', '2025-10-12 14:21:24', '', '', 1),
(108, 273, 450.00, '', '2025-10-12 14:21:30', '', '', 1),
(109, 274, 450.00, 'Gcash', '2025-10-12 14:21:47', '68eb48fb37da8_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111131333451', 1),
(110, 275, 150.00, '', '2025-10-12 14:22:02', '', '', 1),
(111, 276, 300.00, '', '2025-10-12 14:22:20', '', '', 1),
(112, 277, 102.00, '', '2025-10-12 14:23:26', '', '', 1),
(113, 278, 51.00, '', '2025-10-12 14:23:52', '', '', 1),
(114, 279, 2150.00, 'Gcash', '2025-10-12 14:26:31', '68eb4a17bda91_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1231231231231', 1),
(115, 280, 2150.00, 'Gcash', '2025-10-12 14:26:56', '68eb4a304090c_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1231231231231', 1),
(116, 281, 2150.00, 'Gcash', '2025-10-12 14:32:55', '68eb4b974a005_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1231231231231', 1),
(117, 282, 225.00, '', '2025-10-12 14:33:18', '', '', 1),
(118, 283, 700.00, '', '2025-10-12 14:33:33', '', '', 1),
(119, 284, 700.00, '', '2025-10-12 14:38:41', '', '', 1),
(120, 285, 550.00, '', '2025-10-12 14:39:05', '', '', 1),
(121, 286, 700.00, '', '2025-10-12 14:39:18', '', '', 1),
(122, 287, 700.00, '', '2025-10-12 14:41:33', '', '', 1),
(123, 288, 700.00, '', '2025-10-12 14:43:06', '', '', 1),
(124, 289, 700.00, '', '2025-10-12 14:43:37', '', '', 1),
(125, 290, 700.00, 'Gcash', '2025-10-12 14:46:17', '68eb4eb905747_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1231231231231', 1),
(126, 291, 700.00, '', '2025-10-12 14:46:48', '', '', 1),
(127, 292, 550.00, '', '2025-10-12 14:47:24', '', '', 1),
(128, 293, 700.00, '', '2025-10-12 14:48:11', '', '', 1),
(129, 294, 700.00, '', '2025-10-12 14:51:11', '', '', 1),
(130, 295, 700.00, '', '2025-10-12 14:52:03', '', '', 1),
(131, 296, 700.00, '', '2025-10-12 14:52:11', '', '', 1),
(132, 297, 700.00, '', '2025-10-12 14:52:35', '', '', 1),
(133, 298, 700.00, '', '2025-10-12 14:53:21', '', '', 1),
(134, 299, 700.00, '', '2025-10-12 14:56:42', '', '', 1),
(135, 300, 550.00, '', '2025-10-12 15:00:06', '', '', 1),
(136, 301, 550.00, '', '2025-10-12 15:08:27', '', '', 1),
(137, 302, 275.00, 'Gcash', '2025-10-12 15:08:59', '68eb540b1bedb_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1231231231231', 1),
(138, 303, 250.00, 'Gcash', '2025-10-12 19:29:53', '68eb9131aff90_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1234567891111', 1),
(139, 304, 150.00, '', '2025-10-12 22:46:03', '', '', 1),
(140, 305, 75.00, '', '2025-10-12 22:50:10', '', '', 1),
(141, 306, 150.00, '', '2025-10-12 22:58:53', '', '', 1),
(142, 307, 150.00, '', '2025-10-12 23:06:05', '', '', 1),
(143, 308, 150.00, '', '2025-10-12 23:51:21', '', '', 1),
(144, 309, 150.00, '', '2025-10-12 23:52:14', '', '', 1),
(145, 310, 150.00, '', '2025-10-12 23:55:58', '', '', 1),
(146, 311, 300.00, '', '2025-10-13 00:02:52', '', '', 1),
(147, 312, 150.00, 'Gcash', '2025-10-13 15:15:04', '68eca6f89eac5_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(148, 313, 150.00, '', '2025-10-13 15:51:47', '', '', 1),
(149, 314, 150.00, '', '2025-10-13 15:52:13', '', '', 1),
(150, 315, 51.00, '', '2025-10-13 15:52:58', '', '', 1),
(151, 316, 51.00, '', '2025-10-13 15:54:09', '', '', 1),
(152, 317, 1173.00, '', '2025-10-16 23:13:29', '', '', 1),
(153, 318, 15000.00, 'Gcash', '2025-10-17 08:53:03', '68f1936fba81d_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(154, 319, 3000.00, 'Gcash', '2025-10-17 09:00:27', '68f1952bdb79a_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(155, 320, 15000.00, 'Gcash', '2025-10-17 10:55:50', '68f1b0362b258_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(156, 321, 1750.00, '', '2025-10-17 10:56:31', '', '', 1),
(157, 322, 3500.00, 'Gcash', '2025-10-19 02:06:00', '68f3d708d6ac5_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(158, 323, 1050.00, '', '2025-10-19 02:24:18', '', '', 1),
(159, 324, 350.00, 'Gcash', '2025-10-19 02:52:40', '68f3e1f8729b9_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1111111111111', 1),
(160, 325, 350.00, 'Gcash', '2025-10-19 03:03:10', '68f3e46ed89d7_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '3875637861231', 1),
(161, 326, 350.00, 'Gcash', '2025-10-19 03:05:28', '68f3e4f8cac10_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '7874564563435', 1),
(162, 327, 350.00, 'Gcash', '2025-10-19 03:07:47', '68f3e583ee2f8_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '5454354312312', 1),
(163, 328, 4600.00, 'Gcash', '2025-10-19 16:18:07', '68f49ebf5492c_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '7547547455454', 1),
(164, 329, 1410.00, '', '2025-10-19 16:19:58', '', '', 1),
(165, 330, 1410.00, '', '2025-10-19 16:21:36', '', '', 1),
(166, 331, 1120.00, '', '2025-10-19 16:22:54', '', '', 1),
(167, 332, 990.00, '', '2025-10-19 16:23:46', '', '', 1),
(168, 333, 640.00, '', '2025-10-19 16:26:07', '', '', 1),
(169, 334, 770.00, '', '2025-10-19 16:29:00', '', '', 1),
(170, 335, 640.00, '', '2025-10-19 16:30:59', '', '', 1),
(171, 336, 640.00, '', '2025-10-19 16:37:18', '', '', 1),
(172, 337, 640.00, '', '2025-10-19 16:38:12', '', '', 1),
(173, 338, 350.00, '', '2025-10-19 16:46:20', '', '', 1),
(174, 339, 640.00, '', '2025-10-19 16:51:54', '', '', 1),
(175, 340, 847.00, '', '2025-10-19 16:52:09', '', '', 1),
(176, 341, 2560.00, 'Gcash', '2025-10-19 16:52:37', '68f4a6d5b6f27_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '7656767566756', 1),
(177, 342, 640.00, '', '2025-10-19 16:52:59', '', '', 1),
(178, 343, 44800.00, 'Gcash', '2025-10-19 21:28:49', '68f4e791d7762_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '7687687687656', 1),
(179, 344, 44800.00, 'Gcash', '2025-10-19 21:30:03', '68f4e7db9800b_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '8768798798798', 1),
(180, 345, 4260.00, 'Gcash', '2025-10-19 22:17:01', '68f4f2ddb6bac_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '1232133212133', 1),
(181, 346, 3900.00, 'Gcash', '2025-10-19 22:34:07', '68f4f6df2cc37_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '8879978879879', 1),
(182, 266, 1200.00, 'Gcash', '2025-10-19 23:06:00', 'test_payment_proof_4.jpg', 'GCASH1234567890123456789', 1),
(183, 266, 1200.00, 'Gcash', '2025-10-19 23:06:46', 'test_payment_proof_4.jpg', 'GCASH1234567890123456789', 1),
(184, 347, 1525.00, '', '2025-10-19 23:44:06', '', '', 1),
(185, 348, 3500.00, 'Gcash', '2025-10-19 23:53:46', '68f5098a2a336_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '8787897898797', 1),
(186, 349, 300.00, '', '2025-10-19 23:58:28', '', '', 1),
(187, 350, 248.00, 'Gcash', '2025-10-20 08:31:05', '68f582c9bdd7d_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '8787897898797', 1),
(188, 351, 350.00, '', '2025-10-20 13:08:23', '', '', 1),
(189, 352, 240.00, '', '2025-10-20 13:11:12', '', '', 1),
(190, 353, 1600.00, '', '2025-10-20 21:13:52', '', '', 1),
(191, 354, 450.00, '', '2025-10-21 09:31:59', '', '', 1),
(192, 355, 400.00, 'Gcash', '2025-10-21 09:47:51', '68f6e6476b588_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '234675121', 1),
(193, 356, 1488.00, '', '2025-10-21 11:14:27', '', '', 1),
(194, 357, 1580.00, '', '2025-10-21 11:19:24', '', '', 1),
(195, 358, 5550.00, 'Gcash', '2025-10-22 00:22:04', '68f7b32ca0e1f_6158726250991045979.jpg', '1235334', 1),
(196, 359, 2775.00, 'Gcash', '2025-10-22 01:24:55', '68f7c1e719512_6158726250991045979.jpg', '3121132', 1),
(197, 360, 4100.00, 'Gcash', '2025-10-22 11:51:00', '68f854a46e4f2_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '3243241212', 1),
(198, 361, 3700.00, 'Gcash', '2025-10-22 13:07:45', '68f866a1a981f_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '657654343', 1),
(199, 362, 690.00, '', '2025-10-22 13:45:09', '', '', 1),
(200, 363, 820.00, '', '2025-10-22 13:48:59', '', '', 1),
(201, 364, 790.00, '', '2025-10-22 13:51:35', '', '', 1),
(202, 365, 800.00, '', '2025-10-22 13:57:59', '', '', 1),
(203, 366, 2450.00, 'Gcash', '2025-10-22 14:14:14', '68f876369324a_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '665898912', 1),
(204, 367, 350.00, '', '2025-10-22 15:04:18', '', '', 1),
(205, 368, 5250.00, 'Gcash', '2025-10-22 15:05:45', '68f882497a722_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '125325632', 1),
(206, 369, 800.00, '', '2025-10-22 20:44:47', '', '', 1),
(207, 370, 600.00, '', '2025-10-22 20:47:40', '', '', 1),
(208, 371, 1020.00, '', '2025-10-22 20:54:57', '', '', 1),
(209, 372, 1480.00, '', '2025-10-22 23:45:07', '', '', 1),
(210, 373, 1752.00, '', '2025-10-22 23:58:50', '', '', 1),
(211, 374, 370.00, '', '2025-10-24 09:08:23', '', '', 1),
(212, 375, 740.00, '', '2025-10-24 09:51:43', '', '', 1),
(213, 376, 370.00, '', '2025-10-24 09:58:02', '', '', 1),
(214, 377, 350.00, '', '2025-10-24 10:01:00', '', '', 1),
(215, 378, 370.00, '', '2025-10-24 10:04:21', '', '', 1),
(216, 379, 70.00, '', '2025-10-24 23:06:14', '', '', 1),
(217, 380, 350.00, '', '2025-10-24 23:08:26', '', '', 1),
(218, 381, 340.00, '', '2025-10-25 18:40:13', '', '', 1),
(219, 382, 4200.00, 'Gcash', '2025-10-25 21:52:42', '68fcd62a71d4d_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '2131264', 1),
(220, 383, 370.00, '', '2025-10-25 21:54:42', '', '', 1),
(221, 384, 351.50, '', '2025-10-26 12:52:53', '', '', 1),
(222, 385, 130.00, '', '2025-10-26 17:39:04', '', '', 1),
(223, 386, 370.00, 'Gcash', '2025-10-26 17:40:28', '68fdec8cb0baa_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '321312', 1),
(224, 387, 130.00, 'Gcash', '2025-10-26 21:01:29', '68fe1ba929bc9_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '184731261', 1),
(225, 388, 920.00, 'Gcash', '2025-10-26 21:06:22', '68fe1cce0191f_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '09897878873', 1),
(226, 389, 9940.00, 'Gcash', '2025-10-30 12:09:14', '6902e4eab9150_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '8767832423432', 1),
(227, 390, 2400.00, 'Gcash', '2025-10-30 12:17:19', '6902e6cfe8475_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '2222222222222', 1),
(228, 391, 50.00, '', '2025-10-30 20:32:42', '', '', 1),
(229, 392, 320.00, '', '2025-10-30 20:56:16', '', '', 1),
(230, 393, 350.00, 'Gcash', '2025-10-30 21:11:14', '690363f2df316_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '31232123', 1),
(231, 394, 33720.00, 'Gcash', '2025-10-30 21:18:50', '690365baaf231_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '2321321331235', 1),
(232, 395, 1300.00, '', '2025-10-30 21:47:52', '', '', 1),
(233, 396, 350.00, '', '2025-10-31 16:12:01', '', '', 1),
(234, 397, 930.00, '', '2025-10-31 16:24:40', '', '', 1),
(235, 398, 1220.00, '', '2025-10-31 19:12:59', '', '', 1),
(236, 399, 1220.00, '', '2025-10-31 19:13:50', '', '', 1),
(237, 400, 3700.00, 'Gcash', '2025-10-31 19:22:12', '69049be411a16_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '3212323131212', 1),
(238, 401, 12000.00, 'Gcash', '2025-10-31 19:46:35', '6904a19b2c46f_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '3232323223322', 1),
(239, 402, 6400.00, 'Gcash', '2025-10-31 20:27:08', '6904ab1c449df_c3b3ef3d94cddaf939d67ae0f9126927.jpg', '3232323223323', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payment_status`
--

CREATE TABLE `payment_status` (
  `paymentstatus_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_status`
--

INSERT INTO `payment_status` (`paymentstatus_id`, `status_name`) VALUES
(1, 'Pending'),
(2, 'Completed'),
(3, 'Failed'),
(4, 'Refunded');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `permission_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `module` varchar(50) DEFAULT 'system',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_description`, `created_at`, `module`, `description`) VALUES
(1, 'user_create', 'Create new users', '2025-09-04 13:24:40', 'users', NULL),
(2, 'user_read', 'View user information', '2025-09-04 13:24:40', 'users', NULL),
(3, 'user_update', 'Update user information', '2025-09-04 13:24:40', 'users', NULL),
(4, 'user_delete', 'Delete users', '2025-09-04 13:24:40', 'users', NULL),
(5, 'user_deactivate', 'Deactivate users', '2025-09-04 13:24:40', 'users', NULL),
(6, 'product_create', 'Create new products', '2025-09-04 13:24:40', 'products', NULL),
(7, 'product_read', 'View products', '2025-09-04 13:24:40', 'products', NULL),
(8, 'product_update', 'Update product information', '2025-09-04 13:24:40', 'products', NULL),
(9, 'product_delete', 'Delete products', '2025-09-04 13:24:40', 'products', NULL),
(10, 'product_archive', 'Archive products', '2025-09-04 13:24:40', 'system', NULL),
(11, 'inventory_view', 'View inventory levels', '2025-09-04 13:24:40', 'system', NULL),
(12, 'inventory_restock', 'Record restocking', '2025-09-04 13:24:40', 'system', NULL),
(13, 'inventory_adjust', 'Adjust stock levels', '2025-09-04 13:24:40', 'inventory', NULL),
(14, 'inventory_reports', 'View inventory reports', '2025-09-04 13:24:40', 'system', NULL),
(15, 'order_view', 'View orders', '2025-09-04 13:24:40', 'system', NULL),
(16, 'order_update', 'Update order status', '2025-09-04 13:24:40', 'orders', NULL),
(17, 'order_cancel', 'Cancel orders', '2025-09-04 13:24:40', 'system', NULL),
(18, 'order_refund', 'Process refunds', '2025-09-04 13:24:40', 'system', NULL),
(19, 'category_create', 'Create categories', '2025-09-04 13:24:40', 'system', NULL),
(20, 'category_read', 'View categories', '2025-09-04 13:24:40', 'system', NULL),
(21, 'category_update', 'Update categories', '2025-09-04 13:24:40', 'system', NULL),
(22, 'category_delete', 'Delete categories', '2025-09-04 13:24:40', 'system', NULL),
(23, 'brand_create', 'Create brands', '2025-09-04 13:24:40', 'system', NULL),
(24, 'brand_read', 'View brands', '2025-09-04 13:24:40', 'system', NULL),
(25, 'brand_update', 'Update brands', '2025-09-04 13:24:40', 'system', NULL),
(26, 'brand_delete', 'Delete brands', '2025-09-04 13:24:40', 'system', NULL),
(27, 'supplier_create', 'Create suppliers', '2025-09-04 13:24:40', 'suppliers', NULL),
(28, 'supplier_read', 'View suppliers', '2025-09-04 13:24:40', 'suppliers', NULL),
(29, 'supplier_update', 'Update suppliers', '2025-09-04 13:24:40', 'suppliers', NULL),
(30, 'supplier_delete', 'Delete suppliers', '2025-09-04 13:24:40', 'suppliers', NULL),
(31, 'uom_create', 'Create units of measure', '2025-09-04 13:24:40', 'system', NULL),
(32, 'uom_read', 'View units of measure', '2025-09-04 13:24:40', 'system', NULL),
(33, 'uom_update', 'Update units of measure', '2025-09-04 13:24:40', 'system', NULL),
(34, 'uom_delete', 'Delete units of measure', '2025-09-04 13:24:40', 'system', NULL),
(35, 'reports_view', 'View reports', '2025-09-04 13:24:40', 'system', NULL),
(36, 'reports_export', 'Export reports', '2025-09-04 13:24:40', 'system', NULL),
(37, 'reports_generate', 'Generate custom reports', '2025-09-04 13:24:40', 'system', NULL),
(38, 'system_settings', 'Manage system settings', '2025-09-04 13:24:40', 'system', NULL),
(39, 'system_logs', 'View system logs', '2025-09-04 13:24:40', 'system', NULL),
(40, 'system_backup', 'Create system backups', '2025-09-04 13:24:40', 'system', NULL),
(41, 'system_restore', 'Restore system from backup', '2025-09-04 13:24:40', 'system', NULL),
(42, 'history_view', 'View activity history', '2025-09-04 13:24:40', 'system', NULL),
(43, 'history_export', 'Export activity history', '2025-09-04 13:24:40', 'system', NULL),
(88, 'inventory_adjustment', NULL, '2025-09-04 15:13:40', 'inventory', 'Stock Adjustments'),
(89, 'products_archive', NULL, '2025-09-04 15:13:40', 'products', 'Archive Products'),
(90, 'products_import', NULL, '2025-09-04 15:13:40', 'products', 'Import Products'),
(91, 'orders_process', NULL, '2025-09-04 15:13:40', 'orders', 'Process Orders'),
(92, 'orders_cancel', NULL, '2025-09-04 15:13:40', 'orders', 'Cancel Orders'),
(94, 'reports_analytics', NULL, '2025-09-04 15:13:40', 'reports', 'View Analytics'),
(95, 'users_permissions', NULL, '2025-09-04 15:13:40', 'users', 'Manage User Permissions'),
(96, 'suppliers_manage', NULL, '2025-09-04 15:13:40', 'suppliers', 'Manage Suppliers'),
(97, 'transactions_manage', NULL, '2025-09-04 15:13:40', 'transactions', 'Manage Transactions');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `is_archive` tinyint(1) DEFAULT 0,
  `brand_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `uom_id` int(11) DEFAULT NULL,
  `shelf_life_days` int(11) DEFAULT NULL COMMENT 'Expected shelf life in days',
  `storage_requirements` enum('room_temp','refrigerated','frozen','special') DEFAULT 'room_temp',
  `is_perishable` tinyint(1) DEFAULT 0 COMMENT '1 if product is perishable, 0 if not'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `product_description`, `created_at`, `category_id`, `is_archive`, `brand_id`, `supplier_id`, `uom_id`, `shelf_life_days`, `storage_requirements`, `is_perishable`) VALUES
(1, 'TRIMMINGS', 'BEEF', '2025-09-04 06:03:55', 23, 1, 4, 5, 1, 240, 'refrigerated', 1),
(2, 'BANGUS', 'DINAING', '2025-09-04 06:14:52', 27, 1, 5, 3, 2, 240, 'refrigerated', 1),
(3, 'Marion Brix Quiling', 'S', '2025-09-05 00:57:57', 24, 1, 1, 1, 1, NULL, 'room_temp', 0),
(4, 'TEST', 'test', '2025-09-09 15:52:32', 28, 1, 1, 1, 1, NULL, 'room_temp', 0),
(5, 'TRIMMINGS', 'BEEF', '2025-09-17 15:45:01', 23, 1, 4, NULL, 1, NULL, 'room_temp', 0),
(6, 'TRIMMINGS', 'BEEF', '2025-09-17 15:47:02', 23, 1, 4, NULL, 1, NULL, 'room_temp', 0),
(7, 'HIPON', 'HIPON', '2025-09-19 03:07:36', 27, 1, 6, NULL, 1, NULL, 'room_temp', 0),
(8, 'FLANK', 'BEEF', '2025-09-21 11:51:42', 23, 1, 5, 1, 1, NULL, 'room_temp', 0),
(9, 'Pindang', 'Carabao Meat', '2025-09-22 04:51:36', 32, 1, 10, 1, 1, NULL, 'room_temp', 0),
(10, 'Jowls', 'Pork', '2025-09-24 09:27:15', 28, 1, 5, NULL, 1, NULL, 'room_temp', 0),
(11, 'Breast', 'Chicken', '2025-09-27 15:29:46', 24, 1, 4, NULL, 1, NULL, 'room_temp', 0),
(12, 'SCRAP', 'BEEF SCRAP', '2025-10-07 05:24:10', 23, 1, 1, NULL, 1, NULL, 'room_temp', 0),
(13, 'KASIM', 'Frozen Pork Kasim', '2025-10-08 04:05:17', 28, 1, 6, NULL, 1, NULL, 'room_temp', 0),
(14, 'Beef Fats', 'Beef', '2025-10-08 05:52:59', 23, 1, 1, NULL, 1, NULL, 'room_temp', 0),
(15, 'Chicken Neck', 'Frozen Chicken Neck', '2025-10-10 06:12:16', 24, 1, NULL, NULL, 1, NULL, 'room_temp', 0),
(16, 'Pork Ribs', 'Pork', '2025-10-10 11:24:06', 28, 1, NULL, NULL, 1, NULL, 'room_temp', 0),
(17, 'Forequarter', 'Beef', '2025-10-12 05:23:13', 23, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(18, 'Test Expired Product', NULL, '2025-10-18 15:55:23', 1, 1, NULL, NULL, 1, NULL, 'room_temp', 1),
(19, 'Test Expired Product', NULL, '2025-10-18 15:59:40', 1, 1, NULL, NULL, 1, NULL, 'room_temp', 1),
(20, 'Beef Sirloin', 'A premium, tender beef cut sourced from the upper hindquarter, known for its rich flavor and perfect marbling. Ideal for steaks, stir-fried dishes, or grilled recipes. Its balance of leanness and juiciness makes it a favorite for both household cooking and restaurant use, delivering a melt-in-your-mouth texture when cooked properly.', '2025-10-19 05:12:51', 23, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(21, 'Chicken Fillet', 'Lean and tender white meat, perfect for health-conscious meals. Excellent for sandwiches, salads, and grilled recipes. Known for its mild taste and high protein content, making it a versatile option in any kitchen.', '2025-10-19 09:23:22', 24, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(22, 'Chicken Wings', 'Crispy, tender, and packed with flavor. perfect for buffalo wings, adobo, or crispy-fried dishes. Chicken wings are a bestseller for both home cooking and small businesses.', '2025-10-19 09:24:16', 24, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(23, 'Chicken Skin', 'Crispy and indulgent, perfect for frying into a crunchy snack. Loved for its savory taste and golden texture. Great for reselling as chicharon or a quick pulutan option.', '2025-10-19 09:25:58', 24, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(24, 'Beef Tapa', 'Traditional Filipino marinated beef, cured in a flavorful mix of soy sauce, vinegar, and spices. Perfect for quick breakfast meals like “Tapsilog,” it offers a tender texture and savory-sweet taste. Ready to fry straight from the pack, making it convenient for busy customers who want delicious home-style meals anytime.', '2025-10-19 09:27:41', 23, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(25, 'Beef Shank', 'The go-to cut for classic Bulalo soup — meaty, bone-in shank rich in collagen and marrow. When simmered slowly, it produces a hearty broth packed with natural beef goodness. A popular choice for home and restaurant use, especially during cold or rainy days.', '2025-10-19 09:28:33', 23, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(26, 'Pork Belly', 'A flavorful and fatty cut, perfect for grilling, roasting, or frying. Its alternating layers of meat and fat deliver a crisp yet juicy bite. Commonly used for liempo barbecue, lechon kawali, and other Filipino favorites, this versatile cut offers unbeatable tenderness and taste.', '2025-10-19 09:31:02', 28, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(27, 'Pork Adobo Cut', 'Pre-cut pork pieces perfect for Filipino-style Adobo dishes. These cuts balance meat and fat for rich flavor absorption. Conveniently portioned for easy cooking, ideal for households seeking ready-to-cook meal solutions.', '2025-10-19 09:32:22', 28, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(28, 'Ground Pork', 'Finely ground pork made from fresh, lean cuts. Commonly used in dumplings, lumpia, meatballs, and other savory dishes. Offers rich flavor and great texture, making it a must-have for both home cooks and small eateries.', '2025-10-19 09:33:53', 28, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(29, 'Fiesta Ham', 'Cured and sliced pork with a smoky-sweet flavor, ideal for sandwiches, breakfast meals, or holiday celebrations. A staple in Filipino households, especially during festive seasons.', '2025-10-19 09:37:25', 32, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(30, 'Tocino', 'Sweet and savory cured pork slices, loved for their caramelized glaze when cooked. Great for quick meals and a hit with both kids and adults. Best served with garlic rice and egg for a classic ‘Tosilog’.', '2025-10-19 09:39:02', 32, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(31, 'Fishball', 'Popular Filipino street food made from finely ground fish meat, shaped into small balls, and pre-cooked for convenience. Crispy on the outside and soft inside when fried. Perfect with sweet or spicy dipping sauce for snacks or side dishes.', '2025-10-19 09:42:41', 27, 0, NULL, NULL, 1, NULL, 'room_temp', 0),
(32, 'Tempura', 'Lightly battered and pre-cooked seafood sticks inspired by Japanese-style tempura. Crispy when fried and soft inside, offering a delicious umami flavor. Perfect for snacks, bento meals, or party platters.', '2025-10-19 09:43:44', 27, 0, NULL, NULL, 1, NULL, 'room_temp', 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `batch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `quantity_received` decimal(10,1) NOT NULL,
  `quantity_remaining` decimal(10,1) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `expiration_date` date NOT NULL,
  `received_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `reference_type` enum('restock','adjustment','manual') DEFAULT 'restock',
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_processed_expired` tinyint(1) DEFAULT 0,
  `processed_expired_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`batch_id`, `product_id`, `supplier_id`, `brand_id`, `batch_number`, `quantity_received`, `quantity_remaining`, `unit_cost`, `expiration_date`, `received_date`, `created_at`, `created_by`, `reference_type`, `reference_id`, `notes`, `is_active`, `is_processed_expired`, `processed_expired_at`) VALUES
(1, 1, NULL, 4, 'TRIMMINGS-BATCH001', 20.0, 0.0, NULL, '2025-09-10', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 0, 0, NULL),
(2, 1, NULL, 4, 'TRIMMINGS-BATCH002', 15.0, 0.0, NULL, '2025-09-20', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1, 1, '2025-10-22 21:53:58'),
(3, 1, NULL, 4, 'TRIMMINGS-BATCH003', 25.0, 0.0, NULL, '2025-10-05', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1, 1, '2025-10-22 21:56:52'),
(4, 2, NULL, 5, 'BANGUS-BATCH001', 20.0, 0.0, NULL, '2025-09-10', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 0, 0, NULL),
(5, 2, NULL, 5, 'BANGUS-BATCH002', 15.0, 0.0, NULL, '2025-09-20', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 0, 0, NULL),
(6, 2, NULL, 5, 'BANGUS-BATCH003', 25.0, 0.0, NULL, '2025-10-05', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1, 1, '2025-10-22 21:58:13'),
(7, 5, 7, 4, 'B5-20250919-001', 10.0, 0.0, 25.00, '2025-10-19', '2025-09-19', '2025-09-19 02:23:11', 1, 'manual', NULL, 'Test batch from interface', 1, 0, NULL),
(10, 5, 7, 4, 'B5-20250919-002', 50.0, 47.0, 25.00, '2025-10-19', '2025-09-19', '2025-09-19 02:25:59', 10, 'manual', NULL, 'Test batch from interface', 1, 0, NULL),
(11, 5, 7, 4, 'B5-20250919-003', 10.0, 10.0, 150.00, '2026-03-19', '2025-09-19', '2025-09-19 02:36:00', 4, 'restock', 10, 'Restocking: ', 1, 0, NULL),
(12, 5, 3, 4, 'B5-20250919-004', 10.0, 10.0, NULL, '2026-03-26', '2025-09-19', '2025-09-19 02:38:21', 4, 'adjustment', 5, 'Stock adjustment: Quality Control', 1, 0, NULL),
(13, 7, 5, 6, 'B7-20250919-001', 10.0, 8.0, 120.00, '2026-02-19', '2025-09-19', '2025-09-19 03:08:01', 4, 'restock', 11, 'Restocking: ', 1, 0, NULL),
(14, 7, 1, 6, 'B7-20250919-002', 10.0, 10.0, 120.00, '2026-01-15', '2025-09-19', '2025-09-19 03:14:50', 4, 'restock', 12, 'Restocking: ', 1, 0, NULL),
(15, 4, NULL, 1, 'B4-20250919-001', 50.0, 0.0, NULL, '2025-10-04', '2025-09-19', '2025-09-19 03:19:27', 10, 'restock', 999, 'Test batch for order integration', 1, 0, NULL),
(16, 4, NULL, 1, 'B4-20250919-002', 30.0, 0.0, NULL, '2025-10-19', '2025-09-19', '2025-09-19 03:19:27', 10, 'restock', 999, 'Test batch 2 - expires later', 1, 1, '2025-10-22 21:56:44'),
(17, 4, NULL, 1, 'B4-20250919-003', 20.0, 0.0, NULL, '2025-09-24', '2025-09-19', '2025-09-19 03:19:27', 10, 'restock', 999, 'Test batch 3 - expires sooner', 1, 1, '2025-10-22 21:53:25'),
(18, 7, 1, 6, 'B7-20250922-001', 10.0, 10.0, NULL, '2026-03-23', '2025-09-22', '2025-09-22 03:57:22', 4, 'adjustment', 6, 'Stock adjustment: Other', 1, 0, NULL),
(19, 9, 7, 10, 'B9-20250922-001', 1.0, 0.0, 100.00, '2026-06-16', '2025-09-23', '2025-09-22 04:57:34', 4, 'restock', 13, 'Restocking: ', 1, 0, NULL),
(20, 9, 3, 10, 'B9-20250922-002', 30.0, 0.0, 100.00, '2026-03-28', '2025-09-22', '2025-09-22 04:59:56', 4, 'restock', 14, 'Restocking: ', 1, 0, NULL),
(21, 9, 3, 10, 'B9-20250922-003', 30.0, 0.0, 100.00, '2026-03-22', '2025-09-22', '2025-09-22 05:04:22', 4, 'restock', 15, 'Restocking: ', 1, 0, NULL),
(22, 8, 5, 5, 'B8-20250923-001', 1.0, 0.0, 100.00, '2026-11-23', '2025-09-23', '2025-09-23 09:21:21', 4, 'restock', 16, 'Restocking: ', 1, 0, NULL),
(23, 8, 1, 5, 'B8-20250923-002', 1.0, 0.0, 100.00, '2026-11-23', '2025-09-23', '2025-09-23 09:22:21', 4, 'restock', 17, 'Restocking: ', 1, 0, NULL),
(24, 9, 7, 10, 'B9-20250923-001', 2.0, 0.0, 100.00, '2026-10-23', '2025-09-23', '2025-09-23 09:30:13', 4, 'restock', 18, 'Restocking: ', 1, 0, NULL),
(25, 9, 3, 10, 'B9-20250923-002', 2.0, 0.0, 100.00, '2026-12-19', '2025-09-23', '2025-09-23 09:30:54', 4, 'restock', 19, 'Restocking: ', 1, 0, NULL),
(26, 9, 7, 10, 'B9-20250923-003', 2.0, 0.0, NULL, '2026-03-23', '2025-09-23', '2025-09-23 09:34:21', 4, 'adjustment', 12, 'Stock adjustment: Other', 1, 0, NULL),
(27, 9, 3, 10, 'B9-20250923-004', 2.0, 0.0, 100.00, '2026-03-24', '2025-09-23', '2025-09-23 09:57:34', 4, 'restock', 20, 'Restocking: ', 1, 0, NULL),
(28, 9, 3, 10, 'B9-20250923-005', 1.0, 0.0, 100.00, '2025-12-23', '2025-09-23', '2025-09-23 14:08:26', 4, 'restock', 21, 'Restocking: ', 1, 0, NULL),
(29, 10, 3, 5, 'B10-20250924-001', 10.0, 2.0, 100.00, '2025-12-24', '2025-09-24', '2025-09-24 09:28:42', 4, 'restock', 22, 'Restocking: ', 1, 0, NULL),
(30, 11, 7, 4, 'B11-20250927-001', 9.0, 4.0, 150.00, '2025-12-27', '2025-09-27', '2025-09-27 15:30:59', 4, 'restock', 23, 'Restocking: ', 1, 0, NULL),
(31, 8, 1, 5, 'B8-20251007-001', 100.0, 57.8, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 02:46:47', 4, 'restock', 24, 'Restocking: ', 1, 0, NULL),
(32, 6, 5, 4, 'B6-20251007-001', 50.0, 42.0, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 02:47:34', 4, 'restock', 25, 'Restocking: ', 1, 0, NULL),
(33, 9, 3, 10, 'B9-20251007-001', 2.5, 0.0, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 04:41:54', 4, 'restock', 26, 'Restocking: ', 1, 0, NULL),
(34, 9, 7, 10, 'B9-20251007-002', 3.5, 0.0, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 04:42:10', 4, 'restock', 27, 'Restocking: ', 1, 0, NULL),
(35, 9, 3, 10, 'B9-20251007-003', 2.5, 2.5, 70.00, '2026-01-07', '2025-10-07', '2025-10-07 05:10:48', 4, 'restock', 28, 'Restocking: ', 1, 0, NULL),
(36, 12, 1, 1, 'B12-20251007-001', 2.5, 2.5, 10.00, '2026-01-07', '2025-10-07', '2025-10-07 05:28:21', 4, 'restock', 29, 'Restocking: ', 1, 0, NULL),
(37, 12, 1, 1, 'B12-20251007-002', 3.5, 3.5, 15.00, '2026-01-07', '2025-10-07', '2025-10-07 05:29:18', 4, 'restock', 30, 'Restocking: ', 1, 0, NULL),
(38, 13, 1, 6, 'B13-20251008-001', 10.0, 10.0, 10.00, '2026-01-08', '2025-10-08', '2025-10-08 04:15:57', 4, 'restock', 31, 'Restocking: ', 1, 0, NULL),
(39, 13, 1, 6, 'B13-20251008-002', 10.0, 10.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 04:18:27', 4, 'restock', 32, 'Restocking: ', 1, 0, NULL),
(40, 14, 5, 1, 'B14-20251008-001', 10.5, 0.0, 105.00, '2026-01-08', '2025-10-08', '2025-10-08 05:56:26', 4, 'restock', 33, 'Restocking: ', 1, 0, NULL),
(41, 14, 5, 1, 'B14-20251008-002', 1.0, 0.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 10:20:02', 4, 'restock', 34, 'Restocking: ', 1, 0, NULL),
(42, 14, 5, 1, 'B14-20251008-003', 10.0, 0.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 11:22:04', 4, 'restock', 35, 'Restocking: ', 1, 0, NULL),
(43, 14, 5, 1, 'B14-20251008-004', 50.0, 0.0, 200.00, '2026-01-08', '2025-10-08', '2025-10-08 14:52:29', 4, 'restock', 36, 'Restocking: ', 1, 0, NULL),
(44, 14, 7, 1, 'B14-20251008-005', 2.0, 0.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 15:14:01', 4, 'restock', 37, 'Restocking: ', 1, 0, NULL),
(45, 14, 5, 1, 'B14-20251008-006', 10.0, 0.0, 350.00, '2026-01-08', '2025-10-08', '2025-10-08 15:15:50', 4, 'restock', 38, 'Restocking: ', 1, 0, NULL),
(46, 14, 5, 1, 'B14-20251009-001', 1.0, 0.0, 100.00, '2026-01-09', '2025-10-09', '2025-10-09 04:06:27', 4, 'restock', 39, 'Restocking: ', 1, 0, NULL),
(47, 14, 5, 1, 'B14-20251009-002', 1.0, 0.0, 500.00, '2026-01-09', '2025-10-09', '2025-10-09 04:28:37', 4, 'restock', 40, 'Restocking: ', 1, 0, NULL),
(48, 14, 5, 1, 'B14-20251009-003', 10.0, 3.0, 100.00, '2026-01-09', '2025-10-09', '2025-10-09 04:31:04', 4, 'restock', 41, 'Restocking: ', 1, 0, NULL),
(49, 15, 3, 4, 'B15-20251010-001', 10.0, 10.0, 100.00, '2026-01-10', '2025-10-10', '2025-10-10 06:13:18', 4, 'restock', 42, 'Restocking: ', 1, 0, NULL),
(50, 15, 3, 5, 'B15-20251010-002', 10.0, 10.0, 500.00, '2026-01-10', '2025-10-10', '2025-10-10 06:14:13', 4, 'restock', 43, 'Restocking: ', 1, 0, NULL),
(51, 15, 3, 6, 'B15-20251010-003', 15.0, 15.0, 900.00, '2026-01-10', '2025-10-10', '2025-10-10 07:01:40', 4, 'restock', 44, 'Restocking: ', 1, 0, NULL),
(52, 14, 5, 4, 'B14-20251010-001', 35.0, 35.0, 500.00, '2026-01-10', '2025-10-10', '2025-10-10 07:15:28', 4, 'restock', 45, 'Restocking: ', 1, 0, NULL),
(53, 16, 1, 11, 'B16-20251010-001', 35.0, 35.0, 555.00, '2026-01-10', '2025-10-10', '2025-10-10 11:25:05', 4, 'restock', 46, 'Restocking: ', 1, 0, NULL),
(54, 16, 1, 12, 'B16-20251010-002', 13.0, 13.0, 800.00, '2026-01-10', '2025-10-10', '2025-10-10 11:26:49', 4, 'restock', 47, 'Restocking: ', 1, 0, NULL),
(55, 16, 1, 11, 'B16-20251012-001', 30.0, 30.0, 1000.00, '2026-01-12', '2025-10-12', '2025-10-12 04:52:34', 4, 'restock', 48, 'Restocking: ', 1, 0, NULL),
(56, 17, 7, 12, 'B17-20251012-001', 1.0, 0.0, 100.00, '2026-01-12', '2025-10-12', '2025-10-12 05:24:06', 4, 'restock', 49, 'Restocking: ', 1, 0, NULL),
(57, 17, 7, 12, 'B17-20251012-002', 1.0, 0.0, 500.00, '2026-01-12', '2025-10-12', '2025-10-12 05:59:48', 4, 'restock', 50, 'Restocking: ', 1, 0, NULL),
(58, 17, 7, 12, 'B17-20251012-003', 1.0, 0.0, 500.00, '2026-01-12', '2025-10-12', '2025-10-12 06:11:05', 4, 'restock', 51, 'Restocking: ', 1, 0, NULL),
(59, 17, 7, 11, 'B17-20251012-004', 2.0, 0.0, 600.00, '2026-01-12', '2025-10-12', '2025-10-12 06:11:22', 4, 'restock', 52, 'Restocking: ', 1, 0, NULL),
(60, 17, 7, 12, 'B17-20251012-005', 1.0, 0.0, 100.00, '2026-01-12', '2025-10-12', '2025-10-12 06:19:35', 4, 'restock', 53, 'Restocking: ', 1, 0, NULL),
(61, 17, 7, 12, 'B17-20251012-006', 1.0, 0.0, 100.00, '2026-01-12', '2025-10-12', '2025-10-12 06:19:43', 4, 'restock', 54, 'Restocking: ', 1, 0, NULL),
(62, 17, 7, 11, 'B17-20251012-007', 2.0, 0.0, 100.00, '2026-01-12', '2025-10-12', '2025-10-12 06:20:40', 4, 'restock', 55, 'Restocking: ', 1, 0, NULL),
(63, 17, 7, 12, 'B17-20251012-008', 2.0, 0.0, 1.00, '2026-01-12', '2025-10-12', '2025-10-12 06:22:39', 4, 'restock', 56, 'Restocking: ', 1, 0, NULL),
(64, 17, 7, 11, 'B17-20251012-009', 1.0, 0.0, 1.00, '2026-01-12', '2025-10-12', '2025-10-12 06:23:14', 4, 'restock', 57, 'Restocking: ', 1, 0, NULL),
(65, 17, 7, 12, 'B17-20251012-010', 2.5, 0.0, 100.00, '2026-01-12', '2025-10-12', '2025-10-12 06:25:44', 4, 'restock', 58, 'Restocking: ', 1, 0, NULL),
(66, 17, 7, 11, 'B17-20251012-011', 5.5, 0.0, 500.00, '2026-01-12', '2025-10-12', '2025-10-12 06:26:01', 4, 'restock', 59, 'Restocking: ', 1, 0, NULL),
(67, 17, 7, 12, 'B17-20251012-012', 1.0, 0.0, 50.00, '2026-01-12', '2025-10-12', '2025-10-12 11:25:00', 4, 'restock', 60, 'Restocking: ', 1, 0, NULL),
(68, 17, 7, 11, 'B17-20251012-013', 2.5, 0.0, 100.00, '2026-01-12', '2025-10-12', '2025-10-12 11:27:08', 4, 'restock', 61, 'Restocking: ', 1, 0, NULL),
(69, 17, 7, 12, 'B17-20251012-014', 10.0, 0.0, 100.00, '2026-01-12', '2025-10-12', '2025-10-12 14:58:39', 4, 'restock', 62, 'Restocking: ', 1, 0, NULL),
(70, 17, 7, 11, 'B17-20251013-001', 50.0, 0.0, 1.00, '2026-01-13', '2025-10-13', '2025-10-13 07:52:49', 4, 'restock', 63, 'Restocking: ', 1, 0, NULL),
(71, 17, 5, 11, 'B17-20251016-001', 10.0, 0.0, 150.00, '2026-01-16', '2025-10-16', '2025-10-16 06:02:54', 4, 'restock', 64, 'Restocking: ', 1, 0, NULL),
(72, 17, 5, 13, 'B17-20251016-002', 50.0, 0.0, 450.00, '2026-01-16', '2025-10-16', '2025-10-16 15:33:51', 4, 'restock', 65, 'Restocking: ', 1, 0, NULL),
(73, 17, 5, 12, 'B17-20251016-003', 20.0, 0.0, 300.00, '2026-01-16', '2025-10-16', '2025-10-16 15:34:22', 4, 'restock', 66, 'Restocking: ', 1, 0, NULL),
(74, 17, 5, 13, 'B17-20251017-001', 50.0, 12.0, 300.00, '2026-01-17', '2025-10-17', '2025-10-17 00:51:45', 4, 'restock', 67, 'Restocking: ', 1, 0, NULL),
(75, 17, 5, 11, 'B17-20251017-002', 30.0, 0.0, 550.00, '2026-01-17', '2025-10-17', '2025-10-17 00:52:33', 4, 'restock', 68, 'Restocking: ', 1, 0, NULL),
(76, 18, 1, 1, 'B18-20251018-001', 50.0, 0.0, NULL, '2025-10-17', '2025-10-13', '2025-10-18 15:55:23', 1, '', NULL, 'Test expired batch', 0, 0, NULL),
(77, 19, 1, 1, 'B19-20251018-001', 50.0, 0.0, NULL, '2025-10-17', '2025-10-13', '2025-10-18 15:59:40', 1, '', NULL, 'Test expired batch', 0, 0, NULL),
(78, 20, 8, 13, 'B20-20251019-001', 54.0, 54.0, 500.00, '2026-02-26', '2025-10-19', '2025-10-19 05:15:46', 4, 'restock', 69, 'Restocking: ', 1, 0, NULL),
(79, 20, 8, 13, 'B20-20251019-002', 1.0, 0.0, 600.00, '2026-01-19', '2025-10-19', '2025-10-19 05:33:54', 4, 'restock', 70, 'Restocking: ', 1, 0, NULL),
(80, 20, 8, 14, 'B20-20251019-003', 10.0, 0.0, 500.00, '2026-01-19', '2025-10-19', '2025-10-19 05:37:01', 4, 'restock', 71, 'Restocking: ', 1, 0, NULL),
(81, 20, 8, 14, 'B20-20251019-004', 550.0, 8.0, 2.00, '2026-01-19', '2025-10-19', '2025-10-19 05:37:23', 4, 'restock', 72, 'Restocking: ', 1, 0, NULL),
(82, 20, 8, 13, 'B20-20251019-005', 10.0, 1.9, 500.00, '2026-01-19', '2025-10-19', '2025-10-19 05:38:56', 4, 'restock', 73, 'Restocking: ', 1, 0, NULL),
(83, 20, 8, 14, 'B20-20251019-006', 1.0, 1.0, 570.00, '2026-01-19', '2025-10-19', '2025-10-19 05:41:48', 4, 'restock', 74, 'Restocking: ', 1, 0, NULL),
(84, 20, 8, 13, 'B20-20251019-007', 10.0, 10.0, 700.00, '2026-01-19', '2025-10-19', '2025-10-19 05:50:40', 4, 'restock', 75, 'Restocking: ', 1, 0, NULL),
(85, 17, 5, 11, 'B17-20251019-001', 2.0, 0.0, 500.00, '2026-01-19', '2025-10-19', '2025-10-19 06:16:30', 4, 'restock', 76, 'Restocking: ', 1, 0, NULL),
(86, 25, 8, 19, 'B25-20251019-001', 5.0, 0.0, 256.00, '2026-01-19', '2025-10-19', '2025-10-19 11:39:11', 4, 'restock', 77, 'Restocking: ', 1, 0, NULL),
(87, 24, 9, 19, 'B24-20251019-001', 9.0, 0.0, 330.00, '2026-01-19', '2025-10-19', '2025-10-19 11:39:32', 4, 'restock', 78, 'Restocking: ', 1, 0, NULL),
(88, 21, 9, 15, 'B21-20251019-001', 7.0, 0.0, 218.00, '2026-01-19', '2025-10-19', '2025-10-19 11:39:48', 4, 'restock', 79, 'Restocking: ', 1, 0, NULL),
(89, 30, 3, 20, 'B30-20251019-001', 10.0, 5.0, 100.00, '2026-01-19', '2025-10-19', '2025-10-19 11:40:21', 4, 'restock', 80, 'Restocking: ', 1, 0, NULL),
(90, 32, 8, 16, 'B32-20251019-001', 12.0, 10.0, 120.00, '2026-01-19', '2025-10-19', '2025-10-19 11:40:34', 4, 'restock', 81, 'Restocking: ', 1, 0, NULL),
(91, 29, 11, 20, 'B29-20251019-001', 14.0, 12.0, 250.00, '2026-01-19', '2025-10-19', '2025-10-19 11:42:09', 4, 'restock', 82, 'Restocking: ', 1, 0, NULL),
(92, 22, 7, 12, 'B22-20251019-001', 11.0, 0.0, 350.00, '2026-01-19', '2025-10-19', '2025-10-19 11:42:28', 4, 'restock', 83, 'Restocking: ', 1, 0, NULL),
(93, 31, 11, 16, 'B31-20251019-001', 15.0, 4.0, 120.00, '2026-01-19', '2025-10-19', '2025-10-19 11:42:42', 4, 'restock', 84, 'Restocking: ', 1, 0, NULL),
(94, 28, 10, 17, 'B28-20251019-001', 17.0, 0.0, 234.00, '2026-01-19', '2025-10-19', '2025-10-19 11:42:54', 4, 'restock', 85, 'Restocking: ', 1, 0, NULL),
(95, 26, 10, 17, 'B26-20251019-001', 21.0, 4.0, 320.00, '2026-01-19', '2025-10-19', '2025-10-19 11:43:32', 4, 'restock', 86, 'Restocking: ', 1, 0, NULL),
(98, 20, 9, 11, 'B20-20251019-010', 10.0, 0.0, 800.00, '2026-01-19', '2025-10-19', '2025-10-19 12:14:13', 4, 'restock', 87, 'Restocking: ', 1, 0, NULL),
(99, 20, 9, 11, 'B20-20251019-009', 149.0, 102.0, 0.00, '2026-01-19', '2025-10-19', '2025-10-19 14:00:47', 4, 'restock', 91, 'Restocking: ', 1, 0, NULL),
(100, 27, 10, 15, 'B27-20251019-001', 10.0, 0.0, 235.00, '2026-01-19', '2025-10-19', '2025-10-19 14:10:49', 4, 'restock', 92, 'Restocking: ', 1, 0, NULL),
(101, 23, 5, 18, 'B23-20251019-001', 21.0, 7.0, 135.00, '2026-01-19', '2025-10-19', '2025-10-19 14:11:33', 4, 'restock', 93, 'Restocking: ', 1, 0, NULL),
(102, 25, 8, 19, 'B25-20251019-002', 1.0, 0.0, 335.00, '2026-01-19', '2025-10-19', '2025-10-19 14:16:01', 4, 'restock', 94, 'Restocking: ', 1, 0, NULL),
(103, 22, 7, 12, 'B22-20251020-001', 15.0, 0.0, 0.00, '2026-01-20', '2025-10-20', '2025-10-20 01:08:09', 4, 'restock', 95, 'Restocking: ', 1, 0, NULL),
(104, 28, 10, 17, 'B28-20251020-001', 14.0, 0.0, 0.00, '2026-01-20', '2025-10-20', '2025-10-20 01:55:21', 4, 'restock', 96, 'Restocking: ', 1, 0, NULL),
(105, 22, 7, 12, 'B22-20251020-002', 45.0, 21.0, 330.00, '2026-01-20', '2025-10-20', '2025-10-20 14:22:20', 4, 'restock', 97, 'Restocking: ', 1, 0, NULL),
(106, 20, 8, 13, 'B20-20251022-001', 5.0, 6.0, 850.00, '2026-01-22', '2025-10-22', '2025-10-22 03:49:33', 4, 'restock', 98, 'Restocking: ', 1, 0, NULL),
(107, 20, 9, 11, 'B20-20251022-002', 5.0, 5.0, 750.00, '2026-01-22', '2025-10-22', '2025-10-22 03:50:24', 4, 'restock', 99, 'Restocking: ', 1, 0, NULL),
(108, 28, 10, 15, 'B28-20251022-001', 2.0, 0.0, 250.00, '2026-01-22', '2025-10-22', '2025-10-22 12:46:54', 4, 'restock', 100, 'Restocking: ', 1, 0, NULL),
(109, 28, 10, 15, 'B28-20251022-002', 2.0, 2.0, 290.00, '2026-01-22', '2025-10-22', '2025-10-22 12:47:22', 4, 'restock', 101, 'Restocking: ', 1, 0, NULL),
(110, 23, 5, 18, 'B23-20251024-001', 15.0, 17.0, 300.00, '2026-01-24', '2025-10-24', '2025-10-24 01:26:42', 4, 'restock', 102, 'Restocking: ', 1, 0, NULL),
(111, 25, 11, 20, 'B25-20251025-001', 10.0, 7.0, 250.00, '2026-01-25', '2025-10-25', '2025-10-25 13:29:57', 4, 'restock', 103, 'Restocking: ', 1, 0, NULL),
(112, 24, 10, 1, 'B24-20251031-001', 10.0, 0.0, 10.00, '2026-01-31', '2025-10-31', '2025-10-31 08:50:29', 4, 'restock', 107, 'Restocking: ', 1, 0, NULL),
(113, 21, 9, 15, 'B21-20251031-001', 8.0, 8.0, 8.00, '2026-01-31', '2025-10-31', '2025-10-31 09:41:29', 4, 'restock', 110, 'Restocking: ', 1, 0, NULL),
(114, 21, 1, 15, 'B21-20251031-002', 10.0, 10.0, 200.00, '2026-01-31', '2025-10-31', '2025-10-31 09:43:12', 4, 'restock', 111, 'Restocking: ', 1, 0, NULL),
(115, 24, 9, 1, 'B24-20251031-002', 10.0, 0.0, 250.00, '2026-01-31', '2025-10-31', '2025-10-31 09:43:34', 4, 'restock', 112, 'Restocking: ', 1, 0, NULL),
(116, 27, 3, 18, 'B27-20251031-001', 10.0, 10.0, 250.00, '2026-01-31', '2025-10-31', '2025-10-31 11:16:48', 4, 'restock', 113, 'Restocking: ', 1, 0, NULL),
(117, 27, 3, 12, 'B27-20251031-002', 20.0, 10.0, 300.00, '2026-01-31', '2025-10-31', '2025-10-31 11:20:55', 4, 'restock', 114, 'Restocking: ', 1, 0, NULL),
(118, 27, 3, 13, 'B27-20251031-003', 10.0, 10.0, 300.00, '2026-01-31', '2025-10-31', '2025-10-31 11:30:00', 4, 'restock', 115, 'Restocking: ', 1, 0, NULL),
(119, 27, 10, 15, 'B27-20251031-004', 20.0, 20.0, 325.00, '2026-01-31', '2025-10-31', '2025-10-31 11:42:07', 4, 'restock', 116, 'Restocking: ', 1, 0, NULL),
(120, 23, 1, 11, 'B23-20251031-001', 20.0, 0.0, 150.00, '2026-01-31', '2025-10-31', '2025-10-31 11:43:56', 4, 'restock', 117, 'Restocking: ', 1, 0, NULL),
(121, 23, 5, 11, 'B23-20251031-002', 20.0, 0.0, 250.00, '2026-01-31', '2025-10-31', '2025-10-31 11:44:45', 4, 'restock', 118, 'Restocking: ', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_boxes`
--

CREATE TABLE `product_boxes` (
  `box_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` varchar(50) DEFAULT NULL,
  `weight` decimal(10,2) NOT NULL,
  `is_sold` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_boxes`
--

INSERT INTO `product_boxes` (`box_id`, `product_id`, `batch_id`, `weight`, `is_sold`, `created_at`, `updated_at`) VALUES
(1, 2, 'BATCH001', 18.50, 0, '2025-09-07 14:10:32', '2025-09-07 14:10:32'),
(2, 2, 'BATCH001', 20.00, 0, '2025-09-07 14:10:32', '2025-09-07 14:10:32'),
(3, 2, 'BATCH002', 19.20, 0, '2025-09-07 14:10:32', '2025-09-07 14:10:32');

-- --------------------------------------------------------

--
-- Table structure for table `product_discounts`
--

CREATE TABLE `product_discounts` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `product_image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`product_image_id`, `product_id`, `image_url`, `is_primary`, `created_at`) VALUES
(1, 1, 'uploads/68b92bcb17e30-trimmings1.jpg', 1, '2025-09-04 06:03:55'),
(2, 1, 'uploads/68b92bcb17ffd-trimmings2.jpg', 0, '2025-09-04 06:03:55'),
(3, 1, 'uploads/68b92bcb18568-trimmings3.jpg', 0, '2025-09-04 06:03:55'),
(4, 2, 'uploads/68235946cbdc7_bangus.jpg', 1, '2025-09-04 06:14:52'),
(7, 3, 'uploads/68ba35956159c-beefscrap-hero-section.png', 1, '2025-09-05 00:57:57'),
(8, 3, 'uploads/68ba359561778-beeftrim.jpg', 0, '2025-09-05 00:57:57'),
(9, 3, 'uploads/68ba3595618c7-breast.jpg', 0, '2025-09-05 00:57:57'),
(10, 4, 'uploads/68c04d408a841-porktapa.jpg', 1, '2025-09-09 15:52:32'),
(11, 4, 'uploads/68c04d408ab46-porktapa.jpg', 0, '2025-09-09 15:52:32'),
(12, 4, 'uploads/68c04d408b0bd-porktapa.jpg', 0, '2025-09-09 15:52:32'),
(13, 5, 'uploads/68cad77d7da07-trimmings1.jpg', 1, '2025-09-17 15:45:01'),
(14, 5, 'uploads/68cad77d7dca4-trimmings2.jpg', 0, '2025-09-17 15:45:01'),
(15, 5, 'uploads/68cad77d7de22-trimmings3.jpg', 0, '2025-09-17 15:45:01'),
(16, 6, 'uploads/68cad7f6eaef1-trimmings1.jpg', 1, '2025-09-17 15:47:02'),
(17, 6, 'uploads/68cad7f6eb093-trimmings2.jpg', 0, '2025-09-17 15:47:02'),
(18, 6, 'uploads/68cad7f6eb1d5-trimmings3.jpg', 0, '2025-09-17 15:47:02'),
(19, 7, 'uploads/68ccc8f8d6829-hipon.jpg', 1, '2025-09-19 03:07:36'),
(20, 7, 'uploads/68ccc8f8d69d5-hipon.jpg', 0, '2025-09-19 03:07:36'),
(21, 7, 'uploads/68ccc8f8d6b18-hipon.jpg', 0, '2025-09-19 03:07:36'),
(22, 8, 'uploads/68cfe6ce2bc56-beefflank.jpg', 1, '2025-09-21 11:51:42'),
(23, 8, 'uploads/68cfe6ce2be44-beefflank.jpg', 0, '2025-09-21 11:51:42'),
(24, 8, 'uploads/68cfe6ce2c013-beefflank.jpg', 0, '2025-09-21 11:51:42'),
(25, 9, 'uploads/68d0d5d811dca-bangus.jpg', 1, '2025-09-22 04:51:36'),
(26, 9, 'uploads/68d0d5d811edb-bangus.jpg', 0, '2025-09-22 04:51:36'),
(27, 9, 'uploads/68d0d5d811fa0-bangus.jpg', 0, '2025-09-22 04:51:36'),
(28, 10, 'uploads/68d3b9731d124-porkjowls.jpg', 1, '2025-09-24 09:27:15'),
(29, 10, 'uploads/68d3b9731d2cc-porkjowls.jpg', 0, '2025-09-24 09:27:15'),
(30, 10, 'uploads/68d3b9731d46d-porkjowls.jpg', 0, '2025-09-24 09:27:15'),
(31, 11, 'uploads/68d802eaba65a-breast.jpg', 1, '2025-09-27 15:29:46'),
(32, 11, 'uploads/68d802eaba7ee-breast.jpg', 0, '2025-09-27 15:29:46'),
(33, 11, 'uploads/68d802eaba939-breast.jpg', 0, '2025-09-27 15:29:46'),
(34, 12, 'uploads/68e4a3fa96757-beefscrap.png', 1, '2025-10-07 05:24:10'),
(35, 12, 'uploads/68e4a3fa96920-beefscrap-hero-section.png', 0, '2025-10-07 05:24:10'),
(36, 12, 'uploads/68e4a3fa96a9d-beefscrap-hero-section.png', 0, '2025-10-07 05:24:10'),
(37, 13, 'uploads/68e5e2fd88bb9-prokkasim.jpg', 1, '2025-10-08 04:05:17'),
(38, 13, 'uploads/68e5e2fd88d8f-prokkasim.jpg', 0, '2025-10-08 04:05:17'),
(39, 13, 'uploads/68e5e2fd88efa-prokkasim.jpg', 0, '2025-10-08 04:05:17'),
(40, 14, 'uploads/68e5fc3bd3fd5-beeffats.png', 1, '2025-10-08 05:52:59'),
(41, 14, 'uploads/68e5fc3bd4183-beeffats.png', 0, '2025-10-08 05:52:59'),
(42, 14, 'uploads/68e5fc3bd4838-beeffats.png', 0, '2025-10-08 05:52:59'),
(43, 15, 'uploads/68e8a3c0cab82-neck.jpg', 1, '2025-10-10 06:12:16'),
(44, 15, 'uploads/68e8a3c0cad0e-neck.jpg', 0, '2025-10-10 06:12:16'),
(45, 15, 'uploads/68e8a3c0cae4f-neck.jpg', 0, '2025-10-10 06:12:16'),
(46, 16, 'uploads/68e8ecd6036fe-porkribs.jpg', 1, '2025-10-10 11:24:06'),
(47, 16, 'uploads/68e8ecd60392b-porkribs.jpg', 0, '2025-10-10 11:24:06'),
(48, 16, 'uploads/68e8ecd603b26-porkribs.jpg', 0, '2025-10-10 11:24:06'),
(49, 17, 'uploads/68eb3b416284f-beefforequarter.jpg', 1, '2025-10-12 05:23:13'),
(50, 17, 'uploads/68eb3b41629d7-beefforequarter.jpg', 0, '2025-10-12 05:23:13'),
(51, 17, 'uploads/68eb3b4168c97-beefforequarter.jpg', 0, '2025-10-12 05:23:13'),
(52, 20, 'uploads/68f47353389cb-Beef Serloin 1.jpg', 1, '2025-10-19 05:12:51'),
(53, 20, 'uploads/68f4735338b5a-Beef Serloin 2.jpg', 0, '2025-10-19 05:12:51'),
(54, 20, 'uploads/68f4735338ca8-Beef Serloin 3.jpg', 0, '2025-10-19 05:12:51'),
(55, 21, 'uploads/68f4ae0a7f6e0-breast 1.jpg', 1, '2025-10-19 09:23:22'),
(56, 21, 'uploads/68f4ae0a7f86b-breast 3.jpg', 0, '2025-10-19 09:23:22'),
(57, 21, 'uploads/68f4ae0a7f9c6-breast 2.jpg', 0, '2025-10-19 09:23:22'),
(58, 22, 'uploads/68f4ae4002e39-wings 3.jpg', 1, '2025-10-19 09:24:16'),
(59, 22, 'uploads/68f4ae4002fd9-wings 2.jpg', 0, '2025-10-19 09:24:16'),
(60, 22, 'uploads/68f4ae4003167-wings 1.jpg', 0, '2025-10-19 09:24:16'),
(61, 23, 'uploads/68f4aea677ce4-skin 1.jpg', 1, '2025-10-19 09:25:58'),
(62, 23, 'uploads/68f4aea677e76-skin 5.jpg', 0, '2025-10-19 09:25:58'),
(63, 23, 'uploads/68f4aea677fcd-skin 2.jpg', 0, '2025-10-19 09:25:58'),
(64, 24, 'uploads/68f4af0ddc45e-Beef Tapa 4.jpg', 1, '2025-10-19 09:27:41'),
(65, 24, 'uploads/68f4af0ddc5e6-Beef Tapa 1.jpg', 0, '2025-10-19 09:27:41'),
(66, 24, 'uploads/68f4af0ddc733-Beef Tapa 3.jpg', 0, '2025-10-19 09:27:41'),
(67, 25, 'uploads/68f4af415a880-Beef Bulalo 1.jpg', 1, '2025-10-19 09:28:33'),
(68, 25, 'uploads/68f4af415aa06-Beef Bulalo 2.jpg', 0, '2025-10-19 09:28:33'),
(69, 25, 'uploads/68f4af415ac27-Beef Bulalo 3.jpg', 0, '2025-10-19 09:28:33'),
(70, 26, 'uploads/68f4afd6342ab-Pork Belly 5.jpg', 1, '2025-10-19 09:31:02'),
(71, 26, 'uploads/68f4afd634464-pork belly 6.jpg', 0, '2025-10-19 09:31:02'),
(72, 26, 'uploads/68f4afd6345d0-pork belly 7.jpg', 0, '2025-10-19 09:31:02'),
(73, 27, 'uploads/68f4b026a6976-adobo cut 4.jpg', 1, '2025-10-19 09:32:22'),
(74, 27, 'uploads/68f4b026a6b4c-adobo cut 5.jpg', 0, '2025-10-19 09:32:22'),
(75, 27, 'uploads/68f4b026a6f15-adobo cut 2.jpg', 0, '2025-10-19 09:32:22'),
(76, 28, 'uploads/68f4b08138948-ground pork 1.jpg', 1, '2025-10-19 09:33:53'),
(77, 28, 'uploads/68f4b08138ae8-ground pork 3.jpg', 0, '2025-10-19 09:33:53'),
(78, 28, 'uploads/68f4b08138cbc-ground pork 2.jpg', 0, '2025-10-19 09:33:53'),
(79, 29, 'uploads/68f4b15586baf-images (1).jpg', 1, '2025-10-19 09:37:25'),
(80, 29, 'uploads/68f4b15586d2c-images.jpg', 0, '2025-10-19 09:37:25'),
(81, 29, 'uploads/68f4b15586e83-purefoods_fiesta_ham_1728547978_f8b4b033_progressive.jpg', 0, '2025-10-19 09:37:25'),
(82, 30, 'uploads/68f4b1b63b00e-images (2).jpg', 1, '2025-10-19 09:39:02'),
(83, 30, 'uploads/68f4b1b63b19e-images (3).jpg', 0, '2025-10-19 09:39:02'),
(84, 30, 'uploads/68f4b1b63b300-images (2).jpg', 0, '2025-10-19 09:39:02'),
(85, 31, 'uploads/68f4b2911eb8b-fishball.jpg', 1, '2025-10-19 09:42:41'),
(86, 31, 'uploads/68f4b2911ed26-fishball.jpg', 0, '2025-10-19 09:42:41'),
(87, 31, 'uploads/68f4b2911ee74-fishball.jpg', 0, '2025-10-19 09:42:41'),
(88, 32, 'uploads/68f4b2d0a91cf-images (4).jpg', 1, '2025-10-19 09:43:44'),
(89, 32, 'uploads/68f4b2d0a9364-S2844f32da57341ad97dc8932ed66bdc5A.jpg', 0, '2025-10-19 09:43:44'),
(90, 32, 'uploads/68f4b2d0a955f-images (4).jpg', 0, '2025-10-19 09:43:44');

-- --------------------------------------------------------

--
-- Table structure for table `product_pricing`
--

CREATE TABLE `product_pricing` (
  `productpricing_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `markup_price` decimal(10,2) DEFAULT 0.00,
  `pricing_type` enum('computed','stored') DEFAULT 'stored'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_pricing`
--

INSERT INTO `product_pricing` (`productpricing_id`, `product_id`, `cost_price`, `markup_price`, `pricing_type`) VALUES
(1, 1, 250.00, 10.00, 'stored'),
(2, 2, 110.00, 20.00, 'stored'),
(3, 3, 1.00, 1.00, 'stored'),
(4, 2, 110.00, 20.00, 'stored'),
(5, 4, 200.00, 20.00, 'stored'),
(6, 5, 150.00, 20.00, 'stored'),
(7, 6, 150.00, 20.00, 'stored'),
(8, 7, 120.00, 20.00, 'stored'),
(9, 8, 150.00, 20.00, 'stored'),
(10, 9, 180.00, 10.01, 'stored'),
(11, 10, 0.00, 8.00, 'stored'),
(12, 11, 0.00, 10.00, 'stored'),
(13, 12, 0.00, 5.00, 'stored'),
(14, 13, 0.00, 10.00, 'stored'),
(15, 14, 100.00, 50.00, 'stored'),
(16, 15, 0.00, 10.00, 'stored'),
(17, 16, 0.00, 30.00, 'stored'),
(18, 17, 0.00, 50.00, 'stored'),
(19, 20, 0.00, 70.00, 'stored'),
(20, 21, 0.00, 30.00, 'stored'),
(21, 22, 0.00, 40.00, 'stored'),
(22, 23, 0.00, 50.00, 'stored'),
(23, 24, 0.00, 70.00, 'stored'),
(24, 25, 0.00, 60.00, 'stored'),
(25, 26, 0.00, 30.00, 'stored'),
(26, 27, 0.00, 70.00, 'stored'),
(27, 28, 0.00, 50.00, 'stored'),
(28, 29, 0.00, 70.00, 'stored'),
(29, 30, 0.00, 30.00, 'stored'),
(30, 31, 0.00, 10.00, 'stored'),
(31, 32, 0.00, 30.00, 'stored');

-- --------------------------------------------------------

--
-- Table structure for table `product_sale`
--

CREATE TABLE `product_sale` (
  `productsale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `purchase_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_stock`
--

CREATE TABLE `product_stock` (
  `productstock_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `current_stock` decimal(10,2) DEFAULT 0.00,
  `reorder_point` decimal(10,2) DEFAULT 0.00,
  `max_stock` decimal(10,2) DEFAULT 0.00,
  `expiration_date` date DEFAULT NULL,
  `last_restock_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_stock`
--

INSERT INTO `product_stock` (`productstock_id`, `product_id`, `current_stock`, `reorder_point`, `max_stock`, `expiration_date`, `last_restock_date`) VALUES
(1, 1, -37.00, 10.00, 0.00, '2025-12-05', '2025-09-05 00:00:00'),
(2, 2, -23.00, 10.00, 0.00, '2026-01-01', '2025-09-04 00:00:00'),
(3, 3, 0.00, 10.00, 0.00, NULL, NULL),
(4, 2, -23.00, 10.00, 200.00, '2026-01-01', '2025-09-04 00:00:00'),
(5, 4, -27.00, 10.00, 0.00, '2026-06-16', '2025-09-16 00:00:00'),
(6, 5, 64.00, 10.00, 0.00, '2026-03-19', '2025-09-19 00:00:00'),
(7, 6, 43.00, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(8, 7, 27.00, 10.00, 0.00, '2026-01-15', '2025-09-19 00:00:00'),
(9, 8, 58.30, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(10, 9, 2.50, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(11, 10, 15.00, 10.00, 0.00, '2025-12-24', '2025-09-24 00:00:00'),
(12, 11, 3.50, 10.00, 0.00, '2025-12-27', '2025-09-27 00:00:00'),
(13, 12, 6.00, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(14, 13, 20.00, 10.00, 0.00, '2026-01-08', '2025-10-08 00:00:00'),
(15, 14, 38.00, 10.00, 0.00, '2026-01-10', '2025-10-10 00:00:00'),
(16, 15, 35.00, 10.00, 0.00, '2026-01-10', '2025-10-10 00:00:00'),
(17, 16, 78.00, 10.00, 0.00, '2026-01-12', '2025-10-12 00:00:00'),
(18, 17, 12.00, 51.41, 0.00, '2026-01-19', '2025-10-19 00:00:00'),
(19, 18, 50.00, 10.00, 0.00, NULL, NULL),
(20, 19, 50.00, 10.00, 0.00, NULL, NULL),
(21, 20, 187.90, 1.00, 0.00, '2026-01-22', '2025-10-22 00:00:00'),
(22, 21, 18.00, 1.00, 0.00, '2026-01-31', '2025-10-31 00:00:00'),
(23, 22, 21.00, 51.41, 0.00, '2026-01-20', '2025-10-20 00:00:00'),
(24, 23, 24.00, 41.11, 0.00, '2026-01-31', '2025-10-31 00:00:00'),
(25, 24, 0.00, 20.59, 0.00, '2026-01-31', '2025-10-31 00:00:00'),
(26, 25, 7.00, 1.00, 0.00, '2026-01-25', '2025-10-25 00:00:00'),
(27, 26, 4.00, 1.00, 0.00, '2026-01-19', '2025-10-19 00:00:00'),
(28, 27, 50.00, 9.29, 0.00, '2026-01-31', '2025-10-31 00:00:00'),
(29, 28, 2.00, 1.00, 0.00, '2026-01-22', '2025-10-22 00:00:00'),
(30, 29, 12.00, 1.00, 0.00, '2026-01-19', '2025-10-19 00:00:00'),
(31, 30, 5.00, 1.00, 0.00, '2026-01-19', '2025-10-19 00:00:00'),
(32, 31, 4.00, 12.31, 0.00, '2026-01-19', '2025-10-19 00:00:00'),
(33, 32, 10.00, 1.00, 0.00, '2026-01-19', '2025-10-19 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_uom_conversions`
--

CREATE TABLE `product_uom_conversions` (
  `conversion_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `conversion_rate` decimal(10,4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_uom_conversions`
--

INSERT INTO `product_uom_conversions` (`conversion_id`, `product_id`, `uom_id`, `conversion_rate`, `created_at`, `updated_at`) VALUES
(3, 2, 2, 0.2500, '2025-09-07 14:10:32', '2025-09-07 14:10:32');

-- --------------------------------------------------------

--
-- Table structure for table `promo_messages`
--

CREATE TABLE `promo_messages` (
  `id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `icon_class` varchar(100) NOT NULL DEFAULT 'fas fa-star',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `promo_messages`
--

INSERT INTO `promo_messages` (`id`, `message_text`, `icon_class`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, '₱1,000 OFF on orders ₱10,000+', 'fas fa-gift', 1, 1, '2025-09-27 15:45:20', '2025-09-27 16:05:41'),
(2, 'Fast Delivery', 'fas fa-truck', 1, 2, '2025-09-27 15:45:20', '2025-09-27 16:06:17'),
(3, 'Sign up & get 10% OFF your first order', 'fas fa-user-plus', 1, 3, '2025-09-27 15:45:20', '2025-09-27 15:45:20'),
(4, 'Premium Quality Products Guaranteed', 'fas fa-star', 1, 4, '2025-09-27 15:45:20', '2025-09-27 15:45:20'),
(8, 'Fresh Chuchu', 'fas fa-gift', 1, 5, '2025-10-26 02:32:36', '2025-10-26 02:32:36');

-- --------------------------------------------------------

--
-- Table structure for table `rating_images`
--

CREATE TABLE `rating_images` (
  `image_id` int(11) NOT NULL,
  `rating_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_order` int(11) DEFAULT 1,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rating_images`
--

INSERT INTO `rating_images` (`image_id`, `rating_id`, `image_path`, `image_order`, `uploaded_at`) VALUES
(1, 34, 'uploads/rating_images/rating_321_9_1_1761109523.jpg', 1, '2025-10-22 05:05:23'),
(2, 34, 'uploads/rating_images/rating_321_9_2_1761109523.jpg', 2, '2025-10-22 05:05:23'),
(3, 34, 'uploads/rating_images/rating_321_9_3_1761109523.jpg', 3, '2025-10-22 05:05:23'),
(4, 37, 'uploads/rating_images/rating_361_10_1_1761109729.jpg', 1, '2025-10-22 05:08:49'),
(5, 37, 'uploads/rating_images/rating_361_10_2_1761109729.jpg', 2, '2025-10-22 05:08:49'),
(6, 37, 'uploads/rating_images/rating_361_10_3_1761109729.jpg', 3, '2025-10-22 05:08:49'),
(7, 38, 'uploads/rating_images/rating_362_20_1_1761112084.jpg', 1, '2025-10-22 05:48:04'),
(8, 38, 'uploads/rating_images/rating_362_20_2_1761112084.jpg', 2, '2025-10-22 05:48:04'),
(9, 38, 'uploads/rating_images/rating_362_20_3_1761112084.jpg', 3, '2025-10-22 05:48:04'),
(10, 39, 'uploads/rating_images/rating_363_20_1_1761112192.jpg', 1, '2025-10-22 05:49:52'),
(11, 39, 'uploads/rating_images/rating_363_20_2_1761112192.jpg', 2, '2025-10-22 05:49:52'),
(12, 39, 'uploads/rating_images/rating_363_20_3_1761112192.jpg', 3, '2025-10-22 05:49:52'),
(13, 40, 'uploads/rating_images/rating_364_20_1_1761112359.jpg', 1, '2025-10-22 05:52:39'),
(14, 40, 'uploads/rating_images/rating_364_20_2_1761112359.jpg', 2, '2025-10-22 05:52:39'),
(15, 40, 'uploads/rating_images/rating_364_20_3_1761112359.jpg', 3, '2025-10-22 05:52:39'),
(16, 41, 'uploads/rating_images/rating_365_20_1_1761112784.jpg', 1, '2025-10-22 05:59:44'),
(17, 41, 'uploads/rating_images/rating_365_20_2_1761112784.jpg', 2, '2025-10-22 05:59:44'),
(18, 41, 'uploads/rating_images/rating_365_20_3_1761112784.jpg', 3, '2025-10-22 05:59:44'),
(19, 32, 'uploads/rating_images/rating_323_9_1761103721.jpg', 1, '2025-10-22 06:11:16'),
(20, 33, 'uploads/rating_images/rating_360_9_1761105263.jpg', 1, '2025-10-22 06:11:16'),
(21, 42, 'uploads/rating_images/rating_366_20_1_1761113992.png', 1, '2025-10-22 06:19:52'),
(22, 42, 'uploads/rating_images/rating_366_20_2_1761113992.png', 2, '2025-10-22 06:19:52'),
(23, 42, 'uploads/rating_images/rating_366_20_3_1761113992.png', 3, '2025-10-22 06:19:52'),
(24, 43, 'uploads/rating_images/rating_367_20_1_1761116684.jpg', 1, '2025-10-22 07:04:44'),
(25, 43, 'uploads/rating_images/rating_367_20_2_1761116684.jpg', 2, '2025-10-22 07:04:44'),
(26, 43, 'uploads/rating_images/rating_367_20_3_1761116684.jpg', 3, '2025-10-22 07:04:44'),
(27, 44, 'uploads/rating_images/rating_368_3_1_1761116791.jpg', 1, '2025-10-22 07:06:31'),
(28, 44, 'uploads/rating_images/rating_368_3_2_1761116791.jpg', 2, '2025-10-22 07:06:31'),
(29, 44, 'uploads/rating_images/rating_368_3_3_1761116791.jpg', 3, '2025-10-22 07:06:31'),
(30, 45, 'uploads/rating_images/rating_372_10_1_1761147989.jpeg', 1, '2025-10-22 15:46:29'),
(31, 45, 'uploads/rating_images/rating_372_10_2_1761147989.jpg', 2, '2025-10-22 15:46:29'),
(32, 45, 'uploads/rating_images/rating_372_10_3_1761147989.jpeg', 3, '2025-10-22 15:46:29'),
(33, 46, 'uploads/rating_images/rating_373_3_1_1761148931.jpg', 1, '2025-10-22 16:02:11'),
(34, 52, 'uploads/rating_images/rating_390_3_1_1761798084.jpg', 1, '2025-10-30 04:21:24');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `report_type_id` int(11) NOT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_types`
--

CREATE TABLE `report_types` (
  `report_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restocking`
--

CREATE TABLE `restocking` (
  `restocking_id` int(11) NOT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `is_purchase_order` tinyint(1) DEFAULT 0,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_added` int(11) NOT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `restock_date` date NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `actual_quantity_received` decimal(10,2) DEFAULT NULL COMMENT 'Actual quantity received from supplier'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Restocking records with support for quantity adjustments during receiving';

--
-- Dumping data for table `restocking`
--

INSERT INTO `restocking` (`restocking_id`, `po_number`, `is_purchase_order`, `product_id`, `supplier_id`, `brand_id`, `batch_id`, `quantity_added`, `cost_per_unit`, `total_cost`, `restock_date`, `expiration_date`, `expected_delivery`, `status_id`, `notes`, `created_by`, `created_at`, `actual_quantity_received`) VALUES
(2, NULL, 0, 2, 1, NULL, NULL, 21, NULL, NULL, '2025-09-04', '2025-12-04', '2025-09-04', 2, NULL, 3, '2025-09-04 12:58:34', 21.00),
(3, NULL, 0, 1, 1, NULL, NULL, 10, NULL, NULL, '2025-09-05', '2025-12-05', '2025-09-05', 2, NULL, 4, '2025-09-05 00:51:50', 10.00),
(4, NULL, 0, 4, 3, NULL, NULL, 50, NULL, NULL, '2025-09-16', '2025-12-16', '2025-09-16', 2, NULL, 4, '2025-09-16 15:09:01', 50.00),
(5, NULL, 0, 5, 3, NULL, NULL, 20, NULL, NULL, '2025-09-17', '2025-12-17', '2025-09-17', 2, NULL, 4, '2025-09-17 15:46:07', 20.00),
(10, NULL, 0, 5, 7, NULL, 11, 10, NULL, NULL, '2025-09-19', '2025-12-19', '2025-09-19', 2, NULL, 4, '2025-09-19 02:36:00', 10.00),
(11, NULL, 0, 7, 5, NULL, 13, 10, NULL, NULL, '2025-09-19', '2025-12-19', '2025-09-19', 2, NULL, 4, '2025-09-19 03:08:01', 10.00),
(12, NULL, 0, 7, 1, NULL, 14, 10, NULL, NULL, '2025-09-19', '2025-12-19', '2025-09-19', 2, NULL, 4, '2025-09-19 03:14:50', 10.00),
(13, NULL, 0, 9, 7, NULL, 19, 1, NULL, NULL, '2025-09-23', '2025-12-23', '2025-09-22', 2, NULL, 4, '2025-09-22 04:57:34', 1.00),
(14, NULL, 0, 9, 3, NULL, 20, 30, NULL, NULL, '2025-09-22', '2025-12-22', '2025-09-22', 2, NULL, 4, '2025-09-22 04:59:56', 30.00),
(15, NULL, 0, 9, 3, NULL, 21, 30, NULL, NULL, '2025-09-22', '2025-12-22', '2025-09-22', 2, NULL, 4, '2025-09-22 05:04:22', 30.00),
(16, NULL, 0, 8, 5, NULL, 22, 1, NULL, NULL, '2025-09-23', '2025-12-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:21:21', 1.00),
(17, NULL, 0, 8, 1, NULL, 23, 1, NULL, NULL, '2025-09-23', '2025-12-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:22:21', 1.00),
(18, NULL, 0, 9, 7, NULL, 24, 2, NULL, NULL, '2025-09-23', '2025-12-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:30:13', 2.00),
(19, NULL, 0, 9, 3, NULL, 25, 2, NULL, NULL, '2025-09-23', '2025-12-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:30:54', 2.00),
(20, NULL, 0, 9, 3, NULL, 27, 2, NULL, NULL, '2025-09-23', '2025-12-23', NULL, 2, NULL, 4, '2025-09-23 09:57:34', 2.00),
(21, NULL, 0, 9, 3, NULL, 28, 1, NULL, NULL, '2025-09-23', '2025-12-23', NULL, 2, NULL, 4, '2025-09-23 14:08:26', 1.00),
(22, NULL, 0, 10, 3, NULL, 29, 10, NULL, NULL, '2025-09-24', '2025-12-24', NULL, 2, NULL, 4, '2025-09-24 09:28:42', 10.00),
(23, NULL, 0, 11, 7, NULL, 30, 9, NULL, NULL, '2025-09-27', '2025-12-27', NULL, 2, NULL, 4, '2025-09-27 15:30:59', 9.00),
(24, NULL, 0, 8, 1, NULL, 31, 100, NULL, NULL, '2025-10-07', '2026-01-07', NULL, 2, NULL, 4, '2025-10-07 02:46:47', 100.00),
(25, NULL, 0, 6, 5, NULL, 32, 50, NULL, NULL, '2025-10-07', '2026-01-07', NULL, 2, NULL, 4, '2025-10-07 02:47:34', 50.00),
(26, NULL, 0, 9, 3, NULL, NULL, 3, NULL, NULL, '2025-10-07', '2026-01-07', NULL, 2, NULL, 4, '2025-10-07 04:41:54', 3.00),
(27, NULL, 0, 9, 7, NULL, NULL, 4, NULL, NULL, '2025-10-07', '2026-01-07', NULL, 2, NULL, 4, '2025-10-07 04:42:10', 4.00),
(28, NULL, 0, 9, 3, NULL, NULL, 3, NULL, NULL, '2025-10-07', '2026-01-07', NULL, 2, NULL, 4, '2025-10-07 05:10:48', 3.00),
(29, NULL, 0, 12, 1, NULL, NULL, 3, NULL, NULL, '2025-10-07', '2026-01-07', NULL, 2, NULL, 4, '2025-10-07 05:28:21', 3.00),
(30, NULL, 0, 12, 1, NULL, NULL, 4, NULL, NULL, '2025-10-07', '2026-01-07', NULL, 2, NULL, 4, '2025-10-07 05:29:18', 4.00),
(31, NULL, 0, 13, 1, NULL, 38, 10, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 04:15:57', 10.00),
(32, NULL, 0, 13, 1, NULL, 39, 10, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 04:18:27', 10.00),
(33, NULL, 0, 14, 5, NULL, NULL, 11, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 05:56:26', 11.00),
(34, NULL, 0, 14, 5, NULL, 41, 1, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 10:20:02', 1.00),
(35, NULL, 0, 14, 5, NULL, 42, 10, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 11:22:04', 10.00),
(36, NULL, 0, 14, 5, NULL, 43, 50, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 14:52:29', 50.00),
(37, NULL, 0, 14, 7, NULL, 44, 2, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 15:14:01', 2.00),
(38, NULL, 0, 14, 5, NULL, 45, 10, NULL, NULL, '2025-10-08', '2026-01-08', NULL, 2, NULL, 4, '2025-10-08 15:15:50', 10.00),
(39, NULL, 0, 14, 5, NULL, 46, 1, NULL, NULL, '2025-10-09', '2026-01-09', NULL, 2, NULL, 4, '2025-10-09 04:06:27', 1.00),
(40, NULL, 0, 14, 5, NULL, 47, 1, NULL, NULL, '2025-10-09', '2026-01-09', NULL, 2, NULL, 4, '2025-10-09 04:28:37', 1.00),
(41, NULL, 0, 14, 5, NULL, 48, 10, NULL, NULL, '2025-10-09', '2026-01-09', NULL, 2, NULL, 4, '2025-10-09 04:31:04', 10.00),
(42, NULL, 0, 15, 3, NULL, 49, 10, NULL, NULL, '2025-10-10', '2026-01-10', NULL, 2, NULL, 4, '2025-10-10 06:13:18', 10.00),
(43, NULL, 0, 15, 3, NULL, 50, 10, NULL, NULL, '2025-10-10', '2026-01-10', NULL, 2, NULL, 4, '2025-10-10 06:14:13', 10.00),
(44, NULL, 0, 15, 3, NULL, 51, 15, NULL, NULL, '2025-10-10', '2026-01-10', NULL, 2, NULL, 4, '2025-10-10 07:01:40', 15.00),
(45, NULL, 0, 14, 5, NULL, 52, 35, NULL, NULL, '2025-10-10', '2026-01-10', NULL, 2, NULL, 4, '2025-10-10 07:15:28', 35.00),
(46, NULL, 0, 16, 1, NULL, 53, 35, NULL, NULL, '2025-10-10', '2026-01-10', NULL, 2, NULL, 4, '2025-10-10 11:25:05', 35.00),
(47, NULL, 0, 16, 1, NULL, 54, 13, NULL, NULL, '2025-10-10', '2026-01-10', NULL, 2, NULL, 4, '2025-10-10 11:26:49', 13.00),
(48, NULL, 0, 16, 1, NULL, 55, 30, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 04:52:34', 30.00),
(49, NULL, 0, 17, 7, NULL, 56, 1, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 05:24:06', 1.00),
(50, NULL, 0, 17, 7, NULL, 57, 1, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 05:59:48', 1.00),
(51, NULL, 0, 17, 7, NULL, 58, 1, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:11:05', 1.00),
(52, NULL, 0, 17, 7, NULL, 59, 2, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:11:22', 2.00),
(53, NULL, 0, 17, 7, NULL, 60, 1, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:19:35', 1.00),
(54, NULL, 0, 17, 7, NULL, 61, 1, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:19:43', 1.00),
(55, NULL, 0, 17, 7, NULL, 62, 2, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:20:40', 2.00),
(56, NULL, 0, 17, 7, NULL, 63, 2, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:22:39', 2.00),
(57, NULL, 0, 17, 7, NULL, 64, 1, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:23:14', 1.00),
(58, NULL, 0, 17, 7, NULL, 65, 3, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:25:44', 3.00),
(59, NULL, 0, 17, 7, NULL, 66, 6, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 06:26:01', 6.00),
(60, NULL, 0, 17, 7, NULL, 67, 1, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 11:25:00', 1.00),
(61, NULL, 0, 17, 7, NULL, 68, 3, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 11:27:08', 3.00),
(62, NULL, 0, 17, 7, NULL, 69, 10, NULL, NULL, '2025-10-12', '2026-01-12', NULL, 2, NULL, 4, '2025-10-12 14:58:39', 10.00),
(63, NULL, 0, 17, 7, NULL, 70, 50, NULL, NULL, '2025-10-13', '2026-01-13', NULL, 2, NULL, 4, '2025-10-13 07:52:49', 50.00),
(64, NULL, 0, 17, 5, NULL, 71, 10, NULL, NULL, '2025-10-16', '2026-01-16', NULL, 2, NULL, 4, '2025-10-16 06:02:54', 10.00),
(65, NULL, 0, 17, 5, NULL, 72, 50, NULL, NULL, '2025-10-16', '2026-01-16', NULL, 2, NULL, 4, '2025-10-16 15:33:51', 50.00),
(66, NULL, 0, 17, 5, NULL, 73, 20, NULL, NULL, '2025-10-16', '2026-01-16', NULL, 2, NULL, 4, '2025-10-16 15:34:22', 20.00),
(67, NULL, 0, 17, 5, NULL, 74, 50, NULL, NULL, '2025-10-17', '2026-01-17', NULL, 2, NULL, 4, '2025-10-17 00:51:45', 50.00),
(68, NULL, 0, 17, 5, NULL, 75, 30, NULL, NULL, '2025-10-17', '2026-01-17', NULL, 2, NULL, 4, '2025-10-17 00:52:33', 30.00),
(69, NULL, 0, 20, 8, NULL, 78, 54, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 05:15:46', 54.00),
(70, NULL, 0, 20, 8, NULL, 79, 1, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 05:33:54', 1.00),
(71, NULL, 0, 20, 8, NULL, 80, 10, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 05:37:01', 10.00),
(72, NULL, 0, 20, 8, NULL, 81, 550, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 05:37:23', 550.00),
(73, NULL, 0, 20, 8, NULL, 82, 10, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 05:38:56', 10.00),
(74, NULL, 0, 20, 8, NULL, 83, 1, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 05:41:48', 1.00),
(75, NULL, 0, 20, 8, NULL, 84, 10, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 05:50:40', 10.00),
(76, NULL, 0, 17, 5, NULL, 85, 2, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 06:16:30', 2.00),
(77, NULL, 0, 25, 8, NULL, 86, 5, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:39:11', 5.00),
(78, NULL, 0, 24, 9, NULL, 87, 9, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:39:32', 9.00),
(79, NULL, 0, 21, 9, NULL, 88, 7, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:39:48', 7.00),
(80, NULL, 0, 30, 3, NULL, 89, 10, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:40:21', 10.00),
(81, NULL, 0, 32, 8, NULL, 90, 12, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:40:34', 12.00),
(82, NULL, 0, 29, 11, NULL, 91, 14, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:42:09', 14.00),
(83, NULL, 0, 22, 7, NULL, 92, 11, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:42:28', 11.00),
(84, NULL, 0, 31, 11, NULL, 93, 15, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:42:42', 15.00),
(85, NULL, 0, 28, 10, NULL, 94, 17, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:42:54', 17.00),
(86, NULL, 0, 26, 10, NULL, 95, 21, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 11:43:32', 21.00),
(87, NULL, 0, 20, 9, NULL, 98, 10, NULL, NULL, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 12:14:13', 10.00),
(88, 'PO-20251019-0001', 1, 20, 9, 13, NULL, 88, 0.00, 0.00, '2025-10-19', '2026-01-19', NULL, 3, '', 4, '2025-10-19 13:42:54', NULL),
(89, 'PO-20251019-0001', 1, 20, 9, 14, NULL, 150, 0.00, 0.00, '2025-10-19', '2026-01-19', NULL, 3, '', 4, '2025-10-19 13:42:54', NULL),
(90, 'PO-20251019-0001', 1, 20, 9, 11, NULL, 149, 0.00, 0.00, '2025-10-19', '2026-01-19', NULL, 3, '', 4, '2025-10-19 13:42:54', NULL),
(91, 'PO-20251019-0004', 1, 20, 9, 11, 99, 149, 550.00, 81950.00, '2025-10-19', '2026-01-19', NULL, 2, '', 4, '2025-10-19 13:57:29', 149.00),
(92, NULL, 0, 27, 10, NULL, 100, 10, NULL, NULL, '2025-10-19', NULL, NULL, 2, NULL, 4, '2025-10-19 14:10:49', 10.00),
(93, NULL, 0, 23, 5, NULL, 101, 21, NULL, NULL, '2025-10-19', NULL, NULL, 2, NULL, 4, '2025-10-19 14:11:33', 21.00),
(94, NULL, 0, 25, 8, 19, 102, 1, 335.00, 335.00, '2025-10-19', '2026-01-19', NULL, 2, NULL, 4, '2025-10-19 14:16:01', 1.00),
(95, 'PO-20251020-0001', 1, 22, 7, 12, 103, 20, 260.00, 5200.00, '2025-10-20', '2026-01-20', NULL, 2, '', 4, '2025-10-20 00:58:43', 15.00),
(96, 'PO-20251020-0002', 1, 28, 10, 17, 104, 15, 250.00, 3750.00, '2025-10-20', '2026-01-20', NULL, 2, '', 4, '2025-10-20 01:54:28', 14.00),
(97, 'PO-20251020-0003', 1, 22, 7, 12, 105, 50, 330.00, 14850.00, '2025-10-20', '2026-01-20', NULL, 2, '', 4, '2025-10-20 14:21:38', 45.00),
(98, NULL, 0, 20, 8, 13, 106, 5, 850.00, 4250.00, '2025-10-22', '2026-01-22', NULL, 2, NULL, 4, '2025-10-22 03:49:33', NULL),
(99, NULL, 0, 20, 9, 11, 107, 5, 750.00, 3750.00, '2025-10-22', '2026-01-22', NULL, 2, NULL, 4, '2025-10-22 03:50:24', NULL),
(100, NULL, 0, 28, 10, 15, 108, 2, 250.00, 500.00, '2025-10-22', '2026-01-22', NULL, 2, NULL, 4, '2025-10-22 12:46:54', NULL),
(101, NULL, 0, 28, 10, 15, 109, 2, 290.00, 580.00, '2025-10-22', '2026-01-22', NULL, 2, NULL, 4, '2025-10-22 12:47:22', NULL),
(102, 'PO-20251024-0001', 1, 23, 5, 18, 110, 15, 300.00, 4500.00, '2025-10-24', '2026-01-24', NULL, 2, '', 4, '2025-10-24 01:25:59', 15.00),
(103, NULL, 0, 25, 11, 20, 111, 10, 250.00, 2500.00, '2025-10-25', '2026-01-25', NULL, 2, NULL, 4, '2025-10-25 13:29:57', NULL),
(104, 'PO-20251025-0001', 1, 24, 10, 1, NULL, 50, NULL, NULL, '2025-10-25', NULL, NULL, 3, '', 4, '2025-10-25 13:31:51', 50.00),
(105, 'PO-20251025-0002', 1, 24, 9, 19, NULL, 10, NULL, NULL, '2025-10-25', NULL, NULL, 3, '', 4, '2025-10-25 13:34:24', 10.00),
(106, 'PO-20251025-0002', 1, 21, 9, 15, NULL, 10, NULL, NULL, '2025-10-25', NULL, NULL, 3, '', 4, '2025-10-25 13:34:24', 10.00),
(107, 'PO-20251031-0001', 1, 24, 10, 1, 112, 10, 10.00, 100.00, '2025-10-31', '2026-01-31', NULL, 2, '', 4, '2025-10-31 08:41:55', 10.00),
(108, 'PO-20251031-0002', 1, 21, 1, 1, NULL, 8, NULL, NULL, '2025-10-31', NULL, NULL, 3, '', 4, '2025-10-31 08:58:34', 8.00),
(109, 'PO-20251031-0003', 1, 21, 1, 1, NULL, 8, NULL, NULL, '2025-10-31', NULL, NULL, 3, '', 4, '2025-10-31 09:39:39', 8.00),
(110, 'PO-20251031-0004', 1, 21, 9, 15, 113, 8, 8.00, 64.00, '2025-10-31', '2026-01-31', NULL, 2, '', 4, '2025-10-31 09:41:04', 8.00),
(111, NULL, 0, 21, 1, 15, 114, 10, 200.00, 2000.00, '2025-10-31', '2026-01-31', NULL, 2, NULL, 4, '2025-10-31 09:43:12', NULL),
(112, NULL, 0, 24, 9, 1, 115, 10, 250.00, 2500.00, '2025-10-31', '2026-01-31', NULL, 2, NULL, 4, '2025-10-31 09:43:34', NULL),
(113, NULL, 0, 27, 3, 18, 116, 10, 250.00, 2500.00, '2025-10-31', '2026-01-31', NULL, 2, NULL, 4, '2025-10-31 11:16:48', NULL),
(114, NULL, 0, 27, 3, 12, 117, 20, 300.00, 6000.00, '2025-10-31', '2026-01-31', NULL, 2, NULL, 4, '2025-10-31 11:20:55', NULL),
(115, NULL, 0, 27, 3, 13, 118, 10, 300.00, 3000.00, '2025-10-31', '2026-01-31', NULL, 2, NULL, 4, '2025-10-31 11:30:00', NULL),
(116, 'PO-20251031-0005', 1, 27, 10, 15, 119, 20, 325.00, 6500.00, '2025-10-31', '2026-01-31', NULL, 2, '', 4, '2025-10-31 11:41:19', 20.00),
(117, NULL, 0, 23, 1, 11, 120, 20, 150.00, 3000.00, '2025-10-31', '2026-01-31', NULL, 2, NULL, 4, '2025-10-31 11:43:56', NULL),
(118, NULL, 0, 23, 5, 11, 121, 20, 250.00, 5000.00, '2025-10-31', '2026-01-31', NULL, 2, NULL, 4, '2025-10-31 11:44:45', NULL),
(119, 'PO-20251031-0006', 1, 23, 1, 11, NULL, 42, NULL, NULL, '2025-10-31', NULL, NULL, 1, '', 4, '2025-10-31 11:52:26', NULL),
(120, 'PO-20251031-0007', 1, 24, 9, 1, NULL, 21, NULL, NULL, '2025-10-31', NULL, NULL, 1, '', 4, '2025-10-31 12:28:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `restocking_status`
--

CREATE TABLE `restocking_status` (
  `status_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restocking_status`
--

INSERT INTO `restocking_status` (`status_id`, `name`) VALUES
(1, 'Pending'),
(2, 'Received'),
(3, 'Cancelled');

-- --------------------------------------------------------

--
-- Table structure for table `return_orders`
--

CREATE TABLE `return_orders` (
  `returnorders_id` int(11) NOT NULL,
  `orders_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `returnstatus_id` int(11) NOT NULL,
  `return_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_status`
--

CREATE TABLE `return_status` (
  `returnstatus_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_status`
--

INSERT INTO `return_status` (`returnstatus_id`, `status_name`) VALUES
(1, 'Pending'),
(2, 'Approved'),
(3, 'Rejected'),
(4, 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_permission_id` int(11) NOT NULL,
  `usertype_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_permission_id`, `usertype_id`, `permission_id`, `created_at`) VALUES
(1, 3, 23, '2025-09-04 13:24:40'),
(2, 3, 26, '2025-09-04 13:24:40'),
(3, 3, 24, '2025-09-04 13:24:40'),
(4, 3, 25, '2025-09-04 13:24:40'),
(5, 3, 19, '2025-09-04 13:24:40'),
(6, 3, 22, '2025-09-04 13:24:40'),
(7, 3, 20, '2025-09-04 13:24:40'),
(8, 3, 21, '2025-09-04 13:24:40'),
(9, 3, 43, '2025-09-04 13:24:40'),
(10, 3, 42, '2025-09-04 13:24:40'),
(11, 3, 13, '2025-09-04 13:24:40'),
(12, 3, 14, '2025-09-04 13:24:40'),
(13, 3, 12, '2025-09-04 13:24:40'),
(14, 3, 11, '2025-09-04 13:24:40'),
(15, 3, 17, '2025-09-04 13:24:40'),
(16, 3, 18, '2025-09-04 13:24:40'),
(17, 3, 16, '2025-09-04 13:24:40'),
(18, 3, 15, '2025-09-04 13:24:40'),
(19, 3, 10, '2025-09-04 13:24:40'),
(20, 3, 6, '2025-09-04 13:24:40'),
(21, 3, 9, '2025-09-04 13:24:40'),
(22, 3, 7, '2025-09-04 13:24:40'),
(23, 3, 8, '2025-09-04 13:24:40'),
(24, 3, 36, '2025-09-04 13:24:40'),
(25, 3, 37, '2025-09-04 13:24:40'),
(26, 3, 35, '2025-09-04 13:24:40'),
(27, 3, 27, '2025-09-04 13:24:40'),
(28, 3, 30, '2025-09-04 13:24:40'),
(29, 3, 28, '2025-09-04 13:24:40'),
(30, 3, 29, '2025-09-04 13:24:40'),
(31, 3, 40, '2025-09-04 13:24:40'),
(32, 3, 39, '2025-09-04 13:24:40'),
(33, 3, 41, '2025-09-04 13:24:40'),
(34, 3, 38, '2025-09-04 13:24:40'),
(35, 3, 31, '2025-09-04 13:24:40'),
(36, 3, 34, '2025-09-04 13:24:40'),
(37, 3, 32, '2025-09-04 13:24:40'),
(38, 3, 33, '2025-09-04 13:24:40'),
(39, 3, 1, '2025-09-04 13:24:40'),
(40, 3, 5, '2025-09-04 13:24:40'),
(41, 3, 4, '2025-09-04 13:24:40'),
(42, 3, 2, '2025-09-04 13:24:40'),
(43, 3, 3, '2025-09-04 13:24:40'),
(175, 12, 15, '2025-10-15 15:39:07'),
(176, 13, 35, '2025-10-15 15:59:58'),
(177, 13, 36, '2025-10-15 15:59:58'),
(178, 13, 37, '2025-10-15 15:59:58'),
(179, 13, 42, '2025-10-15 15:59:58'),
(180, 13, 43, '2025-10-15 15:59:58'),
(204, 11, 11, '2025-10-16 04:15:22'),
(205, 11, 12, '2025-10-16 04:15:22'),
(206, 11, 13, '2025-10-16 04:15:22'),
(207, 11, 88, '2025-10-16 04:15:22'),
(208, 11, 6, '2025-10-16 04:15:22'),
(209, 11, 7, '2025-10-16 04:15:22'),
(210, 11, 8, '2025-10-16 04:15:22'),
(211, 11, 9, '2025-10-16 04:15:22'),
(212, 11, 20, '2025-10-16 04:15:22'),
(213, 11, 21, '2025-10-16 04:15:22'),
(214, 11, 22, '2025-10-16 04:15:22'),
(215, 11, 23, '2025-10-16 04:15:22'),
(216, 11, 24, '2025-10-16 04:15:22'),
(217, 11, 25, '2025-10-16 04:15:22'),
(218, 11, 26, '2025-10-16 04:15:22'),
(219, 11, 27, '2025-10-16 04:15:22'),
(220, 11, 28, '2025-10-16 04:15:22'),
(221, 11, 29, '2025-10-16 04:15:22'),
(222, 11, 30, '2025-10-16 04:15:22'),
(223, 11, 32, '2025-10-16 04:15:22'),
(224, 11, 33, '2025-10-16 04:15:22'),
(225, 11, 34, '2025-10-16 04:15:22'),
(226, 11, 89, '2025-10-16 04:15:22');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment`
--

CREATE TABLE `stock_adjustment` (
  `stockadjustment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `adjustment_type_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustment`
--

INSERT INTO `stock_adjustment` (`stockadjustment_id`, `product_id`, `adjustment_type_id`, `quantity`, `previous_stock`, `new_stock`, `reason`, `notes`, `supplier_id`, `expiration_date`, `created_by`, `created_at`) VALUES
(1, 2, 1, 10, 21, 31, 'Damaged Items', NULL, NULL, NULL, 3, '2025-09-04 12:58:53'),
(2, 5, 1, 10, 20, 30, 'Manual Correction', NULL, NULL, NULL, 4, '2025-09-17 15:51:47'),
(3, 5, 1, 10, 27, 37, 'Other', NULL, NULL, NULL, 4, '2025-09-19 01:48:44'),
(4, 5, 1, 10, 37, 47, 'Quality Control', NULL, 7, '2026-01-20', 4, '2025-09-19 01:55:56'),
(5, 5, 1, 10, 57, 67, 'Quality Control', NULL, 3, '2026-03-26', 4, '2025-09-19 02:38:21'),
(6, 7, 1, 10, 18, 28, 'Other', NULL, 1, '2026-03-23', 4, '2025-09-22 03:57:22'),
(7, 9, 2, 30, 31, 1, 'Damaged Items', NULL, 3, '2026-03-22', 4, '2025-09-22 05:01:55'),
(8, 9, 2, 1, 1, 0, 'Damaged Items', NULL, 3, NULL, 4, '2025-09-22 05:02:22'),
(9, 8, 2, 1, 2, 1, 'Damaged Items', NULL, 5, NULL, 4, '2025-09-23 09:22:44'),
(10, 8, 2, 1, 1, 0, 'Damaged Items', NULL, 1, NULL, 4, '2025-09-23 09:23:30'),
(11, 9, 2, 29, 29, 0, 'Damaged Items', NULL, 3, NULL, 4, '2025-09-23 09:28:15'),
(12, 9, 1, 2, 2, 4, 'Other', NULL, 7, '2026-03-23', 4, '2025-09-23 09:34:21'),
(13, 9, 2, 1, 4, 3, 'Damaged Items', NULL, 3, NULL, 4, '2025-09-23 09:58:02'),
(14, 9, 2, 1, 3, 2, 'Manual Correction', NULL, 3, NULL, 4, '2025-09-23 09:58:15'),
(15, 9, 2, 2, 2, 0, 'Damaged Items', NULL, 7, NULL, 4, '2025-09-23 09:58:37'),
(16, 17, 2, 10, 58, 48, 'Supplier Return', 'Near Expiration ', 5, NULL, 4, '2025-10-16 06:06:59'),
(17, 17, 2, 5, 48, 43, 'Damaged Items', NULL, 7, NULL, 4, '2025-10-16 06:16:37'),
(18, 17, 2, 10, 43, 33, 'Supplier Return', NULL, 7, NULL, 4, '2025-10-16 13:38:17'),
(19, 17, 2, 10, 33, 23, 'Supplier Return', NULL, 7, NULL, 4, '2025-10-16 13:50:52'),
(20, 17, 2, 10, 70, 60, 'Damaged Items', NULL, 5, NULL, 4, '2025-10-16 15:35:55'),
(21, 17, 2, 10, 75, 65, 'Supplier Return', NULL, 5, NULL, 4, '2025-10-18 07:29:09'),
(28, 1, 2, 20, 23, 3, 'Expired', 'Automatic expiration processing - Batch: TRIMMINGS-BATCH001, Expired: 2025-09-10', NULL, '2025-09-10', 2, '2025-10-18 15:59:33'),
(29, 2, 2, 20, 37, 17, 'Expired', 'Automatic expiration processing - Batch: BANGUS-BATCH001, Expired: 2025-09-10', NULL, '2025-09-10', 2, '2025-10-18 15:59:33'),
(30, 2, 2, 15, 17, 2, 'Expired', 'Automatic expiration processing - Batch: BANGUS-BATCH002, Expired: 2025-09-20', NULL, '2025-09-20', 2, '2025-10-18 15:59:33'),
(31, 18, 2, 50, 100, 50, 'Expired', 'Automatic expiration processing - Batch: B18-20251018-001, Expired: 2025-10-17', 1, '2025-10-17', 2, '2025-10-18 15:59:33'),
(32, 19, 2, 50, 100, 50, 'Expired', 'Automatic expiration processing - Batch: B19-20251018-001, Expired: 2025-10-17', 1, '2025-10-17', 2, '2025-10-18 15:59:40'),
(33, 20, 2, 200, 625, 425, 'Supplier Return', NULL, 8, NULL, 4, '2025-10-19 05:40:00'),
(34, 20, 2, 100, 425, 325, 'Supplier Return', NULL, 8, NULL, 4, '2025-10-19 05:40:44'),
(35, 20, 2, 100, 325, 225, 'Supplier Return', NULL, 8, NULL, 4, '2025-10-19 05:41:10'),
(36, 4, 2, 20, 18, -2, 'Expired', 'Manual pull out - Batch: B4-20250919-003', NULL, '2025-09-24', 4, '2025-10-22 13:53:25'),
(37, 1, 2, 15, 3, -12, 'Expired', 'Manual pull out - Batch: TRIMMINGS-BATCH002', NULL, '2025-09-20', 4, '2025-10-22 13:53:58'),
(38, 4, 2, 25, -2, -27, 'Expired', 'Manual pull out - Batch: B4-20250919-002', NULL, '2025-10-19', 4, '2025-10-22 13:56:44'),
(39, 1, 2, 25, -12, -37, 'Expired', 'Manual pull out - Batch: TRIMMINGS-BATCH003', NULL, '2025-10-05', 4, '2025-10-22 13:56:52'),
(40, 2, 2, 25, 2, -23, 'Expired', 'Manual pull out - Batch: BANGUS-BATCH003', NULL, '2025-10-05', 4, '2025-10-22 13:58:13');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `stockmovement_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stockmovementtype_id` int(11) NOT NULL,
  `quantity` decimal(10,1) NOT NULL,
  `previous_stock` decimal(10,1) NOT NULL,
  `new_stock` decimal(10,1) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `batch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`stockmovement_id`, `product_id`, `stockmovementtype_id`, `quantity`, `previous_stock`, `new_stock`, `reference_id`, `reference_type`, `reason`, `created_by`, `created_at`, `batch_id`) VALUES
(1, 2, 1, 21.0, 0.0, 21.0, 0, 'restock', 'Restocking', 3, '2025-09-04 12:58:34', NULL),
(2, 2, 4, 10.0, 21.0, 31.0, 1, 'adjustment', 'Damaged Items', 3, '2025-09-04 12:58:53', NULL),
(3, 1, 1, 10.0, 0.0, 10.0, 0, 'restock', 'Restocking', 1000000, '2025-09-05 00:51:50', NULL),
(4, 1, 1, 10.0, 3.0, 13.0, 3, 'restock', 'Restocking - Status Updated', 4, '2025-09-10 16:25:59', NULL),
(5, 1, 1, 10.0, 13.0, 23.0, 3, 'restock', 'Restocking - Status Updated', 4, '2025-09-10 16:26:10', NULL),
(6, 2, 1, 21.0, 16.0, 37.0, 2, 'restock', 'Restocking - Status Updated', 4, '2025-09-10 16:26:23', NULL),
(7, 4, 1, 50.0, 0.0, 50.0, 0, 'restock', 'Restocking', 4, '2025-09-16 15:09:01', NULL),
(8, 5, 1, 20.0, 0.0, 20.0, 0, 'restock', 'Restocking', 4, '2025-09-17 15:46:07', NULL),
(9, 5, 4, 10.0, 20.0, 30.0, 2, 'adjustment', 'Manual Correction', 4, '2025-09-17 15:51:47', NULL),
(10, 5, 4, 10.0, 27.0, 37.0, 3, 'adjustment', 'Other', 4, '2025-09-19 01:48:44', NULL),
(11, 5, 4, 10.0, 37.0, 47.0, 4, 'adjustment', 'Quality Control', 4, '2025-09-19 01:55:56', NULL),
(12, 5, 1, 10.0, 47.0, 57.0, 10, 'restock', 'Restocking', 4, '2025-09-19 02:36:00', NULL),
(13, 5, 4, 10.0, 57.0, 67.0, 5, 'adjustment', 'Quality Control', 4, '2025-09-19 02:38:21', NULL),
(14, 7, 1, 10.0, 0.0, 10.0, 11, 'restock', 'Restocking', 4, '2025-09-19 03:08:01', NULL),
(15, 7, 1, 10.0, 10.0, 20.0, 12, 'restock', 'Restocking', 4, '2025-09-19 03:14:50', NULL),
(16, 7, 4, 10.0, 18.0, 28.0, 6, 'adjustment', 'Other', 4, '2025-09-22 03:57:22', NULL),
(17, 9, 1, 1.0, 0.0, 1.0, 13, 'restock', 'Restocking', 4, '2025-09-22 04:57:34', NULL),
(18, 9, 1, 30.0, 1.0, 31.0, 14, 'restock', 'Restocking', 4, '2025-09-22 04:59:56', NULL),
(19, 9, 4, 30.0, 31.0, 1.0, 7, 'adjustment', 'Damaged Items', 4, '2025-09-22 05:01:55', NULL),
(20, 9, 4, 1.0, 1.0, 0.0, 8, 'adjustment', 'Damaged Items', 4, '2025-09-22 05:02:22', NULL),
(21, 9, 1, 30.0, 0.0, 30.0, 15, 'restock', 'Restocking', 4, '2025-09-22 05:04:22', NULL),
(22, 8, 1, 1.0, 0.0, 1.0, 16, 'restock', 'Restocking', 4, '2025-09-23 09:21:21', NULL),
(23, 8, 1, 1.0, 1.0, 2.0, 17, 'restock', 'Restocking', 4, '2025-09-23 09:22:21', NULL),
(24, 8, 4, 1.0, 2.0, 1.0, 9, 'adjustment', 'Damaged Items', 4, '2025-09-23 09:22:44', NULL),
(25, 8, 4, 1.0, 1.0, 0.0, 10, 'adjustment', 'Damaged Items', 4, '2025-09-23 09:23:30', NULL),
(26, 9, 4, 29.0, 29.0, 0.0, 11, 'adjustment', 'Damaged Items', 4, '2025-09-23 09:28:15', NULL),
(27, 9, 1, 2.0, 0.0, 2.0, 18, 'restock', 'Restocking', 4, '2025-09-23 09:30:13', NULL),
(28, 9, 1, 2.0, 2.0, 4.0, 19, 'restock', 'Restocking', 4, '2025-09-23 09:30:54', NULL),
(29, 9, 4, 2.0, 2.0, 4.0, 12, 'adjustment', 'Other', 4, '2025-09-23 09:34:21', NULL),
(30, 9, 1, 2.0, 2.0, 4.0, 20, 'restock', 'Restocking', 4, '2025-09-23 09:57:34', NULL),
(31, 9, 4, 1.0, 4.0, 3.0, 13, 'adjustment', 'Damaged Items', 4, '2025-09-23 09:58:02', NULL),
(32, 9, 4, 1.0, 3.0, 2.0, 14, 'adjustment', 'Manual Correction', 4, '2025-09-23 09:58:15', NULL),
(33, 9, 4, 2.0, 2.0, 0.0, 15, 'adjustment', 'Damaged Items', 4, '2025-09-23 09:58:37', NULL),
(34, 9, 1, 1.0, 0.0, 1.0, 21, 'restock', 'Restocking', 4, '2025-09-23 14:08:26', NULL),
(35, 10, 1, 10.0, 0.0, 10.0, 22, 'restock', 'Restocking', 4, '2025-09-24 09:28:42', NULL),
(36, 11, 1, 9.0, 0.0, 9.0, 23, 'restock', 'Restocking', 4, '2025-09-27 15:30:59', NULL),
(37, 8, 1, 100.0, 0.0, 100.0, 24, 'restock', 'Restocking', 4, '2025-10-07 02:46:47', NULL),
(38, 6, 1, 50.0, 0.0, 50.0, 25, 'restock', 'Restocking', 4, '2025-10-07 02:47:34', NULL),
(39, 6, 3, 2.0, 41.0, 43.0, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-07 04:16:38', NULL),
(40, 9, 1, 2.5, -0.5, 2.0, 26, 'restock', 'Restocking', 4, '2025-10-07 04:41:54', NULL),
(41, 9, 1, 3.5, 2.5, 6.0, 27, 'restock', 'Restocking', 4, '2025-10-07 04:42:10', NULL),
(42, 9, 1, 2.5, -0.5, 2.0, 28, 'restock', 'Restocking', 4, '2025-10-07 05:10:48', NULL),
(43, 12, 1, 2.5, -0.5, 2.0, 29, 'restock', 'Restocking', 4, '2025-10-07 05:28:21', NULL),
(44, 12, 1, 3.5, 2.5, 6.0, 30, 'restock', 'Restocking', 4, '2025-10-07 05:29:18', NULL),
(45, 13, 1, 10.0, 0.0, 10.0, 31, 'restock', 'Restocking', 4, '2025-10-08 04:15:57', NULL),
(46, 13, 1, 10.0, 10.0, 20.0, 32, 'restock', 'Restocking', 4, '2025-10-08 04:18:27', NULL),
(47, 14, 1, 10.5, -0.5, 10.0, 33, 'restock', 'Restocking', 4, '2025-10-08 05:56:26', NULL),
(48, 14, 1, 1.0, 0.0, 1.0, 34, 'restock', 'Restocking', 4, '2025-10-08 10:20:02', NULL),
(49, 14, 3, 1.0, 0.0, 1.0, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:02:01', NULL),
(50, 10, 3, 2.5, 2.5, 5.0, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:06:40', NULL),
(51, 10, 3, 2.5, 5.0, 7.5, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:06:44', NULL),
(52, 10, 3, 2.5, 7.5, 10.0, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:06:49', NULL),
(53, 10, 3, 2.5, 10.0, 12.5, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:06:51', NULL),
(54, 10, 3, 2.5, 12.5, 15.0, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:14:58', NULL),
(55, 11, 3, 3.5, 0.0, 3.5, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:15:02', NULL),
(56, 13, 3, 1.0, 19.0, 20.0, NULL, 'order_cancellation', 'Order cancellation - stock restored', 4, '2025-10-08 11:15:04', NULL),
(57, 14, 1, 10.0, 1.0, 11.0, 35, 'restock', 'Restocking', 4, '2025-10-08 11:22:04', NULL),
(58, 14, 1, 50.0, 0.0, 50.0, 36, 'restock', 'Restocking', 4, '2025-10-08 14:52:29', NULL),
(59, 14, 1, 2.0, 45.0, 47.0, 37, 'restock', 'Restocking', 4, '2025-10-08 15:14:01', NULL),
(60, 14, 1, 10.0, 2.0, 12.0, 38, 'restock', 'Restocking', 4, '2025-10-08 15:15:50', NULL),
(61, 14, 1, 1.0, 8.0, 9.0, 39, 'restock', 'Restocking', 4, '2025-10-09 04:06:27', NULL),
(62, 14, 1, 1.0, 1.0, 2.0, 40, 'restock', 'Restocking', 4, '2025-10-09 04:28:37', NULL),
(63, 14, 1, 10.0, 0.0, 10.0, 41, 'restock', 'Restocking', 4, '2025-10-09 04:31:04', NULL),
(64, 15, 1, 10.0, 0.0, 10.0, 42, 'restock', 'Restocking', 4, '2025-10-10 06:13:18', NULL),
(65, 15, 1, 10.0, 10.0, 20.0, 43, 'restock', 'Restocking', 4, '2025-10-10 06:14:13', NULL),
(66, 15, 1, 15.0, 20.0, 35.0, 44, 'restock', 'Restocking', 4, '2025-10-10 07:01:40', NULL),
(67, 14, 1, 35.0, 3.0, 38.0, 45, 'restock', 'Restocking', 4, '2025-10-10 07:15:28', NULL),
(68, 16, 1, 35.0, 0.0, 35.0, 46, 'restock', 'Restocking', 4, '2025-10-10 11:25:05', NULL),
(69, 16, 1, 13.0, 35.0, 48.0, 47, 'restock', 'Restocking', 4, '2025-10-10 11:26:49', NULL),
(70, 16, 1, 30.0, 48.0, 78.0, 48, 'restock', 'Restocking', 4, '2025-10-12 04:52:34', NULL),
(71, 17, 1, 1.0, 0.0, 1.0, 49, 'restock', 'Restocking', 4, '2025-10-12 05:24:06', NULL),
(72, 17, 1, 1.0, 0.0, 1.0, 50, 'restock', 'Restocking', 4, '2025-10-12 05:59:48', NULL),
(73, 17, 1, 1.0, 0.0, 1.0, 51, 'restock', 'Restocking', 4, '2025-10-12 06:11:05', NULL),
(74, 17, 1, 2.0, 1.0, 3.0, 52, 'restock', 'Restocking', 4, '2025-10-12 06:11:22', NULL),
(75, 17, 1, 1.0, 0.0, 1.0, 53, 'restock', 'Restocking', 4, '2025-10-12 06:19:35', NULL),
(76, 17, 1, 1.0, 1.0, 2.0, 54, 'restock', 'Restocking', 4, '2025-10-12 06:19:43', NULL),
(77, 17, 1, 2.0, 1.0, 3.0, 55, 'restock', 'Restocking', 4, '2025-10-12 06:20:40', NULL),
(78, 17, 1, 2.0, 0.0, 2.0, 56, 'restock', 'Restocking', 4, '2025-10-12 06:22:39', NULL),
(79, 17, 1, 1.0, 2.0, 3.0, 57, 'restock', 'Restocking', 4, '2025-10-12 06:23:14', NULL),
(80, 17, 1, 2.5, -0.5, 2.0, 58, 'restock', 'Restocking', 4, '2025-10-12 06:25:44', NULL),
(81, 17, 1, 5.5, 2.5, 8.0, 59, 'restock', 'Restocking', 4, '2025-10-12 06:26:01', NULL),
(82, 17, 1, 1.0, 0.0, 1.0, 60, 'restock', 'Restocking', 4, '2025-10-12 11:25:00', NULL),
(83, 17, 1, 2.5, 0.5, 3.0, 61, 'restock', 'Restocking', 4, '2025-10-12 11:27:08', NULL),
(84, 17, 1, 10.0, 0.0, 10.0, 62, 'restock', 'Restocking', 4, '2025-10-12 14:58:39', NULL),
(85, 17, 1, 50.0, 0.0, 50.0, 63, 'restock', 'Restocking', 4, '2025-10-13 07:52:49', NULL),
(86, 17, 1, 10.0, 48.0, 58.0, 64, 'restock', 'Restocking', 4, '2025-10-16 06:02:54', NULL),
(87, 17, 4, 10.0, 58.0, 48.0, 16, 'adjustment', 'Supplier Return', 4, '2025-10-16 06:06:59', NULL),
(88, 17, 4, 5.0, 48.0, 43.0, 17, 'adjustment', 'Damaged Items', 4, '2025-10-16 06:16:37', NULL),
(89, 17, 4, 10.0, 43.0, 33.0, 18, 'adjustment', 'Supplier Return', 4, '2025-10-16 13:38:17', NULL),
(90, 17, 4, 10.0, 33.0, 23.0, 19, 'adjustment', 'Supplier Return', 4, '2025-10-16 13:50:52', NULL),
(91, 17, 1, 50.0, 0.0, 50.0, 65, 'restock', 'Restocking', 4, '2025-10-16 15:33:51', NULL),
(92, 17, 1, 20.0, 50.0, 70.0, 66, 'restock', 'Restocking', 4, '2025-10-16 15:34:22', NULL),
(93, 17, 4, 10.0, 70.0, 60.0, 20, 'adjustment', 'Damaged Items', 4, '2025-10-16 15:35:55', NULL),
(94, 17, 1, 50.0, 60.0, 110.0, 67, 'restock', 'Restocking', 4, '2025-10-17 00:51:45', NULL),
(95, 17, 1, 30.0, 110.0, 140.0, 68, 'restock', 'Restocking', 4, '2025-10-17 00:52:33', NULL),
(96, 17, 4, 10.0, 75.0, 65.0, 21, 'adjustment', 'Supplier Return', 4, '2025-10-18 07:29:09', NULL),
(97, 1, 4, 20.0, 23.0, 3.0, NULL, 'expiration', 'Automatic Expiration: Batch TRIMMINGS-BATCH001', 2, '2025-10-18 15:59:33', NULL),
(98, 2, 4, 20.0, 37.0, 17.0, NULL, 'expiration', 'Automatic Expiration: Batch BANGUS-BATCH001', 2, '2025-10-18 15:59:33', NULL),
(99, 2, 4, 15.0, 17.0, 2.0, NULL, 'expiration', 'Automatic Expiration: Batch BANGUS-BATCH002', 2, '2025-10-18 15:59:33', NULL),
(100, 18, 4, 50.0, 100.0, 50.0, NULL, 'expiration', 'Automatic Expiration: Batch B18-20251018-001', 2, '2025-10-18 15:59:33', NULL),
(101, 19, 4, 50.0, 100.0, 50.0, NULL, 'expiration', 'Automatic Expiration: Batch B19-20251018-001', 2, '2025-10-18 15:59:40', NULL),
(102, 20, 1, 54.0, 0.0, 54.0, 69, 'restock', 'Restocking', 4, '2025-10-19 05:15:46', NULL),
(103, 20, 1, 1.0, 54.0, 55.0, 70, 'restock', 'Restocking', 4, '2025-10-19 05:33:54', NULL),
(104, 20, 1, 10.0, 55.0, 65.0, 71, 'restock', 'Restocking', 4, '2025-10-19 05:37:01', NULL),
(105, 20, 1, 550.0, 65.0, 615.0, 72, 'restock', 'Restocking', 4, '2025-10-19 05:37:23', NULL),
(106, 20, 1, 10.0, 615.0, 625.0, 73, 'restock', 'Restocking', 4, '2025-10-19 05:38:56', NULL),
(107, 20, 4, 200.0, 625.0, 425.0, 33, 'adjustment', 'Supplier Return', 4, '2025-10-19 05:40:00', NULL),
(108, 20, 4, 100.0, 425.0, 325.0, 34, 'adjustment', 'Supplier Return', 4, '2025-10-19 05:40:44', NULL),
(109, 20, 4, 100.0, 325.0, 225.0, 35, 'adjustment', 'Supplier Return', 4, '2025-10-19 05:41:10', NULL),
(110, 20, 1, 1.0, 225.0, 226.0, 74, 'restock', 'Restocking', 4, '2025-10-19 05:41:49', NULL),
(111, 20, 1, 10.0, 226.0, 236.0, 75, 'restock', 'Restocking', 4, '2025-10-19 05:50:40', NULL),
(112, 17, 1, 2.0, 48.0, 50.0, 76, 'restock', 'Restocking', 4, '2025-10-19 06:16:30', NULL),
(113, 25, 1, 5.0, 0.0, 5.0, 77, 'restock', 'Restocking', 4, '2025-10-19 11:39:11', NULL),
(114, 24, 1, 9.0, 0.0, 9.0, 78, 'restock', 'Restocking', 4, '2025-10-19 11:39:32', NULL),
(115, 21, 1, 7.0, 0.0, 7.0, 79, 'restock', 'Restocking', 4, '2025-10-19 11:39:48', NULL),
(116, 30, 1, 10.0, 0.0, 10.0, 80, 'restock', 'Restocking', 4, '2025-10-19 11:40:21', NULL),
(117, 32, 1, 12.0, 0.0, 12.0, 81, 'restock', 'Restocking', 4, '2025-10-19 11:40:34', NULL),
(118, 29, 1, 14.0, 0.0, 14.0, 82, 'restock', 'Restocking', 4, '2025-10-19 11:42:09', NULL),
(119, 22, 1, 11.0, 0.0, 11.0, 83, 'restock', 'Restocking', 4, '2025-10-19 11:42:28', NULL),
(120, 31, 1, 15.0, 0.0, 15.0, 84, 'restock', 'Restocking', 4, '2025-10-19 11:42:42', NULL),
(121, 28, 1, 17.0, 0.0, 17.0, 85, 'restock', 'Restocking', 4, '2025-10-19 11:42:54', NULL),
(122, 26, 1, 21.0, 0.0, 21.0, 86, 'restock', 'Restocking', 4, '2025-10-19 11:43:32', NULL),
(123, 20, 1, 10.0, 219.0, 229.0, 87, 'restock', 'Restocking', 4, '2025-10-19 12:14:13', NULL),
(124, 20, 1, 149.0, 89.0, 238.0, 91, 'restock', 'Restocking - Status Updated', 4, '2025-10-19 14:00:47', NULL),
(125, 27, 1, 10.0, 0.0, 10.0, 92, 'restock', 'Restocking', 4, '2025-10-19 14:10:49', NULL),
(126, 23, 1, 21.0, 0.0, 21.0, 93, 'restock', 'Restocking', 4, '2025-10-19 14:11:33', NULL),
(127, 25, 1, 1.0, 5.0, 6.0, 94, 'restock', 'Restocking', 4, '2025-10-19 14:16:01', NULL),
(128, 22, 1, 15.0, 1.0, 16.0, 95, 'restock', 'Restocking - Status Updated', 4, '2025-10-20 01:08:09', NULL),
(129, 28, 1, 14.0, 2.0, 16.0, 96, 'restock', 'Restocking - Status Updated', 4, '2025-10-20 01:55:21', NULL),
(130, 22, 1, 45.0, 10.0, 55.0, 97, 'restock', 'Restocking - Status Updated', 4, '2025-10-20 14:22:20', NULL),
(131, 20, 1, 5.0, 238.0, 243.0, 98, 'restock', 'Restocking', 4, '2025-10-22 03:49:33', NULL),
(132, 20, 1, 5.0, 243.0, 248.0, 99, 'restock', 'Restocking', 4, '2025-10-22 03:50:24', NULL),
(133, 28, 1, 2.0, 0.0, 2.0, 100, 'restock', 'Restocking', 4, '2025-10-22 12:46:54', NULL),
(134, 28, 1, 2.0, 2.0, 4.0, 101, 'restock', 'Restocking', 4, '2025-10-22 12:47:22', NULL),
(135, 28, 3, 3.0, 0.0, 3.0, 371, 'order_cancellation', 'Order cancellation: Customer unable to visit the store', 4, '2025-10-22 12:55:06', NULL),
(136, 23, 3, 15.0, 6.0, 21.0, 359, 'order_cancellation', 'Order cancellation: Customer unable to visit the store', 4, '2025-10-22 12:55:52', NULL),
(137, 4, 2, 20.0, 18.0, -2.0, 36, 'stock_adjustment', 'Expired', 4, '2025-10-22 13:53:25', NULL),
(138, 1, 2, 15.0, 3.0, -12.0, 37, 'stock_adjustment', 'Expired', 4, '2025-10-22 13:53:58', NULL),
(139, 4, 2, 25.0, -2.0, -27.0, 38, 'stock_adjustment', 'Expired', 4, '2025-10-22 13:56:44', NULL),
(140, 1, 2, 25.0, -12.0, -37.0, 39, 'stock_adjustment', 'Expired', 4, '2025-10-22 13:56:52', NULL),
(141, 2, 2, 25.0, 2.0, -23.0, 40, 'stock_adjustment', 'Expired', 4, '2025-10-22 13:58:13', NULL),
(142, 23, 1, 15.0, 11.0, 26.0, 102, 'restock', 'Restocking - Status Updated', 4, '2025-10-24 01:26:42', NULL),
(143, 25, 1, 10.0, 0.0, 10.0, 103, 'restock', 'Restocking', 4, '2025-10-25 13:29:57', NULL),
(144, 17, 3, 12.0, 0.0, 12.0, 382, 'order_cancellation', 'Order cancellation: Customer did not pick up order within 3 hours (6.9 hours elapsed)', 4, '2025-10-26 02:48:50', NULL),
(145, 20, 3, 1.0, 186.9, 187.9, 388, 'order_cancellation', 'Order cancellation: Customer did not pick up order within 3 hours (11.6 hours elapsed)', 4, '2025-10-31 07:56:10', NULL),
(146, 31, 3, 1.0, 3.0, 4.0, 385, 'order_cancellation', 'Order cancellation: Customer did not pick up order within 3 hours (111.4 hours elapsed)', 4, '2025-10-31 08:05:44', NULL),
(147, 23, 3, 1.0, 23.0, 24.0, 393, 'order_cancellation', 'Order cancellation: Insufficient Payment', 4, '2025-10-31 08:06:27', NULL),
(148, 23, 3, 1.0, 23.0, 24.0, 396, 'order_cancellation', 'Order cancellation: Insufficient Payment', 4, '2025-10-31 08:12:30', NULL),
(149, 24, 1, 10.0, 0.0, 10.0, 107, 'restock', 'Restocking - Status Updated', 4, '2025-10-31 08:50:29', NULL),
(150, 21, 1, 8.0, 0.0, 8.0, 110, 'restock', 'Restocking - Status Updated', 4, '2025-10-31 09:41:29', NULL),
(151, 21, 1, 10.0, 8.0, 18.0, 111, 'restock', 'Restocking', 4, '2025-10-31 09:43:12', NULL),
(152, 24, 1, 10.0, 10.0, 20.0, 112, 'restock', 'Restocking', 4, '2025-10-31 09:43:34', NULL),
(153, 27, 3, 4.0, 0.0, 4.0, 398, 'order_cancellation', 'Order cancellation: Customer unable to visit the store', 4, '2025-10-31 11:13:23', NULL),
(154, 27, 1, 10.0, 0.0, 10.0, 113, 'restock', 'Restocking', 4, '2025-10-31 11:16:48', NULL),
(155, 27, 1, 20.0, 10.0, 30.0, 114, 'restock', 'Restocking', 4, '2025-10-31 11:20:55', NULL),
(156, 27, 1, 10.0, 20.0, 30.0, 115, 'restock', 'Restocking', 4, '2025-10-31 11:30:00', NULL),
(157, 27, 1, 20.0, 30.0, 50.0, 116, 'restock', 'Restocking - Status Updated', 4, '2025-10-31 11:42:07', NULL),
(158, 23, 1, 20.0, 24.0, 44.0, 117, 'restock', 'Restocking', 4, '2025-10-31 11:43:56', NULL),
(159, 23, 1, 20.0, 44.0, 64.0, 118, 'restock', 'Restocking', 4, '2025-10-31 11:44:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movement_types`
--

CREATE TABLE `stock_movement_types` (
  `stockmovementtype_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movement_types`
--

INSERT INTO `stock_movement_types` (`stockmovementtype_id`, `name`) VALUES
(1, 'Restock'),
(2, 'Sale'),
(3, 'Return'),
(4, 'Adjustment');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archive` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `name`, `phone`, `email`, `address_line`, `city`, `postal_code`, `country`, `notes`, `is_archive`, `created_at`, `updated_at`) VALUES
(1, 'SGB Goods', '0921 676 444', 'ronaldo@gmail.com', '', NULL, NULL, NULL, '', 0, '2025-07-12 13:56:23', '2025-09-24 08:53:46'),
(3, 'ZAYN GOODS', '09213197822', 'gi@gmail.com', '', NULL, NULL, NULL, '', 0, '2025-07-25 12:27:22', '2025-09-24 08:53:49'),
(4, 'Marion Brix Quiling', '09213197822', 'brixquils16@gmail.com', '100-c Kasunduan Extension Brgy. Commonwealth Q.c.\r\nKatena Hoa Multipurpose', NULL, NULL, NULL, '', 1, '2025-09-03 12:20:02', '2025-09-03 12:23:49'),
(5, 'Marion Brix Quiling', '09213197822', 'brixquils16@gmail.com', '100-c Kasunduan Extension Brgy. Commonwealth Q.c.\r\nKatena Hoa Multipurpose', NULL, NULL, NULL, '', 0, '2025-09-03 12:22:48', '2025-09-03 12:22:48'),
(6, 'TEST TEST TEST', '09213197822', 'test@gmail.com', '100-c Kasunduan Extension Brgy. Commonwealth Q.c.\r\nKatena Hoa Multipurpose', NULL, NULL, NULL, '', 0, '2025-09-09 15:00:23', '2025-10-25 11:01:11'),
(7, 'BALIWAG', '09213197822', 'jay@gmail.com', '10th avenue, caloocan', NULL, NULL, NULL, '', 0, '2025-09-10 15:19:19', '2025-09-10 15:19:19'),
(8, 'Davidson Frozen', '09213197822', 'kulot@gmail.com', '324 Batasan, Quezon City', NULL, NULL, NULL, '', 0, '2025-10-19 05:14:44', '2025-10-19 05:14:44'),
(9, 'Andoks', '09213138726', 'kali@gmail.com', '361 Caloocan Quezon City', NULL, NULL, NULL, '', 0, '2025-10-19 11:31:56', '2025-10-19 11:31:56'),
(10, 'Jay Inc.', '09516871236', 'jhayyyy2@gmail.com', '110, San Jose Del Monte, Bulacan', NULL, NULL, NULL, '', 0, '2025-10-19 11:34:00', '2025-10-19 11:34:00'),
(11, 'Cheraine Goods', '09381233441', 'keke@gmail.com', '998, Buendia, Manila', NULL, NULL, NULL, '', 0, '2025-10-19 11:36:50', '2025-10-19 11:36:50');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_products`
--

CREATE TABLE `supplier_products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0 COMMENT '1 if this is the primary supplier for this product',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1 if this supplier-product relationship is active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'Additional notes about this supplier-product relationship'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_products`
--

INSERT INTO `supplier_products` (`id`, `supplier_id`, `product_id`, `is_primary`, `is_active`, `created_at`, `created_by`, `notes`) VALUES
(1, 1, 3, 1, 1, '2025-09-21 12:30:43', NULL, NULL),
(2, 1, 4, 1, 1, '2025-09-21 12:30:43', NULL, NULL),
(3, 3, 2, 1, 1, '2025-09-21 12:30:43', NULL, NULL),
(4, 5, 1, 1, 1, '2025-09-21 12:30:43', NULL, NULL),
(8, 1, 7, 0, 1, '2025-09-21 12:35:38', 4, NULL),
(9, 5, 8, 0, 1, '2025-09-21 13:43:57', 4, NULL),
(10, 1, 8, 0, 1, '2025-09-21 14:29:37', 4, NULL),
(11, 1, 5, 0, 1, '2025-09-21 14:29:46', 4, NULL),
(12, 6, 7, 0, 1, '2025-09-22 04:05:33', 4, NULL),
(13, 3, 9, 0, 1, '2025-09-22 04:52:57', 4, NULL),
(14, 7, 9, 0, 1, '2025-09-22 04:53:12', 4, NULL),
(15, 3, 10, 0, 1, '2025-09-24 09:27:53', 4, NULL),
(16, 7, 11, 0, 1, '2025-09-27 15:30:41', 4, NULL),
(17, 5, 6, 0, 1, '2025-10-07 02:47:18', 4, NULL),
(18, 1, 12, 0, 1, '2025-10-07 05:27:48', 4, NULL),
(19, 1, 13, 0, 1, '2025-10-08 04:15:20', 4, NULL),
(20, 5, 14, 0, 1, '2025-10-08 05:55:47', 4, NULL),
(21, 7, 14, 0, 1, '2025-10-08 15:13:42', 4, NULL),
(22, 3, 15, 0, 1, '2025-10-10 06:12:51', 4, NULL),
(23, 1, 16, 0, 1, '2025-10-10 11:24:46', 4, NULL),
(24, 7, 17, 0, 1, '2025-10-12 05:23:44', 4, NULL),
(25, 5, 17, 0, 1, '2025-10-16 06:02:38', 4, NULL),
(26, 8, 20, 0, 1, '2025-10-19 05:14:57', 4, NULL),
(27, 9, 21, 0, 1, '2025-10-19 11:34:25', 4, NULL),
(28, 7, 22, 0, 1, '2025-10-19 11:34:35', 4, NULL),
(29, 10, 28, 0, 1, '2025-10-19 11:34:42', 4, NULL),
(30, 10, 27, 0, 1, '2025-10-19 11:34:50', 4, NULL),
(31, 10, 26, 0, 1, '2025-10-19 11:34:55', 4, NULL),
(32, 3, 30, 0, 1, '2025-10-19 11:35:12', 4, NULL),
(33, 8, 32, 0, 1, '2025-10-19 11:35:21', 4, NULL),
(34, 8, 25, 0, 1, '2025-10-19 11:35:27', 4, NULL),
(35, 9, 20, 0, 1, '2025-10-19 11:35:34', 4, NULL),
(36, 9, 24, 0, 1, '2025-10-19 11:35:37', 4, NULL),
(37, 11, 29, 0, 1, '2025-10-19 11:37:09', 4, NULL),
(38, 11, 31, 0, 1, '2025-10-19 11:37:24', 4, NULL),
(39, 5, 23, 0, 1, '2025-10-19 14:11:11', 4, NULL),
(40, 11, 25, 0, 1, '2025-10-25 13:27:21', 4, NULL),
(41, 10, 24, 0, 1, '2025-10-25 13:31:22', 4, NULL),
(42, 1, 21, 0, 1, '2025-10-31 08:51:41', 4, NULL),
(43, 3, 25, 0, 1, '2025-10-31 10:32:57', 4, NULL),
(44, 3, 27, 0, 1, '2025-10-31 11:14:27', 4, NULL),
(45, 1, 23, 0, 1, '2025-10-31 11:43:16', 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','file','json') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'gcash_qr_code', 'assets/gcashqr/gcash_qr_1760339681.jpg', 'file', 'GCash QR Code image file path', 1, '2025-10-13 07:09:19', '2025-10-13 07:14:41'),
(2, 'gcash_account_name', 'Marion Brix Quiling', 'text', 'GCash account holder name', 1, '2025-10-13 07:09:19', '2025-10-13 07:18:51'),
(3, 'gcash_account_number', '09514971216', 'text', 'GCash account number', 1, '2025-10-13 07:09:19', '2025-10-13 07:18:51'),
(4, 'gcash_instructions', 'Scan the QR code above and complete your payment', 'text', 'Payment instructions for customers', 1, '2025-10-13 07:09:19', '2025-10-13 07:18:51'),
(5, 'gcash_minimum_amount', '1', 'number', 'Minimum amount for GCash payment', 1, '2025-10-13 07:09:19', '2025-10-13 07:14:41'),
(6, 'gcash_maximum_amount', '50000', 'number', 'Maximum amount for GCash payment', 1, '2025-10-13 07:09:19', '2025-10-13 07:14:41'),
(7, 'gcash_enabled', '1', 'boolean', 'Enable/disable GCash payment method', 1, '2025-10-13 07:09:19', '2025-10-13 07:14:41');

-- --------------------------------------------------------

--
-- Table structure for table `uom`
--

CREATE TABLE `uom` (
  `uom_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_archive` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uom`
--

INSERT INTO `uom` (`uom_id`, `name`, `is_archive`, `created_at`) VALUES
(1, 'Kilos', 0, '2025-09-04 03:19:06'),
(2, 'Pieces', 0, '2025-09-04 03:19:06'),
(10, 'Boxes', 0, '2025-09-17 15:50:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usertype_id` int(11) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `id_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `is_active`, `date_created`, `date_updated`, `usertype_id`, `email_verified`, `id_verified`) VALUES
(2, 'admin1', '$2y$10$C6GWhpUZgY7LMvYluQEsZ.0UdK3gtjsy0j/P6Xm5mOmCYePDazjEG', 0, '2025-09-03 11:30:44', '2025-10-15 13:30:52', 1, 1, 0),
(3, 'brix', '$2y$10$1mJ.wgSPTv/BatVGcen5J.vwCJdYx2YIkN3KA4/L76y0qAhpAwxLG', 1, '2025-09-04 04:31:55', '2025-09-17 11:51:29', 2, 1, 1),
(4, 'superadmin', '$2y$10$a5j.TQ/6tn.aYWPnK63SMuVFiPvFS53pq0uQmKcskoBs8.eOZknEC', 1, '2025-09-04 13:23:31', '2025-09-15 14:36:26', 3, 1, 0),
(5, 'admin2', '$2y$10$AReKMWhBQimIhgQNcFr1Se/D1zQ7NIKCc6EPaxDqKZUaxkWwluo9m', 0, '2025-09-05 11:49:59', '2025-10-13 06:17:44', 1, 0, 0),
(6, 'inventory_admin_test', '$2y$10$C3esvAlICqA0Uw.xGZ6tmODrMipPVykykhPlw7fqSK4qrdg..LRVG', 0, '2025-09-08 13:04:23', '2025-10-15 14:20:29', 2, 0, 0),
(7, 'admin3', '$2y$10$o8XfpPAlnQLmD3W.tM7g/uAfZKQA3JXhSQh86u2GpBivYt0PR55/m', 0, '2025-09-08 13:05:16', '2025-10-13 06:18:17', 1, 0, 0),
(8, 'sales_admin', '$2y$10$o6RAuh1kqQy8Xyd1EFPFDu3vZKqF7WWpFp2gt8JC7PnF8xkgUStx.', 0, '2025-09-08 13:28:52', '2025-10-15 14:20:24', 2, 1, 0),
(9, 'giancarmen', '$2y$10$Fympd4rSTpswM1WFJrBNuuAoYq2gOsZlr3XFbmgNQBzRJUcAmnxeu', 1, '2025-09-15 14:28:22', '2025-09-17 12:19:13', 2, 1, 1),
(10, 'kaycee', '$2y$10$R2ZAfl7C9bDMsSqlN8NK2uo35ukC1SnjParCIsRZnkPFQQpGd0Mku', 1, '2025-09-17 15:32:23', '2025-10-12 22:58:13', 2, 1, 1),
(11, 'katcat05', '$2y$10$UEugO9J4PRa7CJ5HDo9CwemlMV2Fgn4/BIr3/RsiabTRTx0CAq24i', 1, '2025-10-06 14:36:34', '2025-10-12 22:58:13', 2, 1, 1),
(12, 'marquils', '$2y$10$CLNJVcsT7D9nKQsQbdD0iuaU3EoKklHUrKLHT8vQujOnTSQdplxHi', 0, '2025-10-06 14:46:10', '2025-10-25 11:21:33', 2, 1, 0),
(13, 'brixxxx', '$2y$10$w6zT2ga14N65IhOQvyD1seDwO7tXD7HMZuuKxTCcJmAqf3wkMeZaC', 1, '2025-10-09 12:51:01', '2025-10-12 22:58:13', 2, 1, 0),
(14, 'inventory_kervie', '$2y$10$xobZbBiLieHOXC6QEoWGE.SZwaCukMHGci7TUzIAbVLBDRAyITheK', 0, '2025-10-09 13:32:35', '2025-10-15 14:20:29', 2, 0, 0),
(15, 'inventory_kervie2', '$2y$10$08KD5ZtnnBpauaPiySgSAOx8YYQ/U03iOPxtWDO2zXTFQHcTsk2m2', 0, '2025-10-09 13:42:10', '2025-10-15 14:20:29', 2, 0, 0),
(16, 'salesadmin_test', '$2y$10$9k0OQj3n1y75.qwOcM9kWuphzD/WQ63evEHywl1PW3RRkbbD5b1He', 0, '2025-10-12 09:13:01', '2025-10-15 14:20:24', 2, 0, 0),
(17, 'admin5', '$2y$10$KLYET0TNg4wuwKzTf2tjpeuVAcSDJ7iXsJaeWFXWS80.5RiF4Q4tO', 0, '2025-10-12 09:16:10', '2025-10-15 14:20:29', 2, 0, 0),
(18, 'monitoring_admin', '$2y$10$fZWS.U8Ub8M7dam/yFgQS.huxmkAEk0rS00nzwb8tWGEpmVeMCHFG', 0, '2025-10-12 09:28:37', '2025-10-15 14:19:25', 2, 0, 0),
(19, 'monitoring_admin2', '$2y$10$y3igQQZMwaDh4m5U5aWqdepvjOXA92K8uLwXd0.flunGFFEpTH/sK', 0, '2025-10-13 06:21:21', '2025-10-15 14:19:25', 2, 0, 0),
(20, 'davidkulots', '$2y$10$fIulfDbxZfVEMiEIA4mxleDpjkx1N.uff1DAjIorcGc37SIECOC9K', 1, '2025-10-15 12:37:18', '2025-10-22 05:18:01', 2, 1, 1),
(21, 'hachiko', '$2y$10$R.foTFT6mCHjtZKpLQI9o.9pZlRSqHFRVXrB8zGyTfClG9GjYo1my', 1, '2025-10-15 12:52:28', '2025-10-15 12:58:24', 2, 1, 0),
(22, 'Gian_Inventory', '$2y$10$FJlKGhRA9fiEcmOirYA.zu8txnJPDiyG/tpLm6O2nW0ZOfuXHnK7G', 0, '2025-10-15 14:51:03', '2025-10-15 15:38:48', 11, 0, 0),
(23, 'gian_inventory1', '$2y$10$oJlfdciU3U2hb2HBsp5j5uRncylJPjEB63xd.QPXUOYGiek.Y09y2', 1, '2025-10-15 15:17:15', '2025-10-15 15:17:15', 11, 0, 0),
(24, 'david_sales', '$2y$10$THuMjTPOLApNJDoFJP8LtOk2k1tKV7f9ywI1TMUvpzjLMpbD3oVci', 1, '2025-10-15 15:39:31', '2025-10-15 15:39:31', 12, 0, 0),
(25, 'kervie_monitoring', '$2y$10$At202hbDhVRhO8C5XE3H5OGc1r31SxzoV6rzqZ4Am1nD/4svI8bWy', 1, '2025-10-16 03:42:53', '2025-10-16 03:54:43', 13, 0, 0),
(26, 'maryanzoi', '$2y$10$UkyuKoNbSdlSE8assebWkezLNwlWoREUrAqvo6pJ9UNC4PnqbQF1.', 1, '2025-10-18 14:55:20', '2025-10-18 15:03:42', 2, 1, 0),
(30, 'maryanzoi1', '$2y$10$XmPD42kpt4.XWmuoBBPRUe7lxUhlgrJm7RYbcRMdtfYZyGkpkwIhe', 1, '2025-10-18 15:03:02', '2025-10-18 15:03:02', 2, 0, 0),
(31, 'mariannequiling', '$2y$10$M4JO5nUp9iAUPkcEKu46QuWHL3tlJkrRylFmZlv.0cArcGq04PRpW', 1, '2025-10-18 15:09:11', '2025-10-18 15:09:11', 2, 0, 0),
(32, 'mariannequilingzoii', '$2y$10$KVi9qIXQN33/9l0LnRFgculBsm0sBugyzddT3Ep1xCE4zIVxwBQsa', 1, '2025-10-18 15:09:46', '2025-10-18 15:10:13', 2, 1, 0),
(33, 'briybriy', '$2y$10$T0D6O9APhEH0Vk3Fy16XzOMgPXD8gRXhfiIKRFas/MawCQpnavcI2', 1, '2025-10-19 15:20:02', '2025-10-19 15:20:02', 2, 0, 0),
(34, 'briybriy1', '$2y$10$wDsUV3oi9gTfdlohMGxWnuR8CRM6syNj8UxCKOryDdMcwJHA9PZyO', 1, '2025-10-19 15:24:36', '2025-10-19 15:24:47', 2, 1, 0),
(35, 'brixxxsaaa', '$2y$10$JhbremI/e193mBJUs63F1OWCCxgiWffo3RxluEZvX4KjkpuhonQCy', 1, '2025-10-25 11:19:30', '2025-10-25 11:19:30', 2, 0, 0),
(36, 'brixxxxxsu', '$2y$10$Ai7rLsIJzeB6EDgyL1o0OOMTY1jYmrtHL1O.kgFfEbxtytwlD6zKG', 1, '2025-10-29 03:42:01', '2025-10-29 03:42:01', 2, 0, 0),
(37, 'brixxxxxssss', '$2y$10$OgZH25ZIcGhv6Tu.qNfp4.89v0oJZ0vm34Mv/Vhqytq88MT20rXiC', 1, '2025-10-29 03:52:02', '2025-10-29 03:52:02', 2, 0, 0),
(38, 'brixssaa', '$2y$10$J7YHELwU7i05FmVqJwKQ3Olmmp5conkZ0UfRgoWwsDTCkO6kl69Du', 1, '2025-10-29 03:55:22', '2025-10-29 03:55:22', 2, 0, 0),
(39, 'brixxxxxsuuuu', '$2y$10$.H7tWg1aGU41HaHTrZAtS.OqkGI.EMWG7wEJ7subPunjhwCzUtJKa', 1, '2025-10-30 03:40:36', '2025-10-30 03:41:02', 2, 1, 0),
(40, 'zaynngian', '$2y$10$PXc9tP4R6CQCyW8VTnAo2O1iCxffjSdO/HPBtJAbSYA4gCuLCdWF6', 1, '2025-10-30 03:53:32', '2025-10-30 03:53:32', 2, 0, 0),
(41, 'giannnzz', '$2y$10$5CVvWcwFDe4N8Bd0sdpCKOWgNcdvSIV9TPPkgTlOOzl4drq5K3Imm', 1, '2025-10-30 03:55:10', '2025-10-30 03:55:10', 2, 0, 0);

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
(13, 10, 'Kaycee', 'Gallaza', 'kreatives09@gmail.com', '09321344122', '09514721312', 'uploads/profile_10_1758123186_68cad4b29a052.jpg', '2025-09-17 15:32:23', '2025-10-31 11:46:04'),
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

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_permission_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`user_permission_id`, `user_id`, `permission_id`, `created_at`) VALUES
(1, 4, 1, '2025-09-04 15:13:40'),
(2, 4, 2, '2025-09-04 15:13:40'),
(3, 4, 3, '2025-09-04 15:13:40'),
(4, 4, 4, '2025-09-04 15:13:40'),
(5, 4, 5, '2025-09-04 15:13:40'),
(6, 4, 6, '2025-09-04 15:13:40'),
(7, 4, 7, '2025-09-04 15:13:40'),
(8, 4, 8, '2025-09-04 15:13:40'),
(9, 4, 9, '2025-09-04 15:13:40'),
(10, 4, 10, '2025-09-04 15:13:40'),
(11, 4, 11, '2025-09-04 15:13:40'),
(12, 4, 12, '2025-09-04 15:13:40'),
(13, 4, 13, '2025-09-04 15:13:40'),
(14, 4, 14, '2025-09-04 15:13:40'),
(15, 4, 15, '2025-09-04 15:13:40'),
(16, 4, 16, '2025-09-04 15:13:40'),
(17, 4, 17, '2025-09-04 15:13:40'),
(18, 4, 18, '2025-09-04 15:13:40'),
(19, 4, 19, '2025-09-04 15:13:40'),
(20, 4, 20, '2025-09-04 15:13:40'),
(21, 4, 21, '2025-09-04 15:13:40'),
(22, 4, 22, '2025-09-04 15:13:40'),
(23, 4, 23, '2025-09-04 15:13:40'),
(24, 4, 24, '2025-09-04 15:13:40'),
(25, 4, 25, '2025-09-04 15:13:40'),
(26, 4, 26, '2025-09-04 15:13:40'),
(27, 4, 27, '2025-09-04 15:13:40'),
(28, 4, 28, '2025-09-04 15:13:40'),
(29, 4, 29, '2025-09-04 15:13:40'),
(30, 4, 30, '2025-09-04 15:13:40'),
(31, 4, 31, '2025-09-04 15:13:40'),
(32, 4, 32, '2025-09-04 15:13:40'),
(33, 4, 33, '2025-09-04 15:13:40'),
(34, 4, 34, '2025-09-04 15:13:40'),
(35, 4, 35, '2025-09-04 15:13:40'),
(36, 4, 36, '2025-09-04 15:13:40'),
(37, 4, 37, '2025-09-04 15:13:40'),
(38, 4, 38, '2025-09-04 15:13:40'),
(39, 4, 39, '2025-09-04 15:13:40'),
(40, 4, 40, '2025-09-04 15:13:40'),
(41, 4, 41, '2025-09-04 15:13:40'),
(42, 4, 42, '2025-09-04 15:13:40'),
(43, 4, 43, '2025-09-04 15:13:40'),
(44, 2, 13, '2025-09-04 15:15:39'),
(45, 2, 88, '2025-09-04 15:15:39'),
(46, 5, 96, '2025-09-06 05:41:43'),
(47, 5, 27, '2025-09-06 05:41:43'),
(48, 5, 30, '2025-09-06 05:41:43'),
(49, 5, 28, '2025-09-06 05:41:43'),
(50, 5, 29, '2025-09-06 05:41:43'),
(51, 6, 13, '2025-09-08 13:25:34'),
(52, 6, 88, '2025-09-08 13:25:34'),
(53, 8, 13, '2025-09-08 13:29:29'),
(54, 8, 88, '2025-09-08 13:29:29');

-- --------------------------------------------------------

--
-- Table structure for table `user_type`
--

CREATE TABLE `user_type` (
  `usertype_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_type`
--

INSERT INTO `user_type` (`usertype_id`, `role`) VALUES
(1, 'admin'),
(2, 'customer'),
(3, 'super_admin'),
(11, 'inventory_admin'),
(12, 'sales_admin'),
(13, 'monitoring_admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_addresses_coordinates` (`latitude`,`longitude`),
  ADD KEY `idx_addresses_geocoded` (`geocoded`);

--
-- Indexes for table `adjustment_types`
--
ALTER TABLE `adjustment_types`
  ADD PRIMARY KEY (`adjustment_type_id`);

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `type` (`type`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `alert_types`
--
ALTER TABLE `alert_types`
  ADD PRIMARY KEY (`alerttype_id`);

--
-- Indexes for table `batch_movements`
--
ALTER TABLE `batch_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_movement_type` (`movement_type`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `fk_batch_movements_user` (`created_by`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brand_product_stock`
--
ALTER TABLE `brand_product_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_brand` (`product_id`,`brand_id`),
  ADD KEY `fk_bps_product` (`product_id`),
  ADD KEY `fk_bps_brand` (`brand_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `fk_cart_user` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cartitem_id`),
  ADD UNIQUE KEY `user_product_brand` (`user_id`,`product_id`,`brand_id`),
  ADD KEY `user_cart_ibfk_2` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `customer_id_verification`
--
ALTER TABLE `customer_id_verification`
  ADD PRIMARY KEY (`verification_id`),
  ADD UNIQUE KEY `unique_user_verification` (`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `discount_code_usage`
--
ALTER TABLE `discount_code_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_discount` (`discount_code_id`,`user_id`),
  ADD KEY `fk_discount_usage_code` (`discount_code_id`),
  ADD KEY `fk_discount_usage_user` (`user_id`),
  ADD KEY `fk_discount_usage_order` (`order_id`);

--
-- Indexes for table `email_verification`
--
ALTER TABLE `email_verification`
  ADD PRIMARY KEY (`emailverify_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `faq_questions`
--
ALTER TABLE `faq_questions`
  ADD PRIMARY KEY (`faq_id`),
  ADD KEY `answered_by` (`answered_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_answered_at` (`answered_at`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`product_id`);

--
-- Indexes for table `history_action_types`
--
ALTER TABLE `history_action_types`
  ADD PRIMARY KEY (`history_action_type_id`);

--
-- Indexes for table `history_logs`
--
ALTER TABLE `history_logs`
  ADD PRIMARY KEY (`historylog_id`),
  ADD KEY `idx_history_action_type_id` (`history_action_type_id`);

--
-- Indexes for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  ADD PRIMARY KEY (`inventoryalert_id`),
  ADD KEY `fk_alert_product` (`product_id`),
  ADD KEY `fk_alert_type` (`alerttype_id`),
  ADD KEY `fk_alert_user` (`resolved_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`orders_id`),
  ADD UNIQUE KEY `uk_orders_transaction_number` (`transaction_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_orders_address_id` (`address_id`),
  ADD KEY `idx_orders_plate_number` (`plate_number`),
  ADD KEY `idx_orders_transaction_number` (`transaction_number`),
  ADD KEY `idx_orders_pickup_ready_at` (`pickup_ready_at`),
  ADD KEY `idx_orders_application` (`application_name`);

--
-- Indexes for table `order_cancellations`
--
ALTER TABLE `order_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_cancellations_order_id` (`order_id`),
  ADD KEY `idx_receipt_path` (`receipt_path`),
  ADD KEY `idx_receipt_uploaded` (`receipt_uploaded_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`orderitems_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_items_ibfk_1` (`order_id`);

--
-- Indexes for table `order_ratings`
--
ALTER TABLE `order_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD UNIQUE KEY `unique_order_rating` (`order_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_status`
--
ALTER TABLE `order_status`
  ADD PRIMARY KEY (`orderstatus_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payments_id`),
  ADD KEY `fk_payments_order` (`orders_id`),
  ADD KEY `idx_payments_transaction_id` (`transaction_id`);

--
-- Indexes for table `payment_status`
--
ALTER TABLE `payment_status`
  ADD PRIMARY KEY (`paymentstatus_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `unique_permission` (`permission_name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fk_products_brand` (`brand_id`),
  ADD KEY `fk_products_supplier` (`supplier_id`),
  ADD KEY `fk_products_uom` (`uom_id`),
  ADD KEY `idx_products_category` (`category_id`),
  ADD KEY `idx_products_brand` (`brand_id`);

--
-- Indexes for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `expiration_date` (`expiration_date`),
  ADD KEY `idx_product_batches_brand` (`brand_id`),
  ADD KEY `idx_expired_batches` (`expiration_date`,`is_processed_expired`,`is_active`);

--
-- Indexes for table `product_boxes`
--
ALTER TABLE `product_boxes`
  ADD PRIMARY KEY (`box_id`),
  ADD KEY `fk_box_product` (`product_id`);

--
-- Indexes for table `product_discounts`
--
ALTER TABLE `product_discounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_discount` (`product_id`),
  ADD KEY `idx_product_discounts_product_id` (`product_id`),
  ADD KEY `idx_product_discounts_expires_at` (`expires_at`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`product_image_id`),
  ADD KEY `fk_images_product` (`product_id`);

--
-- Indexes for table `product_pricing`
--
ALTER TABLE `product_pricing`
  ADD PRIMARY KEY (`productpricing_id`),
  ADD KEY `fk_pricing_product` (`product_id`);

--
-- Indexes for table `product_sale`
--
ALTER TABLE `product_sale`
  ADD PRIMARY KEY (`productsale_id`),
  ADD KEY `fk_sale_product` (`product_id`);

--
-- Indexes for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD PRIMARY KEY (`productstock_id`),
  ADD KEY `fk_stock_product` (`product_id`);

--
-- Indexes for table `product_uom_conversions`
--
ALTER TABLE `product_uom_conversions`
  ADD PRIMARY KEY (`conversion_id`),
  ADD KEY `fk_conversion_product` (`product_id`),
  ADD KEY `fk_conversion_uom` (`uom_id`);

--
-- Indexes for table `promo_messages`
--
ALTER TABLE `promo_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rating_images`
--
ALTER TABLE `rating_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `idx_rating_order` (`rating_id`,`image_order`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_report_type` (`report_type_id`),
  ADD KEY `idx_generated_by` (`generated_by`);

--
-- Indexes for table `report_types`
--
ALTER TABLE `report_types`
  ADD PRIMARY KEY (`report_type_id`);

--
-- Indexes for table `restocking`
--
ALTER TABLE `restocking`
  ADD PRIMARY KEY (`restocking_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_restocking_product` (`product_id`),
  ADD KEY `fk_restocking_user` (`created_by`),
  ADD KEY `fk_restocking_status` (`status_id`),
  ADD KEY `idx_po_number` (`po_number`),
  ADD KEY `idx_restocking_actual_qty` (`actual_quantity_received`);

--
-- Indexes for table `restocking_status`
--
ALTER TABLE `restocking_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `return_orders`
--
ALTER TABLE `return_orders`
  ADD PRIMARY KEY (`returnorders_id`),
  ADD KEY `order_id` (`orders_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_return_orders_product` (`product_id`);

--
-- Indexes for table `return_status`
--
ALTER TABLE `return_status`
  ADD PRIMARY KEY (`returnstatus_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_permission_id`),
  ADD UNIQUE KEY `unique_role_permission` (`usertype_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  ADD PRIMARY KEY (`stockadjustment_id`),
  ADD KEY `fk_stockadj_product` (`product_id`),
  ADD KEY `fk_stockadj_type` (`adjustment_type_id`),
  ADD KEY `fk_stockadj_user` (`created_by`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_expiration_date` (`expiration_date`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`stockmovement_id`),
  ADD KEY `fk_stockmov_product` (`product_id`),
  ADD KEY `fk_stockmov_type` (`stockmovementtype_id`),
  ADD KEY `fk_stockmov_user` (`created_by`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `stock_movement_types`
--
ALTER TABLE `stock_movement_types`
  ADD PRIMARY KEY (`stockmovementtype_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supplier_product` (`supplier_id`,`product_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_is_primary` (`is_primary`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `fk_supplier_products_created_by` (`created_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`);

--
-- Indexes for table `uom`
--
ALTER TABLE `uom`
  ADD PRIMARY KEY (`uom_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_user_type` (`usertype_id`);

--
-- Indexes for table `user_info`
--
ALTER TABLE `user_info`
  ADD PRIMARY KEY (`user_info_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_profile_picture` (`profile_picture`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_permission_id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `user_type`
--
ALTER TABLE `user_type`
  ADD PRIMARY KEY (`usertype_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `adjustment_types`
--
ALTER TABLE `adjustment_types`
  MODIFY `adjustment_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `alert_types`
--
ALTER TABLE `alert_types`
  MODIFY `alerttype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `batch_movements`
--
ALTER TABLE `batch_movements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=281;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `brand_product_stock`
--
ALTER TABLE `brand_product_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cartitem_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2693;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `customer_id_verification`
--
ALTER TABLE `customer_id_verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `discount_codes`
--
ALTER TABLE `discount_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `discount_code_usage`
--
ALTER TABLE `discount_code_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `email_verification`
--
ALTER TABLE `email_verification`
  MODIFY `emailverify_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `faq_questions`
--
ALTER TABLE `faq_questions`
  MODIFY `faq_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `history_action_types`
--
ALTER TABLE `history_action_types`
  MODIFY `history_action_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `history_logs`
--
ALTER TABLE `history_logs`
  MODIFY `historylog_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=517;

--
-- AUTO_INCREMENT for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  MODIFY `inventoryalert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `orders_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=403;

--
-- AUTO_INCREMENT for table `order_cancellations`
--
ALTER TABLE `order_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `orderitems_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=269;

--
-- AUTO_INCREMENT for table `order_ratings`
--
ALTER TABLE `order_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `order_status`
--
ALTER TABLE `order_status`
  MODIFY `orderstatus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payments_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=240;

--
-- AUTO_INCREMENT for table `payment_status`
--
ALTER TABLE `payment_status`
  MODIFY `paymentstatus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `product_boxes`
--
ALTER TABLE `product_boxes`
  MODIFY `box_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_discounts`
--
ALTER TABLE `product_discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `product_image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `product_pricing`
--
ALTER TABLE `product_pricing`
  MODIFY `productpricing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `product_sale`
--
ALTER TABLE `product_sale`
  MODIFY `productsale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_stock`
--
ALTER TABLE `product_stock`
  MODIFY `productstock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `product_uom_conversions`
--
ALTER TABLE `product_uom_conversions`
  MODIFY `conversion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `promo_messages`
--
ALTER TABLE `promo_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `rating_images`
--
ALTER TABLE `rating_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_types`
--
ALTER TABLE `report_types`
  MODIFY `report_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restocking`
--
ALTER TABLE `restocking`
  MODIFY `restocking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `restocking_status`
--
ALTER TABLE `restocking_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `return_orders`
--
ALTER TABLE `return_orders`
  MODIFY `returnorders_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_status`
--
ALTER TABLE `return_status`
  MODIFY `returnstatus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `role_permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=227;

--
-- AUTO_INCREMENT for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  MODIFY `stockadjustment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `stockmovement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `stock_movement_types`
--
ALTER TABLE `stock_movement_types`
  MODIFY `stockmovementtype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `uom`
--
ALTER TABLE `uom`
  MODIFY `uom_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `user_info`
--
ALTER TABLE `user_info`
  MODIFY `user_info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `user_permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `user_type`
--
ALTER TABLE `user_type`
  MODIFY `usertype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `batch_movements`
--
ALTER TABLE `batch_movements`
  ADD CONSTRAINT `fk_batch_movements_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `brand_product_stock`
--
ALTER TABLE `brand_product_stock`
  ADD CONSTRAINT `fk_bps_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bps_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `discount_code_usage`
--
ALTER TABLE `discount_code_usage`
  ADD CONSTRAINT `fk_discount_usage_code` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_discount_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`orders_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_discount_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verification`
--
ALTER TABLE `email_verification`
  ADD CONSTRAINT `email_verification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `faq_questions`
--
ALTER TABLE `faq_questions`
  ADD CONSTRAINT `faq_questions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faq_questions_ibfk_2` FOREIGN KEY (`answered_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  ADD CONSTRAINT `fk_alert_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_alert_type` FOREIGN KEY (`alerttype_id`) REFERENCES `alert_types` (`alerttype_id`),
  ADD CONSTRAINT `fk_alert_user` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_address_id` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_cancellations`
--
ALTER TABLE `order_cancellations`
  ADD CONSTRAINT `fk_order_cancellations_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`orders_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`orders_id`),
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`orders_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_batches`
--
ALTER TABLE `product_batches`
  ADD CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_batches_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`);

--
-- Constraints for table `product_boxes`
--
ALTER TABLE `product_boxes`
  ADD CONSTRAINT `fk_box_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_discounts`
--
ALTER TABLE `product_discounts`
  ADD CONSTRAINT `product_discounts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `product_pricing`
--
ALTER TABLE `product_pricing`
  ADD CONSTRAINT `fk_pricing_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `product_sale`
--
ALTER TABLE `product_sale`
  ADD CONSTRAINT `fk_sale_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `product_uom_conversions`
--
ALTER TABLE `product_uom_conversions`
  ADD CONSTRAINT `fk_conversion_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conversion_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`uom_id`) ON DELETE CASCADE;

--
-- Constraints for table `rating_images`
--
ALTER TABLE `rating_images`
  ADD CONSTRAINT `rating_images_ibfk_1` FOREIGN KEY (`rating_id`) REFERENCES `order_ratings` (`rating_id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_type` FOREIGN KEY (`report_type_id`) REFERENCES `report_types` (`report_type_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `restocking`
--
ALTER TABLE `restocking`
  ADD CONSTRAINT `fk_restocking_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_restocking_status` FOREIGN KEY (`status_id`) REFERENCES `restocking_status` (`status_id`),
  ADD CONSTRAINT `fk_restocking_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `fk_restocking_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `return_orders`
--
ALTER TABLE `return_orders`
  ADD CONSTRAINT `fk_return_orders_order` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`orders_id`),
  ADD CONSTRAINT `fk_return_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`usertype_id`) REFERENCES `user_type` (`usertype_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  ADD CONSTRAINT `fk_stockadj_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_stockadj_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stockadj_type` FOREIGN KEY (`adjustment_type_id`) REFERENCES `adjustment_types` (`adjustment_type_id`),
  ADD CONSTRAINT `fk_stockadj_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_movements_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stockmov_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_stockmov_type` FOREIGN KEY (`stockmovementtype_id`) REFERENCES `stock_movement_types` (`stockmovementtype_id`),
  ADD CONSTRAINT `fk_stockmov_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD CONSTRAINT `fk_supplier_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supplier_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supplier_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_type` FOREIGN KEY (`usertype_id`) REFERENCES `user_type` (`usertype_id`);

--
-- Constraints for table `user_info`
--
ALTER TABLE `user_info`
  ADD CONSTRAINT `user_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
