<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
set_exception_handler(static function(Throwable $error): void { fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1); });
$settings=config(\Config\Database::class)->default;
if ($settings['database']!=='pharxmaco_drugstore' || !in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Known local database required.');
$base=\Config\Database::connect('default');$base->initialize();
$sql=file_get_contents(ROOTPATH.'database/upgrades/2026-10-04-stock-expiry-safeguards.sql');
$run=static function($db) use($sql): void {
    if (!$db->connID->multi_query($sql)) throw new RuntimeException($db->connID->error);
    do { if ($r=$db->connID->store_result()) $r->free();if (!$db->connID->more_results()) break;if (!$db->connID->next_result()) throw new RuntimeException($db->connID->error); } while(true);
    $db->resetDataCache();
};
$snapshot=static function($db): array {
    $rows=[];foreach ($db->listTables() as $table) { if (!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Unexpected table.');$rows[$table]=$db->table($table)->orderBy($table==='receipt_invoice_sequences'?'series_key':'id')->get()->getResultArray(); }return $rows;
};
$assertPreserved=static function(array $before,array $after): void {
    foreach ($before as $table=>$rows) {
        if ($table!=='migrations' && count($rows)!==count($after[$table])) throw new RuntimeException('Existing row count changed: '.$table);
        $key=$table==='receipt_invoice_sequences'?'series_key':'id';$indexed=array_column($after[$table],null,$key);
        foreach ($rows as $row) {
            $next=$indexed[$row[$key]] ?? [];
            foreach ($row as $column=>$value) if (!array_key_exists($column,$next) || $next[$column]!==$value) throw new RuntimeException('Existing field changed: '.$table.'.'.$column);
            foreach (array_diff_key($next,$row) as $value) if ($value!==null) throw new RuntimeException('Historical provenance invented.');
        }
    }
};
$schema='phx_brand_schema_review_'.bin2hex(random_bytes(6));$created=false;$db=null;
try {
    $base->query('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $settings['database']=$schema;$db=\Config\Database::connect($settings,false);$db->initialize();
    foreach (['branch_products','sale_items','migrations'] as $table) {
        $db->query('CREATE TABLE `'.$table.'` LIKE `pharxmaco_drugstore`.`'.$table.'`');
        $db->query('INSERT INTO `'.$table.'` SELECT * FROM `pharxmaco_drugstore`.`'.$table.'`');
    }
    $before=$snapshot($db);$run($db);$after=$snapshot($db);$assertPreserved($before,$after);$run($db);
    if ($snapshot($db)!==$after) throw new RuntimeException('Prerequisite reimport changed records.');
} finally {
    if ($db) $db->close();
    if ($created && preg_match('/^phx_brand_schema_review_[a-f0-9]{12}$/D',$schema)) $base->query('DROP DATABASE `'.$schema.'`');
}
$before=$snapshot($base);$run($base);$after=$snapshot($base);$assertPreserved($before,$after);$run($base);
if ($snapshot($base)!==$after) throw new RuntimeException('Local prerequisite repeat import changed records.');
$report=['database'=>'pharxmaco_drugstore','existing_stock_expiry_upgrade_applied'=>true,'isolated_clone_verified'=>true,
    'existing_field_values_preserved'=>true,'new_historical_provenance_null'=>true,'repeat_import_unchanged'=>true,
    'added_columns'=>['branch_products.stock_delivery_id','sale_items.stock_delivery_id','sale_items.expiration_date_at_sale']];
file_put_contents(__DIR__.'/local-prerequisite.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
