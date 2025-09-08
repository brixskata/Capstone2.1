-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 03, 2025 at 10:14 AM
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
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(6, 'XYZ INC.', 0, '2025-08-05 15:20:31');

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

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`cartitem_id`, `cart_id`, `user_id`, `product_id`, `quantity`, `created_at`, `updated_at`) VALUES
(157, 0, 21, 53, 1, '2025-04-28 14:17:39', '2025-04-28 14:17:39'),
(264, 0, 40, 72, 2, '2025-05-25 23:38:13', '2025-05-25 23:58:40'),
(266, 0, 41, 57, 1, '2025-05-26 00:10:41', '2025-05-26 00:10:41'),
(267, 0, 41, 72, 1, '2025-05-26 00:10:45', '2025-05-26 00:10:45'),
(268, 0, 41, 78, 1, '2025-05-26 00:10:47', '2025-05-26 00:10:47'),
(269, 0, 42, 57, 1, '2025-05-27 13:16:52', '2025-05-27 13:16:52'),
(270, 0, 43, 52, 1, '2025-06-26 14:21:15', '2025-06-26 14:21:15'),
(271, 0, 43, 57, 1, '2025-06-26 14:26:23', '2025-06-26 14:26:23'),
(294, 0, 45, 87, 1, '2025-08-06 06:40:15', '2025-08-06 06:40:15'),
(483, 0, 46, 87, 1, '2025-08-15 15:27:51', '2025-08-15 15:58:28'),
(487, 0, 46, 91, 6, '2025-08-15 15:30:59', '2025-08-15 15:58:37'),
(488, 0, 46, 89, 4, '2025-08-15 15:31:08', '2025-08-15 15:58:37');

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
(28, 'PORK');

-- --------------------------------------------------------

--
-- Table structure for table `delivered_orders`
--

CREATE TABLE `delivered_orders` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer` varchar(255) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `delivered_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'MIKEMADZ10', 'percent', 10.00, 1, '2025-07-27 00:26:00'),
(3, 'BAGO', 'percent', 50.00, 1, '2025-07-31 20:31:00');

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

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Unknown'),
(2, 'Imported_0');

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
(1, 0, NULL, NULL, 'Product Name: s', 0, '2025-07-01 22:34:30'),
(2, 0, NULL, NULL, 'Username: TECHNO, Email: techno@gmail.com', 0, '2025-07-09 16:30:54'),
(3, 0, NULL, NULL, 'Username: gian22, Email: gian22@gmail.com', 0, '2025-07-09 16:32:10'),
(4, 0, NULL, NULL, 'Supplier Name: San Gabriel Beef, Contact: Ronaldo Reyes', 0, '2025-07-12 21:56:23'),
(5, 0, NULL, NULL, 'Brand Name: San Gabriel Beef', 0, '2025-07-12 22:00:25'),
(6, 0, NULL, NULL, 'Brand Name: HAHA', 0, '2025-07-12 22:00:56'),
(7, 0, NULL, NULL, 'Supplier Name: sadasd, Contact: sadas', 0, '2025-07-12 22:01:23'),
(8, 0, NULL, NULL, 'UOM Name: Kilos', 0, '2025-07-12 22:01:48'),
(9, 0, NULL, NULL, 'UOM Name: Boxes', 0, '2025-07-12 22:01:53'),
(10, 0, NULL, NULL, 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱0.00, Markup: 0.00%, Selling Price: ₱0', 0, '2025-07-12 22:02:23'),
(11, 0, NULL, NULL, 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱0.00, Markup: 0.00%, Selling Price: ₱0', 0, '2025-07-12 22:02:33'),
(12, 0, NULL, NULL, 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱250, Markup: 20%, Selling Price: ₱300', 0, '2025-07-12 22:02:52'),
(13, 0, NULL, NULL, 'Supplier ID: 1, Name: SGB, Contact: Ronaldo Reyes', 0, '2025-07-12 22:03:25'),
(14, 0, NULL, NULL, 'Supplier ID: 1, Name: SGB Goods, Contact: Ronaldo Reyes', 0, '2025-07-12 22:03:32'),
(15, 0, NULL, NULL, 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱250.00, Markup: 20.00%, Selling Price: ₱300', 0, '2025-07-12 22:04:43'),
(16, 0, NULL, NULL, 'Supplier ID: 2, Name: sadasd', 0, '2025-07-12 22:05:18'),
(17, 0, NULL, NULL, 'Supplier ID: 2, Name: sadasd', 0, '2025-07-13 22:13:22'),
(18, 0, NULL, NULL, 'Supplier ID: 2, Name: SUPPLIER TEST, Contact: sadas', 0, '2025-07-13 22:13:30'),
(19, 0, NULL, NULL, 'Supplier ID: 2, Name: SUPPLIER TEST', 0, '2025-07-13 22:13:46'),
(20, 0, NULL, NULL, 'Username: user, Email: user@gmail.com', 0, '2025-07-13 22:36:17'),
(21, 0, NULL, NULL, 'Brand ID: 2, Name: HAHA', 0, '2025-07-13 22:44:18'),
(22, 0, NULL, NULL, 'Product ID: 67, Type: set, Quantity: 10, Reason: Manual Correction', 0, '2025-07-14 23:20:08'),
(23, 0, NULL, NULL, 'Product ID: 51, Type: set, Quantity: 10, Reason: Quality Control', 0, '2025-07-14 23:20:31'),
(24, 0, NULL, NULL, 'Product ID: 67, Quantity: 21, Cost: ₱5250', 0, '2025-07-14 23:25:28'),
(25, 0, NULL, NULL, 'Product ID: 51, Type: subtract, Quantity: 2, Reason: Other', 0, '2025-07-16 19:46:54'),
(26, 0, NULL, NULL, 'Product ID: 66, Quantity: 2, Cost: ₱400', 0, '2025-07-16 19:49:55'),
(27, 0, NULL, NULL, 'Product ID: 80, Quantity: 20, Cost: ₱400', 0, '2025-07-16 20:01:56'),
(28, 0, NULL, NULL, 'Product Name: TRIMMINGS, Cost: ₱190, Markup: 10%, Selling Price: ₱209', 0, '2025-07-22 19:38:10'),
(29, 0, NULL, NULL, 'Product Name: DAING NA BANGUS, Cost: ₱190, Markup: 10%, Selling Price: ₱209', 0, '2025-07-24 19:06:38'),
(30, 0, NULL, NULL, 'Product Name: HIPON, Cost: ₱100, Markup: 20%, Selling Price: ₱120', 0, '2025-07-25 19:44:27'),
(31, 0, NULL, NULL, 'Product Name: ANDOKS SCRAP, Cost: ₱160, Markup: 15%, Selling Price: ₱184', 0, '2025-07-25 19:59:05'),
(32, 0, NULL, NULL, 'Supplier Name: ZAYN GOODS, Contact: GIAN CARMEN', 0, '2025-07-25 20:27:22'),
(33, 0, NULL, NULL, 'Brand Name: Zayn Bangus', 0, '2025-07-25 20:31:19'),
(34, 0, NULL, NULL, 'Product Name: BEEF FLANK, Cost: ₱210, Markup: 10%, Selling Price: ₱231', 0, '2025-08-05 23:18:50'),
(35, 0, NULL, NULL, 'Brand Name: ANDOKS ', 0, '2025-08-05 23:19:56'),
(36, 0, NULL, NULL, 'Brand Name: GOODS GOODS', 0, '2025-08-05 23:20:14'),
(37, 0, NULL, NULL, 'Brand Name: XYZ INC.', 0, '2025-08-05 23:20:31'),
(38, 0, NULL, NULL, 'Product Name: CHICKEN NECK, Cost: ₱55, Markup: 21%, Selling Price: ₱66.55', 0, '2025-08-05 23:21:03'),
(39, 0, NULL, NULL, 'Product Name: PORK TAPA, Cost: ₱150, Markup: 5%, Selling Price: ₱157.5', 0, '2025-08-05 23:21:39'),
(40, 0, NULL, NULL, 'Product Name: PORK RIBS, Cost: ₱99, Markup: 2%, Selling Price: ₱100.98', 0, '2025-08-05 23:22:22'),
(41, 0, NULL, NULL, 'Product Name: CHICKEN BREAST, Cost: ₱170, Markup: 5%, Selling Price: ₱178.5', 0, '2025-08-05 23:23:10'),
(42, 0, NULL, NULL, 'Product ID: 87, Name: ANDOKS SCRAPP, Cost: ₱160.00, Markup: 15.00%, Selling Price: ₱184', 0, '2025-08-09 08:01:09');

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
(1, 2, 2, 'Your order has been delivered.', 0, '2024-11-24 16:10:11');

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
  `delivery_option` varchar(50) NOT NULL DEFAULT 'Pickup'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`orders_id`, `user_id`, `orderstatus_id`, `created_at`, `total_price`, `delivered_at`, `delivery_option`) VALUES
(158, 45, 0, '2025-07-25 13:13:06', 209.00, NULL, 'pickup'),
(159, 45, 0, '2025-07-25 13:23:37', 418.00, NULL, 'delivery'),
(160, 45, 0, '2025-07-25 20:32:32', 722.00, NULL, 'delivery'),
(161, 45, 0, '2025-07-25 20:33:13', 209.00, NULL, 'delivery'),
(162, 45, 0, '2025-07-25 20:33:45', 209.00, NULL, 'delivery'),
(163, 46, 0, '2025-08-09 09:31:06', 231.00, NULL, 'pickup'),
(164, 46, 0, '2025-08-15 23:08:35', 1339.00, NULL, 'pickup'),
(165, 46, 0, '2025-08-16 08:05:25', 184.00, NULL, 'pickup');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `orderitems_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`orderitems_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(311, 158, 85, 1, 0.00),
(312, 159, 85, 1, 0.00),
(313, 159, 84, 1, 0.00),
(314, 160, 85, 1, 0.00),
(315, 160, 86, 1, 0.00),
(316, 160, 87, 1, 0.00),
(317, 160, 84, 1, 0.00),
(318, 161, 85, 1, 0.00),
(319, 162, 85, 1, 0.00),
(320, 163, 88, 1, 0.00),
(321, 164, 87, 1, 0.00),
(322, 164, 88, 5, 0.00),
(323, 165, 87, 1, 0.00);

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
(3, 'Shipped'),
(4, 'Completed'),
(5, 'Cancelled'),
(6, 'Return');

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
  `paymentstatus_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `uom_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `product_description`, `created_at`, `category_id`, `is_archive`, `brand_id`, `supplier_id`, `uom_id`) VALUES
(84, 'TRIMMINGS', 'BEEF', '2025-07-22 11:38:10', 23, 1, 1, 1, 1),
(85, 'DAING NA BANGUS', 'FISH', '2025-07-24 11:06:38', 27, 0, NULL, 1, 1),
(86, 'HIPON', 'SEA FOOD', '2025-07-25 11:44:27', 27, 0, 1, 1, 1),
(87, 'ANDOKS SCRAPP', 'BEEF', '2025-07-25 11:59:05', 27, 0, 1, 1, 1),
(88, 'BEEF FLANK', 'BEEF', '2025-08-05 15:18:50', 23, 0, 1, 1, 1),
(89, 'CHICKEN NECK', 'CHICKEN', '2025-08-05 15:21:03', 24, 0, 5, 3, 1),
(90, 'PORK TAPA', 'PORK', '2025-08-05 15:21:39', 28, 0, 6, 3, 1),
(91, 'PORK RIBS', 'PORK', '2025-08-05 15:22:22', 28, 0, 4, 1, 1),
(92, 'CHICKEN BREAST', 'CHICKEN', '2025-08-05 15:23:10', 24, 0, 4, 1, 1);

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

-- --------------------------------------------------------

--
-- Table structure for table `product_pricing`
--

CREATE TABLE `product_pricing` (
  `productpricing_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `markup_percentage` decimal(5,2) DEFAULT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `pricing_type` enum('computed','stored') DEFAULT 'stored'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `current_stock` int(11) DEFAULT 0,
  `reorder_point` int(11) DEFAULT 0,
  `max_stock` int(11) DEFAULT 0,
  `expiration_date` date DEFAULT NULL,
  `last_restock_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `cost_per_unit` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `restock_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `stockmovement_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `stockmovementtype_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'SGB Goods', '0921 676 444', 'ronaldo@gmail.com', NULL, NULL, NULL, NULL, 'Primary Supplier', 0, '2025-07-12 13:56:23', '2025-08-24 14:42:52'),
(2, 'SUPPLIER TEST', '213123123', 'asdsad@gmail.com', NULL, NULL, NULL, NULL, 'asdasd', 1, '2025-07-12 14:01:23', '2025-08-24 14:42:52'),
(3, 'ZAYN GOODS', '09213197822', 'gi@gmail.com', NULL, NULL, NULL, NULL, 'Secondary Supplier', 0, '2025-07-25 12:27:22', '2025-08-24 14:42:52');

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

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `usertype_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `is_verified`, `is_active`, `date_created`, `date_updated`, `usertype_id`) VALUES
(1, 'admin', '$2y$10$TNFNspCy92t/JlmtBQbYC.W9tpcXPwUMr4botKh8ZBPyFprNLAW0m', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(16, 'BRIXBRIX', '$2y$10$x81HFEP4Rs2IbFHKU6yM5.wHbFeAxcpxg47McBtVEV8NZ2WxV5uwu', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(17, 'gian22', '$2y$10$Ks9BAICfMTRIKj9qLVR.7OzBvM1DF8xRRWXYV9mMatShsC2S5Ko4e', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(18, 'zayn', '$2y$10$ufE5KpZI2SaZ79V2xteMeO/023NJ6Mut8VsYy1rwB/r0dWhWR0Lvi', 0, 1, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(19, 'TECHNO', '$2y$10$/swn7X6xUiuzEgAIud.CvOp5iJIpGzHwLpR00n50IVW4BgRMMoh3O', 0, 1, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(20, 'user', '$2y$10$XIgAGvEPMPGdleEIyf2fLOB1UIfqJDKrGIldvVtQR2pE3rBym4Dca', 0, 1, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(21, 'Test', '$2y$10$WMGdl7e2jvWebGx.8XW6d.r1TdTQWZ4ZYM2GE8yoDqk2.AuuuS6kO', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(22, 'khian', '$2y$10$1qQDPgxNo1KS5I2r7FfiT.MokJpJE5xj57GsqSUKCougWmeJX54ra', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(23, 'Marion', '$2y$10$33n1NtEhtNdBxx/zzoafm.T6mzvd68ZAgwF74nOy8L4ZydLecHjn.', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(24, 'Kat', '$2y$10$R.z0dqGZYI8ORjqX6Em6x.cWFMA82WdcExlcwseknnhcofUnszSCu', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(29, 'Testingg', '$2y$10$IDyJriYxyZWh7is2TKMEBOGgbrr9FWiWXEHKxz5IOc59SMi.Cf6qW', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(30, 'brixu', '$2y$10$XIiPWu.5t.jN5nfL1ZFqHunFz2VLigErPrjGvsWfwzNRXN9i3ZIOe', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(37, 'maryan', '$2y$10$aMc6CsDSUsUYnjkj/Z51qOFpR2F0mO9SmLfrzqehtJ0PJ20z5FykS', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(39, 'zoi', '$2y$10$upGKOO6tqud/X01jTtZaHOiIVN.4YZsiZvXp9kp825mgFUVEY2cke', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(40, 'brixskata', '$2y$10$aYLcUajn6haiU4q0ksv5h.noTgfdo6thZtZDLjwZksP3aqaMz.Idi', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(42, 'katkat', '$2y$10$9Xsp/QFxY36ePcqBbl8xheNV7MJ48Ec92IqaIlOYf/QnlTzJClbF2', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(45, 'marionmarion', '$2y$10$G4qpCHktQyUEe0Jq021dkeCOTGnAOLWd1vUYwt2eHwOQeSufrjJqi', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL),
(46, 'MarionBrix', '$2y$10$qFJ/CWaP6juX5WaeUqbzIOOMcb0jmX9ekP87QYJ4emPLbqtpDhMEe', 0, 0, '2025-08-24 07:40:42', '2025-08-24 07:40:42', NULL);

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
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_type`
--

CREATE TABLE `user_type` (
  `usertype_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `adjustment_types`
--
ALTER TABLE `adjustment_types`
  ADD PRIMARY KEY (`adjustment_type_id`);

--
-- Indexes for table `alert_types`
--
ALTER TABLE `alert_types`
  ADD PRIMARY KEY (`alerttype_id`);

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
-- Indexes for table `delivered_orders`
--
ALTER TABLE `delivered_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

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
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`orderitems_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_items_ibfk_1` (`order_id`);

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
  ADD KEY `fk_payments_order` (`orders_id`);

--
-- Indexes for table `payment_status`
--
ALTER TABLE `payment_status`
  ADD PRIMARY KEY (`paymentstatus_id`);

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
-- Indexes for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  ADD PRIMARY KEY (`stockadjustment_id`),
  ADD KEY `fk_stockadj_product` (`product_id`),
  ADD KEY `fk_stockadj_type` (`adjustment_type_id`),
  ADD KEY `fk_stockadj_user` (`created_by`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`stockmovement_id`),
  ADD KEY `fk_stockmov_product` (`product_id`),
  ADD KEY `fk_stockmov_type` (`stockmovementtype_id`),
  ADD KEY `fk_stockmov_user` (`created_by`);

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
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `adjustment_types`
--
ALTER TABLE `adjustment_types`
  MODIFY `adjustment_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `alert_types`
--
ALTER TABLE `alert_types`
  MODIFY `alerttype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cartitem_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=505;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `delivered_orders`
--
ALTER TABLE `delivered_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `discount_codes`
--
ALTER TABLE `discount_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_verification`
--
ALTER TABLE `email_verification`
  MODIFY `emailverify_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `history_action_types`
--
ALTER TABLE `history_action_types`
  MODIFY `history_action_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `history_logs`
--
ALTER TABLE `history_logs`
  MODIFY `historylog_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  MODIFY `inventoryalert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `orders_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `orderitems_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=324;

--
-- AUTO_INCREMENT for table `order_status`
--
ALTER TABLE `order_status`
  MODIFY `orderstatus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payments_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_status`
--
ALTER TABLE `payment_status`
  MODIFY `paymentstatus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `product_image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_pricing`
--
ALTER TABLE `product_pricing`
  MODIFY `productpricing_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_sale`
--
ALTER TABLE `product_sale`
  MODIFY `productsale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_stock`
--
ALTER TABLE `product_stock`
  MODIFY `productstock_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `restocking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  MODIFY `stockadjustment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `stockmovement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movement_types`
--
ALTER TABLE `stock_movement_types`
  MODIFY `stockmovementtype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `uom`
--
ALTER TABLE `uom`
  MODIFY `uom_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `user_info`
--
ALTER TABLE `user_info`
  MODIFY `user_info_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_type`
--
ALTER TABLE `user_type`
  MODIFY `usertype_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

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
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`orders_id`),
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`orders_id`) ON DELETE CASCADE;

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
-- Constraints for table `stock_adjustment`
--
ALTER TABLE `stock_adjustment`
  ADD CONSTRAINT `fk_stockadj_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_stockadj_type` FOREIGN KEY (`adjustment_type_id`) REFERENCES `adjustment_types` (`adjustment_type_id`),
  ADD CONSTRAINT `fk_stockadj_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stockmov_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `fk_stockmov_type` FOREIGN KEY (`stockmovementtype_id`) REFERENCES `stock_movement_types` (`stockmovementtype_id`),
  ADD CONSTRAINT `fk_stockmov_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
