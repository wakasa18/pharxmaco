<?php
require dirname(__DIR__, 2) . '/app/Libraries/CashierForeignKeys.php';
$database = 'u315645644_pharxmacotest';
$expected = [];
foreach (\App\Libraries\CashierForeignKeys::definitions() as [, $table, $column, $parent]) {
    $expected[] = "SELECT '$table' AS child_table, '$column' AS child_column, '$parent' AS parent_table";
}
$links = implode("\n UNION ALL\n", $expected);
$sql = <<<SQL
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
 WHERE k.TABLE_SCHEMA='$database' AND k.REFERENCED_TABLE_NAME IS NOT NULL
 GROUP BY k.TABLE_NAME,k.CONSTRAINT_NAME
 ) f
 JOIN information_schema.REFERENTIAL_CONSTRAINTS r
 ON r.CONSTRAINT_SCHEMA='$database' AND r.TABLE_NAME=f.TABLE_NAME AND r.CONSTRAINT_NAME=f.CONSTRAINT_NAME
 JOIN (
 $links
 ) e ON e.child_table=f.TABLE_NAME AND e.child_column=f.child_column AND e.parent_table=f.parent_table
 WHERE f.column_count=1 AND f.parent_schema='$database' AND f.parent_column='id'
 AND r.DELETE_RULE IN ('RESTRICT','NO ACTION') AND r.UPDATE_RULE IN ('RESTRICT','NO ACTION')
);

INSERT INTO `$database`.`migrations` (version,class,`group`,namespace,time,batch)
SELECT '2026_10_21_000017',CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'EnforceCashierForeignKeys'),
 'default','App',UNIX_TIMESTAMP(),(SELECT COALESCE(MAX(batch),0)+1 FROM `$database`.`migrations`)
WHERE @phx_finish_verified=24
AND NOT EXISTS (SELECT 1 FROM `$database`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default');

SELECT '$database' AS checked_database,@phx_finish_verified AS verified_foreign_keys,
 (SELECT COUNT(*) FROM `$database`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default'
 AND class=CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'EnforceCashierForeignKeys')) AS migration_000017,
 CASE WHEN @phx_finish_verified=24 AND
 (SELECT COUNT(*) FROM `$database`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default'
 AND class=CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'EnforceCashierForeignKeys'))=1
 THEN 'Upgrade complete' ELSE 'NOT COMPLETE: expected 24 verified foreign keys and one migration record' END AS upgrade_status;

SET @phx_finish_verified=NULL;
SQL;
$root = dirname(__DIR__, 2);
file_put_contents($root . '/database/upgrades/2026-10-21-finish-cashier-foreign-keys.sql', $sql . "\n");
$tables = array_values(array_unique(array_column(\App\Libraries\CashierForeignKeys::definitions(), 1)));
$names = "'" . implode("','", $tables) . "'";
$status = <<<SQL
-- Read-only. Run in phpMyAdmin SQL, even if information_schema is selected.
SELECT
 (SELECT COUNT(*) FROM `$database`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default') AS migration_000017,
 (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA='$database' AND TABLE_NAME IN ($names)) AS cashier_foreign_keys;
SQL;
file_put_contents($root . '/database/upgrades/2026-10-21-cashier-upgrade-status.sql', $status . "\n");
echo "Generated completion and explicit-database status SQL.\n";
