-- Pharxmaco cashier single-login protection, October 20, 2026.
-- Select the EXISTING application database in phpMyAdmin, then import this entire file.
-- Repeatable/additive. Existing accounts, passwords, sales, stock, drawers and shifts are preserved.
-- Import BEFORE uploading the matching application files; cashier sign-in requires this table.
SELECT id, session_version FROM users LIMIT 0;
SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;

CREATE TABLE IF NOT EXISTS `cashier_login_sessions` (
  `user_id` int NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `account_fingerprint` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `last_seen_at` bigint unsigned NOT NULL DEFAULT 0,
  `expires_at` bigint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

START TRANSACTION;
SET @phx_login_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (version, class, `group`, namespace, time, batch)
SELECT '2026_10_20_000015',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'AddCashierLoginSessions'),
       'default', 'App', UNIX_TIMESTAMP(), @phx_login_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE version = '2026_10_20_000015' AND namespace = 'App' AND `group` = 'default')
AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cashier_login_sessions'
    AND COLUMN_NAME IN ('user_id', 'token_hash', 'account_fingerprint', 'last_seen_at', 'expires_at')) = 5;
COMMIT;
SET @phx_login_batch = NULL;
SELECT 'Cashier single-login schema ready; existing login leases preserved' AS upgrade_status;
