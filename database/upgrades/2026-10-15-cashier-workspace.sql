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
