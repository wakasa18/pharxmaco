<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); });
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true) || $settings['database']!=='pharxmaco_drugstore') throw new RuntimeException('The known local application database is required.');
$db=\Config\Database::connect('default'); $db->initialize();
$tables=['products','branch_products','sales','sale_items','refund_items','users','discounts','stock_logs','activity_logs','receipt_invoice_sequences'];
$snapshot=static function() use($db,$tables): array {
    $state=[];
    foreach ($tables as $table) {
        $rows=$db->table($table)->orderBy($table==='receipt_invoice_sequences'?'series_key':'id')->get()->getResultArray();
        $state[$table]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
    }
    return $state;
};
$before=$snapshot(); $sql=file_get_contents(ROOTPATH.'database/upgrades/2026-10-15-cashier-workspace.sql');
$run=static function() use($db,$sql): void {
    if (!$db->connID->multi_query($sql)) throw new RuntimeException($db->connID->error);
    do { if ($result=$db->connID->store_result()) $result->free(); if (!$db->connID->more_results()) break; if (!$db->connID->next_result()) throw new RuntimeException($db->connID->error); } while(true);
};
$run(); $run();
if ($snapshot()!==$before) throw new RuntimeException('Existing application data changed.');
$report=['database'=>$settings['database'],'cashier_upgrade_applied'=>true,'repeated_import_passed'=>true,'preserved'=>$before,
    'drawers'=>$db->table('cashier_drawers')->select('branch_id, drawer_name, status')->get()->getResultArray(),
    'shift_count'=>$db->table('cashier_shifts')->countAllResults()];
file_put_contents(__DIR__.'/local-upgrade.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'Cashier upgrade applied locally twice. Existing application data preserved; no shifts or sales created.'.PHP_EOL;
