<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Shared definitions for migration 000017 and its phpMyAdmin import. */
final class CashierForeignKeys
{
    public static function definitions(): array
    {
        return [
            ['fk_phx_drawer_branch', 'cashier_drawers', 'branch_id', 'branches'],
            ['fk_phx_drawer_active_shift', 'cashier_drawers', 'active_shift_id', 'cashier_shifts'],
            ['fk_phx_drawer_last_shift', 'cashier_drawers', 'last_shift_id', 'cashier_shifts'],
            ['fk_phx_shift_drawer', 'cashier_shifts', 'drawer_id', 'cashier_drawers'],
            ['fk_phx_shift_branch', 'cashier_shifts', 'branch_id', 'branches'],
            ['fk_phx_shift_user', 'cashier_shifts', 'user_id', 'users'],
            ['fk_phx_shift_active_user', 'cashier_shifts', 'active_user_id', 'users'],
            ['fk_phx_shift_active_drawer', 'cashier_shifts', 'active_drawer_id', 'cashier_drawers'],
            ['fk_phx_shift_handover_user', 'cashier_shifts', 'handover_to_user_id', 'users'],
            ['fk_phx_shift_handover_accepted', 'cashier_shifts', 'handover_accepted_shift_id', 'cashier_shifts'],
            ['fk_phx_event_shift', 'cashier_events', 'shift_id', 'cashier_shifts'],
            ['fk_phx_event_branch', 'cashier_events', 'branch_id', 'branches'],
            ['fk_phx_event_user', 'cashier_events', 'user_id', 'users'],
            ['fk_phx_event_sale', 'cashier_events', 'sale_id', 'sales'],
            ['fk_phx_hold_user', 'cashier_held_sales', 'user_id', 'users'],
            ['fk_phx_hold_branch', 'cashier_held_sales', 'branch_id', 'branches'],
            ['fk_phx_shift_review_shift', 'cashier_shift_reviews', 'shift_id', 'cashier_shifts'],
            ['fk_phx_shift_review_user', 'cashier_shift_reviews', 'reviewed_by', 'users'],
            ['fk_phx_event_review_event', 'cashier_event_reviews', 'event_id', 'cashier_events'],
            ['fk_phx_event_review_user', 'cashier_event_reviews', 'reviewed_by', 'users'],
            ['fk_phx_shift_action_shift', 'cashier_shift_actions', 'shift_id', 'cashier_shifts'],
            ['fk_phx_shift_action_user', 'cashier_shift_actions', 'user_id', 'users'],
            ['fk_phx_login_user', 'cashier_login_sessions', 'user_id', 'users'],
            ['fk_phx_presence_user', 'user_presence_sessions', 'user_id', 'users'],
        ];
    }

    private static function fkMatch(array $definition): string
    {
        [, $table, $column, $parent] = $definition;
        $key = $table . '.' . $column;
        $name = self::value('by_column', $key, 'name');
        $rule = static fn ($field) => "CAST(JSON_UNQUOTE(JSON_EXTRACT(@phx_fk_rules,CONVERT(CONCAT('$.' ,CHAR(34),HEX($name),CHAR(34),'.$field') USING utf8mb4))) AS BINARY)";
        $shape = "JSON_UNQUOTE(JSON_EXTRACT(@phx_fk_shapes,CONVERT(CONCAT('$.',CHAR(34),HEX(CONCAT('$table.',$name)),CHAR(34),'.columns') USING utf8mb4)))";
        return 'COALESCE((' . self::value('by_column', $key, 'count') . '=1 AND '
            . self::value('by_column', $key, 'parent_schema') . "=@phx_fk_database AND "
            . self::value('by_column', $key, 'parent') . "='$parent' AND "
            . self::value('by_column', $key, 'parent_column') . "='id' AND $shape=1 AND "
            . $rule('table') . "='$table' AND " . $rule('delete') . " IN ('RESTRICT','NO ACTION') AND "
            . $rule('update') . " IN ('RESTRICT','NO ACTION')),0)";
    }

    private static function value(string $cache, string $key, string $field): string
    {
        $hex = strtoupper(bin2hex($key));
        return "CAST(JSON_UNQUOTE(JSON_EXTRACT(@phx_fk_$cache,'$.\"$hex\".$field')) AS BINARY)";
    }

    /** Read each catalogue once, rather than repeatedly joining MariaDB information_schema. */
    private static function metadataStatements(): array
    {
        return [
            "SET @phx_fk_columns=CONVERT((SELECT JSON_OBJECTAGG(HEX(CONCAT(TABLE_NAME,'.',COLUMN_NAME)),
 JSON_OBJECT('type',DATA_TYPE,'unsigned',IF(LOCATE('unsigned',COLUMN_TYPE)>0,1,0),'nullable',IS_NULLABLE))
 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@phx_fk_database) USING utf8mb4)",
            "SET @phx_fk_shapes=CONVERT((SELECT JSON_OBJECTAGG(HEX(CONCAT(TABLE_NAME,'.',CONSTRAINT_NAME)),
 JSON_OBJECT('columns',n,'first',first_column)) FROM
 (SELECT TABLE_NAME,CONSTRAINT_NAME,COUNT(*) n,MIN(COLUMN_NAME) first_column
 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=@phx_fk_database
 GROUP BY TABLE_NAME,CONSTRAINT_NAME) phx_shapes) USING utf8mb4)",
            "SET @phx_fk_by_column=CONVERT((SELECT JSON_OBJECTAGG(HEX(CONCAT(TABLE_NAME,'.',COLUMN_NAME)),
 JSON_OBJECT('count',n,'name',fk_name,'parent_schema',parent_schema,'parent',parent_table,'parent_column',parent_column)) FROM
 (SELECT TABLE_NAME,COLUMN_NAME,COUNT(*) n,MIN(CONSTRAINT_NAME) fk_name,
 MIN(REFERENCED_TABLE_SCHEMA) parent_schema,MIN(REFERENCED_TABLE_NAME) parent_table,MIN(REFERENCED_COLUMN_NAME) parent_column
 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=@phx_fk_database AND REFERENCED_TABLE_NAME IS NOT NULL
 GROUP BY TABLE_NAME,COLUMN_NAME) phx_columns) USING utf8mb4)",
            "SET @phx_fk_rules=CONVERT((SELECT JSON_OBJECTAGG(HEX(CONSTRAINT_NAME),JSON_OBJECT('table',TABLE_NAME,'delete',DELETE_RULE,'update',UPDATE_RULE))
 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@phx_fk_database) USING utf8mb4)",
        ];
    }

    private static function typeMatch(array $definition, bool $allowSaleConversion): string
    {
        [, $table, $column, $parent] = $definition;
        $child = static fn ($field) => self::value('columns', "$table.$column", $field);
        $parentValue = static fn ($field) => self::value('columns', "$parent.id", $field);
        $match = $child('type') . '=' . $parentValue('type') . ' AND ' . $child('unsigned') . '=' . $parentValue('unsigned');
        if ($allowSaleConversion && $table === 'cashier_events' && $column === 'sale_id') {
            $match = $child('type') . " IN ('tinyint','smallint','mediumint','int','bigint') AND " . $child('nullable') . "='YES'";
        }
        return 'COALESCE((' . $parentValue('type') . " IN ('tinyint','smallint','mediumint','int','bigint') AND "
            . $parentValue('nullable') . "='NO' AND ($match)),0)";
    }

    public static function preflightStatements(): array
    {
        $tables = ['migrations']; $parents = []; $conditions = []; $orphans = [];
        foreach (self::definitions() as $definition) {
            [$name, $table, $column, $parent] = $definition;
            $tables[] = $table; $tables[] = $parent; $parents[$parent] = true;
            $conditions[] = self::typeMatch($definition, true);
            $match = self::fkMatch($definition);
            $count = self::value('by_column', "$table.$column", 'count');
            $reserved = self::value('rules', $name, 'table');
            $conditions[] = "($match OR ($count IS NULL AND $reserved IS NULL))";
            $orphans[] = "SELECT '$table.$column' AS relationship, COUNT(*) AS orphan_count FROM `$table` c
 LEFT JOIN `$parent` p ON p.id=c.`$column` WHERE c.`$column` IS NOT NULL AND p.id IS NULL";
        }
        foreach (array_keys($parents) as $parent) {
            $conditions[] = self::value('shapes', "$parent.PRIMARY", 'columns') . '=1';
            $conditions[] = self::value('shapes', "$parent.PRIMARY", 'first') . "='id'";
        }
        $tables = array_values(array_unique($tables));
        $names = "'" . implode("','", $tables) . "'";
        $conditions[] = "(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@phx_fk_database
 AND TABLE_NAME IN ($names) AND ENGINE='InnoDB')=" . count($tables);
        $conditions[] = '@@FOREIGN_KEY_CHECKS=1';
        return array_merge([
            'SET @phx_fk_ready=0, @phx_fk_schema_ok=0, @phx_fk_orphans=NULL, @phx_fk_complete=0',
            'SET @phx_fk_database=CAST(DATABASE() AS BINARY), @phx_fk_old_lock_wait=@@SESSION.lock_wait_timeout',
            'SET SESSION lock_wait_timeout=10',
            'SELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0',
        ], self::metadataStatements(), [
            'SET @phx_fk_schema_ok=(' . implode("\n AND ", $conditions) . ')',
            'SET @phx_fk_orphans=(SELECT SUM(orphan_count) FROM (' . implode("\n UNION ALL\n", $orphans) . ') phx_orphans)',
            'SET @phx_fk_ready=(@phx_fk_schema_ok=1 AND @phx_fk_orphans=0)',
            "SELECT CASE WHEN @phx_fk_ready=1 THEN 'Preflight passed' ELSE 'NOT APPLIED: fix missing tables, incompatible keys/types or orphan references; keep FOREIGN_KEY_CHECKS enabled' END AS preflight_status,
 @phx_fk_schema_ok AS schema_compatible, @phx_fk_orphans AS orphan_references",
        ]);
    }

    public static function upgradeStatements(): array
    {
        $statements = [];
        $alterSale = "CONCAT('MODIFY COLUMN `sale_id` '," . self::value('columns', 'sales.id', 'type')
            . ",IF(" . self::value('columns', 'sales.id', 'unsigned') . "=1,' UNSIGNED',''),' NULL DEFAULT NULL')";
        $sale = ['fk_phx_event_sale', 'cashier_events', 'sale_id', 'sales'];
        $groups = [];
        foreach (self::definitions() as $definition) {
            [$name, $table, $column, $parent] = $definition;
            $ddl = "ADD CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$parent` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT";
            $groups[$table][] = 'IF(' . self::fkMatch($definition) . ",NULL,'$ddl')";
        }
        foreach ($groups as $table => $clauses) {
            if ($table === 'cashier_events') array_unshift($clauses, 'IF(' . self::typeMatch($sale, false) . ",NULL,$alterSale)");
            $statements[] = "SET @phx_fk_clauses=CONCAT_WS(', '," . implode(',', $clauses) . ')';
            $statements[] = "SET @phx_fk_sql=IF(@phx_fk_ready=1 AND LENGTH(@phx_fk_clauses)>0,
 CONCAT('ALTER TABLE `',REPLACE(@phx_fk_database,'`','``'),'`.`$table` ',@phx_fk_clauses),'DO 0')";
            $statements[] = 'PREPARE phx_fk_statement FROM @phx_fk_sql';
            $statements[] = 'EXECUTE phx_fk_statement';
            $statements[] = 'DEALLOCATE PREPARE phx_fk_statement';
        }
        $statements = array_merge($statements, self::metadataStatements());
        $matches = array_map(static fn ($d) => self::fkMatch($d), self::definitions());
        $statements[] = 'SET @phx_fk_complete=(@phx_fk_ready=1 AND ' . implode("\n AND ", $matches) . ')';
        return $statements;
    }

    public static function apply(BaseConnection $db): void
    {
        if ($db->DBDriver !== 'MySQLi') throw new RuntimeException('Cashier foreign-key migration requires MySQL/MariaDB with InnoDB.');
        try {
        foreach (self::preflightStatements() as $sql) $db->query($sql);
        $check = $db->query('SELECT @phx_fk_ready AS ready, @phx_fk_orphans AS orphans')->getRowArray();
        if ((int) $check['ready'] !== 1) throw new RuntimeException('Cashier foreign-key preflight refused: check table types, existing constraints and orphan references (' . ($check['orphans'] ?? 'unknown') . '). No upgrade DDL was run.');
        foreach (self::upgradeStatements() as $sql) $db->query($sql);
        if ((int) $db->query('SELECT @phx_fk_complete AS complete')->getRowArray()['complete'] !== 1) {
            throw new RuntimeException('Cashier foreign-key upgrade is incomplete. Resolve the SQL error and rerun.');
        }
        $db->resetDataCache();
        } finally {
            $db->query('SET SESSION lock_wait_timeout=@phx_fk_old_lock_wait');
        }
    }

    public static function importSql(): string
    {
        $statements = array_merge(self::preflightStatements(), self::upgradeStatements(), [
            'SET @phx_fk_batch=(SELECT COALESCE(MAX(batch),0)+1 FROM migrations)',
            "INSERT INTO migrations (version,class,`group`,namespace,time,batch)
SELECT '2026_10_21_000017', CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'EnforceCashierForeignKeys'),
 'default','App',UNIX_TIMESTAMP(),@phx_fk_batch WHERE @phx_fk_complete=1
 AND NOT EXISTS (SELECT 1 FROM migrations WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default')",
            "SELECT CASE WHEN @phx_fk_complete=1 THEN 'Cashier foreign-key upgrade complete: 24 relationships enforced; existing records preserved'
 ELSE 'NOT COMPLETE: upgrade was refused or a SQL step failed; no new migration registration was written' END AS upgrade_status",
            'SET SESSION lock_wait_timeout=@phx_fk_old_lock_wait',
            'SET @phx_fk_sql=NULL, @phx_fk_batch=NULL, @phx_fk_ready=NULL, @phx_fk_schema_ok=NULL, @phx_fk_orphans=NULL, @phx_fk_complete=NULL, @phx_fk_columns=NULL, @phx_fk_shapes=NULL, @phx_fk_by_column=NULL, @phx_fk_rules=NULL, @phx_fk_clauses=NULL, @phx_fk_database=NULL, @phx_fk_old_lock_wait=NULL',
        ]);
        return "-- Pharxmaco cashier foreign-key upgrade 000017, October 21, 2026.\n"
            . "-- Select the EXISTING database in phpMyAdmin. Requires updates 000014, 000015 and 000016.\n"
            . "-- Export a backup and pause application writes while importing this entire file.\n"
            . "-- Repeatable. Preserves rows and values; aligns cashier_events.sale_id to sales.id.\n"
            . "-- All 24 links use ON DELETE RESTRICT / ON UPDATE RESTRICT. No cascade deletion.\n"
            . "-- Preflight refuses incompatible schemas, disabled FK checks and orphan references without repairing/deleting data.\n"
            . "-- Schema changes commit automatically. Stop on any SQL error, fix its cause, then rerun.\n"
            . "-- Check the final upgrade_status; migration 000017 is registered only when every FK is verified.\n"
            . "-- Upload the matching migration/helper and backup-service update. Old shift-reset copies must be replaced.\n"
            . "-- Generated by tools/database/build-cashier-foreign-keys.php; do not hand-edit generated statements.\n\n"
            . "-- Revised: metadata is read into four session caches per pass, with at most nine ALTER statements.\n"
            . "-- Metadata-lock waits are limited to 10 seconds; no global server settings are changed.\n\n"
            . implode(";\n\n", $statements) . ";\n";
    }
}
