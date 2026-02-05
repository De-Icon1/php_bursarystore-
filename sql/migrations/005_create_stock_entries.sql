-- Migration: create legacy stock_entries table for movement history
-- Run in MySQL (phpMyAdmin or mysql CLI) while `bursarystore` is selected.

CREATE TABLE IF NOT EXISTS `stock_entries` (
  `entry_id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `qty_in` DECIMAL(12,2) DEFAULT 0,
  `qty_out` DECIMAL(12,2) DEFAULT 0,
  `reference` VARCHAR(191) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stock_entries_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
