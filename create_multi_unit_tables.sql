-- Create tables for multi-unit ordering system
-- This script adds the necessary tables for product unit conversions and variable weight boxes

-- Table for product unit conversions
CREATE TABLE `product_uom_conversions` (
  `conversion_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `uom_id` int(11) NOT NULL,
  `conversion_rate` decimal(10,4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`conversion_id`),
  KEY `fk_conversion_product` (`product_id`),
  KEY `fk_conversion_uom` (`uom_id`),
  CONSTRAINT `fk_conversion_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversion_uom` FOREIGN KEY (`uom_id`) REFERENCES `uom` (`uom_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for variable weight boxes
CREATE TABLE `product_boxes` (
  `box_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `batch_id` varchar(50) DEFAULT NULL,
  `weight` decimal(10,2) NOT NULL,
  `is_sold` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`box_id`),
  KEY `fk_box_product` (`product_id`),
  CONSTRAINT `fk_box_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add some UOM entries if they don't exist
INSERT IGNORE INTO `uom` (`uom_id`, `name`, `is_archive`, `created_at`) VALUES
(1, 'Kilos', 0, NOW()),
(2, 'Pieces', 0, NOW()),
(3, 'Boxes', 0, NOW());

-- Add sample data for Bangus product (product_id 2)
-- First, let's add conversion rates for Bangus
INSERT INTO `product_uom_conversions` (`product_id`, `uom_id`, `conversion_rate`) VALUES
(2, 2, 0.25), -- 1 Piece = 0.25 kilo
(2, 3, 20.00); -- 1 Box (average) = 20 kilos

-- Add sample variable weight boxes for Bangus
INSERT INTO `product_boxes` (`product_id`, `batch_id`, `weight`, `is_sold`) VALUES
(2, 'BATCH001', 18.5, 0), -- Box #1 = 18.5kg
(2, 'BATCH001', 20.0, 0), -- Box #2 = 20.0kg
(2, 'BATCH002', 19.2, 0); -- Box #3 = 19.2kg

-- Update product pricing for Bangus (assuming it needs pricing data)
INSERT IGNORE INTO `product_pricing` (`product_id`, `cost_price`, `markup_percentage`, `selling_price`, `pricing_type`) VALUES
(2, 110.00, 20.00, 132.00, 'stored');

-- Update product stock for Bangus
INSERT IGNORE INTO `product_stock` (`product_id`, `current_stock`, `reorder_point`, `max_stock`) VALUES
(2, 100, 10, 200);
