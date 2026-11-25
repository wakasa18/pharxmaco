-- PHARXMACO: TWO WEEKS OF SYNTHETIC SALES AND MATCHING STOCK MOVEMENTS.
-- Prepared for u315645644_pharxmacotest (2).sql, exported October 12, 2026.
-- Import into a test copy containing that export, in one connection.
-- Fixed complete days: September 28 through October 11, 2026.
-- Daily demand has a modest linear trend and no imposed weekly seasonality.
-- The application's existing Holt weights are alpha=0.3 and beta=0.2.
-- These observations demonstrate operation; they do not establish accuracy.
-- Each sale has one item and one stock_out. Two stock_in deliveries per pair
-- cover the sold units, returning each pair to its original low closing stock.
-- Existing sales, logs, product details, expiry dates and stock quantities stay
-- intact. Only the 19 sampled branch_products.updated_at timestamps advance.
-- A complete repeat is a no-op; partial fixtures or changed source inventory
-- are rejected before any writes. Do not reconnect or ignore SQL errors.
-- phpMyAdmin: select the existing test database, Import, choose this SQL file,
-- character set utf-8, format SQL, then Import/Go. Keep delimiter as semicolon.
-- No application tables are created or dropped; no custom delimiter is needed.

SET @phx_holt_old_character_set_client = @@character_set_client;
SET @phx_holt_old_character_set_results = @@character_set_results;
SET @phx_holt_old_collation_connection = @@collation_connection;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @phx_holt_old_time_zone = @@SESSION.time_zone;
SET time_zone = '+00:00';
SET @phx_holt_marker = 'PHX-DEMO-HOLT14-20261012';
SET @phx_holt_note = CONCAT(@phx_holt_marker, ': SYNTHETIC sale with matching demo stock movement; not a real payment.');
SET @phx_holt_from = '2026-09-28';
SET @phx_holt_to = '2026-10-11';

DROP TEMPORARY TABLE IF EXISTS phx_holt_pairs;
CREATE TEMPORARY TABLE phx_holt_pairs (
    inventory_id INT PRIMARY KEY,
    branch_id INT NOT NULL,
    product_id INT NOT NULL,
    opening_stock INT NOT NULL
) ENGINE=InnoDB;
INSERT INTO phx_holt_pairs VALUES
    (138,1,21,0), (139,1,22,5), (149,1,32,0), (150,1,33,9),
    (160,1,43,0), (171,1,54,0), (172,1,55,3),
    (176,3,19,0), (177,3,20,9), (178,3,21,11), (187,3,30,0),
    (188,3,31,5), (189,3,32,4), (198,3,41,0), (199,3,42,4),
    (200,3,43,9), (209,3,52,0), (210,3,53,15), (211,3,54,13);

DROP TEMPORARY TABLE IF EXISTS phx_holt_days;
CREATE TEMPORARY TABLE phx_holt_days (
    day_index INT PRIMARY KEY,
    trend_increment INT NOT NULL,
    cumulative_week_increment INT NOT NULL
) ENGINE=InnoDB;
INSERT INTO phx_holt_days VALUES
    (0,0,0), (1,0,0), (2,0,0), (3,1,1), (4,1,2), (5,1,3), (6,2,5),
    (7,1,1), (8,2,3), (9,2,5), (10,2,7), (11,3,10), (12,3,13), (13,3,16);

DROP TEMPORARY TABLE IF EXISTS phx_holt_rows;
CREATE TEMPORARY TABLE phx_holt_rows ENGINE=InnoDB AS
SELECT
    x.inventory_id, x.branch_id, x.product_id, x.opening_stock,
    p.product_name, bp.price, bp.cost_price, p.supplier_id, p.manufacturer,
    d.day_index, FLOOR(d.day_index / 7) AS delivery_week,
    (2 + MOD(x.product_id + x.branch_id, 3)) AS base_quantity,
    (2 + MOD(x.product_id + x.branch_id, 3) + d.trend_increment) AS quantity,
    (7 * (2 + MOD(x.product_id + x.branch_id, 3))
        + IF(d.day_index < 7, 5, 16)) AS delivery_quantity,
    x.opening_stock + (7 * (2 + MOD(x.product_id + x.branch_id, 3))
        + IF(d.day_index < 7, 5, 16))
        - (MOD(d.day_index, 7) + 1) * (2 + MOD(x.product_id + x.branch_id, 3))
        - d.cumulative_week_increment AS new_stock,
    CONCAT('DEMO-HOLT14-', DATE_FORMAT(DATE_ADD(@phx_holt_from, INTERVAL d.day_index DAY), '%Y%m%d'),
        '-B', x.branch_id, '-P', x.product_id) AS invoice_no,
    TIMESTAMP(DATE_ADD(@phx_holt_from, INTERVAL d.day_index DAY), '12:00:00') AS sale_date
FROM phx_holt_pairs x
JOIN branch_products bp ON bp.id=x.inventory_id AND bp.branch_id=x.branch_id AND bp.product_id=x.product_id
JOIN products p ON p.id=x.product_id
CROSS JOIN phx_holt_days d;

SET @phx_holt_transactional = (
    SELECT COUNT(*) = 4 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('branch_products','sales','sale_items','stock_logs') AND ENGINE='InnoDB'
);

START TRANSACTION;
-- Lock the source inventory while checking it and recording its movements.
SELECT bp.id FROM branch_products bp JOIN phx_holt_pairs x ON x.inventory_id=bp.id
ORDER BY bp.id FOR UPDATE;

SET @phx_holt_existing_sales = (SELECT COUNT(*) FROM sales WHERE notes=@phx_holt_note);
SET @phx_holt_existing_items = (
    SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.notes=@phx_holt_note
);
SET @phx_holt_existing_logs = (SELECT COUNT(*) FROM stock_logs WHERE remarks LIKE CONCAT(@phx_holt_marker, ':%'));
SET @phx_holt_existing_deliveries = (SELECT COUNT(*) FROM stock_logs
    WHERE remarks LIKE CONCAT(@phx_holt_marker, ':%') AND action_type='stock_in');
SET @phx_holt_existing_deductions = (SELECT COUNT(*) FROM stock_logs
    WHERE remarks LIKE CONCAT(@phx_holt_marker, ':%') AND action_type='stock_out');
SET @phx_holt_existing_invoices = (SELECT COUNT(*) FROM sales s JOIN phx_holt_rows r ON r.invoice_no=s.invoice_no);
SET @phx_holt_complete = (@phx_holt_existing_sales=266 AND @phx_holt_existing_items=266
    AND @phx_holt_existing_logs=304 AND @phx_holt_existing_invoices=266
    AND @phx_holt_existing_deliveries=38 AND @phx_holt_existing_deductions=266);
SET @phx_holt_source_ok = (
    SELECT COUNT(*)=19 FROM phx_holt_pairs x
    JOIN branch_products bp ON bp.id=x.inventory_id AND bp.branch_id=x.branch_id AND bp.product_id=x.product_id
    JOIN products p ON p.id=x.product_id JOIN branches b ON b.id=x.branch_id
    WHERE bp.stock=x.opening_stock AND bp.stock<=bp.reorder_level
      AND bp.status='active' AND bp.deleted_at IS NULL AND bp.is_deleted=0 AND bp.is_permanently_deleted=0
      AND p.status='active' AND p.deleted_at IS NULL AND p.is_deleted=0 AND p.is_permanently_deleted=0
      AND b.status='active' AND bp.expiration_date >= '2026-10-12'
      AND bp.price > 0 AND bp.cost_price >= 0 AND bp.price >= bp.cost_price
      AND NOT EXISTS (SELECT 1 FROM stock_logs sl WHERE sl.branch_id=x.branch_id AND sl.product_id=x.product_id
          AND sl.created_at >= @phx_holt_from)
      AND NOT EXISTS (SELECT 1 FROM sales s JOIN sale_items si ON si.sale_id=s.id
          WHERE s.branch_id=x.branch_id AND si.product_id=x.product_id AND s.sale_date >= @phx_holt_from)
      AND x.opening_stock=COALESCE((SELECT sl.new_stock FROM stock_logs sl
          WHERE sl.branch_id=x.branch_id AND sl.product_id=x.product_id
          ORDER BY sl.created_at DESC, sl.id DESC LIMIT 1), x.opening_stock)
);
SET @phx_holt_insert = (@phx_holt_transactional AND @phx_holt_source_ok
    AND (SELECT COUNT(*) FROM phx_holt_rows)=266
    AND @phx_holt_existing_sales=0 AND @phx_holt_existing_items=0
    AND @phx_holt_existing_logs=0 AND @phx_holt_existing_invoices=0);

INSERT INTO sales
    (invoice_no, user_id, branch_id, discount_id, total_amount, discount_amount,
     final_total, payment_method, reference_no, amount_paid, change_amount,
     status, notes, sale_date, created_at, updated_at)
SELECT invoice_no, NULL, branch_id, NULL, ROUND(price*quantity,2), 0,
    ROUND(price*quantity,2), 'cash', NULL, ROUND(price*quantity,2), 0,
    'completed', @phx_holt_note, sale_date, sale_date, sale_date
FROM phx_holt_rows WHERE @phx_holt_insert ORDER BY sale_date, branch_id, product_id;
-- Read the stored rows instead of relying on the client's previous statement.
SET @phx_holt_new_sales = (SELECT COUNT(*) FROM sales WHERE notes=@phx_holt_note)
    - @phx_holt_existing_sales;

INSERT INTO sale_items
    (sale_id, product_id, product_name_snapshot, cost_price_at_sale, quantity,
     price, subtotal, discount_applied, profit, created_at)
SELECT s.id, r.product_id, r.product_name, r.cost_price, r.quantity, r.price,
    ROUND(r.price*r.quantity,2), 0, ROUND((r.price-r.cost_price)*r.quantity,2), r.sale_date
FROM phx_holt_rows r JOIN sales s ON s.invoice_no=r.invoice_no AND s.notes=@phx_holt_note
WHERE @phx_holt_insert ORDER BY r.sale_date, r.branch_id, r.product_id;
SET @phx_holt_new_items = (SELECT COUNT(*) FROM sale_items si
    JOIN sales s ON s.id=si.sale_id WHERE s.notes=@phx_holt_note) - @phx_holt_existing_items;

INSERT INTO stock_logs
    (product_id, branch_id, user_id, action_type, quantity, previous_stock,
     new_stock, remarks, created_at, supplier_id, manufacturer)
SELECT product_id, branch_id, NULL, 'stock_in', delivery_quantity, opening_stock,
    opening_stock+delivery_quantity,
    CONCAT(@phx_holt_marker, ': SYNTHETIC delivery W', delivery_week+1, '-B', branch_id, '-P', product_id),
    TIMESTAMP(DATE(sale_date), '09:00:00'), supplier_id, manufacturer
FROM phx_holt_rows WHERE @phx_holt_insert AND day_index IN (0,7)
ORDER BY sale_date, branch_id, product_id;
SET @phx_holt_new_deliveries = (SELECT COUNT(*) FROM stock_logs
    WHERE remarks LIKE CONCAT(@phx_holt_marker, ':%') AND action_type='stock_in') - @phx_holt_existing_deliveries;

INSERT INTO stock_logs
    (product_id, branch_id, user_id, action_type, quantity, previous_stock,
     new_stock, remarks, created_at, supplier_id, manufacturer)
SELECT product_id, branch_id, NULL, 'stock_out', quantity, new_stock+quantity,
    new_stock, CONCAT(@phx_holt_marker, ': SYNTHETIC sold via POS Invoice ', invoice_no),
    sale_date, NULL, manufacturer
FROM phx_holt_rows WHERE @phx_holt_insert ORDER BY sale_date, branch_id, product_id;
SET @phx_holt_new_deductions = (SELECT COUNT(*) FROM stock_logs
    WHERE remarks LIKE CONCAT(@phx_holt_marker, ':%') AND action_type='stock_out') - @phx_holt_existing_deductions;

UPDATE branch_products bp JOIN phx_holt_rows r ON r.inventory_id=bp.id AND r.day_index=13
SET bp.stock=r.new_stock, bp.updated_at=r.sale_date
WHERE @phx_holt_insert;
SET @phx_holt_updated_inventory = (SELECT COUNT(*) FROM branch_products bp
    JOIN phx_holt_rows r ON r.inventory_id=bp.id AND r.day_index=13
    WHERE @phx_holt_insert AND bp.stock=r.new_stock AND bp.updated_at=r.sale_date);

SET @phx_holt_ok = (@phx_holt_transactional AND @phx_holt_complete)
    OR (@phx_holt_insert AND @phx_holt_new_sales=266 AND @phx_holt_new_items=266
        AND @phx_holt_new_deliveries=38 AND @phx_holt_new_deductions=266 AND @phx_holt_updated_inventory=19);
SET @phx_holt_sql = IF(@phx_holt_ok, 'COMMIT', 'ROLLBACK');
PREPARE phx_holt_statement FROM @phx_holt_sql;
EXECUTE phx_holt_statement;
DEALLOCATE PREPARE phx_holt_statement;

DROP TEMPORARY TABLE phx_holt_rows;
DROP TEMPORARY TABLE phx_holt_days;
DROP TEMPORARY TABLE phx_holt_pairs;
SET time_zone = @phx_holt_old_time_zone;
SET character_set_client = @phx_holt_old_character_set_client;
SET character_set_results = @phx_holt_old_character_set_results;
SET collation_connection = @phx_holt_old_collation_connection;

-- Keep this last so phpMyAdmin can display the completion result.
SELECT CASE WHEN @phx_holt_complete AND @phx_holt_ok THEN 'Already complete: no changes'
    WHEN @phx_holt_ok THEN 'Two-week demo import complete'
    ELSE 'ROLLED BACK: source differs, fixture is partial, or an insert failed' END AS result,
    DATABASE() AS selected_database, @phx_holt_from AS forecast_from, @phx_holt_to AS forecast_to,
    'daily' AS forecast_interval, 0.3 AS alpha, 0.2 AS beta,
    IF(@phx_holt_ok,@phx_holt_new_sales,0) AS new_sales,
    IF(@phx_holt_ok,@phx_holt_new_deliveries+@phx_holt_new_deductions,0) AS new_stock_movements;
