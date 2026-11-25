-- Pharxmaco: expiry safeguards for an EXISTING installation.
-- Requires the application schema through 2026_10_03_000009.
-- Select the application database in phpMyAdmin or SQLyog; run ALL statements.
-- Back up first and pause sales/inventory changes. Stop on any SQL error.
-- Import BEFORE uploading the matching PHP files. Re-import is supported.
-- Adds nullable delivery/expiry snapshots. Existing sales, stock and dates are preserved.
-- Historical delivery identities and expiry values are deliberately NOT invented.

SELECT `id`, `expiration_date` FROM `branch_products` LIMIT 0;
SELECT `id`, `sale_id`, `product_id` FROM `sale_items` LIMIT 0;
SELECT `version`, `class`, `group`, `namespace`, `time`, `batch` FROM `migrations` LIMIT 0;

SET @phx_expiry_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branch_products' AND COLUMN_NAME = 'stock_delivery_id'),
    'SELECT ''branch_products.stock_delivery_id already exists'' AS upgrade_status',
    'ALTER TABLE `branch_products` ADD COLUMN `stock_delivery_id` VARCHAR(32) NULL DEFAULT NULL'
);
PREPARE phx_expiry_statement FROM @phx_expiry_sql;
EXECUTE phx_expiry_statement;
DEALLOCATE PREPARE phx_expiry_statement;

SET @phx_expiry_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'stock_delivery_id'),
    'SELECT ''sale_items.stock_delivery_id already exists'' AS upgrade_status',
    'ALTER TABLE `sale_items` ADD COLUMN `stock_delivery_id` VARCHAR(32) NULL DEFAULT NULL'
);
PREPARE phx_expiry_statement FROM @phx_expiry_sql;
EXECUTE phx_expiry_statement;
DEALLOCATE PREPARE phx_expiry_statement;

SET @phx_expiry_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'expiration_date_at_sale'),
    'SELECT ''sale_items.expiration_date_at_sale already exists'' AS upgrade_status',
    'ALTER TABLE `sale_items` ADD COLUMN `expiration_date_at_sale` DATE NULL DEFAULT NULL'
);
PREPARE phx_expiry_statement FROM @phx_expiry_sql;
EXECUTE phx_expiry_statement;
DEALLOCATE PREPARE phx_expiry_statement;

SET @phx_expiry_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT '2026_10_04_000010',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'AddStockExpiryProvenance'),
       'default', 'App', UNIX_TIMESTAMP(), @phx_expiry_batch
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `version` = '2026_10_04_000010' AND `namespace` = 'App' AND `group` = 'default')
AND (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND ((TABLE_NAME = 'branch_products' AND COLUMN_NAME = 'stock_delivery_id')
        OR (TABLE_NAME = 'sale_items' AND COLUMN_NAME IN ('stock_delivery_id', 'expiration_date_at_sale')))) = 3;

SHOW COLUMNS FROM `branch_products` WHERE `Field` = 'stock_delivery_id';
SHOW COLUMNS FROM `sale_items` WHERE `Field` IN ('stock_delivery_id', 'expiration_date_at_sale');
SELECT `version`, `class`, `group`, `namespace`, `batch` FROM `migrations`
WHERE `version` = '2026_10_04_000010' AND `namespace` = 'App' AND `group` = 'default';
SET @phx_expiry_sql = NULL, @phx_expiry_batch = NULL;
