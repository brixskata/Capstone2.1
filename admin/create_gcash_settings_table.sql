-- Create system_settings table for GCash and other system configurations
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('text','number','boolean','file','json') DEFAULT 'text',
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default GCash settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('gcash_qr_code', 'assets/gcashqr/gcash_qr.jpg', 'file', 'GCash QR Code image file path'),
('gcash_account_name', 'MikeMadz Store', 'text', 'GCash account holder name'),
('gcash_account_number', '', 'text', 'GCash account number'),
('gcash_instructions', 'Scan the QR code above and complete your payment', 'text', 'Payment instructions for customers'),
('gcash_minimum_amount', '1', 'number', 'Minimum amount for GCash payment'),
('gcash_maximum_amount', '50000', 'number', 'Maximum amount for GCash payment'),
('gcash_enabled', '1', 'boolean', 'Enable/disable GCash payment method');
