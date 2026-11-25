<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if(($settings['database']??'')!=='pharxmaco_drugstore'||!in_array($settings['hostname']??'', ['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Known local database required.');
$db=\Config\Database::connect('default');$db->query('START TRANSACTION READ ONLY');
try{
 $out=['sale'=>$db->query('SELECT id,invoice_no,total_amount,discount_amount,final_total,payment_method,amount_paid,change_amount,status,notes,sale_date FROM sales WHERE id=41')->getRowArray(),
  'items'=>$db->query('SELECT id,product_id,quantity,price,subtotal,discount_applied,vat_exemption_amount FROM sale_items WHERE sale_id=41 ORDER BY id')->getResultArray(),
  'refunds'=>$db->query('SELECT id,sale_item_id,quantity_refunded,price_at_sale,refund_subtotal,reason,refund_method,refund_event_id,return_condition,created_at FROM refund_items WHERE sale_id=41 ORDER BY id')->getResultArray(),
  'events'=>$db->query('SELECT id,event_key,event_type,payment_method,amount,reason,created_at FROM cashier_events WHERE sale_id=41 ORDER BY id')->getResultArray(),
  'stock'=>$db->query('SELECT p.id,p.product_name,p.stock master_stock,COALESCE(b.qty,0) branch_stock FROM products p LEFT JOIN (SELECT product_id,SUM(stock) qty FROM branch_products GROUP BY product_id)b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.qty,0) ORDER BY p.id')->getResultArray()];
 file_put_contents(__DIR__.'/reconciliation-before.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{$db->transRollback();}
