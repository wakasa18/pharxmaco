<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if ($settings['database']!=='pharxmaco_drugstore' || !in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Known local database required.');
$db=\Config\Database::connect('default');
$schema=[];foreach (['products','branch_products','sale_items','refund_items'] as $table) $schema[$table]=array_column($db->query('SHOW COLUMNS FROM `'.$table.'`')->getResultArray(),'Field');
echo json_encode(['database'=>$settings['database'],'schema'=>$schema,'migrations'=>$db->table('migrations')->select('version,class')->like('version','2026_10_14','after')->get()->getResultArray(),'products'=>$db->table('products')->countAllResults(),'demo_skus'=>$db->table('products')->like('sku','DEMO-BRD-','after')->countAllResults()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
