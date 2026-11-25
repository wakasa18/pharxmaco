<?php
require dirname(__DIR__,3) . '/system/Test/bootstrap.php';
$path=__DIR__.'/db-health.json';
$out=['audit_date'=>'2026-10-27','scope'=>'local application database; counts and hashed record references only','status'=>'not_checked','services_started'=>false,'transaction_read_only'=>false,'rollback_completed'=>false,'application_writes'=>false];
$finish=static function()use(&$out,$path){file_put_contents($path,json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode(['status'=>$out['status'],'artifact'=>$path,'reason'=>$out['reason']??null],JSON_THROW_ON_ERROR)."\n";};
$settings=config(\Config\Database::class)->default;
if(!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true)||$settings['database']!=='pharxmaco_drugstore'){$out['reason']='Configured database is not the intended loopback application database.';$finish();exit(0);}
mysqli_report(MYSQLI_REPORT_OFF);$db=mysqli_init();$db->options(MYSQLI_OPT_CONNECT_TIMEOUT,3);
if(!@$db->real_connect($settings['hostname'],$settings['username'],$settings['password'],$settings['database'],(int)($settings['port']??3306))){$out['reason']='Local database connection unavailable; no service started.';$out['connection_error_code']=$db->connect_errno;$finish();exit(0);}
$log=[];
$query=static function(string $sql)use($db,&$log):array{if(!preg_match('/^(SELECT|SHOW)\b/i',$sql))throw new RuntimeException('Non-read statement rejected.');$r=$db->query($sql);if(!$r)throw new RuntimeException('Read-only audit query failed, code '.$db->errno);$log[]=['type'=>strtok($sql,' '),'sha256'=>hash('sha256',$sql)];return $r->fetch_all(MYSQLI_ASSOC);};
$count=static fn(string $sql)=>(int)$query('SELECT COUNT(*) AS n FROM ('.$sql.') audit_count')[0]['n'];
$quote=static fn(string $id)=>'`'.str_replace('`','``',$id).'`';
$refs=static fn(array $rows,string $table)=>array_map(static fn($r)=>substr(hash('sha256',$table.':'.($r['id']??json_encode($r))),0,12),array_slice($rows,0,30));
$commands="SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_create_table','Com_drop_table','Com_alter_table')";
try {
 $before=$query($commands);
 if(!$db->query('START TRANSACTION READ ONLY'))throw new RuntimeException('Read-only transaction could not start.');$out['transaction_read_only']=true;
 $tables=$query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");$out['table_count']=count($tables);$out['server_version']=$query('SELECT VERSION() AS v')[0]['v'];
 $out['record_counts']=[];foreach(['products','branch_products','sales','sale_items','refund_items','stock_logs','cashier_shifts','quarantine_resolutions','migrations'] as $t)$out['record_counts'][$t]=$count('SELECT 1 FROM '.$quote($t));
 $checks=[
 'negative_branch_stock'=>'SELECT id FROM branch_products WHERE stock<0',
 'negative_master_stock'=>'SELECT id FROM products WHERE stock<0',
 'negative_branch_prices'=>'SELECT id FROM branch_products WHERE price<0 OR cost_price<0',
 'master_branch_stock_mismatches'=>'SELECT p.id FROM products p LEFT JOIN (SELECT product_id,SUM(stock) AS total FROM branch_products GROUP BY product_id) b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.total,0)',
 'duplicate_product_branch_pairs'=>'SELECT product_id AS id FROM branch_products GROUP BY product_id,branch_id HAVING COUNT(*)>1',
 'duplicate_nonempty_skus'=>"SELECT MIN(id) AS id FROM products WHERE sku IS NOT NULL AND TRIM(sku)<>'' GROUP BY UPPER(TRIM(sku)) HAVING COUNT(*)>1",
 'duplicate_nonempty_invoice_numbers'=>"SELECT MIN(id) AS id FROM sales WHERE invoice_no IS NOT NULL AND TRIM(invoice_no)<>'' GROUP BY invoice_no HAVING COUNT(*)>1",
 'over_refunded_item_quantities'=>'SELECT s.id FROM sale_items s JOIN (SELECT sale_item_id,SUM(quantity_refunded) AS quantity FROM refund_items GROUP BY sale_item_id) r ON r.sale_item_id=s.id WHERE r.quantity>s.quantity',
 'over_refunded_item_amounts'=>'SELECT s.id FROM sale_items s JOIN (SELECT sale_item_id,SUM(refund_subtotal) AS amount FROM refund_items GROUP BY sale_item_id) r ON r.sale_item_id=s.id WHERE ROUND(r.amount,2)>ROUND(GREATEST(0,s.subtotal-COALESCE(s.discount_applied,0)-COALESCE(s.vat_exemption_amount,0)),2)',
 'over_refunded_sale_amounts'=>'SELECT s.id FROM sales s JOIN (SELECT sale_id,SUM(refund_subtotal) AS amount FROM refund_items GROUP BY sale_id) r ON r.sale_id=s.id WHERE ROUND(r.amount,2)>ROUND(s.final_total,2)',
 'negative_refund_amounts_or_quantities'=>'SELECT id FROM refund_items WHERE quantity_refunded<0 OR refund_subtotal<0',
 'sale_items_missing_delivery_snapshot'=>"SELECT id FROM sale_items WHERE stock_delivery_id IS NULL OR stock_delivery_id NOT REGEXP '^[a-f0-9]{32}$'",
 'sale_items_missing_expiry_snapshot'=>'SELECT id FROM sale_items WHERE expiration_date_at_sale IS NULL',
 'branch_inventory_missing_delivery_id'=>"SELECT id FROM branch_products WHERE stock_delivery_id IS NULL OR stock_delivery_id NOT REGEXP '^[a-f0-9]{32}$'",
 'positive_stock_expired_today_or_earlier'=>'SELECT id FROM branch_products WHERE stock>0 AND expiration_date<=CURRENT_DATE()',
 'sale_line_total_mismatches'=>'SELECT s.id FROM sales s JOIN (SELECT sale_id,SUM(subtotal) AS gross,SUM(discount_applied) AS discount,SUM(COALESCE(vat_exemption_amount,0)) AS vat FROM sale_items GROUP BY sale_id) i ON i.sale_id=s.id WHERE ABS(ROUND(s.final_total,2)-ROUND(i.gross-i.discount-i.vat,2))>0.005'
 ];
 $out['checks']=[];foreach($checks as $name=>$sql)$out['checks'][$name]=['count'=>$count($sql),'sample_record_refs'=>$refs($query($sql.' LIMIT 30'),$name)];
 $out['migration_versions']=$query('SELECT version,COUNT(*) AS registrations FROM migrations GROUP BY version ORDER BY version');
 $fk=$query('SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION');$groups=[];foreach($fk as $row)$groups[$row['TABLE_NAME'].'.'.$row['CONSTRAINT_NAME']][]=$row;
 $out['foreign_key_constraints']=count($groups);$out['foreign_key_orphans']=[];
 foreach($groups as $key=>$columns){$notnull=[];$on=[];foreach($columns as $c){$notnull[]='c.'.$quote($c['COLUMN_NAME']).' IS NOT NULL';$on[]='c.'.$quote($c['COLUMN_NAME']).'=p.'.$quote($c['REFERENCED_COLUMN_NAME']);}$out['foreign_key_orphans'][$key]=$count('SELECT 1 FROM '.$quote($columns[0]['TABLE_NAME']).' c LEFT JOIN '.$quote($columns[0]['REFERENCED_TABLE_NAME']).' p ON '.implode(' AND ',$on).' WHERE '.implode(' AND ',$notnull).' AND p.'.$quote($columns[0]['REFERENCED_COLUMN_NAME']).' IS NULL');}
 $out['fk_orphan_total']=array_sum($out['foreign_key_orphans']);
 $out['database_time_basis']=$query('SELECT CURRENT_DATE() AS database_date,@@session.time_zone AS session_time_zone,@@system_time_zone AS system_time_zone')[0];
 $db->rollback();$out['rollback_completed']=true;$after=$query($commands);$out['write_command_counters_unchanged']=$before===$after;$out['write_commands_before']=$before;$out['write_commands_after']=$after;$out['read_query_count']=count($log);$out['read_statement_manifest']=$log;$out['status']='checked';
} catch(Throwable $e) { $db->rollback();$out['rollback_completed']=true;$out['status']='partial';$out['reason']=$e->getMessage(); }
$db->close();$finish();
