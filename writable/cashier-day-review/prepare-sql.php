<?php
require dirname(__DIR__,2) . '/system/Test/bootstrap.php';
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); });
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Local verification server required.');
$base=\Config\Database::connect('default'); $base->initialize();
$schema='phx_cashier_schema_review_'.bin2hex(random_bytes(6)); $created=false;
try {
    $base->query('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    $settings['database']=$schema; $db=\Config\Database::connect($settings,false); $db->initialize();
    $db->query('CREATE TABLE branches (id INT PRIMARY KEY, branch_name VARCHAR(100), status VARCHAR(20))');
    require_once APPPATH.'Database/Migrations/2026_10_15_000014_add_cashier_shifts.php';
    (new \App\Database\Migrations\AddCashierShifts(new \CodeIgniter\Database\MySQLi\Forge($db)))->up();
    $tables=['cashier_drawers','cashier_shifts','cashier_events','cashier_held_sales','cashier_shift_reviews','cashier_event_reviews','cashier_shift_actions'];
    $sql="-- Pharxmaco shared branch drawers and cashier workspace, October 15, 2026.\n-- Select the EXISTING application database. Apply updates 000010 through 000013 first.\n-- Repeatable and additive. Existing sales, stock, prices and users are preserved.\n-- Each branch receives one shared drawer; no historical shifts or opening cash are invented.\n-- Apply this upgrade before uploading the matching cashier workspace application files.\nSELECT id, receipt_metadata FROM sales LIMIT 0;\nSELECT id, vat_exemption_amount FROM sale_items LIMIT 0;\nSELECT version, class, `group`, namespace, time, batch FROM migrations LIMIT 0;\n\n";
    foreach ($tables as $table) {
        $row=$db->query('SHOW CREATE TABLE `'.$table.'`')->getRowArray();
        $sql.=preg_replace('/^CREATE TABLE /','CREATE TABLE IF NOT EXISTS ', $row['Create Table']).";\n\n";
    }
    $sql.="START TRANSACTION;\nINSERT INTO cashier_drawers (branch_id, drawer_name, status, created_at)\nSELECT b.id, 'Main drawer', 'active', NOW() FROM branches b\nWHERE NOT EXISTS (SELECT 1 FROM cashier_drawers d WHERE d.branch_id = b.id);\n\nSET @phx_cashier_batch = (SELECT COALESCE(MAX(batch),0)+1 FROM migrations);\nINSERT INTO migrations (version, class, `group`, namespace, time, batch)\nSELECT '2026_10_15_000014', CONCAT('App',CHAR(92),'Database',CHAR(92),'Migrations',CHAR(92),'AddCashierShifts'),\n'default', 'App', UNIX_TIMESTAMP(), @phx_cashier_batch\nWHERE NOT EXISTS (SELECT 1 FROM migrations WHERE version='2026_10_15_000014' AND namespace='App' AND `group`='default')\nAND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('cashier_drawers','cashier_shifts','cashier_events','cashier_held_sales','cashier_shift_reviews','cashier_event_reviews','cashier_shift_actions'))=7;\nCOMMIT;\nSET @phx_cashier_batch = NULL;\nSELECT 'Cashier workspace upgrade complete' AS upgrade_status;\n";
    file_put_contents(ROOTPATH.'database/upgrades/2026-10-15-cashier-workspace.sql',$sql);
    echo 'Prepared additive SQL for seven cashier tables.'.PHP_EOL;
    $db->close();
} finally {
    if ($created && preg_match('/^phx_cashier_schema_review_[a-f0-9]{12}$/D',$schema)) $base->query('DROP DATABASE `'.$schema.'`');
}
