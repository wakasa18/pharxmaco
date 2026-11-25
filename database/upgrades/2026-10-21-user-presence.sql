-- Pharxmaco Online/Offline presence, October 21, 2026.
-- Select the EXISTING application database. Requires account-session migration 000015.
-- Repeatable and additive. Existing accounts/passwords, sales, stock, shifts and login leases are preserved.
-- No users are marked online and no historical last-seen values are invented by this import.
SELECT id, session_version FROM users LIMIT 0;
SELECT user_id, token_hash, account_fingerprint, expires_at FROM cashier_login_sessions LIMIT 0;
SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;

CREATE TABLE IF NOT EXISTS `user_presence_sessions` (
  `token_hash` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `account_fingerprint` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `cashier_token_hash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` bigint unsigned NOT NULL,
  `last_seen_at` bigint unsigned NOT NULL,
  `expires_at` bigint unsigned NOT NULL,
  `ended_at` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `user_id_last_seen_at` (`user_id`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

START TRANSACTION;
SET @phx_presence_batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (version, class, `group`, namespace, time, batch)
SELECT '2026_10_21_000016',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'AddUserPresenceSessions'),
       'default', 'App', UNIX_TIMESTAMP(), @phx_presence_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE version = '2026_10_21_000016' AND namespace = 'App' AND `group` = 'default')
AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_presence_sessions'
    AND COLUMN_NAME IN ('token_hash','user_id','account_fingerprint','cashier_token_hash','created_at','last_seen_at','expires_at','ended_at')) = 8;
COMMIT;
SET @phx_presence_batch = NULL;
SELECT 'User presence schema ready; existing records preserved' AS upgrade_status;
