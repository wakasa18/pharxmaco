-- Select the existing application database before importing.
-- Adds an explicit Branded/Generic classification. Existing products remain Not specified.
-- Repeatable; no products, prices, stock, tax classifications or saved sales are changed.
SELECT id FROM products LIMIT 0;
SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;

SET @phx_medicine_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'products' AND COLUMN_NAME = 'medicine_type'),
    'SELECT ''products.medicine_type already exists'' AS upgrade_status',
    'ALTER TABLE `products` ADD COLUMN `medicine_type` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE phx_medicine_statement FROM @phx_medicine_sql;
EXECUTE phx_medicine_statement;
DEALLOCATE PREPARE phx_medicine_statement;

SET @phx_medicine_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (version, class, `group`, namespace, time, batch)
SELECT '2026_10_14_000012',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'AddProductMedicineType'),
       'default', 'App', UNIX_TIMESTAMP(), @phx_medicine_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations
    WHERE version = '2026_10_14_000012' AND namespace = 'App' AND `group` = 'default')
AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products' AND COLUMN_NAME = 'medicine_type');

SHOW COLUMNS FROM products WHERE Field = 'medicine_type';
SET @phx_medicine_sql = NULL, @phx_medicine_batch = NULL;
