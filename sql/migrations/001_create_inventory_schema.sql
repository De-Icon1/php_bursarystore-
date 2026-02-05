-- Migration: create inventory schema for bursary store
-- Run in MySQL (phpMyAdmin or mysql CLI) while `bursarystore` is selected.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `units` (
  `unit_id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(32) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `items` (
  `item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(64) DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `unit_measure` VARCHAR(50) DEFAULT 'each',
  `reorder_level` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_receipts` (
  `receipt_id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier` VARCHAR(200) DEFAULT NULL,
  `received_by` INT DEFAULT NULL,
  `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `note` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `receipt_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_cost` DECIMAL(12,2) DEFAULT NULL,
  FOREIGN KEY (`receipt_id`) REFERENCES `stock_receipts`(`receipt_id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `unit_id` INT NOT NULL,
  `requested_by` INT DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  FOREIGN KEY (`unit_id`) REFERENCES `units`(`unit_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `request_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT NOT NULL,
  `item_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  FOREIGN KEY (`request_id`) REFERENCES `stock_requests`(`request_id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transaction log for stock movements (receipts, dispatches, adjustments)
CREATE TABLE IF NOT EXISTS `stock_transactions` (
  `tx_id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `qty_change` INT NOT NULL,
  `tx_type` ENUM('receive','dispatch','adjustment') NOT NULL,
  `reference_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `note` TEXT DEFAULT NULL,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Sample seed data (units and items)
INSERT INTO `units` (`code`, `name`) VALUES
  ('ADMIN', 'Administration'),
  ('REG', 'Registry'),
  ('FIN', 'Finance');

INSERT INTO `items` (`sku`, `name`, `category`, `unit_measure`, `reorder_level`) VALUES
  ('A4-500', 'A4 Paper Ream (500 sheets)', 'Paper', 'ream', 10),
  ('PEN-BP', 'Ballpoint Pen (Blue)', 'Stationery', 'each', 50),
  ('TONER-85A', 'HP Toner 85A (Black)', 'Toner', 'each', 5),
  ('TONER-05A', 'Canon Toner 05A (Black)', 'Toner', 'each', 5);

-- Example: record a receipt (insert into stock_receipts, receipt_items, and a stock_transactions row)
-- INSERT INTO `stock_receipts` (`supplier`, `received_by`, `note`) VALUES ('OfficeSupplies Ltd', 1, 'Initial stock');
-- INSERT INTO `receipt_items` (`receipt_id`, `item_id`, `quantity`, `unit_cost`) VALUES (1, 1, 20, 2500.00);
-- INSERT INTO `stock_transactions` (`item_id`, `qty_change`, `tx_type`, `reference_id`, `user_id`, `note`) VALUES (1, 20, 'receive', 1, 1, 'Initial stock load');

-- End of migration
