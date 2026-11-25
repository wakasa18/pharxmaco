-- Pharxmaco: quarantined-return inspections, 23 October 2026.
-- phpMyAdmin: select the EXISTING application database and import this ENTIRE file.
-- Requires existing updates 000010 through 000017. No existing data is replaced.
-- Adds one empty audit table. Existing quarantined refunds appear automatically.
-- No sale, refund, shift, quantity, price or login record is edited by this upgrade.
-- Parent reference types are read from the selected database, including signedness.
-- DDL commits automatically. Stop on any SQL error; do not disable foreign-key checks.

SELECT id, stock_delivery_id, expiration_date_at_sale FROM sale_items LIMIT 0;
SELECT id, return_condition FROM refund_items LIMIT 0;
SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;

SET @phx_q_refund_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='refund_items' AND COLUMN_NAME='id');
SET @phx_q_user_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id');
SET @phx_q_log_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stock_logs' AND COLUMN_NAME='id');
SET @phx_q_ddl = CONCAT('CREATE TABLE IF NOT EXISTS `quarantine_resolutions` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `refund_item_id` ', @phx_q_refund_type, ' NOT NULL,
 `outcome` varchar(12) NOT NULL,
 `quantity` int unsigned NOT NULL,
 `inspected_by` ', @phx_q_user_type, ' NOT NULL,
 `inspected_at` datetime NOT NULL,
 `note` varchar(500) NOT NULL,
 `stock_log_id` ', @phx_q_log_type, ' DEFAULT NULL,
 `previous_stock` int DEFAULT NULL,
 `new_stock` int DEFAULT NULL,
 `snapshot_json` longtext NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `refund_item_id` (`refund_item_id`),
 KEY `outcome_inspected_at` (`outcome`,`inspected_at`),
 CONSTRAINT `fk_phx_quarantine_refund` FOREIGN KEY (`refund_item_id`) REFERENCES `refund_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT `fk_phx_quarantine_inspector` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT `fk_phx_quarantine_log` FOREIGN KEY (`stock_log_id`) REFERENCES `stock_logs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
PREPARE phx_q_statement FROM @phx_q_ddl;
EXECUTE phx_q_statement;
DEALLOCATE PREPARE phx_q_statement;

SET @phx_q_ready = (
 (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quarantine_resolutions'
 AND COLUMN_NAME IN ('id','refund_item_id','outcome','quantity','inspected_by','inspected_at','note','stock_log_id','previous_stock','new_stock','snapshot_json'))=11
 AND (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS c
 JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA=c.CONSTRAINT_SCHEMA AND k.TABLE_NAME=c.TABLE_NAME AND k.CONSTRAINT_NAME=c.CONSTRAINT_NAME
 WHERE c.CONSTRAINT_SCHEMA=DATABASE() AND c.TABLE_NAME='quarantine_resolutions' AND c.DELETE_RULE='RESTRICT' AND c.UPDATE_RULE='RESTRICT'
 AND k.REFERENCED_TABLE_SCHEMA=DATABASE() AND k.REFERENCED_COLUMN_NAME='id' AND k.ORDINAL_POSITION=1
 AND ((k.COLUMN_NAME='refund_item_id' AND k.REFERENCED_TABLE_NAME='refund_items')
 OR (k.COLUMN_NAME='inspected_by' AND k.REFERENCED_TABLE_NAME='users')
 OR (k.COLUMN_NAME='stock_log_id' AND k.REFERENCED_TABLE_NAME='stock_logs'))
 AND NOT EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE extra WHERE extra.CONSTRAINT_SCHEMA=c.CONSTRAINT_SCHEMA
 AND extra.TABLE_NAME=c.TABLE_NAME AND extra.CONSTRAINT_NAME=c.CONSTRAINT_NAME AND extra.ORDINAL_POSITION>1))=3
 AND EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quarantine_resolutions'
 AND NON_UNIQUE=0 GROUP BY INDEX_NAME HAVING COUNT(*)=1 AND MAX(COLUMN_NAME)='refund_item_id')
);
INSERT INTO migrations (version, class, `group`, namespace, time, batch)
SELECT '2026_10_23_000018', CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'AddQuarantineResolutions'),
 'default', 'App', UNIX_TIMESTAMP(), (SELECT COALESCE(MAX(m.batch),0)+1 FROM migrations m)
WHERE @phx_q_ready=1 AND NOT EXISTS (SELECT 1 FROM migrations WHERE version='2026_10_23_000018' AND namespace='App' AND `group`='default');
SELECT CASE WHEN @phx_q_ready=1 THEN 'Quarantine upgrade complete' ELSE 'NOT COMPLETE: inspect quarantine table structure before using the page' END AS upgrade_status;
SELECT COUNT(*) AS pending_returns FROM refund_items r LEFT JOIN quarantine_resolutions q ON q.refund_item_id=r.id
WHERE r.return_condition='quarantined' AND q.id IS NULL;
SET @phx_q_refund_type=NULL, @phx_q_user_type=NULL, @phx_q_log_type=NULL, @phx_q_ddl=NULL, @phx_q_ready=NULL;
