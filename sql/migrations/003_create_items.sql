-- Migration: create items table
CREATE TABLE IF NOT EXISTS `items` (
  `item_id` INT NOT NULL AUTO_INCREMENT,
  `item_name` VARCHAR(150) NOT NULL,
  `category_id` INT DEFAULT NULL,
  `unit` VARCHAR(50) NOT NULL DEFAULT 'pcs',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uq_items_name` (`item_name`),
  KEY `idx_items_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
