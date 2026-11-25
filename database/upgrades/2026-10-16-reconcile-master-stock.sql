-- Select the EXISTING application database in phpMyAdmin. Back it up first.
-- Run while checkout/receiving is paused. Stop on any SQL error.
-- Repairs only the derived products.stock total from existing branch_products quantities.
-- Does not change branch quantities, prices, product timestamps, sales or refund amounts.
-- Repeatable: only discrepancies are updated and logged. Requires an active administrator.
SELECT id, stock, updated_at FROM products LIMIT 0;
SELECT id, product_id, stock FROM branch_products LIMIT 0;
SELECT user_id, activity, log_time FROM activity_logs LIMIT 0;

CREATE TEMPORARY TABLE phx_stock_reconciliation (
  product_id INT PRIMARY KEY, old_stock BIGINT NOT NULL, new_stock BIGINT NOT NULL
) ENGINE=InnoDB;
START TRANSACTION;
SET @phx_reconcile_actor=(SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1);
SELECT id FROM branch_products ORDER BY id FOR UPDATE;
SELECT id FROM products ORDER BY id FOR UPDATE;
INSERT INTO phx_stock_reconciliation (product_id,old_stock,new_stock)
SELECT p.id,p.stock,COALESCE(b.qty,0) FROM products p
LEFT JOIN (SELECT product_id,SUM(stock) qty FROM branch_products GROUP BY product_id)b ON b.product_id=p.id
WHERE p.stock<>COALESCE(b.qty,0) AND @phx_reconcile_actor IS NOT NULL;
-- Keep this before/after result with your backup.
SELECT * FROM phx_stock_reconciliation ORDER BY product_id;
INSERT INTO activity_logs (user_id,activity,log_time)
SELECT @phx_reconcile_actor,CONCAT('2026-10-16 master-stock reconciliation; product #',product_id,
    ': ',old_stock,' -> ',new_stock,'. Derived from existing branch balances; branch quantities unchanged.'),NOW()
FROM phx_stock_reconciliation;
UPDATE products p JOIN phx_stock_reconciliation r ON r.product_id=p.id
SET p.stock=r.new_stock,p.updated_at=p.updated_at;
COMMIT;
SELECT CASE WHEN @phx_reconcile_actor IS NULL THEN 'No active administrator; no stock changed'
    ELSE 'Master-stock reconciliation complete' END AS reconciliation_status;
SELECT COUNT(*) AS remaining_discrepancies FROM products p
LEFT JOIN (SELECT product_id,SUM(stock) qty FROM branch_products GROUP BY product_id)b ON b.product_id=p.id
WHERE p.stock<>COALESCE(b.qty,0);
DROP TEMPORARY TABLE phx_stock_reconciliation;
SET @phx_reconcile_actor=NULL;
