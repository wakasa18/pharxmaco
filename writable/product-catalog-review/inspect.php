<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true) || $settings['database']!=='pharxmaco_drugstore') throw new RuntimeException('Known local database required.');
$db=\Config\Database::connect('default');
$rows=$db->table('products')->orderBy('id')->get()->getResultArray();
file_put_contents(__DIR__.'/local-products.json',json_encode($rows,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
foreach ($rows as $row) echo json_encode(array_intersect_key($row,array_flip(['id','product_name','sku','generic_name','medicine_type','senior_pwd_eligible'])),JSON_THROW_ON_ERROR).PHP_EOL;
