-- Migration: create stock_issues table with free-text unit field
-- Run in MySQL (phpMyAdmin or mysql CLI) while `bursarystore` is selected.

CREATE TABLE IF NOT EXISTS `stock_issues` (
  `issue_id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `unit` VARCHAR(150) NOT NULL,          -- e.g. "Administration", "Registry", "FIN"
  `quantity` DECIMAL(12,2) NOT NULL,
  `issued_by` VARCHAR(100) NOT NULL,     -- username / staff identifier
  `purpose` VARCHAR(255) DEFAULT NULL,   -- job card / reference / note
  `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stock_issues_item`
    FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
