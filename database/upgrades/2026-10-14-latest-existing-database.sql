-- Pharxmaco: latest upgrade for the EXISTING application database, October 15, 2026.
-- Prepared against u315645644_pharxmacotest (3).sql.
-- Source SHA-256: 6ab0d7836c2493c6814a3b8175c5f82fd8f15c10982d6647029cf6d0a779b0fe
-- Select the existing application database in phpMyAdmin, then import this entire file.
-- Adds missing updates 000010 through 000014. Safe to repeat; retains existing nonempty product details.
-- Includes the previously requested identifiable generic names/strengths and ALL products VATable.
-- Keeps existing stock, prices, timestamps, users, sales, payments and recorded invoice details.
-- Medicine type and SC/PWD eligibility are preserved when set; new fields start Not specified/Not confirmed.
-- No historical medicine/tax/delivery/beneficiary snapshots are invented.
-- SQL schema changes commit automatically. Stop on any SQL error.
-- This upgrade adds structure and updates matching product details; it does not import backup records.

SELECT id, checkout_token_hash FROM sales LIMIT 0;
SELECT id, return_condition, refund_method, refund_event_id FROM refund_items LIMIT 0;
SELECT id, session_version FROM users LIMIT 0;
SELECT id, email FROM suppliers LIMIT 0;

-- Included upgrade: 2026-10-04-stock-expiry-safeguards.sql
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


-- Included upgrade: 2026-10-14-receipt-invoice-fields.sql
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



-- Included upgrade: 2026-10-14-product-medicine-types.sql
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


-- Included upgrade: 2026-10-14-senior-pwd-discounts.sql
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



-- Included upgrade: 2026-10-15-cashier-workspace.sql
-- Pharxmaco shared branch drawers and cashier workspace, October 15, 2026.
-- Select the EXISTING application database. Apply updates 000010 through 000013 first.
-- Repeatable and additive. Existing sales, stock, prices and users are preserved.
-- Each branch receives one shared drawer; no historical shifts or opening cash are invented.
-- Apply this upgrade before uploading the matching cashier workspace application files.
SELECT id, receipt_metadata FROM sales LIMIT 0;
SELECT id, vat_exemption_amount FROM sale_items LIMIT 0;
SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;

CREATE TABLE IF NOT EXISTS `cashier_drawers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `drawer_name` varchar(60) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(12) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `active_shift_id` bigint unsigned DEFAULT NULL,
  `last_shift_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_id_drawer_name` (`branch_id`,`drawer_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cashier_shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `drawer_id` bigint unsigned DEFAULT NULL,
  `branch_id` int NOT NULL,
  `user_id` int NOT NULL,
  `active_user_id` int DEFAULT NULL,
  `active_drawer_id` bigint unsigned DEFAULT NULL,
  `open_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(12) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `opening_cash` decimal(12,2) NOT NULL DEFAULT '0.00',
  `opening_expected` decimal(12,2) DEFAULT NULL,
  `opening_difference` decimal(12,2) NOT NULL DEFAULT '0.00',
  `opening_note` text COLLATE utf8mb4_general_ci,
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `expected_cash` decimal(12,2) DEFAULT NULL,
  `counted_cash` decimal(12,2) DEFAULT NULL,
  `cash_difference` decimal(12,2) DEFAULT NULL,
  `cash_left` decimal(12,2) NOT NULL DEFAULT '0.00',
  `banked_cash` decimal(12,2) NOT NULL DEFAULT '0.00',
  `closing_note` text COLLATE utf8mb4_general_ci,
  `summary_json` longtext COLLATE utf8mb4_general_ci,
  `handover_to_user_id` int DEFAULT NULL,
  `handover_status` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `handover_accepted_shift_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `open_key` (`open_key`),
  UNIQUE KEY `active_user_id` (`active_user_id`),
  UNIQUE KEY `active_drawer_id` (`active_drawer_id`),
  KEY `user_id_branch_id_opened_at` (`user_id`,`branch_id`,`opened_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cashier_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `shift_id` bigint unsigned DEFAULT NULL,
  `branch_id` int NOT NULL,
  `user_id` int NOT NULL,
  `event_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `payment_method` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reason` text COLLATE utf8mb4_general_ci,
  `snapshot_json` longtext COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_key` (`event_key`),
  KEY `user_id_branch_id_created_at` (`user_id`,`branch_id`,`created_at`),
  KEY `shift_id_id` (`shift_id`,`id`),
  KEY `sale_id` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cashier_held_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hold_key` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cart_json` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `held_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hold_key` (`hold_key`),
  KEY `user_id_branch_id` (`user_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cashier_shift_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shift_id` bigint unsigned DEFAULT NULL,
  `reviewed_by` int NOT NULL,
  `review_note` text COLLATE utf8mb4_general_ci,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shift_id` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cashier_event_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned DEFAULT NULL,
  `reviewed_by` int NOT NULL,
  `review_note` text COLLATE utf8mb4_general_ci,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cashier_shift_actions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shift_id` bigint unsigned DEFAULT NULL,
  `user_id` int NOT NULL,
  `action` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `note` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shift_id_id` (`shift_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

START TRANSACTION;
INSERT INTO cashier_drawers (branch_id, drawer_name, status, created_at)
SELECT b.id, 'Main drawer', 'active', NOW() FROM branches b
WHERE NOT EXISTS (SELECT 1 FROM cashier_drawers d WHERE d.branch_id = b.id);

SET @phx_cashier_batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);
INSERT INTO migrations (version, class, `group`, namespace, time, batch)
SELECT '2026_10_15_000014', CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'AddCashierShifts'),
'default', 'App', UNIX_TIMESTAMP(), @phx_cashier_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE version='2026_10_15_000014' AND namespace='App' AND `group`='default')
AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('cashier_drawers','cashier_shifts','cashier_events','cashier_held_sales','cashier_shift_reviews','cashier_event_reviews','cashier_shift_actions'))=7;
COMMIT;
SET @phx_cashier_batch = NULL;
SELECT 'Cashier workspace upgrade complete' AS upgrade_status;


-- Fill only identifiable product details that remain empty; identity guards match the supplied backup.
START TRANSACTION;
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Bacillus clausii' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 2 AND `product_name` = 'erceflora' AND `sku` <=> '';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Paracetamol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '500 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 6 AND `product_name` = 'Biogesic 500mg' AND `sku` <=> 'BIO500-001';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '10 mg + 2 mg + 500 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 7 AND `product_name` = 'Neozep Forte' AND `sku` <=> 'NEO-FOR-002';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Ibuprofen + Paracetamol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '200 mg + 325 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 8 AND `product_name` = 'Alaxan FR' AND `sku` <=> 'ALX-FR-003';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Paracetamol' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 9 AND `product_name` = 'Tempra Syrup' AND `sku` <=> 'TMP-SYP-004';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Cetirizine' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '10 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 10 AND `product_name` = 'Cetirizine 10mg' AND `sku` <=> 'CET10-005';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Amoxicillin' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '500 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 11 AND `product_name` = 'Amoxicillin 500mg' AND `sku` <=> 'AMX500-006';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Vitamin C' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '500 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 12 AND `product_name` = 'Vitamin C 500mg' AND `sku` <=> 'VITC500-007';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Paracetamol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '500 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 13 AND `product_name` = 'Paracetamol 500mg' AND `sku` <=> 'PAR500-008';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Loperamide' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '2 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 14 AND `product_name` = 'Loperamide 2mg' AND `sku` <=> 'LOP2-009';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Mefenamic acid' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '500 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 15 AND `product_name` = 'Mefenamic Acid 500mg' AND `sku` <=> 'MEF500-010';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Ibuprofen' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '200 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 16 AND `product_name` = 'Ibuprofen 200mg Tablet' AND `sku` <=> 'IBU200-011';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Aspirin' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '80 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 17 AND `product_name` = 'Aspirin 80mg Tablet' AND `sku` <=> 'ASP80-012';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Naproxen sodium' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '275 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 18 AND `product_name` = 'Naproxen Sodium 275mg' AND `sku` <=> 'NAP275-013';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Diclofenac sodium' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '50 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 19 AND `product_name` = 'Diclofenac Sodium 50mg' AND `sku` <=> 'DIC50-014';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Paracetamol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '250 mg / 5 mL' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 20 AND `product_name` = 'Paracetamol Syrup 250mg/5mL' AND `sku` <=> 'PARSYP-015';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Dextromethorphan' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 21 AND `product_name` = 'Dextromethorphan Cough Syrup' AND `sku` <=> 'DEXSYP-016';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Ambroxol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '30 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 22 AND `product_name` = 'Ambroxol 30mg Tablet' AND `sku` <=> 'AMB30-017';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Carbocisteine' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '500 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 23 AND `product_name` = 'Carbocisteine 500mg Capsule' AND `sku` <=> 'CARB500-018';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Phenylephrine + Chlorphenamine' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 24 AND `product_name` = 'Phenylephrine + Chlorphenamine' AND `sku` <=> 'PHENCPM-019';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Salbutamol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '2.5 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 25 AND `product_name` = 'Salbutamol Nebule 2.5mg' AND `sku` <=> 'SALNEB-020';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Omeprazole' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '20 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 26 AND `product_name` = 'Omeprazole 20mg Capsule' AND `sku` <=> 'OME20-021';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Famotidine' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '20 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 27 AND `product_name` = 'Famotidine 20mg Tablet' AND `sku` <=> 'FAM20-022';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Oral rehydration salts' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 28 AND `product_name` = 'Oral Rehydration Salts' AND `sku` <=> 'ORS-023';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Bisacodyl' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '5 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 30 AND `product_name` = 'Bisacodyl 5mg Tablet' AND `sku` <=> 'BIS5-025';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Loratadine' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '10 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 31 AND `product_name` = 'Loratadine 10mg Tablet' AND `sku` <=> 'LOR10-026';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Fexofenadine' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '120 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 32 AND `product_name` = 'Fexofenadine 120mg Tablet' AND `sku` <=> 'FEX120-027';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Diphenhydramine' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '25 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 33 AND `product_name` = 'Diphenhydramine 25mg Capsule' AND `sku` <=> 'DPH25-028';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Montelukast' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '10 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 34 AND `product_name` = 'Montelukast 10mg Tablet' AND `sku` <=> 'MON10-029';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Hydrocortisone' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '1%' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 35 AND `product_name` = 'Hydrocortisone Cream 1%' AND `sku` <=> 'HYD1-030';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Multivitamins' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 36 AND `product_name` = 'Adult Multivitamins Capsule' AND `sku` <=> 'MULTI-031';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Vitamin B complex' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 37 AND `product_name` = 'Vitamin B Complex Tablet' AND `sku` <=> 'VITB-032';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Zinc' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '20 mg' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 38 AND `product_name` = 'Zinc 20mg Tablet' AND `sku` <=> 'ZINC20-033';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Calcium + Vitamin D' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 39 AND `product_name` = 'Calcium + Vitamin D Tablet' AND `sku` <=> 'CALD-034';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Iron + Folic acid' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 40 AND `product_name` = 'Iron + Folic Acid Tablet' AND `sku` <=> 'IRONFA-035';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Isopropyl alcohol' ELSE `generic_name` END, `strength` = CASE WHEN `strength` IS NULL OR TRIM(`strength`) = '' THEN '70%' ELSE `strength` END, `updated_at` = `updated_at` WHERE `id` = 41 AND `product_name` = 'Isopropyl Alcohol 70% 500mL' AND `sku` <=> 'ALC500-036';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Povidone iodine' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 42 AND `product_name` = 'Povidone Iodine 60mL' AND `sku` <=> 'POV60-037';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Multivitamins' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 51 AND `product_name` = 'Pediatric Multivitamin Syrup' AND `sku` <=> 'PEDVIT-046';
UPDATE `products` SET `generic_name` = CASE WHEN `generic_name` IS NULL OR TRIM(`generic_name`) = '' THEN 'Saline' ELSE `generic_name` END, `updated_at` = `updated_at` WHERE `id` = 54 AND `product_name` = 'Saline Nasal Drops 15mL' AND `sku` <=> 'SAL15-049';

-- Every current product VATable, as previously requested. Past sale tax snapshots remain unchanged.
UPDATE `products` SET `tax_treatment` = 'taxable', `updated_at` = `updated_at` WHERE NOT (`tax_treatment` <=> 'taxable');
COMMIT;

SELECT 'Latest schema and product updates completed' AS upgrade_status;
SELECT COUNT(*) AS current_products, SUM(tax_treatment = 'taxable') AS vatable_products FROM products;
SELECT version, class, `group`, namespace FROM migrations WHERE version IN ('2026_10_04_000010','2026_10_14_000011','2026_10_14_000012','2026_10_14_000013','2026_10_15_000014') AND namespace = 'App' AND `group` = 'default' ORDER BY version;
