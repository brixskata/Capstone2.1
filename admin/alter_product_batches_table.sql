-- Alter existing product_batches table to add missing columns for FIFO inventory management
-- This script updates your existing table structure

-- Add missing columns to existing product_batches table
ALTER TABLE `product_batches` 
ADD COLUMN `supplier_id` int(11) DEFAULT NULL AFTER `product_id`,
ADD COLUMN `unit_cost` decimal(10,2) DEFAULT NULL AFTER `remaining_quantity`,
ADD COLUMN `received_date` date DEFAULT NULL AFTER `expiration_date`,
ADD COLUMN `reference_type` enum('restock','adjustment','manual') DEFAULT 'restock' AFTER `created_by`,
ADD COLUMN `reference_id` int(11) DEFAULT NULL AFTER `reference_type`,
ADD COLUMN `notes` text DEFAULT NULL AFTER `reference_id`,
ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `notes`;

-- Rename existing columns to match new naming convention
ALTER TABLE `product_batches` 
CHANGE COLUMN `quantity` `quantity_received` int(11) NOT NULL DEFAULT 0,
CHANGE COLUMN `remaining_quantity` `quantity_remaining` int(11) NOT NULL DEFAULT 0;

-- Update received_date to use created_at if it's NULL
UPDATE `product_batches` SET `received_date` = DATE(`created_at`) WHERE `received_date` IS NULL;

-- Make received_date NOT NULL after updating
ALTER TABLE `product_batches` 
MODIFY COLUMN `received_date` date NOT NULL;

-- Add indexes for better performance
ALTER TABLE `product_batches`
ADD KEY `idx_supplier_id` (`supplier_id`),
ADD KEY `idx_received_date` (`received_date`),
ADD KEY `idx_quantity_remaining` (`quantity_remaining`),
ADD KEY `idx_reference` (`reference_type`, `reference_id`);

-- Add foreign key constraints
ALTER TABLE `product_batches`
ADD CONSTRAINT `fk_batches_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_batches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
