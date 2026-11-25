-- Pharxmaco: sales invoice fields and historical medicine snapshots.
-- Select the existing application database in phpMyAdmin or SQLyog.
-- Import BEFORE the matching PHP files. Run all statements; stop on errors.
-- Additive/repeatable. No old sale is assigned invented tax or medicine history.
-- No sale totals, invoice references, payments or stock quantities are changed.
SELECT id FROM products LIMIT 0;
SELECT id FROM sale_items LIMIT 0;
SELECT id FROM sales LIMIT 0;
SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;

SET @phx_invoice_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'products' AND COLUMN_NAME = 'generic_name'),
    'SELECT ''products.generic_name already exists'' AS upgrade_status',
    'ALTER TABLE `products` ADD COLUMN `generic_name` VARCHAR(150) NULL DEFAULT NULL'
);
PREPARE phx_invoice_statement FROM @phx_invoice_sql;
EXECUTE phx_invoice_statement;
DEALLOCATE PREPARE phx_invoice_statement;

SET @phx_invoice_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'products' AND COLUMN_NAME = 'strength'),
    'SELECT ''products.strength already exists'' AS upgrade_status',
    'ALTER TABLE `products` ADD COLUMN `strength` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE phx_invoice_statement FROM @phx_invoice_sql;
EXECUTE phx_invoice_statement;
DEALLOCATE PREPARE phx_invoice_statement;

SET @phx_invoice_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'products' AND COLUMN_NAME = 'tax_treatment'),
    'SELECT ''products.tax_treatment already exists'' AS upgrade_status',
    'ALTER TABLE `products` ADD COLUMN `tax_treatment` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE phx_invoice_statement FROM @phx_invoice_sql;
EXECUTE phx_invoice_statement;
DEALLOCATE PREPARE phx_invoice_statement;

SET @phx_invoice_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'generic_name_snapshot'),
    'SELECT ''sale_items.generic_name_snapshot already exists'' AS upgrade_status',
    'ALTER TABLE `sale_items` ADD COLUMN `generic_name_snapshot` VARCHAR(150) NULL DEFAULT NULL'
);
PREPARE phx_invoice_statement FROM @phx_invoice_sql;
EXECUTE phx_invoice_statement;
DEALLOCATE PREPARE phx_invoice_statement;

SET @phx_invoice_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'strength_snapshot'),
    'SELECT ''sale_items.strength_snapshot already exists'' AS upgrade_status',
    'ALTER TABLE `sale_items` ADD COLUMN `strength_snapshot` VARCHAR(80) NULL DEFAULT NULL'
);
PREPARE phx_invoice_statement FROM @phx_invoice_sql;
EXECUTE phx_invoice_statement;
DEALLOCATE PREPARE phx_invoice_statement;

SET @phx_invoice_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'tax_treatment_snapshot'),
    'SELECT ''sale_items.tax_treatment_snapshot already exists'' AS upgrade_status',
    'ALTER TABLE `sale_items` ADD COLUMN `tax_treatment_snapshot` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE phx_invoice_statement FROM @phx_invoice_sql;
EXECUTE phx_invoice_statement;
DEALLOCATE PREPARE phx_invoice_statement;

SET @phx_invoice_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'receipt_metadata'),
    'SELECT ''sales.receipt_metadata already exists'' AS upgrade_status',
    'ALTER TABLE `sales` ADD COLUMN `receipt_metadata` TEXT NULL'
);
PREPARE phx_invoice_statement FROM @phx_invoice_sql;
EXECUTE phx_invoice_statement;
DEALLOCATE PREPARE phx_invoice_statement;

CREATE TABLE IF NOT EXISTS `receipt_invoice_sequences` (
    `series_key` VARCHAR(64) NOT NULL,
    `next_number` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`series_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @phx_invoice_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (version, class, `group`, namespace, time, batch)
SELECT '2026_10_14_000011',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'AddReceiptInvoiceFields'),
       'default', 'App', UNIX_TIMESTAMP(), @phx_invoice_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations
    WHERE version = '2026_10_14_000011' AND namespace = 'App' AND `group` = 'default')
AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND (
    (TABLE_NAME = 'products' AND COLUMN_NAME IN ('generic_name','strength','tax_treatment')) OR
    (TABLE_NAME = 'sale_items' AND COLUMN_NAME IN ('generic_name_snapshot','strength_snapshot','tax_treatment_snapshot')) OR
    (TABLE_NAME = 'sales' AND COLUMN_NAME = 'receipt_metadata')
)) = 7
AND EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'receipt_invoice_sequences');

SHOW COLUMNS FROM products WHERE Field IN ('generic_name','strength','tax_treatment');
SHOW COLUMNS FROM sale_items WHERE Field IN ('generic_name_snapshot','strength_snapshot','tax_treatment_snapshot');
SHOW COLUMNS FROM sales WHERE Field = 'receipt_metadata';
SHOW COLUMNS FROM receipt_invoice_sequences;
SELECT version, class, `group`, namespace FROM migrations
WHERE version = '2026_10_14_000011' AND namespace = 'App' AND `group` = 'default';
SET @phx_invoice_sql = NULL, @phx_invoice_batch = NULL;

