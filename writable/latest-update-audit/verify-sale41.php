<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if (($settings['database']??'')!=='pharxmaco_drugstore'||!in_array($settings['hostname']??'',['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Known local database required.');
$result=json_decode(file_get_contents(__DIR__.'/sale41-refund-reconciliation-result.json'),true,512,JSON_THROW_ON_ERROR);
$before=json_decode(file_get_contents($result['backup']),true,512,JSON_THROW_ON_ERROR)['before'];
$db=\Config\Database::connect('default');$db->query('START TRANSACTION READ ONLY');
try {
    $original=$db->table('phx_backup_20261016_sale41_refunds')->orderBy('id')->get()->getResultArray();
    if ($original!==$before['refunds']) throw new RuntimeException('Original refund backup does not match.');
    $lines=$db->query('SELECT i.id,i.quantity,i.subtotal-i.discount_applied-i.vat_exemption_amount net_amount,
        SUM(r.quantity_refunded) refunded_qty,SUM(r.refund_subtotal) refunded_amount
        FROM sale_items i JOIN refund_items r ON r.sale_item_id=i.id WHERE i.sale_id=41
        GROUP BY i.id,i.quantity,i.subtotal,i.discount_applied,i.vat_exemption_amount ORDER BY i.id')->getResultArray();
    foreach ($lines as $line) if ((int)$line['quantity']!==(int)$line['refunded_qty'] || (float)$line['net_amount']!==(float)$line['refunded_amount']) throw new RuntimeException('Refund does not equal saved net amount.');
    $count=(int)$db->table('activity_logs')->like('activity','2026-10-16 owner-confirmed TEST data repair: sale #41 /','after')->countAllResults();
    if ($count!==1) throw new RuntimeException('Expected one repair audit entry.');
    $mismatches=(int)$db->query('SELECT COUNT(*) n FROM products p LEFT JOIN (SELECT product_id,SUM(stock) qty FROM branch_products GROUP BY product_id)b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.qty,0)')->getRowArray()['n'];
    if ($mismatches) throw new RuntimeException('Stock discrepancies remain.');
    echo json_encode(['sale_id'=>41,'refund_total'=>array_sum(array_column($lines,'refunded_amount')),'refund_excess'=>0,
        'items'=>$lines,'original_rows_backed_up_exactly'=>count($original),'audit_entries'=>$count,'stock_discrepancies'=>$mismatches],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {$db->transRollback();}
