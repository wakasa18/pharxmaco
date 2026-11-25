-- Select the existing application database. Apply receipt/invoice upgrade 000011 first.
-- Repeatable. Existing product eligibility remains Not confirmed; past invoice amounts are preserved.
SELECT id, receipt_metadata FROM sales LIMIT 0;
SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;

SET @phx_benefit_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'products' AND COLUMN_NAME = 'senior_pwd_eligible'),
    'SELECT ''products.senior_pwd_eligible already exists'' AS upgrade_status',
    'ALTER TABLE `products` ADD COLUMN `senior_pwd_eligible` TINYINT(1) NULL DEFAULT NULL'
);
PREPARE phx_benefit_statement FROM @phx_benefit_sql;
EXECUTE phx_benefit_statement;
DEALLOCATE PREPARE phx_benefit_statement;

SET @phx_benefit_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'vat_exemption_amount'),
    'SELECT ''sale_items.vat_exemption_amount already exists'' AS upgrade_status',
    'ALTER TABLE `sale_items` ADD COLUMN `vat_exemption_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00'
);
PREPARE phx_benefit_statement FROM @phx_benefit_sql;
EXECUTE phx_benefit_statement;
DEALLOCATE PREPARE phx_benefit_statement;

SET @phx_benefit_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'statutory_discount_kind'),
    'SELECT ''sale_items.statutory_discount_kind already exists'' AS upgrade_status',
    'ALTER TABLE `sale_items` ADD COLUMN `statutory_discount_kind` VARCHAR(20) NULL DEFAULT NULL'
);
PREPARE phx_benefit_statement FROM @phx_benefit_sql;
EXECUTE phx_benefit_statement;
DEALLOCATE PREPARE phx_benefit_statement;

SET @phx_benefit_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (version, class, `group`, namespace, time, batch)
SELECT '2026_10_14_000013',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'AddStatutoryDiscountFields'),
       'default', 'App', UNIX_TIMESTAMP(), @phx_benefit_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE version = '2026_10_14_000013' AND namespace = 'App' AND `group` = 'default')
AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND ((TABLE_NAME = 'products' AND COLUMN_NAME = 'senior_pwd_eligible')
        OR (TABLE_NAME = 'sale_items' AND COLUMN_NAME IN ('vat_exemption_amount', 'statutory_discount_kind')))) = 3;
SET @phx_benefit_sql = NULL, @phx_benefit_batch = NULL;

