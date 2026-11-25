-- Pharxmaco: latest upgrade for the EXISTING database, October 15, 2026.
-- Prepared against u315645644_pharxmacotest (4).sql.
-- Source SHA-256: 3a3e54b64fdf2bea5f4f506733da7ce50de4b3aa19fc2047f04974a5248209b5
-- Select the existing application database in phpMyAdmin, then import this ENTIRE file.
-- The supplied export already includes updates 000010 through 000013.
-- Adds cashier update 000014 and the three previously requested demo accounts if absent.
-- Includes the requested product-name, Branded/Generic/Non-medicine and benefit-eligibility data update.
-- Adds twelve missing branded medicines with requested DEMO stock (50 units each), prices and expiry.
-- Preserves existing users/passwords, stock, prices, tax classifications, sales and invoice details.
-- No backup records are reinserted. No existing table or record is deleted or replaced.
-- New branch drawers start without shifts, events, counts or fabricated sales history.
-- Demo credentials are in the companion .md guide. Existing matching usernames are skipped.
-- Use the current cashier workspace application files for Start Shift, My Day and Review buttons.
-- Schema changes commit automatically; stop the import on any SQL error.

SELECT id, generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible FROM products LIMIT 0;
SELECT id, stock_delivery_id FROM branch_products LIMIT 0;
SELECT id, receipt_metadata, checkout_token_hash FROM sales LIMIT 0;
SELECT id, generic_name_snapshot, strength_snapshot, tax_treatment_snapshot, stock_delivery_id,
       expiration_date_at_sale, vat_exemption_amount, statutory_discount_kind FROM sale_items LIMIT 0;
SELECT id, refund_method, refund_event_id, return_condition FROM refund_items LIMIT 0;
SELECT id, session_version FROM users LIMIT 0;

-- Included update: 2026-10-15-cashier-workspace.sql
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


-- Included update: 2026-10-15-demo-users.sql
-- Pharxmaco OPTIONAL demo login accounts, October 15, 2026.
-- Select the existing database. This is a data seed, not a schema migration.
-- Creates one administrator and two cashiers; passwords are listed in the companion .md file.
-- Requires the existing users.session_version field.
-- Reimport skips existing usernames, including inactive or edited demo accounts.
-- Does not reset passwords, change permissions, or create sales, shifts, stock or branches.
-- Demo accounts use normal application permissions and the selected database.
SELECT id, full_name, username, password, role, branch_id, status, session_version, created_at, updated_at FROM users LIMIT 0;
SELECT id, branch_name, status FROM branches LIMIT 0;

START TRANSACTION;
-- Prefer the active Main Branch; otherwise use the first active branch.
-- To choose a specific branch, replace this assignment with its active branch ID.
SET @phx_demo_branch_id = (SELECT id FROM branches WHERE status = 'active'
    ORDER BY CASE WHEN branch_name = 'Main Branch' THEN 0 ELSE 1 END, id LIMIT 1);
SET @phx_demo_created_at = NOW();

INSERT INTO users (full_name, username, password, role, branch_id, status, session_version, created_at, updated_at)
SELECT 'Patricia Garcia', 'demo_admin', '$2y$12$yAgS.GHxsDFUo/OPar4OPeKzl6BVFyE8GpDshdHc16pYe0gbZhyS6', 'admin', NULL, 'active', REPLACE(UUID(), '-', ''), @phx_demo_created_at, @phx_demo_created_at
WHERE EXISTS (SELECT 1 FROM branches WHERE id = @phx_demo_branch_id AND status = 'active')
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'demo_admin');
SET @phx_demo_added_0 = ROW_COUNT();

INSERT INTO users (full_name, username, password, role, branch_id, status, session_version, created_at, updated_at)
SELECT 'Daniel Cruz', 'demo_cashier1', '$2y$12$yAgS.GHxsDFUo/OPar4OPeKzl6BVFyE8GpDshdHc16pYe0gbZhyS6', 'cashier', @phx_demo_branch_id, 'active', REPLACE(UUID(), '-', ''), @phx_demo_created_at, @phx_demo_created_at
WHERE EXISTS (SELECT 1 FROM branches WHERE id = @phx_demo_branch_id AND status = 'active')
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'demo_cashier1');
SET @phx_demo_added_1 = ROW_COUNT();

INSERT INTO users (full_name, username, password, role, branch_id, status, session_version, created_at, updated_at)
SELECT 'Sofia Ramos', 'demo_cashier2', '$2y$12$yAgS.GHxsDFUo/OPar4OPeKzl6BVFyE8GpDshdHc16pYe0gbZhyS6', 'cashier', @phx_demo_branch_id, 'active', REPLACE(UUID(), '-', ''), @phx_demo_created_at, @phx_demo_created_at
WHERE EXISTS (SELECT 1 FROM branches WHERE id = @phx_demo_branch_id AND status = 'active')
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'demo_cashier2');
SET @phx_demo_added_2 = ROW_COUNT();

COMMIT;
SELECT CASE WHEN @phx_demo_branch_id IS NULL THEN 'No active branch found; no demo accounts created' ELSE 'Demo account import completed; existing usernames preserved' END AS demo_status,
    @phx_demo_branch_id AS cashier_branch_id,
    (@phx_demo_added_0 + @phx_demo_added_1 + @phx_demo_added_2) AS accounts_created;
SELECT u.id, u.full_name, u.username, u.role, u.status, b.branch_name FROM users u
LEFT JOIN branches b ON b.id = u.branch_id
WHERE u.username IN ('demo_admin', 'demo_cashier1', 'demo_cashier2') ORDER BY u.username;
SET @phx_demo_branch_id = NULL, @phx_demo_created_at = NULL,
    @phx_demo_added_0 = NULL, @phx_demo_added_1 = NULL, @phx_demo_added_2 = NULL;


-- Included update: 2026-10-15-product-catalog.sql
-- Pharxmaco product names and classifications, October 15, 2026.
-- Prepared for the catalog in u315645644_pharxmacotest (4).sql and the matching local catalog.
-- Select the EXISTING database in phpMyAdmin. Requires invoice/medicine/benefit updates 000011-000013.
-- Upload matching application files to display the new Non-medicine choice.
-- Product names, generic details, medicine types and benefit flags only; no stock, prices or tax changes.
-- No historical invoice or sale item is changed. Existing product IDs and SKUs are retained.
-- Full old-value guards skip different or subsequently edited products; repeat imports preserve later edits.
-- Medicines with clear identities become eligible. Supplements and uncertain devices remain Not confirmed.
-- The existing adhesive-bandage eligibility is retained, not newly certified by this import.
-- Erceflora variant/registration is unrecorded; current food supplements are not treated as medicines.
-- Missing concentrations and unidentified food/test identities are not invented. Review the companion guide.
SELECT id, product_name, generic_name, strength, medicine_type, senior_pwd_eligible FROM products LIMIT 0;
START TRANSACTION;
UPDATE `products` SET `product_name` = 'Erceflora (variant unrecorded)', `generic_name` = 'Bacillus clausii', `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 2 AND BINARY `sku` <=> BINARY '' AND ((BINARY `product_name` <=> BINARY 'erceflora' AND BINARY `generic_name` <=> BINARY 'Bacillus clausii' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Erceflora (variant unrecorded)', `generic_name` = 'Bacillus clausii', `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 3 AND BINARY `sku` <=> BINARY '53463431233' AND ((BINARY `product_name` <=> BINARY 'ercefloraaaa' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Erceflora (variant unrecorded)', `generic_name` = 'Bacillus clausii', `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 4 AND BINARY `sku` <=> BINARY '534634312333' AND ((BINARY `product_name` <=> BINARY 'ercefloraaaaaaaa' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Unidentified food item (verify label)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 5 AND BINARY `sku` <=> BINARY '534634312333123' AND ((BINARY `product_name` <=> BINARY 'randommm' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Biogesic 500 mg tablet', `generic_name` = 'Paracetamol', `strength` = '500 mg', `medicine_type` = 'branded', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 6 AND BINARY `sku` <=> BINARY 'BIO500-001' AND ((BINARY `product_name` <=> BINARY 'Biogesic 500mg' AND BINARY `generic_name` <=> BINARY 'Paracetamol' AND BINARY `strength` <=> BINARY '500 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Neozep Forte tablet', `generic_name` = 'Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol', `strength` = '10 mg + 2 mg + 500 mg', `medicine_type` = 'branded', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 7 AND BINARY `sku` <=> BINARY 'NEO-FOR-002' AND ((BINARY `product_name` <=> BINARY 'Neozep Forte' AND BINARY `generic_name` <=> BINARY 'Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol' AND BINARY `strength` <=> BINARY '10 mg + 2 mg + 500 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Alaxan FR 200 mg / 325 mg capsule', `generic_name` = 'Ibuprofen + Paracetamol', `strength` = '200 mg + 325 mg', `medicine_type` = 'branded', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 8 AND BINARY `sku` <=> BINARY 'ALX-FR-003' AND ((BINARY `product_name` <=> BINARY 'Alaxan FR' AND BINARY `generic_name` <=> BINARY 'Ibuprofen + Paracetamol' AND BINARY `strength` <=> BINARY '200 mg + 325 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Tempra syrup', `generic_name` = 'Paracetamol', `strength` = NULL, `medicine_type` = 'branded', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 9 AND BINARY `sku` <=> BINARY 'TMP-SYP-004' AND ((BINARY `product_name` <=> BINARY 'Tempra Syrup' AND BINARY `generic_name` <=> BINARY 'Paracetamol' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Cetirizine 10 mg', `generic_name` = 'Cetirizine', `strength` = '10 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 10 AND BINARY `sku` <=> BINARY 'CET10-005' AND ((BINARY `product_name` <=> BINARY 'Cetirizine 10mg' AND BINARY `generic_name` <=> BINARY 'Cetirizine' AND BINARY `strength` <=> BINARY '10 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Amoxicillin 500 mg', `generic_name` = 'Amoxicillin', `strength` = '500 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 11 AND BINARY `sku` <=> BINARY 'AMX500-006' AND ((BINARY `product_name` <=> BINARY 'Amoxicillin 500mg' AND BINARY `generic_name` <=> BINARY 'Amoxicillin' AND BINARY `strength` <=> BINARY '500 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Vitamin C 500 mg', `generic_name` = 'Vitamin C', `strength` = '500 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 12 AND BINARY `sku` <=> BINARY 'VITC500-007' AND ((BINARY `product_name` <=> BINARY 'Vitamin C 500mg' AND BINARY `generic_name` <=> BINARY 'Vitamin C' AND BINARY `strength` <=> BINARY '500 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Paracetamol 500 mg', `generic_name` = 'Paracetamol', `strength` = '500 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 13 AND BINARY `sku` <=> BINARY 'PAR500-008' AND ((BINARY `product_name` <=> BINARY 'Paracetamol 500mg' AND BINARY `generic_name` <=> BINARY 'Paracetamol' AND BINARY `strength` <=> BINARY '500 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Loperamide 2 mg', `generic_name` = 'Loperamide', `strength` = '2 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 14 AND BINARY `sku` <=> BINARY 'LOP2-009' AND ((BINARY `product_name` <=> BINARY 'Loperamide 2mg' AND BINARY `generic_name` <=> BINARY 'Loperamide' AND BINARY `strength` <=> BINARY '2 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Mefenamic acid 500 mg', `generic_name` = 'Mefenamic acid', `strength` = '500 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 15 AND BINARY `sku` <=> BINARY 'MEF500-010' AND ((BINARY `product_name` <=> BINARY 'Mefenamic Acid 500mg' AND BINARY `generic_name` <=> BINARY 'Mefenamic acid' AND BINARY `strength` <=> BINARY '500 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Ibuprofen 200 mg tablet', `generic_name` = 'Ibuprofen', `strength` = '200 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 16 AND BINARY `sku` <=> BINARY 'IBU200-011' AND ((BINARY `product_name` <=> BINARY 'Ibuprofen 200mg Tablet' AND BINARY `generic_name` <=> BINARY 'Ibuprofen' AND BINARY `strength` <=> BINARY '200 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Aspirin 80 mg tablet', `generic_name` = 'Aspirin', `strength` = '80 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 17 AND BINARY `sku` <=> BINARY 'ASP80-012' AND ((BINARY `product_name` <=> BINARY 'Aspirin 80mg Tablet' AND BINARY `generic_name` <=> BINARY 'Aspirin' AND BINARY `strength` <=> BINARY '80 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Naproxen sodium 275 mg', `generic_name` = 'Naproxen sodium', `strength` = '275 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 18 AND BINARY `sku` <=> BINARY 'NAP275-013' AND ((BINARY `product_name` <=> BINARY 'Naproxen Sodium 275mg' AND BINARY `generic_name` <=> BINARY 'Naproxen sodium' AND BINARY `strength` <=> BINARY '275 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Diclofenac sodium 50 mg', `generic_name` = 'Diclofenac sodium', `strength` = '50 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 19 AND BINARY `sku` <=> BINARY 'DIC50-014' AND ((BINARY `product_name` <=> BINARY 'Diclofenac Sodium 50mg' AND BINARY `generic_name` <=> BINARY 'Diclofenac sodium' AND BINARY `strength` <=> BINARY '50 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Paracetamol 250 mg / 5 mL syrup', `generic_name` = 'Paracetamol', `strength` = '250 mg / 5 mL', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 20 AND BINARY `sku` <=> BINARY 'PARSYP-015' AND ((BINARY `product_name` <=> BINARY 'Paracetamol Syrup 250mg/5mL' AND BINARY `generic_name` <=> BINARY 'Paracetamol' AND BINARY `strength` <=> BINARY '250 mg / 5 mL' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Dextromethorphan cough syrup', `generic_name` = 'Dextromethorphan', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 21 AND BINARY `sku` <=> BINARY 'DEXSYP-016' AND ((BINARY `product_name` <=> BINARY 'Dextromethorphan Cough Syrup' AND BINARY `generic_name` <=> BINARY 'Dextromethorphan' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Ambroxol 30 mg tablet', `generic_name` = 'Ambroxol', `strength` = '30 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 22 AND BINARY `sku` <=> BINARY 'AMB30-017' AND ((BINARY `product_name` <=> BINARY 'Ambroxol 30mg Tablet' AND BINARY `generic_name` <=> BINARY 'Ambroxol' AND BINARY `strength` <=> BINARY '30 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Carbocisteine 500 mg capsule', `generic_name` = 'Carbocisteine', `strength` = '500 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 23 AND BINARY `sku` <=> BINARY 'CARB500-018' AND ((BINARY `product_name` <=> BINARY 'Carbocisteine 500mg Capsule' AND BINARY `generic_name` <=> BINARY 'Carbocisteine' AND BINARY `strength` <=> BINARY '500 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Phenylephrine + chlorphenamine', `generic_name` = 'Phenylephrine + Chlorphenamine', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 24 AND BINARY `sku` <=> BINARY 'PHENCPM-019' AND ((BINARY `product_name` <=> BINARY 'Phenylephrine + Chlorphenamine' AND BINARY `generic_name` <=> BINARY 'Phenylephrine + Chlorphenamine' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Salbutamol 2.5 mg nebule', `generic_name` = 'Salbutamol', `strength` = '2.5 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 25 AND BINARY `sku` <=> BINARY 'SALNEB-020' AND ((BINARY `product_name` <=> BINARY 'Salbutamol Nebule 2.5mg' AND BINARY `generic_name` <=> BINARY 'Salbutamol' AND BINARY `strength` <=> BINARY '2.5 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Omeprazole 20 mg capsule', `generic_name` = 'Omeprazole', `strength` = '20 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 26 AND BINARY `sku` <=> BINARY 'OME20-021' AND ((BINARY `product_name` <=> BINARY 'Omeprazole 20mg Capsule' AND BINARY `generic_name` <=> BINARY 'Omeprazole' AND BINARY `strength` <=> BINARY '20 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Famotidine 20 mg tablet', `generic_name` = 'Famotidine', `strength` = '20 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 27 AND BINARY `sku` <=> BINARY 'FAM20-022' AND ((BINARY `product_name` <=> BINARY 'Famotidine 20mg Tablet' AND BINARY `generic_name` <=> BINARY 'Famotidine' AND BINARY `strength` <=> BINARY '20 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Oral rehydration salts', `generic_name` = 'Oral rehydration salts', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 28 AND BINARY `sku` <=> BINARY 'ORS-023' AND ((BINARY `product_name` <=> BINARY 'Oral Rehydration Salts' AND BINARY `generic_name` <=> BINARY 'Oral rehydration salts' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Antacid suspension (120 mL)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 29 AND BINARY `sku` <=> BINARY 'ANT120-024' AND ((BINARY `product_name` <=> BINARY 'Antacid Suspension 120mL' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Bisacodyl 5 mg tablet', `generic_name` = 'Bisacodyl', `strength` = '5 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 30 AND BINARY `sku` <=> BINARY 'BIS5-025' AND ((BINARY `product_name` <=> BINARY 'Bisacodyl 5mg Tablet' AND BINARY `generic_name` <=> BINARY 'Bisacodyl' AND BINARY `strength` <=> BINARY '5 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Loratadine 10 mg tablet', `generic_name` = 'Loratadine', `strength` = '10 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 31 AND BINARY `sku` <=> BINARY 'LOR10-026' AND ((BINARY `product_name` <=> BINARY 'Loratadine 10mg Tablet' AND BINARY `generic_name` <=> BINARY 'Loratadine' AND BINARY `strength` <=> BINARY '10 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Fexofenadine 120 mg tablet', `generic_name` = 'Fexofenadine', `strength` = '120 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 32 AND BINARY `sku` <=> BINARY 'FEX120-027' AND ((BINARY `product_name` <=> BINARY 'Fexofenadine 120mg Tablet' AND BINARY `generic_name` <=> BINARY 'Fexofenadine' AND BINARY `strength` <=> BINARY '120 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Diphenhydramine 25 mg capsule', `generic_name` = 'Diphenhydramine', `strength` = '25 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 33 AND BINARY `sku` <=> BINARY 'DPH25-028' AND ((BINARY `product_name` <=> BINARY 'Diphenhydramine 25mg Capsule' AND BINARY `generic_name` <=> BINARY 'Diphenhydramine' AND BINARY `strength` <=> BINARY '25 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Montelukast 10 mg tablet', `generic_name` = 'Montelukast', `strength` = '10 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 34 AND BINARY `sku` <=> BINARY 'MON10-029' AND ((BINARY `product_name` <=> BINARY 'Montelukast 10mg Tablet' AND BINARY `generic_name` <=> BINARY 'Montelukast' AND BINARY `strength` <=> BINARY '10 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Hydrocortisone 1% cream', `generic_name` = 'Hydrocortisone', `strength` = '1%', `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 35 AND BINARY `sku` <=> BINARY 'HYD1-030' AND ((BINARY `product_name` <=> BINARY 'Hydrocortisone Cream 1%' AND BINARY `generic_name` <=> BINARY 'Hydrocortisone' AND BINARY `strength` <=> BINARY '1%' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Multivitamins capsule (adult)', `generic_name` = 'Multivitamins', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 36 AND BINARY `sku` <=> BINARY 'MULTI-031' AND ((BINARY `product_name` <=> BINARY 'Adult Multivitamins Capsule' AND BINARY `generic_name` <=> BINARY 'Multivitamins' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Vitamin B complex tablet', `generic_name` = 'Vitamin B complex', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 37 AND BINARY `sku` <=> BINARY 'VITB-032' AND ((BINARY `product_name` <=> BINARY 'Vitamin B Complex Tablet' AND BINARY `generic_name` <=> BINARY 'Vitamin B complex' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Zinc 20 mg tablet', `generic_name` = 'Zinc', `strength` = '20 mg', `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 38 AND BINARY `sku` <=> BINARY 'ZINC20-033' AND ((BINARY `product_name` <=> BINARY 'Zinc 20mg Tablet' AND BINARY `generic_name` <=> BINARY 'Zinc' AND BINARY `strength` <=> BINARY '20 mg' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Calcium + vitamin D tablet', `generic_name` = 'Calcium + Vitamin D', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 39 AND BINARY `sku` <=> BINARY 'CALD-034' AND ((BINARY `product_name` <=> BINARY 'Calcium + Vitamin D Tablet' AND BINARY `generic_name` <=> BINARY 'Calcium + Vitamin D' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Iron + folic acid tablet', `generic_name` = 'Iron + Folic acid', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 40 AND BINARY `sku` <=> BINARY 'IRONFA-035' AND ((BINARY `product_name` <=> BINARY 'Iron + Folic Acid Tablet' AND BINARY `generic_name` <=> BINARY 'Iron + Folic acid' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Isopropyl alcohol 70% (500 mL)', `generic_name` = 'Isopropyl alcohol', `strength` = '70%', `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 41 AND BINARY `sku` <=> BINARY 'ALC500-036' AND ((BINARY `product_name` <=> BINARY 'Isopropyl Alcohol 70% 500mL' AND BINARY `generic_name` <=> BINARY 'Isopropyl alcohol' AND BINARY `strength` <=> BINARY '70%' AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Povidone iodine (60 mL)', `generic_name` = 'Povidone iodine', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 42 AND BINARY `sku` <=> BINARY 'POV60-037' AND ((BINARY `product_name` <=> BINARY 'Povidone Iodine 60mL' AND BINARY `generic_name` <=> BINARY 'Povidone iodine' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Sterile gauze pads (10 pieces)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 43 AND BINARY `sku` <=> BINARY 'GAUZE10-038' AND ((BINARY `product_name` <=> BINARY 'Sterile Gauze Pads 10s' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Adhesive bandages (20 pieces)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 44 AND BINARY `sku` <=> BINARY 'BAND20-039' AND ((BINARY `product_name` <=> BINARY 'Adhesive Bandage 20s' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY 'branded' AND `senior_pwd_eligible` <=> 1) OR (BINARY `product_name` <=> BINARY 'Adhesive Bandage 20s' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY 'generic' AND `senior_pwd_eligible` <=> 1));
UPDATE `products` SET `product_name` = 'Digital thermometer', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 45 AND BINARY `sku` <=> BINARY 'THERMO-040' AND ((BINARY `product_name` <=> BINARY 'Digital Thermometer' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Hand sanitizer (100 mL)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 46 AND BINARY `sku` <=> BINARY 'SAN100-041' AND ((BINARY `product_name` <=> BINARY 'Hand Sanitizer 100mL' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Disposable face masks (50 pieces)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 47 AND BINARY `sku` <=> BINARY 'MASK50-042' AND ((BINARY `product_name` <=> BINARY 'Disposable Face Mask 50s' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Cotton balls (100 pieces)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 48 AND BINARY `sku` <=> BINARY 'COT100-043' AND ((BINARY `product_name` <=> BINARY 'Cotton Balls 100s' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Baby powder (100 g)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 49 AND BINARY `sku` <=> BINARY 'BP100-044' AND ((BINARY `product_name` <=> BINARY 'Baby Powder 100g' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Feminine wash (150 mL)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 50 AND BINARY `sku` <=> BINARY 'FW150-045' AND ((BINARY `product_name` <=> BINARY 'Feminine Wash 150mL' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Multivitamins syrup (pediatric)', `generic_name` = 'Multivitamins', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 51 AND BINARY `sku` <=> BINARY 'PEDVIT-046' AND ((BINARY `product_name` <=> BINARY 'Pediatric Multivitamin Syrup' AND BINARY `generic_name` <=> BINARY 'Multivitamins' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Diaper rash cream (50 g)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = NULL, `updated_at` = `updated_at` WHERE `id` = 52 AND BINARY `sku` <=> BINARY 'RASH50-047' AND ((BINARY `product_name` <=> BINARY 'Diaper Rash Cream 50g' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Baby wipes (80 pieces)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 53 AND BINARY `sku` <=> BINARY 'WIPES80-048' AND ((BINARY `product_name` <=> BINARY 'Baby Wipes 80s' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Saline nasal drops (15 mL)', `generic_name` = 'Saline', `strength` = NULL, `medicine_type` = 'generic', `senior_pwd_eligible` = 1, `updated_at` = `updated_at` WHERE `id` = 54 AND BINARY `sku` <=> BINARY 'SAL15-049' AND ((BINARY `product_name` <=> BINARY 'Saline Nasal Drops 15mL' AND BINARY `generic_name` <=> BINARY 'Saline' AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Mosquito repellent lotion (100 mL)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 55 AND BINARY `sku` <=> BINARY 'MOS100-050' AND ((BINARY `product_name` <=> BINARY 'Mosquito Repellent Lotion 100mL' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
UPDATE `products` SET `product_name` = 'Unidentified test item (verify label)', `generic_name` = NULL, `strength` = NULL, `medicine_type` = 'non_medicine', `senior_pwd_eligible` = 0, `updated_at` = `updated_at` WHERE `id` = 56 AND BINARY `sku` <=> BINARY NULL AND ((BINARY `product_name` <=> BINARY 'TESTINGGG' AND BINARY `generic_name` <=> BINARY NULL AND BINARY `strength` <=> BINARY NULL AND BINARY `medicine_type` <=> BINARY NULL AND `senior_pwd_eligible` <=> NULL));
COMMIT;
SELECT medicine_type, COUNT(*) AS products FROM products GROUP BY medicine_type;
SELECT senior_pwd_eligible, COUNT(*) AS products FROM products GROUP BY senior_pwd_eligible;
SELECT 'Product catalog update complete; review unmatched or unconfirmed products in Products' AS upgrade_status;


-- Included update: 2026-10-15-branded-products.sql
-- Pharxmaco: twelve additional branded medicine catalog entries, October 15, 2026.
-- Select the EXISTING database in phpMyAdmin. Requires stock/invoice/medicine/benefit updates 000010-000013.
-- Adds only missing branded product records and DEMO inventory in one active branch.
-- Requested demo data: 50 units per product; sample unit prices/costs; expiry one year after import.
-- Demo prices/expiry are examples, not market quotations or real physical batch details.
-- All new medicines are Branded and VATable, with Senior Citizen/PWD eligibility enabled.
-- Verify required prescriptions, beneficiary documents and exclusive use at checkout.
-- No existing products, stock, prices, classifications, users, sales or invoice snapshots are updated.
-- Reimport skips existing SKUs or matching product names, including inactive or deleted products.
-- Each branch row is created only for a product inserted by this import; it never restocks existing rows.
-- Manufacturer/supplier are left unset for verification against actual stock.
SELECT id, generic_name, strength, medicine_type, senior_pwd_eligible, tax_treatment FROM products LIMIT 0;
SELECT id, stock_delivery_id FROM branch_products LIMIT 0;
SELECT id, action_type, quantity, previous_stock, new_stock, remarks FROM stock_logs LIMIT 0;
SELECT id, is_deleted, is_permanently_deleted FROM categories LIMIT 0;
START TRANSACTION;
-- Prefer Main Branch; otherwise use the first active branch. Set a specific active branch ID here if needed.
SET @phx_brand_branch = (SELECT id FROM branches WHERE status = 'active'
    ORDER BY CASE WHEN branch_name = 'Main Branch' THEN 0 ELSE 1 END, id LIMIT 1);
SET @phx_brand_created = NOW();
SET @phx_brand_expiry = DATE_ADD(CURDATE(), INTERVAL 1 YEAR);
SET @phx_brand_added = 0;

-- Bioflu 10 mg / 2 mg / 500 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Bioflu 10 mg / 2 mg / 500 mg tablet', @phx_brand_category, 'DEMO-BRD-BIOFLU', 'tablet', 7, 10, 50, 5, 'active',
    'Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol', '10 mg + 2 mg + 500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-BIOFLU' OR LOWER(TRIM(product_name)) IN ('bioflu 10 mg / 2 mg / 500 mg tablet', 'bioflu', 'bioflu tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 10, 7, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Solmux 500 mg capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Solmux 500 mg capsule', @phx_brand_category, 'DEMO-BRD-SOLMUX500', 'capsule', 8.4, 12, 50, 5, 'active',
    'Carbocisteine', '500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-SOLMUX500' OR LOWER(TRIM(product_name)) IN ('solmux 500 mg capsule', 'solmux', 'solmux capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Diatabs 2 mg capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Digestive Health'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Diatabs 2 mg capsule', @phx_brand_category, 'DEMO-BRD-DIATABS2', 'capsule', 5.6, 8, 50, 5, 'active',
    'Loperamide hydrochloride', '2 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-DIATABS2' OR LOWER(TRIM(product_name)) IN ('diatabs 2 mg capsule', 'diatabs', 'diatabs capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 8, 5.6, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Allerta 10 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Allergy and Respiratory'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Allerta 10 mg tablet', @phx_brand_category, 'DEMO-BRD-ALLERTA10', 'tablet', 8.4, 12, 50, 5, 'active',
    'Loratadine', '10 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-ALLERTA10' OR LOWER(TRIM(product_name)) IN ('allerta 10 mg tablet', 'allerta', 'allerta tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Alnix 10 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Allergy and Respiratory'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Alnix 10 mg tablet', @phx_brand_category, 'DEMO-BRD-ALNIX10', 'tablet', 7, 10, 50, 5, 'active',
    'Cetirizine dihydrochloride', '10 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-ALNIX10' OR LOWER(TRIM(product_name)) IN ('alnix 10 mg tablet', 'alnix', 'alnix tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 10, 7, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Medicol Advance 200 mg softgel capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Medicol Advance 200 mg softgel capsule', @phx_brand_category, 'DEMO-BRD-MEDICOL200', 'capsule', 5.6, 8, 50, 5, 'active',
    'Ibuprofen', '200 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-MEDICOL200' OR LOWER(TRIM(product_name)) IN ('medicol advance 200 mg softgel capsule', 'medicol advance 200', 'medicol advance 200 capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 8, 5.6, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Medicol Advance 400 mg softgel capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Medicol Advance 400 mg softgel capsule', @phx_brand_category, 'DEMO-BRD-MEDICOL400', 'capsule', 8.4, 12, 50, 5, 'active',
    'Ibuprofen', '400 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-MEDICOL400' OR LOWER(TRIM(product_name)) IN ('medicol advance 400 mg softgel capsule', 'medicol advance 400', 'medicol advance 400 capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Dolfenal 500 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Dolfenal 500 mg tablet', @phx_brand_category, 'DEMO-BRD-DOLFENAL500', 'tablet', 17.5, 25, 50, 5, 'active',
    'Mefenamic acid', '500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-DOLFENAL500' OR LOWER(TRIM(product_name)) IN ('dolfenal 500 mg tablet', 'dolfenal 500', 'dolfenal 500 tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 25, 17.5, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Skelan 550 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Pain Relief'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Skelan 550 mg tablet', @phx_brand_category, 'DEMO-BRD-SKELAN550', 'tablet', 14, 20, 50, 5, 'active',
    'Naproxen sodium', '550 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-SKELAN550' OR LOWER(TRIM(product_name)) IN ('skelan 550 mg tablet', 'skelan 550', 'skelan 550 tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 20, 14, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Kremil-S Advance 10 mg / 800 mg / 165 mg chewable tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Digestive Health'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Kremil-S Advance 10 mg / 800 mg / 165 mg chewable tablet', @phx_brand_category, 'DEMO-BRD-KREMILS-ADV', 'tablet', 10.5, 15, 50, 5, 'active',
    'Famotidine + Calcium carbonate + Magnesium hydroxide', '10 mg + 800 mg + 165 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-KREMILS-ADV' OR LOWER(TRIM(product_name)) IN ('kremil-s advance 10 mg / 800 mg / 165 mg chewable tablet', 'kremil-s advance', 'kremil-s advance tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 15, 10.5, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Tuseran Forte 15 mg / 25 mg / 325 mg capsule
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Tuseran Forte 15 mg / 25 mg / 325 mg capsule', @phx_brand_category, 'DEMO-BRD-TUSERAN', 'capsule', 8.4, 12, 50, 5, 'active',
    'Dextromethorphan hydrobromide + Phenylpropanolamine hydrochloride + Paracetamol', '15 mg + 25 mg + 325 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-TUSERAN' OR LOWER(TRIM(product_name)) IN ('tuseran forte 15 mg / 25 mg / 325 mg capsule', 'tuseran forte', 'tuseran forte capsule'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 12, 8.4, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

-- Decolgen Forte 25 mg / 2 mg / 500 mg tablet
SET @phx_brand_category = (SELECT id FROM categories WHERE category_name = 'Cough and Cold'
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT 'Decolgen Forte 25 mg / 2 mg / 500 mg tablet', @phx_brand_category, 'DEMO-BRD-DECOLGEN', 'tablet', 6.3, 9, 50, 5, 'active',
    'Phenylpropanolamine hydrochloride + Chlorphenamine maleate + Paracetamol', '25 mg + 2 mg + 500 mg', 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO-BRD-DECOLGEN' OR LOWER(TRIM(product_name)) IN ('decolgen forte 25 mg / 2 mg / 500 mg tablet', 'decolgen forte', 'decolgen forte tablet'));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, 9, 6.3, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;

COMMIT;
SELECT CASE WHEN @phx_brand_branch IS NULL THEN 'No active branch; no products added'
    ELSE 'Branded DEMO products ready: 50 units each with sample prices and expiry' END AS upgrade_status,
    @phx_brand_added AS products_added, @phx_brand_branch AS branch_id;
SELECT id, product_name, generic_name, strength, medicine_type, senior_pwd_eligible FROM products
WHERE sku IN ('DEMO-BRD-BIOFLU', 'DEMO-BRD-SOLMUX500', 'DEMO-BRD-DIATABS2', 'DEMO-BRD-ALLERTA10', 'DEMO-BRD-ALNIX10', 'DEMO-BRD-MEDICOL200', 'DEMO-BRD-MEDICOL400', 'DEMO-BRD-DOLFENAL500', 'DEMO-BRD-SKELAN550', 'DEMO-BRD-KREMILS-ADV', 'DEMO-BRD-TUSERAN', 'DEMO-BRD-DECOLGEN') ORDER BY product_name;
SET @phx_brand_branch = NULL, @phx_brand_category = NULL, @phx_brand_created = NULL, @phx_brand_expiry = NULL,
    @phx_brand_new_id = NULL, @phx_brand_added = NULL;


SELECT 'Latest database upgrade complete' AS upgrade_status;
SELECT version, class, namespace, `group` FROM migrations
WHERE version = '2026_10_15_000014' AND namespace = 'App' AND `group` = 'default';
SELECT COUNT(*) AS cashier_drawers FROM cashier_drawers;
