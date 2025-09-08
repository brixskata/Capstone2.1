-- Create product_batches table for FIFO management
CREATE TABLE IF NOT EXISTS `product_batches` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `remaining_quantity` int(11) NOT NULL DEFAULT 0,
  `expiration_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `product_id` (`product_id`),
  KEY `expiration_date` (`expiration_date`),
  CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create waste_tracking table for spoilage management
CREATE TABLE IF NOT EXISTS `waste_tracking` (
  `waste_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_wasted` int(11) NOT NULL,
  `waste_reason` enum('expired','damaged','spoiled','quality_issue','other') NOT NULL,
  `waste_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`waste_id`),
  KEY `product_id` (`product_id`),
  KEY `batch_id` (`batch_id`),
  KEY `waste_date` (`waste_date`),
  CONSTRAINT `fk_waste_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_waste_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create temperature_logs table for cold storage monitoring
CREATE TABLE IF NOT EXISTS `temperature_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `storage_location` varchar(100) NOT NULL,
  `temperature_celsius` decimal(5,2) NOT NULL,
  `humidity_percent` decimal(5,2) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `storage_location` (`storage_location`),
  KEY `recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add shelf_life_days column to products table
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `shelf_life_days` int(11) DEFAULT NULL COMMENT 'Expected shelf life in days';
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `storage_requirements` enum('room_temp','refrigerated','frozen','special') DEFAULT 'room_temp';
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_perishable` tinyint(1) DEFAULT 0 COMMENT '1 if product is perishable, 0 if not';

-- Add batch tracking to stock_movements
ALTER TABLE `stock_movements` ADD COLUMN IF NOT EXISTS `batch_id` int(11) DEFAULT NULL;
ALTER TABLE `stock_movements` ADD KEY `batch_id` (`batch_id`);
ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movements_batch` FOREIGN KEY (`batch_id`) REFERENCES `product_batches` (`batch_id`) ON DELETE SET NULL;

-- Insert sample storage locations
INSERT IGNORE INTO `temperature_logs` (`storage_location`, `temperature_celsius`, `humidity_percent`, `recorded_by`) VALUES
('Main Refrigerator', 4.0, 60.0, 4),
('Freezer Unit A', -18.0, 30.0, 4),
('Freezer Unit B', -20.0, 25.0, 4),
('Cold Storage Room', 2.0, 70.0, 4);
