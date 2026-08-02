-- Migration: create item_categories junction table for many-to-many relationship
-- between items and categories.
--
-- An item (e.g. "Paper") can now be linked to multiple categories (e.g. A4, A5, Legal).
-- Existing category_id column on items is kept for backward compatibility.

CREATE TABLE IF NOT EXISTS `item_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `item_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_category` (`item_id`, `category_id`),
  KEY `idx_item_categories_item` (`item_id`),
  KEY `idx_item_categories_category` (`category_id`),
  CONSTRAINT `fk_item_categories_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_categories_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing data: for each item that has a category_id set,
-- insert a row into item_categories so existing data is not lost.
INSERT INTO `item_categories` (`item_id`, `category_id`)
SELECT `item_id`, `category_id` FROM `items`
WHERE `category_id` IS NOT NULL
  AND `category_id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `item_categories` ic
    WHERE ic.`item_id` = `items`.`item_id`
      AND ic.`category_id` = `items`.`category_id`
  );
