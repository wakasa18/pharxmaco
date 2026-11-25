<?php
// Explicit user confirmation: these are test refunds and must total PHP 500.
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if (($settings['database']??'')!=='pharxmaco_drugstore'||!in_array($settings['hostname']??'',['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Known local database required.');
$db=\Config\Database::connect('default');$db->transException(true);
$snapshot=static function() use ($db):array {
    return ['sale'=>$db->table('sales')->where('id',41)->get()->getRowArray(),
        'items'=>$db->table('sale_items')->where('sale_id',41)->orderBy('id')->get()->getResultArray(),
        'refunds'=>$db->table('refund_items')->where('sale_id',41)->orderBy('id')->get()->getResultArray(),
        'events'=>$db->table('cashier_events')->where('sale_id',41)->orderBy('id')->get()->getResultArray(),
        'product_stock'=>$db->query('SELECT id,stock,updated_at FROM products ORDER BY id')->getResultArray(),
        'branch_stock'=>$db->query('SELECT id,product_id,branch_id,stock,updated_at FROM branch_products ORDER BY id')->getResultArray()];
};
$before=$snapshot();
if (($before['sale']['invoice_no']??'')!=='INV-20260617103518-97E5EE'||count($before['refunds'])!==6) throw new RuntimeException('Expected test sale/refund records required.');
$amount=static fn(array $rows):int=>array_sum(array_map(static fn($row)=>\App\Libraries\DiscountPolicy::cents((float)$row['refund_subtotal']),$rows));
if (!in_array($amount($before['refunds']),[62500,50000],true)) throw new RuntimeException('Unexpected refund amount; no changes made.');
$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
$backup=WRITEPATH.'backups/sale41-refunds-'.$now->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
$json=json_encode(['created_at'=>$now->format('c'),'database'=>$settings['database'],'owner_confirmation'=>'Test records; correct total refunds to PHP 500.00','before'=>$before],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
if (file_put_contents($backup,$json,LOCK_EX)!==strlen($json)) throw new RuntimeException('Backup could not be saved.');
$sql=file_get_contents(ROOTPATH.'database/upgrades/2026-10-16-reconcile-sale41-refunds.sql');
$run=static function() use($db,$sql):void {
    try {foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement) if(trim($statement)!=='') $db->query($statement);}
    catch(Throwable $error){$db->query('ROLLBACK');throw $error;}
};
$auditBefore=$db->table('activity_logs')->countAllResults();
$run();$after=$snapshot();
if ($amount($after['refunds'])!==50000) throw new RuntimeException('Refund total was not corrected; inspect repair status.');
foreach (['sale','items','events','product_stock','branch_stock'] as $key) if ($before[$key]!==$after[$key]) throw new RuntimeException('Unexpected change to '.$key);
$net=[3=>[40,80],4=>[60,60],5=>[60,60],6=>[60,60],7=>[40,80],8=>[160,160]];
foreach ($after['refunds'] as $index=>$row) {
    [$price,$total]=$net[$row['id']];
    if ((float)$row['price_at_sale']!== (float)$price || (float)$row['refund_subtotal']!==(float)$total) throw new RuntimeException('Incorrect refund line.');
    $old=$before['refunds'][$index];unset($old['price_at_sale'],$old['refund_subtotal'],$row['price_at_sale'],$row['refund_subtotal']);
    if ($old!==$row) throw new RuntimeException('Unrelated refund fields changed.');
}
$backupRows=$db->table('phx_backup_20261016_sale41_refunds')->orderBy('id')->get()->getResultArray();
if ($amount($backupRows)!==62500||count($backupRows)!==6) throw new RuntimeException('Original database backup is incomplete.');
$auditAfter=$db->table('activity_logs')->countAllResults();
if ($auditAfter-$auditBefore!==($amount($before['refunds'])===62500?1:0)) throw new RuntimeException('Unexpected audit count.');
$run();
if ($snapshot()!==$after||$db->table('activity_logs')->countAllResults()!==$auditAfter) throw new RuntimeException('Repeat import changed data or added duplicate audit entries.');
$result=['sale_id'=>41,'before_refunds'=>$amount($before['refunds'])/100,'after_refunds'=>500,'refund_excess'=>0,'corrected_refund_ids'=>array_keys($net),
    'unchanged'=>['sale','items','refund quantities/dates/reasons','stock','cashier events'],'repeat_import_no_op'=>true,
    'backup'=>$backup,'database_backup_table'=>'phx_backup_20261016_sale41_refunds','after'=>$after['refunds']];
file_put_contents(__DIR__.'/sale41-refund-reconciliation-result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(array_diff_key($result,['after'=>true]),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
