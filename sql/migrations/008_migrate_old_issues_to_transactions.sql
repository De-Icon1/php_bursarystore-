-- Migration: Migrate old stock_issues data into stock_transactions
-- Run this AFTER migration 007 to ensure stock_balance has correct numbers.

-- For each stock_issue that doesn't already have a matching stock_transaction,
-- insert a negative qty_change record.
INSERT INTO stock_transactions (item_id, qty_change, tx_type, reference_id, user_id, note)
SELECT 
  si.item_id,
  -1 * si.quantity AS qty_change,
  'dispatch' AS tx_type,
  si.issue_id AS reference_id,
  COALESCE((SELECT user_id FROM users WHERE username = si.issued_by LIMIT 1), 0) AS user_id,
  CONCAT('Migrated issue: ', si.purpose) AS note
FROM stock_issues si
WHERE NOT EXISTS (
  SELECT 1 FROM stock_transactions st 
  WHERE st.reference_id = si.issue_id 
    AND st.tx_type = 'dispatch'
);

-- Update stock_balance to reflect migrated data
INSERT INTO stock_balance (item_id, quantity)
SELECT 
  st.item_id,
  COALESCE(SUM(st.qty_change), 0) AS quantity
FROM stock_transactions st
GROUP BY st.item_id
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity);

-- Fill in any items missing from stock_balance
INSERT INTO stock_balance (item_id, quantity)
SELECT item_id, 0 FROM items i
WHERE NOT EXISTS (SELECT 1 FROM stock_balance sb WHERE sb.item_id = i.item_id);
