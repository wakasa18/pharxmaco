<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if(($settings['database']??'')!=='pharxmaco_drugstore'||!in_array($settings['hostname']??'', ['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Known local database required.');
$result=['audited_on'=>'2026-10-16','read_only'=>true,'database'=>'pharxmaco_drugstore'];
$db=\Config\Database::connect('default');$started=false;
try{
 $db->query('START TRANSACTION READ ONLY');$started=true;$result['available']=true;
 $result['counts']=$db->query('SELECT (SELECT COUNT(*) FROM products) products,(SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM sale_items) sale_items')->getRowArray();
 $result['master_stock_mismatches']=$db->query('SELECT COUNT(*) count FROM products p LEFT JOIN (SELECT product_id,SUM(stock) qty FROM branch_products GROUP BY product_id) b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.qty,0)')->getRowArray();
 $result['over_refunded_lines']=$db->query('SELECT COUNT(*) count FROM (SELECT i.id FROM sale_items i JOIN refund_items r ON r.sale_item_id=i.id GROUP BY i.id,i.quantity,i.subtotal,i.discount_applied,i.vat_exemption_amount HAVING SUM(r.quantity_refunded)>i.quantity OR SUM(r.refund_subtotal)>i.subtotal-i.discount_applied-i.vat_exemption_amount+0.01) x')->getRowArray();
 $result['missing_delivery_snapshots']=$db->query("SELECT COUNT(*) count FROM sale_items WHERE stock_delivery_id IS NULL OR TRIM(stock_delivery_id)='' OR expiration_date_at_sale IS NULL")->getRowArray();
 $result['refund_overage_details']=$db->query('SELECT i.id sale_item_id,i.sale_id,i.subtotal-i.discount_applied-i.vat_exemption_amount net_item_amount,SUM(r.refund_subtotal) refunded_amount FROM sale_items i JOIN refund_items r ON r.sale_item_id=i.id GROUP BY i.id,i.sale_id,i.subtotal,i.discount_applied,i.vat_exemption_amount HAVING SUM(r.refund_subtotal)>i.subtotal-i.discount_applied-i.vat_exemption_amount+0.01 ORDER BY i.id')->getResultArray();
 $result['noncash_missing_reference']=$db->query("SELECT COUNT(*) count FROM sales WHERE payment_method IN ('card','gcash') AND (reference_no IS NULL OR TRIM(reference_no)='')")->getRowArray();
 $result['noncash_with_change']=$db->query("SELECT COUNT(*) count FROM sales WHERE payment_method IN ('card','gcash') AND change_amount>0")->getRowArray();
 $result['cashier_schema']=[];
 foreach(['cashier_drawers','cashier_shifts','cashier_events','cashier_held_sales','cashier_shift_reviews','cashier_event_reviews','cashier_shift_actions']as$table)$result['cashier_schema'][$table]=$db->tableExists($table);
}catch(Throwable $error){$result['available']=false;$result['error']=$started?'Read-only inspection could not finish; inspect local schema.':'Local database connection unavailable; no live records inspected.';}
finally{if($started)$db->transRollback();}
file_put_contents(__DIR__.'/local-data-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
