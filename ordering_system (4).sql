-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 06, 2025 at 07:27 AM
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
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
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
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification`
--

INSERT INTO `email_verification` (`id`, `email`, `otp`, `expires_at`, `verified`, `created_at`) VALUES
(20, 'brixquils@gmail.com', '735590', '2025-05-25 17:33:11', 1, '2025-05-25 23:23:11'),
(22, 'mariannequiling893@gmail.com', '928816', '2025-05-25 18:17:28', 1, '2025-05-26 00:07:28'),
(23, 'brixquils.1@gmail.com', '027911', '2025-05-26 01:47:03', 1, '2025-05-26 07:37:03'),
(25, 'katrinacatani05@gmail.com', '576218', '2025-05-27 15:25:55', 1, '2025-05-27 21:15:55'),
(26, 'marionquils16@gmail.com', '605905', '2025-06-26 14:59:23', 1, '2025-06-26 20:49:23'),
(27, 'kreatives09@gmail.com', '571540', '2025-07-02 08:31:05', 1, '2025-07-02 14:21:05'),
(28, 'brixquils16@gmail.com', '407461', '2025-07-22 19:04:47', 1, '2025-07-23 00:54:47');

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
-- Table structure for table `history_logs`
--

CREATE TABLE `history_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `performed_by` varchar(255) NOT NULL,
  `performed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `history_logs`
--

INSERT INTO `history_logs` (`id`, `action`, `details`, `performed_by`, `performed_at`) VALUES
(1, 'Added Product', 'Product Name: s', 'admin', '2025-07-01 22:34:30'),
(2, 'User Deactivated', 'Username: TECHNO, Email: techno@gmail.com', 'admin', '2025-07-09 16:30:54'),
(3, 'User Reactivated', 'Username: gian22, Email: gian22@gmail.com', 'admin', '2025-07-09 16:32:10'),
(4, 'Added Supplier', 'Supplier Name: San Gabriel Beef, Contact: Ronaldo Reyes', 'admin', '2025-07-12 21:56:23'),
(5, 'Added Brand', 'Brand Name: San Gabriel Beef', 'admin', '2025-07-12 22:00:25'),
(6, 'Added Brand', 'Brand Name: HAHA', 'admin', '2025-07-12 22:00:56'),
(7, 'Added Supplier', 'Supplier Name: sadasd, Contact: sadas', 'admin', '2025-07-12 22:01:23'),
(8, 'Added UOM', 'UOM Name: Kilos', 'admin', '2025-07-12 22:01:48'),
(9, 'Added UOM', 'UOM Name: Boxes', 'admin', '2025-07-12 22:01:53'),
(10, 'Edited Product', 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱0.00, Markup: 0.00%, Selling Price: ₱0', 'admin', '2025-07-12 22:02:23'),
(11, 'Edited Product', 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱0.00, Markup: 0.00%, Selling Price: ₱0', 'admin', '2025-07-12 22:02:33'),
(12, 'Edited Product', 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱250, Markup: 20%, Selling Price: ₱300', 'admin', '2025-07-12 22:02:52'),
(13, 'Edited Supplier', 'Supplier ID: 1, Name: SGB, Contact: Ronaldo Reyes', 'admin', '2025-07-12 22:03:25'),
(14, 'Edited Supplier', 'Supplier ID: 1, Name: SGB Goods, Contact: Ronaldo Reyes', 'admin', '2025-07-12 22:03:32'),
(15, 'Edited Product', 'Product ID: 80, Name: BEEF SCRAP, Cost: ₱250.00, Markup: 20.00%, Selling Price: ₱300', 'admin', '2025-07-12 22:04:43'),
(16, 'Archived Supplier', 'Supplier ID: 2, Name: sadasd', 'admin', '2025-07-12 22:05:18'),
(17, 'Unarchived Supplier', 'Supplier ID: 2, Name: sadasd', 'admin', '2025-07-13 22:13:22'),
(18, 'Edited Supplier', 'Supplier ID: 2, Name: SUPPLIER TEST, Contact: sadas', 'admin', '2025-07-13 22:13:30'),
(19, 'Archived Supplier', 'Supplier ID: 2, Name: SUPPLIER TEST', 'admin', '2025-07-13 22:13:46'),
(20, 'User Deactivated', 'Username: user, Email: user@gmail.com', 'admin', '2025-07-13 22:36:17'),
(21, 'Deleted Brand', 'Brand ID: 2, Name: HAHA', 'admin', '2025-07-13 22:44:18'),
(22, 'Stock Adjustment', 'Product ID: 67, Type: set, Quantity: 10, Reason: Manual Correction', 'admin', '2025-07-14 23:20:08'),
(23, 'Stock Adjustment', 'Product ID: 51, Type: set, Quantity: 10, Reason: Quality Control', 'admin', '2025-07-14 23:20:31'),
(24, 'Restocking', 'Product ID: 67, Quantity: 21, Cost: ₱5250', 'admin', '2025-07-14 23:25:28'),
(25, 'Stock Adjustment', 'Product ID: 51, Type: subtract, Quantity: 2, Reason: Other', 'admin', '2025-07-16 19:46:54'),
(26, 'Restocking', 'Product ID: 66, Quantity: 2, Cost: ₱400', 'admin', '2025-07-16 19:49:55'),
(27, 'Restocking', 'Product ID: 80, Quantity: 20, Cost: ₱400', 'admin', '2025-07-16 20:01:56'),
(28, 'Added Product', 'Product Name: TRIMMINGS, Cost: ₱190, Markup: 10%, Selling Price: ₱209', 'admin', '2025-07-22 19:38:10'),
(29, 'Added Product', 'Product Name: DAING NA BANGUS, Cost: ₱190, Markup: 10%, Selling Price: ₱209', 'admin', '2025-07-24 19:06:38'),
(30, 'Added Product', 'Product Name: HIPON, Cost: ₱100, Markup: 20%, Selling Price: ₱120', 'admin', '2025-07-25 19:44:27'),
(31, 'Added Product', 'Product Name: ANDOKS SCRAP, Cost: ₱160, Markup: 15%, Selling Price: ₱184', 'admin', '2025-07-25 19:59:05'),
(32, 'Added Supplier', 'Supplier Name: ZAYN GOODS, Contact: GIAN CARMEN', 'admin', '2025-07-25 20:27:22'),
(33, 'Added Brand', 'Brand Name: Zayn Bangus', 'admin', '2025-07-25 20:31:19'),
(34, 'Added Product', 'Product Name: BEEF FLANK, Cost: ₱210, Markup: 10%, Selling Price: ₱231', 'admin', '2025-08-05 23:18:50'),
(35, 'Added Brand', 'Brand Name: ANDOKS ', 'admin', '2025-08-05 23:19:56'),
(36, 'Added Brand', 'Brand Name: GOODS GOODS', 'admin', '2025-08-05 23:20:14'),
(37, 'Added Brand', 'Brand Name: XYZ INC.', 'admin', '2025-08-05 23:20:31'),
(38, 'Added Product', 'Product Name: CHICKEN NECK, Cost: ₱55, Markup: 21%, Selling Price: ₱66.55', 'admin', '2025-08-05 23:21:03'),
(39, 'Added Product', 'Product Name: PORK TAPA, Cost: ₱150, Markup: 5%, Selling Price: ₱157.5', 'admin', '2025-08-05 23:21:39'),
(40, 'Added Product', 'Product Name: PORK RIBS, Cost: ₱99, Markup: 2%, Selling Price: ₱100.98', 'admin', '2025-08-05 23:22:22'),
(41, 'Added Product', 'Product Name: CHICKEN BREAST, Cost: ₱170, Markup: 5%, Selling Price: ₱178.5', 'admin', '2025-08-05 23:23:10');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_alerts`
--

CREATE TABLE `inventory_alerts` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','overstock','expiring_soon') NOT NULL,
  `threshold_value` int(11) DEFAULT NULL,
  `current_value` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` varchar(100) DEFAULT NULL,
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
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `total_price` decimal(10,2) NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'Cash',
  `payment_proof` varchar(255) DEFAULT NULL,
  `delivery_option` varchar(10) NOT NULL DEFAULT 'Pickup'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `status`, `created_at`, `total_price`, `payment_method`, `payment_proof`, `delivery_option`) VALUES
(158, 45, 'Pending', '2025-07-25 13:13:06', 209.00, 'GCash', '68831262878e3_coke.png', 'pickup'),
(159, 45, 'Pending', '2025-07-25 13:23:37', 418.00, 'GCash', '688314d960f89_logo.png', 'delivery'),
(160, 45, 'Pending', '2025-07-25 20:32:32', 722.00, 'GCash', '6883796011d5d_67f0f1abd3480-486414959_539021679304431_832844970765089701_n.jpg', 'delivery'),
(161, 45, 'Pending', '2025-07-25 20:33:13', 209.00, 'GCash', '68837989e873c_67f0f1abd3480-486414959_539021679304431_832844970765089701_n.jpg', 'delivery'),
(162, 45, 'Shipped', '2025-07-25 20:33:45', 209.00, 'GCash', '688379a93b922_67f0f2f9c10f2-6285260574556281912 (1).jpg', 'delivery');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`) VALUES
(311, 158, 85, 1),
(312, 159, 85, 1),
(313, 159, 84, 1),
(314, 160, 85, 1),
(315, 160, 86, 1),
(316, 160, 87, 1),
(317, 160, 84, 1),
(318, 161, 85, 1),
(319, 162, 85, 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_new` tinyint(1) DEFAULT 0,
  `is_hot` tinyint(1) DEFAULT 0,
  `sales_count` int(11) DEFAULT 0,
  `category_id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `markup_percentage` decimal(5,2) DEFAULT 0.00,
  `brand_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `uom_id` int(11) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `reorder_point` int(11) DEFAULT 10,
  `max_stock` int(11) DEFAULT 100,
  `last_restock_date` date DEFAULT NULL,
  `last_stock_check` date DEFAULT NULL,
  `image1` varchar(255) DEFAULT NULL,
  `image2` varchar(255) DEFAULT NULL,
  `image3` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `stock`, `image_url`, `created_at`, `is_new`, `is_hot`, `sales_count`, `category_id`, `image`, `is_archived`, `cost_price`, `markup_percentage`, `brand_id`, `supplier_id`, `uom_id`, `expiration_date`, `reorder_point`, `max_stock`, `last_restock_date`, `last_stock_check`, `image1`, `image2`, `image3`) VALUES
(84, 'TRIMMINGS', 'BEEF', 209.00, 18, NULL, '2025-07-22 11:38:10', 0, 0, 0, 23, NULL, 1, 190.00, 10.00, 1, 1, 1, '2028-10-17', 10, 100, NULL, NULL, 'uploads/687f7822a2eb0-trimmings1.jpg', 'uploads/687f7822a3b2e-trimmings2.jpg', 'uploads/687f7822a3d3d-trimmings3.jpg'),
(85, 'DAING NA BANGUS', 'FISH', 209.00, 14, NULL, '2025-07-24 11:06:38', 0, 0, 0, 27, NULL, 0, 190.00, 10.00, NULL, 1, 1, '2025-09-24', 10, 100, NULL, NULL, 'uploads/688213bebccbd-bangus.jpg', 'uploads/688213bebdb7c-bangus.jpg', 'uploads/688213bebdd20-bangus.jpg'),
(86, 'HIPON', 'SEA FOOD', 120.00, 20, NULL, '2025-07-25 11:44:27', 0, 0, 0, 27, NULL, 0, 100.00, 20.00, 1, 1, 1, '2025-07-30', 10, 100, NULL, NULL, 'uploads/68836e1ba5477-hipon.jpg', 'uploads/68836e1ba6176-hipon.jpg', 'uploads/68836e1ba62c5-hipon.jpg'),
(87, 'ANDOKS SCRAP', 'BEEF', 184.00, 17, NULL, '2025-07-25 11:59:05', 0, 0, 0, 27, NULL, 0, 160.00, 15.00, 1, 1, 1, '2025-07-30', 10, 100, NULL, NULL, 'uploads/688371891c908-beefscrap-hero-section.png', 'uploads/688371891cacd-beefscrap.png', 'uploads/688371891cc47-beefscrap.jpg'),
(88, 'BEEF FLANK', 'BEEF', 231.00, 21, NULL, '2025-08-05 15:18:50', 0, 0, 0, 23, NULL, 0, 210.00, 10.00, 1, 1, 1, '2025-11-19', 10, 100, NULL, NULL, 'uploads/689220da635be-beefflank.jpg', 'uploads/689220da64368-beefflank.jpg', 'uploads/689220da645ef-beefflank.jpg'),
(89, 'CHICKEN NECK', 'CHICKEN', 66.55, 33, NULL, '2025-08-05 15:21:03', 0, 0, 0, 24, NULL, 0, 55.00, 21.00, 5, 3, 1, '2025-11-27', 10, 100, NULL, NULL, 'uploads/6892215fada82-neck.jpg', 'uploads/6892215fadc50-neck.jpg', 'uploads/6892215faddbf-neck.jpg'),
(90, 'PORK TAPA', 'PORK', 157.50, 21, NULL, '2025-08-05 15:21:39', 0, 0, 0, 28, NULL, 0, 150.00, 5.00, 6, 3, 1, '2025-12-25', 10, 100, NULL, NULL, 'uploads/68922183f1886-porktapa.jpg', 'uploads/68922183f1afc-porktapa.jpg', 'uploads/68922183f1cc1-porktapa.jpg'),
(91, 'PORK RIBS', 'PORK', 100.98, 41, NULL, '2025-08-05 15:22:22', 0, 0, 0, 28, NULL, 0, 99.00, 2.00, 4, 1, 1, '2025-12-05', 10, 100, NULL, NULL, 'uploads/689221ae8f0aa-porkribs.jpg', 'uploads/689221ae8f299-porkribs.jpg', 'uploads/689221ae8f415-porkribs.jpg'),
(92, 'CHICKEN BREAST', 'CHICKEN', 178.50, 31, NULL, '2025-08-05 15:23:10', 0, 0, 0, 24, NULL, 0, 170.00, 5.00, 4, 1, 1, '2026-01-01', 10, 100, NULL, NULL, 'uploads/689221de345a8-breast.jpg', 'uploads/689221de349f4-breast.jpg', 'uploads/689221de34b96-breast.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `product_sales`
--

CREATE TABLE `product_sales` (
  `product_id` int(11) NOT NULL,
  `purchase_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restocking`
--

CREATE TABLE `restocking` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `quantity_added` int(11) NOT NULL,
  `cost_per_unit` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `restock_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status` enum('pending','received','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restocking`
--

INSERT INTO `restocking` (`id`, `product_id`, `supplier_id`, `quantity_added`, `cost_per_unit`, `total_cost`, `restock_date`, `expected_delivery`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 67, 1, 21, 250.00, 5250.00, '2025-07-14', '2025-07-15', 'pending', '', 'admin', '2025-07-14 15:25:28'),
(2, 66, 1, 2, 200.00, 400.00, '2025-07-16', '2025-07-17', 'pending', '', 'admin', '2025-07-16 11:49:55'),
(3, 80, 1, 20, 20.00, 400.00, '2025-07-16', '0000-00-00', 'pending', '', 'admin', '2025-07-16 12:01:56');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product` varchar(255) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_orders`
--

CREATE TABLE `return_orders` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `return_date` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `adjustment_type` enum('add','subtract','set') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`id`, `product_id`, `adjustment_type`, `quantity`, `previous_stock`, `new_stock`, `reason`, `notes`, `created_by`, `created_at`) VALUES
(1, 67, 'set', 10, 8, 10, 'Manual Correction', '', 'admin', '2025-07-14 15:20:08'),
(2, 51, 'set', 10, 0, 10, 'Quality Control', '', 'admin', '2025-07-14 15:20:31'),
(3, 51, 'subtract', 2, 10, 8, 'Other', '', 'admin', '2025-07-16 11:46:54');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `previous_stock`, `new_stock`, `reason`, `reference_id`, `reference_type`, `created_by`, `created_at`) VALUES
(1, 67, 'adjustment', 10, 8, 10, 'Manual Correction', 1, 'adjustment', 'admin', '2025-07-14 15:20:08'),
(2, 51, 'adjustment', 10, 0, 10, 'Quality Control', 2, 'adjustment', 'admin', '2025-07-14 15:20:31'),
(3, 67, 'in', 21, 10, 31, 'Restocking', 0, 'restock', 'admin', '2025-07-14 15:25:28'),
(4, 51, 'adjustment', 2, 10, 8, 'Other', 3, 'adjustment', 'admin', '2025-07-16 11:46:54'),
(5, 66, 'in', 2, 26, 28, 'Restocking', 0, 'restock', 'admin', '2025-07-16 11:49:55'),
(6, 80, 'in', 20, 22, 42, 'Restocking', 0, 'restock', 'admin', '2025-07-16 12:01:56');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_info` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_archived` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_info`, `phone`, `email`, `address`, `notes`, `is_archived`, `created_at`) VALUES
(1, 'SGB Goods', 'Ronaldo Reyes', '0921 676 444', 'ronaldo@gmail.com', 'Blumentritt, Manila', 'Primary Supplier', 0, '2025-07-12 13:56:23'),
(2, 'SUPPLIER TEST', 'sadas', '213123123', 'asdsad@gmail.com', 'asdasd', 'asdasd', 1, '2025-07-12 14:01:23'),
(3, 'ZAYN GOODS', 'GIAN CARMEN', '09213197822', 'gi@gmail.com', 'kasunduan, QC', 'Secondary Supplier', 0, '2025-07-25 12:27:22');

-- --------------------------------------------------------

--
-- Table structure for table `units_of_measurement`
--

CREATE TABLE `units_of_measurement` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_archived` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units_of_measurement`
--

INSERT INTO `units_of_measurement` (`id`, `name`, `is_archived`, `created_at`) VALUES
(1, 'Kilos', 0, '2025-07-12 14:01:48'),
(2, 'Boxes', 0, '2025-07-12 14:01:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','customer') NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `deactivated` tinyint(1) DEFAULT 0,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `email_verified`, `verification_token`, `deactivated`, `first_name`, `last_name`, `phone`, `street`, `barangay`, `city`, `postal_code`, `profile_image`) VALUES
(1, 'admin', '', '$2y$10$TNFNspCy92t/JlmtBQbYC.W9tpcXPwUMr4botKh8ZBPyFprNLAW0m', 'admin', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'BRIXBRIX', 'brixbrix@gmail.com', '$2y$10$x81HFEP4Rs2IbFHKU6yM5.wHbFeAxcpxg47McBtVEV8NZ2WxV5uwu', 'admin', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'gian22', 'gian22@gmail.com', '$2y$10$Ks9BAICfMTRIKj9qLVR.7OzBvM1DF8xRRWXYV9mMatShsC2S5Ko4e', 'admin', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'zayn', 'zayn@gmail.com', '$2y$10$ufE5KpZI2SaZ79V2xteMeO/023NJ6Mut8VsYy1rwB/r0dWhWR0Lvi', 'customer', 0, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'TECHNO', 'techno@gmail.com', '$2y$10$/swn7X6xUiuzEgAIud.CvOp5iJIpGzHwLpR00n50IVW4BgRMMoh3O', 'customer', 0, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'user', 'user@gmail.com', '$2y$10$XIgAGvEPMPGdleEIyf2fLOB1UIfqJDKrGIldvVtQR2pE3rBym4Dca', 'customer', 0, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Test', 'test@gmail.com', '$2y$10$WMGdl7e2jvWebGx.8XW6d.r1TdTQWZ4ZYM2GE8yoDqk2.AuuuS6kO', 'customer', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'khian', 'khian@gmail.com', '$2y$10$1qQDPgxNo1KS5I2r7FfiT.MokJpJE5xj57GsqSUKCougWmeJX54ra', 'customer', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'Marion', 'marion@gmail.com', '$2y$10$33n1NtEhtNdBxx/zzoafm.T6mzvd68ZAgwF74nOy8L4ZydLecHjn.', 'customer', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 'Kat', 'katrinacatani@ymail.com', '$2y$10$R.z0dqGZYI8ORjqX6Em6x.cWFMA82WdcExlcwseknnhcofUnszSCu', 'customer', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'Testingg', 'brix@gmail.com', '$2y$10$IDyJriYxyZWh7is2TKMEBOGgbrr9FWiWXEHKxz5IOc59SMi.Cf6qW', 'customer', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'brixu', 'brixx@gmail.com', '$2y$10$XIiPWu.5t.jN5nfL1ZFqHunFz2VLigErPrjGvsWfwzNRXN9i3ZIOe', 'customer', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'maryan', 'mariannezoiquiling99@gmail.com', '$2y$10$aMc6CsDSUsUYnjkj/Z51qOFpR2F0mO9SmLfrzqehtJ0PJ20z5FykS', 'customer', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'zoi', 'mariannequiling893@gmail.com', '$2y$10$upGKOO6tqud/X01jTtZaHOiIVN.4YZsiZvXp9kp825mgFUVEY2cke', 'customer', 1, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'brixskata', 'brixquils.1@gmail.com', '$2y$10$aYLcUajn6haiU4q0ksv5h.noTgfdo6thZtZDLjwZksP3aqaMz.Idi', 'customer', 1, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 'katkat', 'katrinacatani05@gmail.com', '$2y$10$9Xsp/QFxY36ePcqBbl8xheNV7MJ48Ec92IqaIlOYf/QnlTzJClbF2', 'admin', 1, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 'brixsu', 'marionquils16@gmail.com', '$2y$10$CJZNY5FxVC5cQTwuscMATu05ZP7N75f3brwkaZM2IqZXm4tgAR5s2', 'customer', 1, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(44, 'kaycee', 'kreatives09@gmail.com', '$2y$10$IyQtQZzIySrI0hLH7APe/uCeGZ4b6/whKAdRUVU5kR.jrU6kSntXi', 'customer', 1, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(45, 'marionmarion', 'brixquils16@gmail.com', '$2y$10$G4qpCHktQyUEe0Jq021dkeCOTGnAOLWd1vUYwt2eHwOQeSufrjJqi', 'customer', 1, NULL, 0, 'Marion Brix', 'Quiling', '0921313123', '100 Kasunduan', 'Commonwealth', 'Quezon City', '1121', '6883187a58d0e_67f0f1abd3480-486414959_539021679304431_832844970765089701_n.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `user_cart`
--

CREATE TABLE `user_cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_cart`
--

INSERT INTO `user_cart` (`id`, `user_id`, `product_id`, `quantity`, `created_at`, `updated_at`) VALUES
(157, 21, 53, 1, '2025-04-28 14:17:39', '2025-04-28 14:17:39'),
(264, 40, 72, 2, '2025-05-25 23:38:13', '2025-05-25 23:58:40'),
(266, 41, 57, 1, '2025-05-26 00:10:41', '2025-05-26 00:10:41'),
(267, 41, 72, 1, '2025-05-26 00:10:45', '2025-05-26 00:10:45'),
(268, 41, 78, 1, '2025-05-26 00:10:47', '2025-05-26 00:10:47'),
(269, 42, 57, 1, '2025-05-27 13:16:52', '2025-05-27 13:16:52'),
(270, 43, 52, 1, '2025-06-26 14:21:15', '2025-06-26 14:21:15'),
(271, 43, 57, 1, '2025-06-26 14:26:23', '2025-06-26 14:26:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`product_id`);

--
-- Indexes for table `history_logs`
--
ALTER TABLE `history_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_alerts_product` (`product_id`),
  ADD KEY `idx_inventory_alerts_resolved` (`is_resolved`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_items_ibfk_1` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_products_brand` (`brand_id`),
  ADD KEY `fk_products_supplier` (`supplier_id`),
  ADD KEY `fk_products_uom` (`uom_id`);

--
-- Indexes for table `product_sales`
--
ALTER TABLE `product_sales`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `restocking`
--
ALTER TABLE `restocking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_restocking_product` (`product_id`),
  ADD KEY `idx_restocking_status` (`status`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `return_orders`
--
ALTER TABLE `return_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_adjustments_product` (`product_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_movements_product` (`product_id`),
  ADD KEY `idx_stock_movements_date` (`created_at`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units_of_measurement`
--
ALTER TABLE `units_of_measurement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_cart`
--
ALTER TABLE `user_cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`product_id`),
  ADD KEY `user_cart_ibfk_2` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `history_logs`
--
ALTER TABLE `history_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=320;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `restocking`
--
ALTER TABLE `restocking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_orders`
--
ALTER TABLE `return_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `units_of_measurement`
--
ALTER TABLE `units_of_measurement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `user_cart`
--
ALTER TABLE `user_cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=294;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  ADD CONSTRAINT `inventory_alerts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_uom` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measurement` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`uom_id`) REFERENCES `units_of_measurement` (`id`);

--
-- Constraints for table `product_sales`
--
ALTER TABLE `product_sales`
  ADD CONSTRAINT `product_sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restocking`
--
ALTER TABLE `restocking`
  ADD CONSTRAINT `restocking_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restocking_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `return_orders`
--
ALTER TABLE `return_orders`
  ADD CONSTRAINT `return_orders_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `return_orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_cart`
--
ALTER TABLE `user_cart`
  ADD CONSTRAINT `user_cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
