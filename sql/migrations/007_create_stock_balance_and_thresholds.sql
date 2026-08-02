-- Migration: Create stock_balance table and inventory_thresholds table
-- to support proper stock management with reorder levels.

-- 1. Create stock_balance table for tracking current stock quantities
CREATE TABLE IF NOT EXISTS `stock_balance` (
  `balance_id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_stock_balance_item` (`item_id`),
  CONSTRAINT `fk_stock_balance_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create inventory_thresholds table for per-item reorder levels
CREATE TABLE IF NOT EXISTS `inventory_thresholds` (
  `threshold_id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `threshold_qty` DECIMAL(12,2) NOT NULL DEFAULT 10,
  `notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_threshold_item` (`item_id`),
  CONSTRAINT `fk_threshold_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Populate stock_balance from stock_transactions (if any exist)
INSERT INTO `stock_balance` (`item_id`, `quantity`)
SELECT 
  st.item_id,
  COALESCE(SUM(st.qty_change), 0) AS quantity
FROM stock_transactions st
GROUP BY st.item_id
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity);

-- For any items not in stock_transactions, insert with 0 quantity
INSERT INTO `stock_balance` (`item_id`, `quantity`)
SELECT item_id, 0 FROM items i
WHERE NOT EXISTS (SELECT 1 FROM stock_balance sb WHERE sb.item_id = i.item_id);

-- 4. Populate inventory_thresholds from items.reorder_level (if column exists)
-- Then set defaults for items that don't have thresholds yet
INSERT INTO `inventory_thresholds` (`item_id`, `threshold_qty`, `notified`)
SELECT 
  i.item_id,
  CASE 
    WHEN i.reorder_level IS NOT NULL AND i.reorder_level > 0 THEN i.reorder_level
    ELSE 10
  END AS threshold_qty,
  0 AS notified
FROM items i
WHERE NOT EXISTS (SELECT 1 FROM inventory_thresholds t WHERE t.item_id = i.item_id);
