-- Finish cashier FK upgrade 000017 for the user's existing hosted database.
-- Use this when the 24 foreign keys exist but migration_000017 is 0.
-- This file performs NO ALTER, DROP, DELETE or business-record updates.
-- Every schema reference is explicit so phpMyAdmin's current database cannot affect verification.
-- Verifies all 24 column/parent/delete/update definitions before recording completion.

SET @phx_finish_verified=0;

SET @phx_finish_verified=(
 SELECT COUNT(DISTINCT CONCAT(e.child_table,'.',e.child_column))
 FROM (
 SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,MIN(k.COLUMN_NAME) child_column,
 MIN(k.REFERENCED_TABLE_SCHEMA) parent_schema,MIN(k.REFERENCED_TABLE_NAME) parent_table,
 MIN(k.REFERENCED_COLUMN_NAME) parent_column,COUNT(*) column_count
 FROM information_schema.KEY_COLUMN_USAGE k
 WHERE k.TABLE_SCHEMA='u315645644_pharxmacotest' AND k.REFERENCED_TABLE_NAME IS NOT NULL
 GROUP BY k.TABLE_NAME,k.CONSTRAINT_NAME
 ) f
 JOIN information_schema.REFERENTIAL_CONSTRAINTS r
 ON r.CONSTRAINT_SCHEMA='u315645644_pharxmacotest' AND r.TABLE_NAME=f.TABLE_NAME AND r.CONSTRAINT_NAME=f.CONSTRAINT_NAME
 JOIN (
 SELECT 'cashier_drawers' AS child_table, 'branch_id' AS child_column, 'branches' AS parent_table
 UNION ALL
SELECT 'cashier_drawers' AS child_table, 'active_shift_id' AS child_column, 'cashier_shifts' AS parent_table
 UNION ALL
SELECT 'cashier_drawers' AS child_table, 'last_shift_id' AS child_column, 'cashier_shifts' AS parent_table
 UNION ALL
SELECT 'cashier_shifts' AS child_table, 'drawer_id' AS child_column, 'cashier_drawers' AS parent_table
 UNION ALL
SELECT 'cashier_shifts' AS child_table, 'branch_id' AS child_column, 'branches' AS parent_table
 UNION ALL
SELECT 'cashier_shifts' AS child_table, 'user_id' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_shifts' AS child_table, 'active_user_id' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_shifts' AS child_table, 'active_drawer_id' AS child_column, 'cashier_drawers' AS parent_table
 UNION ALL
SELECT 'cashier_shifts' AS child_table, 'handover_to_user_id' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_shifts' AS child_table, 'handover_accepted_shift_id' AS child_column, 'cashier_shifts' AS parent_table
 UNION ALL
SELECT 'cashier_events' AS child_table, 'shift_id' AS child_column, 'cashier_shifts' AS parent_table
 UNION ALL
SELECT 'cashier_events' AS child_table, 'branch_id' AS child_column, 'branches' AS parent_table
 UNION ALL
SELECT 'cashier_events' AS child_table, 'user_id' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_events' AS child_table, 'sale_id' AS child_column, 'sales' AS parent_table
 UNION ALL
SELECT 'cashier_held_sales' AS child_table, 'user_id' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_held_sales' AS child_table, 'branch_id' AS child_column, 'branches' AS parent_table
 UNION ALL
SELECT 'cashier_shift_reviews' AS child_table, 'shift_id' AS child_column, 'cashier_shifts' AS parent_table
 UNION ALL
SELECT 'cashier_shift_reviews' AS child_table, 'reviewed_by' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_event_reviews' AS child_table, 'event_id' AS child_column, 'cashier_events' AS parent_table
 UNION ALL
SELECT 'cashier_event_reviews' AS child_table, 'reviewed_by' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_shift_actions' AS child_table, 'shift_id' AS child_column, 'cashier_shifts' AS parent_table
 UNION ALL
SELECT 'cashier_shift_actions' AS child_table, 'user_id' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'cashier_login_sessions' AS child_table, 'user_id' AS child_column, 'users' AS parent_table
 UNION ALL
SELECT 'user_presence_sessions' AS child_table, 'user_id' AS child_column, 'users' AS parent_table
 ) e ON e.child_table=f.TABLE_NAME AND e.child_column=f.child_column AND e.parent_table=f.parent_table
 WHERE f.column_count=1 AND f.parent_schema='u315645644_pharxmacotest' AND f.parent_column='id'
 AND r.DELETE_RULE IN ('RESTRICT','NO ACTION') AND r.UPDATE_RULE IN ('RESTRICT','NO ACTION')
);

INSERT INTO `u315645644_pharxmacotest`.`migrations` (version,class,`group`,namespace,time,batch)
SELECT '2026_10_21_000017',CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'EnforceCashierForeignKeys'),
 'default','App',UNIX_TIMESTAMP(),(SELECT COALESCE(MAX(batch),0)+1 FROM `u315645644_pharxmacotest`.`migrations`)
WHERE @phx_finish_verified=24
AND NOT EXISTS (SELECT 1 FROM `u315645644_pharxmacotest`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default');

SELECT 'u315645644_pharxmacotest' AS checked_database,@phx_finish_verified AS verified_foreign_keys,
 (SELECT COUNT(*) FROM `u315645644_pharxmacotest`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default'
 AND class=CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'EnforceCashierForeignKeys')) AS migration_000017,
 CASE WHEN @phx_finish_verified=24 AND
 (SELECT COUNT(*) FROM `u315645644_pharxmacotest`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default'
 AND class=CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'EnforceCashierForeignKeys'))=1
 THEN 'Upgrade complete' ELSE 'NOT COMPLETE: expected 24 verified foreign keys and one migration record' END AS upgrade_status;

SET @phx_finish_verified=NULL;
