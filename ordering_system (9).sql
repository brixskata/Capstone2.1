-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2025 at 06:16 AM
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
(3, 9, 'kasunduan extension', '', 'Quezon City', 'Metro Manila', '1121', 'Philippines', 1, '2025-09-17 12:56:11', '2025-09-17 12:56:11', NULL, NULL, 0, NULL, NULL),
(4, 10, 'bagong silang', '', 'Quezon City', 'Metro Manila', '1119', 'Philippines', 1, '2025-09-17 15:35:32', '2025-09-17 15:35:32', NULL, NULL, 0, NULL, NULL),
(6, 11, 'Blumentritt Road, Santa Cruz, Manila, Capital District, Metro Manila, 1014, Philippines', 'Near 7/11', 'Manila', 'Metro Manila', '1014', 'Philippines', 0, '2025-10-07 12:11:21', '2025-10-07 12:11:21', NULL, NULL, 0, NULL, NULL),
(8, 11, 'De La Salle University Manila, 2401, Taft Avenue, Barangay 726, Malate, Manila, Capital District, Metro Manila, 1004, Philippines', 'Near 7/11', 'Manila', 'Metro Manila', '1004', 'Philippines', 1, '2025-10-08 13:22:38', '2025-10-08 13:22:38', NULL, NULL, 0, NULL, NULL),
(9, 11, 'Housing Project of Diocese of Cubao, Antipolo, Rizal, 1870, Philippines', 'Near 7/11', 'Antipolo', 'Rizal', '1870', 'Philippines', 0, '2025-10-08 13:58:15', '2025-10-08 13:58:15', NULL, NULL, 0, NULL, NULL),
(10, 3, 'Santa Mesa, Manila, Capital District, Metro Manila, Philippines', 'Near 7/11', 'Manila', 'Metro Manila', '1121', 'Philippines', 0, '2025-10-08 14:14:25', '2025-10-08 14:14:25', NULL, NULL, 0, NULL, NULL),
(11, 3, 'Tagaytay, Cavite, 4120, Philippines', 'Near Uncle Johns', 'Cavite', 'Cavite', '4120', 'Philippines', 0, '2025-10-08 14:38:21', '2025-10-08 14:38:21', NULL, NULL, 0, NULL, NULL),
(12, 3, 'BF Homes Caloocan, District 1, Caloocan, Northern Manila District, Metro Manila, 1420, Philippines', '', 'Caloocan', '', '1420', 'Philippines', 0, '2025-10-08 15:10:32', '2025-10-08 15:10:32', NULL, NULL, 0, NULL, NULL);

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
(5, 'id_verification', 'New ID Verification Request', 'User ID: 11 has submitted ID verification documents.', '{\"user_id\":11,\"id_type\":\"National ID\"}', 0, '2025-10-07 12:07:45', NULL);

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
(86, 45, 14, 'sale', 1.0, 'order', 242, '2025-10-09 03:56:36', 3, 'Order #242 - Customer purchase');

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
(3, 'Zayn Bangus', 0, '2025-07-25 12:31:19'),
(4, 'ANDOKS ', 0, '2025-08-05 15:19:56'),
(5, 'GOODS GOODS', 0, '2025-08-05 15:20:14'),
(6, 'XYZ INC.', 0, '2025-08-05 15:20:31'),
(10, 'Pampanga\'s Best', 0, '2025-09-22 04:48:15');

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

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cartitem_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(23, 'BEEF'),
(24, 'CHICKEN'),
(27, 'SEA FOODS'),
(28, 'PORK'),
(32, 'Processed Meat');

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
(5, 11, 'National ID', '2000321312321', 'uploads/id_verification/front_11_1759838865.jpg', 'uploads/id_verification/back_11_1759838865.jpg', 'approved', NULL, 'nice pic', 4, '2025-10-08 13:21:15', '2025-10-07 12:07:45', '2025-10-08 13:21:15');

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
(4, 'MIKEMADZ10', 'percent', 10.00, 1, '2026-04-27 21:01:00');

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
(1, 4, 3, 239, 40.00, '2025-10-09 11:54:09');

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
(4, 3, '033139', '2025-09-04 06:49:27', 1, '2025-09-04 04:39:27'),
(5, 9, '826957', '2025-09-15 16:38:22', 1, '2025-09-15 14:28:22'),
(6, 10, '717024', '2025-09-17 17:42:23', 1, '2025-09-17 15:32:23'),
(7, 11, '669609', '2025-10-06 16:46:34', 1, '2025-10-06 14:36:34'),
(8, 12, '172028', '2025-10-06 16:56:10', 1, '2025-10-06 14:46:10');

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
(180, 11, NULL, NULL, 'Restocking: Product: Beef Fats, Quantity: 1, Cost: ₱100', 4, '2025-10-09 12:06:27');

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
(17, 3, 214, 'Your order has been cancelled by admin: s', 0, '2025-10-08 19:15:04');

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
  `address_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`orders_id`, `user_id`, `orderstatus_id`, `created_at`, `total_price`, `delivered_at`, `delivery_option`, `address_id`) VALUES
(158, 45, 0, '2025-07-25 13:13:06', 209.00, NULL, 'pickup', NULL),
(159, 45, 0, '2025-07-25 13:23:37', 418.00, NULL, 'delivery', NULL),
(160, 45, 0, '2025-07-25 20:32:32', 722.00, NULL, 'delivery', NULL),
(161, 45, 0, '2025-07-25 20:33:13', 209.00, NULL, 'delivery', NULL),
(162, 45, 0, '2025-07-25 20:33:45', 209.00, NULL, 'delivery', NULL),
(163, 46, 0, '2025-08-09 09:31:06', 231.00, NULL, 'pickup', NULL),
(164, 46, 0, '2025-08-15 23:08:35', 1339.00, NULL, 'pickup', NULL),
(165, 46, 0, '2025-08-16 08:05:25', 184.00, NULL, 'pickup', NULL),
(167, 3, 4, '2025-09-06 10:56:28', 814.00, NULL, 'delivery', NULL),
(168, 3, 5, '2025-09-06 11:22:33', 407.00, NULL, 'delivery', NULL),
(169, 3, 4, '2025-09-06 13:15:21', 539.00, NULL, 'pickup', NULL),
(170, 3, 4, '2025-09-06 13:30:45', 132.00, NULL, 'delivery', NULL),
(171, 3, 4, '2025-09-06 13:39:41', 264.00, NULL, 'delivery', NULL),
(172, 3, 7, '2025-09-08 21:48:14', 275.00, NULL, 'delivery', NULL),
(173, 9, 4, '2025-09-16 23:09:49', 240.00, NULL, 'pickup', NULL),
(174, 9, 3, '2025-09-17 20:56:27', 960.00, NULL, 'delivery', NULL),
(175, 10, 4, '2025-09-17 23:35:58', 240.00, NULL, 'delivery', NULL),
(176, 10, 4, '2025-09-17 23:37:31', 2400.00, NULL, 'pickup', NULL),
(177, 10, 4, '2025-09-18 22:16:49', 180.00, NULL, 'pickup', NULL),
(178, 10, 3, '2025-09-18 22:18:21', 240.00, NULL, 'delivery', NULL),
(179, 10, 5, '2025-09-18 22:22:18', 180.00, NULL, 'pickup', NULL),
(180, 10, 3, '2025-09-18 22:22:54', 180.00, NULL, 'delivery', NULL),
(181, 3, 4, '2025-09-19 11:15:24', 144.00, NULL, 'pickup', NULL),
(182, 3, 4, '2025-09-22 10:35:08', 144.00, NULL, 'delivery', NULL),
(183, 3, 4, '2025-09-22 13:08:36', 198.00, NULL, 'delivery', NULL),
(184, 3, 2, '2025-09-23 17:33:28', 396.00, NULL, 'delivery', NULL),
(185, 3, 2, '2025-09-23 17:34:56', 396.00, NULL, 'delivery', NULL),
(186, 3, 5, '2025-09-23 19:29:56', 198.00, NULL, 'pickup', NULL),
(187, 3, 5, '2025-09-23 19:30:00', 198.00, NULL, 'pickup', NULL),
(188, 3, 5, '2025-09-23 19:30:04', 198.00, NULL, 'pickup', NULL),
(189, 3, 8, '2025-09-23 19:30:23', 240.00, NULL, 'pickup', NULL),
(190, 3, 4, '2025-09-23 19:31:25', 180.00, NULL, 'delivery', NULL),
(191, 3, 8, '2025-09-24 16:45:37', 942.00, NULL, 'pickup', NULL),
(192, 3, 4, '2025-09-30 20:34:23', 2410.00, NULL, 'delivery', NULL),
(193, 3, 4, '2025-09-30 20:51:51', 10.00, NULL, 'delivery', NULL),
(194, 3, 4, '2025-09-30 20:59:40', 8.00, NULL, 'delivery', NULL),
(195, 3, 4, '2025-09-30 21:02:18', 10.00, NULL, 'delivery', NULL),
(196, 10, 5, '2025-10-07 10:39:49', 12.00, NULL, 'pickup', NULL),
(199, 10, 8, '2025-10-07 11:13:14', 748.00, NULL, 'pickup', NULL),
(200, 10, 4, '2025-10-07 11:18:10', 630.00, NULL, 'pickup', NULL),
(201, 10, 4, '2025-10-07 11:23:43', 270.00, NULL, 'pickup', NULL),
(202, 10, 4, '2025-10-07 11:31:40', 270.00, NULL, 'pickup', NULL),
(203, 10, 3, '2025-10-07 11:36:01', 2070.00, NULL, 'delivery', NULL),
(204, 10, 8, '2025-10-07 11:55:27', 540.00, NULL, 'pickup', NULL),
(205, 10, 5, '2025-10-07 11:56:06', 630.00, NULL, 'pickup', NULL),
(206, 10, 5, '2025-10-07 12:03:39', 270.00, NULL, 'pickup', NULL),
(207, 10, 3, '2025-10-07 12:23:52', 270.00, NULL, 'delivery', NULL),
(208, 10, 3, '2025-10-07 12:29:15', 25.00, NULL, 'delivery', NULL),
(209, 10, 3, '2025-10-07 12:33:42', 12.00, NULL, 'delivery', NULL),
(210, 10, 4, '2025-10-07 12:39:26', 3996.00, NULL, 'pickup', NULL),
(211, 10, 4, '2025-10-07 12:43:14', 495.00, NULL, 'pickup', NULL),
(212, 10, 4, '2025-10-07 12:43:50', 693.00, NULL, 'pickup', NULL),
(213, 3, 4, '2025-10-08 14:08:47', 1627.50, NULL, 'pickup', NULL),
(214, 3, 5, '2025-10-08 14:14:29', 10.00, NULL, 'pickup', NULL),
(215, 3, 5, '2025-10-08 18:23:47', 35.00, NULL, 'pickup', NULL),
(216, 3, 5, '2025-10-08 18:45:44', 20.00, NULL, 'delivery', NULL),
(217, 3, 5, '2025-10-08 18:45:50', 20.00, NULL, 'delivery', NULL),
(218, 3, 5, '2025-10-08 18:46:01', 20.00, NULL, 'delivery', NULL),
(219, 3, 5, '2025-10-08 18:46:16', 20.00, NULL, 'pickup', NULL),
(220, 3, 5, '2025-10-08 18:46:21', 20.00, NULL, 'pickup', NULL),
(221, 3, 4, '2025-10-08 18:47:30', 20.00, NULL, 'delivery', NULL),
(222, 3, 4, '2025-10-08 19:00:53', 20.00, NULL, 'delivery', NULL),
(223, 3, 5, '2025-10-08 19:01:37', 150.00, NULL, 'delivery', NULL),
(224, 11, 1, '2025-10-08 21:23:20', 300.00, NULL, 'delivery', NULL),
(225, 11, 1, '2025-10-08 21:34:03', 150.00, NULL, 'delivery', NULL),
(226, 11, 1, '2025-10-08 21:36:22', 150.00, NULL, 'delivery', NULL),
(227, 11, 2, '2025-10-08 21:42:57', 150.00, NULL, 'delivery', 8),
(228, 11, 2, '2025-10-08 21:58:48', 150.00, NULL, 'delivery', 8),
(229, 11, 2, '2025-10-08 22:11:19', 150.00, NULL, 'delivery', 8),
(230, 3, 2, '2025-10-08 22:12:36', 150.00, NULL, 'delivery', NULL),
(231, 3, 1, '2025-10-08 22:31:35', 300.00, NULL, 'delivery', 10),
(232, 3, 2, '2025-10-08 22:50:44', 150.00, NULL, 'delivery', 11),
(233, 3, 1, '2025-10-08 22:53:07', 250.00, NULL, 'delivery', 11),
(234, 3, 1, '2025-10-08 22:58:16', 250.00, NULL, 'delivery', 11),
(235, 3, 1, '2025-10-08 23:02:07', 250.00, NULL, 'delivery', 11),
(236, 3, 1, '2025-10-08 23:07:38', 250.00, NULL, 'delivery', 11),
(237, 3, 2, '2025-10-08 23:10:32', 250.00, NULL, 'delivery', 12),
(238, 3, 2, '2025-10-08 23:14:58', 6750.00, NULL, 'delivery', 11),
(239, 3, 1, '2025-10-09 11:54:09', 400.00, NULL, 'delivery', 10),
(240, 3, 1, '2025-10-09 11:55:53', 400.00, NULL, 'delivery', 11),
(241, 3, 1, '2025-10-09 11:56:09', 400.00, NULL, 'delivery', 10),
(242, 3, 1, '2025-10-09 11:56:36', 400.00, NULL, 'pickup', 12);

-- --------------------------------------------------------

--
-- Table structure for table `order_cancellations`
--

CREATE TABLE `order_cancellations` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `cancelled_by` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_cancellations`
--

INSERT INTO `order_cancellations` (`id`, `order_id`, `reason`, `cancelled_by`, `created_at`) VALUES
(1, 179, 'pangit mo kausap', 'superadmin', '2025-09-18 14:36:25'),
(2, 186, 'error', 'superadmin', '2025-09-23 11:44:30'),
(3, 186, 'error', 'superadmin', '2025-09-23 11:44:30'),
(4, 187, 's', 'superadmin', '2025-09-23 14:01:45'),
(5, 188, 's', 'superadmin', '2025-09-23 14:01:50'),
(6, 196, 'did not show', 'superadmin', '2025-10-07 02:41:47'),
(7, 205, 'di nag bayad', 'superadmin', '2025-10-07 03:58:21'),
(13, 206, 's', 'superadmin', '2025-10-07 04:16:38'),
(14, 223, 'no payment', 'superadmin', '2025-10-08 11:02:01'),
(15, 220, 's', 'superadmin', '2025-10-08 11:06:40'),
(16, 219, 's', 'superadmin', '2025-10-08 11:06:44'),
(17, 218, 's', 'superadmin', '2025-10-08 11:06:49'),
(18, 217, 's', 'superadmin', '2025-10-08 11:06:51'),
(19, 216, 's', 'superadmin', '2025-10-08 11:14:58'),
(20, 215, 's', 'superadmin', '2025-10-08 11:15:02'),
(21, 214, 's', 'superadmin', '2025-10-08 11:15:04');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `orderitems_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,1) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`orderitems_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(3, 167, 2, 2.0, 132.00),
(4, 167, 1, 2.0, 275.00),
(5, 168, 2, 1.0, 132.00),
(6, 168, 1, 1.0, 275.00),
(7, 169, 1, 1.0, 275.00),
(8, 169, 2, 2.0, 132.00),
(9, 170, 2, 1.0, 132.00),
(10, 171, 2, 2.0, 132.00),
(11, 172, 1, 1.0, 275.00),
(12, 173, 4, 1.0, 240.00),
(13, 174, 4, 4.0, 240.00),
(14, 175, 4, 1.0, 240.00),
(15, 176, 4, 10.0, 240.00),
(16, 177, 5, 1.0, 180.00),
(17, 178, 4, 1.0, 240.00),
(18, 179, 5, 1.0, 180.00),
(19, 180, 5, 1.0, 180.00),
(20, 181, 7, 1.0, 144.00),
(21, 182, 7, 1.0, 144.00),
(22, 183, 9, 1.0, 198.00),
(23, 184, 9, 2.0, 198.00),
(24, 185, 9, 2.0, 198.00),
(25, 189, 4, 1.0, 240.00),
(26, 190, 5, 1.0, 180.00),
(27, 191, 5, 2.0, 180.00),
(28, 191, 9, 1.0, 198.00),
(29, 191, 7, 1.0, 144.00),
(30, 191, 4, 1.0, 240.00),
(31, 192, 11, 1.0, 10.00),
(32, 192, 4, 10.0, 240.00),
(33, 193, 11, 1.0, 10.00),
(34, 194, 10, 1.0, 8.00),
(35, 195, 11, 1.0, 10.00),
(36, 196, 10, 2.0, 8.00),
(37, 199, 4, 3.0, 240.00),
(38, 199, 10, 4.0, 8.00),
(39, 200, 6, 4.0, 180.00),
(40, 201, 6, 2.0, 180.00),
(41, 202, 6, 2.0, 180.00),
(42, 203, 8, 12.0, 180.00),
(43, 204, 8, 3.0, 180.00),
(44, 205, 8, 4.0, 180.00),
(45, 206, 6, 2.0, 180.00),
(46, 207, 8, 2.0, 180.00),
(47, 208, 11, 3.0, 10.00),
(49, 209, 10, 1.5, 8.00),
(50, 210, 8, 22.2, 180.00),
(51, 211, 9, 2.5, 198.00),
(52, 212, 9, 3.5, 198.00),
(53, 213, 14, 10.5, 155.00),
(54, 214, 13, 1.0, 10.00),
(55, 215, 11, 3.5, 10.00),
(56, 216, 10, 2.5, 8.00),
(57, 217, 10, 2.5, 8.00),
(58, 218, 10, 2.5, 8.00),
(59, 219, 10, 2.5, 8.00),
(60, 220, 10, 2.5, 8.00),
(61, 221, 10, 2.5, 8.00),
(63, 223, 14, 1.0, 150.00),
(64, 224, 14, 2.0, 150.00),
(65, 225, 14, 1.0, 150.00),
(66, 226, 14, 1.0, 150.00),
(67, 227, 14, 1.0, 150.00),
(68, 228, 14, 1.0, 150.00),
(69, 229, 14, 1.0, 150.00),
(70, 230, 14, 1.0, 150.00),
(71, 231, 14, 2.0, 150.00),
(72, 232, 14, 1.0, 150.00),
(73, 233, 14, 1.0, 250.00),
(74, 234, 14, 1.0, 250.00),
(75, 235, 14, 1.0, 250.00),
(76, 236, 14, 1.0, 250.00),
(77, 237, 14, 1.0, 250.00),
(78, 238, 14, 45.0, 150.00),
(79, 239, 14, 1.0, 400.00),
(80, 240, 14, 1.0, 400.00),
(81, 241, 14, 1.0, 400.00),
(82, 242, 14, 1.0, 400.00);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_ratings`
--

INSERT INTO `order_ratings` (`rating_id`, `order_id`, `user_id`, `rating`, `review`, `created_at`, `updated_at`) VALUES
(1, 171, 3, 5, 'angas', '2025-09-17 09:00:06', '2025-09-17 09:00:06'),
(2, 170, 3, 4, 'solid to', '2025-09-17 09:04:57', '2025-09-17 09:04:57'),
(3, 173, 9, 5, 'let\'s go!', '2025-09-17 09:16:17', '2025-09-17 09:16:17'),
(4, 175, 10, 3, 'awit', '2025-09-17 15:39:59', '2025-09-17 15:39:59'),
(5, 195, 3, 5, 'hello', '2025-10-06 15:58:39', '2025-10-06 15:58:39'),
(6, 222, 3, 5, '', '2025-10-08 11:46:56', '2025-10-08 11:46:56'),
(7, 221, 3, 5, '', '2025-10-08 11:48:43', '2025-10-08 11:48:43'),
(8, 169, 3, 5, '', '2025-10-08 12:02:26', '2025-10-08 12:02:26');

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
(77, 242, 400.00, '', '2025-10-09 11:56:36', '', '', 1);

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
(14, 'Beef Fats', 'Beef', '2025-10-08 05:52:59', 23, 0, 1, NULL, 1, NULL, 'room_temp', 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_batches`
--

CREATE TABLE `product_batches` (
  `batch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
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
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_batches`
--

INSERT INTO `product_batches` (`batch_id`, `product_id`, `supplier_id`, `batch_number`, `quantity_received`, `quantity_remaining`, `unit_cost`, `expiration_date`, `received_date`, `created_at`, `created_by`, `reference_type`, `reference_id`, `notes`, `is_active`) VALUES
(1, 1, NULL, 'TRIMMINGS-BATCH001', 20.0, 20.0, NULL, '2025-09-10', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1),
(2, 1, NULL, 'TRIMMINGS-BATCH002', 15.0, 15.0, NULL, '2025-09-20', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1),
(3, 1, NULL, 'TRIMMINGS-BATCH003', 25.0, 25.0, NULL, '2025-10-05', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1),
(4, 2, NULL, 'BANGUS-BATCH001', 20.0, 20.0, NULL, '2025-09-10', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1),
(5, 2, NULL, 'BANGUS-BATCH002', 15.0, 15.0, NULL, '2025-09-20', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1),
(6, 2, NULL, 'BANGUS-BATCH003', 25.0, 25.0, NULL, '2025-10-05', NULL, '2025-09-05 13:07:28', 4, 'restock', NULL, NULL, 1),
(7, 5, 7, 'B5-20250919-001', 10.0, 0.0, 25.00, '2025-10-19', '2025-09-19', '2025-09-19 02:23:11', 1, 'manual', NULL, 'Test batch from interface', 1),
(10, 5, 7, 'B5-20250919-002', 50.0, 47.0, 25.00, '2025-10-19', '2025-09-19', '2025-09-19 02:25:59', 10, 'manual', NULL, 'Test batch from interface', 1),
(11, 5, 7, 'B5-20250919-003', 10.0, 10.0, 150.00, '2026-03-19', '2025-09-19', '2025-09-19 02:36:00', 4, 'restock', 10, 'Restocking: ', 1),
(12, 5, 3, 'B5-20250919-004', 10.0, 10.0, NULL, '2026-03-26', '2025-09-19', '2025-09-19 02:38:21', 4, 'adjustment', 5, 'Stock adjustment: Quality Control', 1),
(13, 7, 5, 'B7-20250919-001', 10.0, 8.0, 120.00, '2026-02-19', '2025-09-19', '2025-09-19 03:08:01', 4, 'restock', 11, 'Restocking: ', 1),
(14, 7, 1, 'B7-20250919-002', 10.0, 10.0, 120.00, '2026-01-15', '2025-09-19', '2025-09-19 03:14:50', 4, 'restock', 12, 'Restocking: ', 1),
(15, 4, NULL, 'B4-20250919-001', 50.0, 0.0, NULL, '2025-10-04', '2025-09-19', '2025-09-19 03:19:27', 10, 'restock', 999, 'Test batch for order integration', 1),
(16, 4, NULL, 'B4-20250919-002', 30.0, 25.0, NULL, '2025-10-19', '2025-09-19', '2025-09-19 03:19:27', 10, 'restock', 999, 'Test batch 2 - expires later', 1),
(17, 4, NULL, 'B4-20250919-003', 20.0, 20.0, NULL, '2025-09-24', '2025-09-19', '2025-09-19 03:19:27', 10, 'restock', 999, 'Test batch 3 - expires sooner', 1),
(18, 7, 1, 'B7-20250922-001', 10.0, 10.0, NULL, '2026-03-23', '2025-09-22', '2025-09-22 03:57:22', 4, 'adjustment', 6, 'Stock adjustment: Other', 1),
(19, 9, 7, 'B9-20250922-001', 1.0, 0.0, 100.00, '2026-06-16', '2025-09-23', '2025-09-22 04:57:34', 4, 'restock', 13, 'Restocking: ', 1),
(20, 9, 3, 'B9-20250922-002', 30.0, 0.0, 100.00, '2026-03-28', '2025-09-22', '2025-09-22 04:59:56', 4, 'restock', 14, 'Restocking: ', 1),
(21, 9, 3, 'B9-20250922-003', 30.0, 0.0, 100.00, '2026-03-22', '2025-09-22', '2025-09-22 05:04:22', 4, 'restock', 15, 'Restocking: ', 1),
(22, 8, 5, 'B8-20250923-001', 1.0, 0.0, 100.00, '2026-11-23', '2025-09-23', '2025-09-23 09:21:21', 4, 'restock', 16, 'Restocking: ', 1),
(23, 8, 1, 'B8-20250923-002', 1.0, 0.0, 100.00, '2026-11-23', '2025-09-23', '2025-09-23 09:22:21', 4, 'restock', 17, 'Restocking: ', 1),
(24, 9, 7, 'B9-20250923-001', 2.0, 0.0, 100.00, '2026-10-23', '2025-09-23', '2025-09-23 09:30:13', 4, 'restock', 18, 'Restocking: ', 1),
(25, 9, 3, 'B9-20250923-002', 2.0, 0.0, 100.00, '2026-12-19', '2025-09-23', '2025-09-23 09:30:54', 4, 'restock', 19, 'Restocking: ', 1),
(26, 9, 7, 'B9-20250923-003', 2.0, 0.0, NULL, '2026-03-23', '2025-09-23', '2025-09-23 09:34:21', 4, 'adjustment', 12, 'Stock adjustment: Other', 1),
(27, 9, 3, 'B9-20250923-004', 2.0, 0.0, 100.00, '2026-03-24', '2025-09-23', '2025-09-23 09:57:34', 4, 'restock', 20, 'Restocking: ', 1),
(28, 9, 3, 'B9-20250923-005', 1.0, 0.0, 100.00, '2025-12-23', '2025-09-23', '2025-09-23 14:08:26', 4, 'restock', 21, 'Restocking: ', 1),
(29, 10, 3, 'B10-20250924-001', 10.0, 2.0, 100.00, '2025-12-24', '2025-09-24', '2025-09-24 09:28:42', 4, 'restock', 22, 'Restocking: ', 1),
(30, 11, 7, 'B11-20250927-001', 9.0, 4.0, 150.00, '2025-12-27', '2025-09-27', '2025-09-27 15:30:59', 4, 'restock', 23, 'Restocking: ', 1),
(31, 8, 1, 'B8-20251007-001', 100.0, 57.8, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 02:46:47', 4, 'restock', 24, 'Restocking: ', 1),
(32, 6, 5, 'B6-20251007-001', 50.0, 42.0, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 02:47:34', 4, 'restock', 25, 'Restocking: ', 1),
(33, 9, 3, 'B9-20251007-001', 2.5, 0.0, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 04:41:54', 4, 'restock', 26, 'Restocking: ', 1),
(34, 9, 7, 'B9-20251007-002', 3.5, 0.0, 100.00, '2026-01-07', '2025-10-07', '2025-10-07 04:42:10', 4, 'restock', 27, 'Restocking: ', 1),
(35, 9, 3, 'B9-20251007-003', 2.5, 2.5, 70.00, '2026-01-07', '2025-10-07', '2025-10-07 05:10:48', 4, 'restock', 28, 'Restocking: ', 1),
(36, 12, 1, 'B12-20251007-001', 2.5, 2.5, 10.00, '2026-01-07', '2025-10-07', '2025-10-07 05:28:21', 4, 'restock', 29, 'Restocking: ', 1),
(37, 12, 1, 'B12-20251007-002', 3.5, 3.5, 15.00, '2026-01-07', '2025-10-07', '2025-10-07 05:29:18', 4, 'restock', 30, 'Restocking: ', 1),
(38, 13, 1, 'B13-20251008-001', 10.0, 10.0, 10.00, '2026-01-08', '2025-10-08', '2025-10-08 04:15:57', 4, 'restock', 31, 'Restocking: ', 1),
(39, 13, 1, 'B13-20251008-002', 10.0, 10.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 04:18:27', 4, 'restock', 32, 'Restocking: ', 1),
(40, 14, 5, 'B14-20251008-001', 10.5, 0.0, 105.00, '2026-01-08', '2025-10-08', '2025-10-08 05:56:26', 4, 'restock', 33, 'Restocking: ', 1),
(41, 14, 5, 'B14-20251008-002', 1.0, 0.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 10:20:02', 4, 'restock', 34, 'Restocking: ', 1),
(42, 14, 5, 'B14-20251008-003', 10.0, 0.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 11:22:04', 4, 'restock', 35, 'Restocking: ', 1),
(43, 14, 5, 'B14-20251008-004', 50.0, 0.0, 200.00, '2026-01-08', '2025-10-08', '2025-10-08 14:52:29', 4, 'restock', 36, 'Restocking: ', 1),
(44, 14, 7, 'B14-20251008-005', 2.0, 0.0, 100.00, '2026-01-08', '2025-10-08', '2025-10-08 15:14:01', 4, 'restock', 37, 'Restocking: ', 1),
(45, 14, 5, 'B14-20251008-006', 10.0, 8.0, 350.00, '2026-01-08', '2025-10-08', '2025-10-08 15:15:50', 4, 'restock', 38, 'Restocking: ', 1),
(46, 14, 5, 'B14-20251009-001', 1.0, 1.0, 100.00, '2026-01-09', '2025-10-09', '2025-10-09 04:06:27', 4, 'restock', 39, 'Restocking: ', 1);

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
(42, 14, 'uploads/68e5fc3bd4838-beeffats.png', 0, '2025-10-08 05:52:59');

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
(15, 14, 100.00, 50.00, 'stored');

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
(1, 1, 23.00, 10.00, 0.00, '2025-12-05', '2025-09-05 00:00:00'),
(2, 2, 37.00, 10.00, 0.00, '2026-01-01', '2025-09-04 00:00:00'),
(3, 3, 0.00, 10.00, 0.00, NULL, NULL),
(4, 2, 121.00, 10.00, 200.00, '2026-01-01', '2025-09-04 00:00:00'),
(5, 4, 18.00, 10.00, 0.00, '2026-06-16', '2025-09-16 00:00:00'),
(6, 5, 64.00, 10.00, 0.00, '2026-03-19', '2025-09-19 00:00:00'),
(7, 6, 43.00, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(8, 7, 27.00, 10.00, 0.00, '2026-01-15', '2025-09-19 00:00:00'),
(9, 8, 58.30, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(10, 9, 2.50, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(11, 10, 15.00, 10.00, 0.00, '2025-12-24', '2025-09-24 00:00:00'),
(12, 11, 3.50, 10.00, 0.00, '2025-12-27', '2025-09-27 00:00:00'),
(13, 12, 6.00, 10.00, 0.00, '2026-01-07', '2025-10-07 00:00:00'),
(14, 13, 20.00, 10.00, 0.00, '2026-01-08', '2025-10-08 00:00:00'),
(15, 14, 9.00, 10.00, 0.00, '2026-01-09', '2025-10-09 00:00:00');

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
(4, 'Premium Quality Products Guaranteed', 'fas fa-star', 1, 4, '2025-09-27 15:45:20', '2025-09-27 15:45:20');

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
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `quantity_added` int(11) NOT NULL,
  `restock_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restocking`
--

INSERT INTO `restocking` (`restocking_id`, `product_id`, `supplier_id`, `quantity_added`, `restock_date`, `expected_delivery`, `status_id`, `notes`, `created_by`, `created_at`) VALUES
(2, 2, 1, 21, '2025-09-04', '2025-09-04', 2, NULL, 3, '2025-09-04 12:58:34'),
(3, 1, 1, 10, '2025-09-05', '2025-09-05', 2, NULL, 4, '2025-09-05 00:51:50'),
(4, 4, 3, 50, '2025-09-16', '2025-09-16', 2, NULL, 4, '2025-09-16 15:09:01'),
(5, 5, 3, 20, '2025-09-17', '2025-09-17', 2, NULL, 4, '2025-09-17 15:46:07'),
(10, 5, 7, 10, '2025-09-19', '2025-09-19', 2, NULL, 4, '2025-09-19 02:36:00'),
(11, 7, 5, 10, '2025-09-19', '2025-09-19', 2, NULL, 4, '2025-09-19 03:08:01'),
(12, 7, 1, 10, '2025-09-19', '2025-09-19', 2, NULL, 4, '2025-09-19 03:14:50'),
(13, 9, 7, 1, '2025-09-23', '2025-09-22', 2, NULL, 4, '2025-09-22 04:57:34'),
(14, 9, 3, 30, '2025-09-22', '2025-09-22', 2, NULL, 4, '2025-09-22 04:59:56'),
(15, 9, 3, 30, '2025-09-22', '2025-09-22', 2, NULL, 4, '2025-09-22 05:04:22'),
(16, 8, 5, 1, '2025-09-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:21:21'),
(17, 8, 1, 1, '2025-09-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:22:21'),
(18, 9, 7, 2, '2025-09-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:30:13'),
(19, 9, 3, 2, '2025-09-23', '2025-09-23', 2, NULL, 4, '2025-09-23 09:30:54'),
(20, 9, 3, 2, '2025-09-23', NULL, 2, NULL, 4, '2025-09-23 09:57:34'),
(21, 9, 3, 1, '2025-09-23', NULL, 2, NULL, 4, '2025-09-23 14:08:26'),
(22, 10, 3, 10, '2025-09-24', NULL, 2, NULL, 4, '2025-09-24 09:28:42'),
(23, 11, 7, 9, '2025-09-27', NULL, 2, NULL, 4, '2025-09-27 15:30:59'),
(24, 8, 1, 100, '2025-10-07', NULL, 2, NULL, 4, '2025-10-07 02:46:47'),
(25, 6, 5, 50, '2025-10-07', NULL, 2, NULL, 4, '2025-10-07 02:47:34'),
(26, 9, 3, 3, '2025-10-07', NULL, 2, NULL, 4, '2025-10-07 04:41:54'),
(27, 9, 7, 4, '2025-10-07', NULL, 2, NULL, 4, '2025-10-07 04:42:10'),
(28, 9, 3, 3, '2025-10-07', NULL, 2, NULL, 4, '2025-10-07 05:10:48'),
(29, 12, 1, 3, '2025-10-07', NULL, 2, NULL, 4, '2025-10-07 05:28:21'),
(30, 12, 1, 4, '2025-10-07', NULL, 2, NULL, 4, '2025-10-07 05:29:18'),
(31, 13, 1, 10, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 04:15:57'),
(32, 13, 1, 10, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 04:18:27'),
(33, 14, 5, 11, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 05:56:26'),
(34, 14, 5, 1, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 10:20:02'),
(35, 14, 5, 10, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 11:22:04'),
(36, 14, 5, 50, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 14:52:29'),
(37, 14, 7, 2, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 15:14:01'),
(38, 14, 5, 10, '2025-10-08', NULL, 2, NULL, 4, '2025-10-08 15:15:50'),
(39, 14, 5, 1, '2025-10-09', NULL, 2, NULL, 4, '2025-10-09 04:06:27');

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
(43, 3, 3, '2025-09-04 13:24:40');

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
(15, 9, 2, 2, 2, 0, 'Damaged Items', NULL, 7, NULL, 4, '2025-09-23 09:58:37');

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
(61, 14, 1, 1.0, 8.0, 9.0, 39, 'restock', 'Restocking', 4, '2025-10-09 04:06:27', NULL);

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
(6, 'TEST TEST TEST', '09213197822', 'test@gmail.com', '100-c Kasunduan Extension Brgy. Commonwealth Q.c.\r\nKatena Hoa Multipurpose', NULL, NULL, NULL, '', 0, '2025-09-09 15:00:23', '2025-09-09 15:00:23'),
(7, 'BALIWAG', '09213197822', 'jay@gmail.com', '10th avenue, caloocan', NULL, NULL, NULL, '', 0, '2025-09-10 15:19:19', '2025-09-10 15:19:19');

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
(21, 7, 14, 0, 1, '2025-10-08 15:13:42', 4, NULL);

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
(2, 'admin1', '$2y$10$C6GWhpUZgY7LMvYluQEsZ.0UdK3gtjsy0j/P6Xm5mOmCYePDazjEG', 1, '2025-09-03 11:30:44', '2025-09-15 14:36:26', 1, 1, 0),
(3, 'brix', '$2y$10$1mJ.wgSPTv/BatVGcen5J.vwCJdYx2YIkN3KA4/L76y0qAhpAwxLG', 1, '2025-09-04 04:31:55', '2025-09-17 11:51:29', 2, 1, 1),
(4, 'superadmin', '$2y$10$a5j.TQ/6tn.aYWPnK63SMuVFiPvFS53pq0uQmKcskoBs8.eOZknEC', 1, '2025-09-04 13:23:31', '2025-09-15 14:36:26', 3, 1, 0),
(5, 'admin2', '$2y$10$AReKMWhBQimIhgQNcFr1Se/D1zQ7NIKCc6EPaxDqKZUaxkWwluo9m', 1, '2025-09-05 11:49:59', '2025-09-05 11:49:59', 1, 0, 0),
(6, 'inventory_admin_test', '$2y$10$C3esvAlICqA0Uw.xGZ6tmODrMipPVykykhPlw7fqSK4qrdg..LRVG', 1, '2025-09-08 13:04:23', '2025-09-08 13:04:23', 6, 0, 0),
(7, 'admin3', '$2y$10$o8XfpPAlnQLmD3W.tM7g/uAfZKQA3JXhSQh86u2GpBivYt0PR55/m', 1, '2025-09-08 13:05:16', '2025-09-08 13:05:16', 1, 0, 0),
(8, 'sales_admin', '$2y$10$o6RAuh1kqQy8Xyd1EFPFDu3vZKqF7WWpFp2gt8JC7PnF8xkgUStx.', 1, '2025-09-08 13:28:52', '2025-09-15 14:45:57', 7, 1, 0),
(9, 'giancarmen', '$2y$10$Fympd4rSTpswM1WFJrBNuuAoYq2gOsZlr3XFbmgNQBzRJUcAmnxeu', 1, '2025-09-15 14:28:22', '2025-09-17 12:19:13', 2, 1, 1),
(10, 'kaycee', '$2y$10$R2ZAfl7C9bDMsSqlN8NK2uo35ukC1SnjParCIsRZnkPFQQpGd0Mku', 1, '2025-09-17 15:32:23', '2025-09-17 15:34:09', NULL, 1, 1),
(11, 'katcat05', '$2y$10$UEugO9J4PRa7CJ5HDo9CwemlMV2Fgn4/BIr3/RsiabTRTx0CAq24i', 1, '2025-10-06 14:36:34', '2025-10-08 13:21:15', NULL, 1, 1),
(12, 'marquils', '$2y$10$CLNJVcsT7D9nKQsQbdD0iuaU3EoKklHUrKLHT8vQujOnTSQdplxHi', 1, '2025-10-06 14:46:10', '2025-10-06 14:46:29', NULL, 1, 0);

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
  `profile_picture` varchar(255) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_info`
--

INSERT INTO `user_info` (`user_info_id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `profile_picture`, `date_created`, `date_updated`) VALUES
(4, 2, 'System', 'Admin', 'admin@example.com', '09123456789', 'uploads/default.png', '2025-09-03 11:34:15', '2025-09-04 05:26:28'),
(5, 3, 'Marion Brix', 'Quiling', 'brixquils16@gmail.com', '09213197822', 'uploads/profile_3_1758620143_68d269efe3fb2.jpg', '2025-09-04 04:31:55', '2025-09-23 09:35:43'),
(7, 4, 'Marion Brix Quiling', '', 'superadmin@mikemadz.com', '09514971216', 'profile_4_1758110938.jpg', '2025-09-04 13:23:31', '2025-09-17 12:08:58'),
(8, 5, NULL, NULL, 'testadmin@gmail.com', NULL, NULL, '2025-09-05 11:49:59', '2025-09-05 11:49:59'),
(9, 6, NULL, NULL, 'inventory@gmail.com', NULL, NULL, '2025-09-08 13:04:23', '2025-09-08 13:04:23'),
(10, 7, NULL, NULL, 'admin3@gmail.com', NULL, NULL, '2025-09-08 13:05:16', '2025-09-08 13:05:16'),
(11, 8, NULL, NULL, 'sales@gmail.com', NULL, NULL, '2025-09-08 13:28:52', '2025-09-08 13:28:52'),
(12, 9, 'GIAN', 'CARMEN', 'giansteven58@gmail.com', '09213197822', 'uploads/profile_9_1758035256_68c97d38429a6.jpg', '2025-09-15 14:28:22', '2025-09-16 15:07:36'),
(13, 10, 'Kaycee', 'Gallaza', 'kreatives09@gmail.com', '0921314193', 'uploads/profile_10_1758123186_68cad4b29a052.jpg', '2025-09-17 15:32:23', '2025-09-17 15:33:06'),
(14, 11, 'Katrina', 'Catani', 'ntalavera0426@gmail.com', '09213197822', 'uploads/profile_11_1759838874_68e5029ad845a.jpg', '2025-10-06 14:36:34', '2025-10-07 12:07:54'),
(15, 12, 'Marianne', 'Quiling', 'mariannequiling893@gmail.com', NULL, NULL, '2025-10-06 14:46:10', '2025-10-06 14:46:10');

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
(6, 'inventory_admin'),
(7, 'sales_admin');

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
  ADD UNIQUE KEY `user_id` (`user_id`,`product_id`),
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
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_orders_address_id` (`address_id`);

--
-- Indexes for table `order_cancellations`
--
ALTER TABLE `order_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_cancellations_order_id` (`order_id`);

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
  ADD KEY `expiration_date` (`expiration_date`);

--
-- Indexes for table `product_boxes`
--
ALTER TABLE `product_boxes`
  ADD PRIMARY KEY (`box_id`),
  ADD KEY `fk_box_product` (`product_id`);

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
  ADD KEY `fk_restocking_status` (`status_id`);

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
  ADD UNIQUE KEY `email` (`email`),
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
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `adjustment_types`
--
ALTER TABLE `adjustment_types`
  MODIFY `adjustment_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `alert_types`
--
ALTER TABLE `alert_types`
  MODIFY `alerttype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `batch_movements`
--
ALTER TABLE `batch_movements`
  MODIFY `movement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cartitem_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `customer_id_verification`
--
ALTER TABLE `customer_id_verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `discount_codes`
--
ALTER TABLE `discount_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `discount_code_usage`
--
ALTER TABLE `discount_code_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `email_verification`
--
ALTER TABLE `email_verification`
  MODIFY `emailverify_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `history_action_types`
--
ALTER TABLE `history_action_types`
  MODIFY `history_action_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `history_logs`
--
ALTER TABLE `history_logs`
  MODIFY `historylog_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  MODIFY `inventoryalert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `orders_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=243;

--
-- AUTO_INCREMENT for table `order_cancellations`
--
ALTER TABLE `order_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `orderitems_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `order_ratings`
--
ALTER TABLE `order_ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_status`
--
ALTER TABLE `order_status`
  MODIFY `orderstatus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payments_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

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
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `product_batches`
--
ALTER TABLE `product_batches`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `product_boxes`
--
ALTER TABLE `product_boxes`
  MODIFY `box_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `product_image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `product_pricing`
--
ALTER TABLE `product_pricing`
  MODIFY `productpricing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `product_sale`
--
ALTER TABLE `product_sale`
  MODIFY `productsale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_stock`
--
ALTER TABLE `product_stock`
  MODIFY `productstock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `product_uom_conversions`
--
ALTER TABLE `product_uom_conversions`
  MODIFY `conversion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `promo_messages`
--
ALTER TABLE `promo_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `restocking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

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
  MODIFY `role_permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  MODIFY `stockadjustment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `stockmovement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `stock_movement_types`
--
ALTER TABLE `stock_movement_types`
  MODIFY `stockmovementtype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `uom`
--
ALTER TABLE `uom`
  MODIFY `uom_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_info`
--
ALTER TABLE `user_info`
  MODIFY `user_info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `user_permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `user_type`
--
ALTER TABLE `user_type`
  MODIFY `usertype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  ADD CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_boxes`
--
ALTER TABLE `product_boxes`
  ADD CONSTRAINT `fk_box_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

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
