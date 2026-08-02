-- Migration: create item_categories junction table (item hasMany categories)
-- and backfill links from the legacy free-text `items.category` column.
-- Safe to run multiple times; does not modify existing item or stock rows.

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

-- Backfill: link each existing item to a category of the same name (legacy `category` text column)
INSERT INTO `item_categories` (`item_id`, `category_id`)
SELECT it.item_id, c.category_id
FROM items it
JOIN categories c ON c.name = it.category
WHERE NOT EXISTS (
    SELECT 1 FROM item_categories ic WHERE ic.item_id = it.item_id AND ic.category_id = c.category_id
);
