-- Migration: add nullable category_id to stock movement tables so a single
-- base item (e.g. "Paper") can track stock separately per category (A4, Legal)
-- instead of requiring a separate item row per variant.

ALTER TABLE `stock_transactions`
  ADD COLUMN `category_id` INT NULL AFTER `item_id`,
  ADD KEY `idx_stock_transactions_category` (`category_id`),
  ADD CONSTRAINT `fk_stock_transactions_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL;

ALTER TABLE `receipt_items`
  ADD COLUMN `category_id` INT NULL AFTER `item_id`,
  ADD KEY `idx_receipt_items_category` (`category_id`),
  ADD CONSTRAINT `fk_receipt_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL;

ALTER TABLE `stock_issues`
  ADD COLUMN `category_id` INT NULL AFTER `item_id`,
  ADD KEY `idx_stock_issues_category` (`category_id`),
  ADD CONSTRAINT `fk_stock_issues_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL;
