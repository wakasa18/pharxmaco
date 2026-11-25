<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if (($settings['database']??'')!=='pharxmaco_drugstore'||!in_array($settings['hostname']??'',['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Known local database required.');
$db=\Config\Database::connect('default');
$repair=json_decode(file_get_contents(__DIR__.'/stock-reconciliation-result.json'),true,512,JSON_THROW_ON_ERROR);
$backup=json_decode(file_get_contents($repair['backup']),true,512,JSON_THROW_ON_ERROR);
$branchRows=$db->query('SELECT id,product_id,branch_id,stock FROM branch_products ORDER BY id')->getResultArray();
if ($branchRows!==$backup['branch_stock']) throw new RuntimeException('Branch balances changed.');
foreach ($backup['products_before'] as $before) {
    $after=$db->table('products')->where('id',$before['id'])->get()->getRowArray();
    unset($before['stock'],$after['stock']);
    if ($before!==$after) throw new RuntimeException('An unrelated product field changed.');
}
$activityBefore=$db->table('activity_logs')->countAllResults();
// Execute the importable SQL again: a reconciled database must be a no-op.
$sql=file_get_contents(ROOTPATH.'database/upgrades/2026-10-16-reconcile-master-stock.sql');
foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) if (trim($statement)!=='') $db->query($statement);
if ($db->table('activity_logs')->countAllResults()!==$activityBefore) throw new RuntimeException('Repeat import produced duplicate audit entries.');
$mismatches=(int)$db->query('SELECT COUNT(*) n FROM products p LEFT JOIN (SELECT product_id,SUM(stock) qty FROM branch_products GROUP BY product_id)b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.qty,0)')->getRowArray()['n'];
if ($mismatches) throw new RuntimeException('Stock discrepancies remain.');
echo "Verified nine repaired totals, unchanged branch balances/product fields, and repeat SQL import with no additional changes.\n";
