<?php
// Imports and mutations are restricted to a newly created isolated local schema.
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
require __DIR__.'/support.php';
set_exception_handler(static function(Throwable $e):void{fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);});
date_default_timezone_set('Asia/Manila');
$manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$source=file_get_contents($manifest['source_path']);$patch=file_get_contents(ROOTPATH.$manifest['output']);
if(hash('sha256',$source)!==$manifest['source_sha256']||hash('sha256',$patch)!==$manifest['output_sha256'])throw new RuntimeException('Prepared input changed.');
monthValidateExport($source,array_keys($manifest['original_row_counts']));
$patchStatements=monthPmaStatements($patch);
foreach($patchStatements as $statement){$syntax=monthSqlSyntax($statement);
    if(preg_match('/^(?:USE|UPDATE|DELETE|REPLACE|TRUNCATE|ALTER|CREATE DATABASE|DROP DATABASE)\b/i',$syntax))throw new RuntimeException('Unexpected data/schema mutation in patch.');
    if(preg_match('/\b(?:OUTFILE|DUMPFILE|LOAD_FILE|GLOBAL|PERSIST|TRIGGER|PROCEDURE|FUNCTION|EVENT)\b/i',$syntax))throw new RuntimeException('Unsafe import operation.');
    if(preg_match('/^(?:CREATE|DROP)\s/i',$syntax)&&!preg_match('/^(?:CREATE TEMPORARY TABLE|DROP TEMPORARY TABLE(?: IF EXISTS)?) phx_m30_(?:sales|items|logs)\b/i',$syntax))throw new RuntimeException('Only expected temporary tables may be created/dropped.');
    if(preg_match('/^INSERT INTO\s+`?([a-z0-9_]+)`?/i',$syntax,$m)&&!in_array($m[1],['sales','sale_items','stock_logs','phx_m30_sales','phx_m30_items','phx_m30_logs'],true))throw new RuntimeException('Unexpected patch insertion.');
}
$settings=config(\Config\Database::class)->default;
if($isolatedPort=getenv('PHX_FORECAST_REVIEW_PORT')){$settings['hostname']='127.0.0.1';$settings['port']=(int)$isolatedPort;$settings['username']='root';$settings['password']='';}
if(!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Verification requires a local server.');
$admin=new mysqli($settings['hostname'],$settings['username'],$settings['password'],'',(int)$settings['port']);
$admin->set_charset('utf8mb4');$schema='phx_month_demo_review_'.bin2hex(random_bytes(6));$created=false;$db=null;
$assert=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$checks=[];
try{
    $admin->query('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $settings['database']=$schema;$db=\Config\Database::connect($settings,false);$db->initialize();$connection=$db->connID;
    monthExecute($connection,monthSqlStatements($source));
    $snapshot=static function()use($connection):array{$all=[];$tables=$connection->query('SHOW TABLES')->fetch_all(MYSQLI_NUM);
        foreach($tables as [$table]){if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Unexpected table identifier.');
            $key=$table==='receipt_invoice_sequences'?'series_key':'id';$all[$table]=$connection->query('SELECT * FROM `'.$table.'` ORDER BY `'.$key.'`')->fetch_all(MYSQLI_ASSOC);}return $all;};
    $before=$snapshot();$counts=array_map('count',$before);$expected=$manifest['original_row_counts'];ksort($counts);ksort($expected);$assert($counts===$expected,'Export parser/import row counts differ.');
    $assert($connection->query('SELECT DATABASE()')->fetch_row()[0]===$schema,'Export changed selected database.');
    $checks[]='Validated supplied export and imported all records only into an isolated local schema.';

    // A source mismatch must be rejected without changing even the mismatched row.
    $inventoryId=(int)$manifest['pairs'][0]['inventory_id'];
    $connection->query('UPDATE branch_products SET price=price+0.01 WHERE id='.$inventoryId);
    $changed=$snapshot();$result=monthExecute($connection,$patchStatements,true);$assert(str_starts_with($result[count($result)-1][0]['result'],'ROLLED BACK'),'Changed source was not rejected.');
    $assert($snapshot()===$changed,'Rejected import modified source data.');
    $connection->query('UPDATE branch_products SET price=price-0.01 WHERE id='.$inventoryId);$assert($snapshot()===$before,'Mismatch scenario did not restore fixture.');
    $checks[]='Changed source inventory is rejected before any sample data is committed.';

    // Exercise failure continuation: even when a client ignores an FK error,
    // the final consistency check must roll back earlier/later inserts.
    $errors=0;$failureOutput=[];
    foreach($patchStatements as $statement){if(str_starts_with(ltrim($statement),'INSERT INTO sale_items '))$statement=str_replace('s.id,i.`product_id`','s.id,0',$statement);
        try{$part=monthExecute($connection,[$statement],true);$failureOutput=array_merge($failureOutput,$part);}catch(mysqli_sql_exception $e){$errors++;}}
    $assert($errors===1,'Expected one simulated item foreign-key error.');$assert($snapshot()===$before,'Insertion failure leaked partially committed demo data.');
    $assert(str_starts_with($failureOutput[count($failureOutput)-1][0]['result'],'ROLLED BACK'),'Ignored insert error did not trigger rollback.');
    $checks[]='Simulated item insert failure rolls back all demo rows even when client execution continues.';

    $result=monthExecute($connection,$patchStatements,true);$completion=$result[count($result)-1][0];
    $assert($completion['result']==='30-day forecast demo import complete','Import failed: '.json_encode($completion));
    $after=$snapshot();$n=(int)$manifest['added_sales'];
    foreach($before as $table=>$rows){$key=$table==='receipt_invoice_sequences'?'series_key':'id';$indexed=array_column($after[$table],null,$key);
        foreach($rows as $row)$assert(($indexed[$row[$key]]??null)===$row,'Original record changed in '.$table);
        $extra=['sales'=>$n,'sale_items'=>$n,'stock_logs'=>2*$n][$table]??0;$assert(count($after[$table])===count($rows)+$extra,'Unexpected added records in '.$table);}
    $checks[]='Every original record is byte-for-byte retained, including stock, prices, expiry, users, shifts and forecast snapshots.';
    $assert((int)$completion['added_sales']===$n&&(int)$completion['added_stock_movements']===2*$n,'Completion counts mismatch.');
    $marker=$manifest['marker'];$newSales=array_values(array_filter($after['sales'],static fn($s)=>str_starts_with($s['notes']??'',$marker)));
    $newIds=array_fill_keys(array_column($newSales,'id'),true);$newItems=array_values(array_filter($after['sale_items'],static fn($si)=>isset($newIds[$si['sale_id']])));
    $products=array_column($after['products'],null,'id');$sales=array_column($newSales,null,'id');$money=static fn($n)=>(int)round((float)$n*100);
    $quantity=0;$revenue=0;$promoCount=0;
    foreach($newItems as $si){$s=$sales[$si['sale_id']];$subtotal=$money($si['price'])*(int)$si['quantity'];$discount=$money($si['discount_applied']);$total=$subtotal-$discount;
        foreach(['total_amount'] as $field)$assert($money($s[$field])===$subtotal,'Sale subtotal mismatch.');
        foreach(['final_total','amount_paid'] as $field)$assert($money($s[$field])===$total,'Sale payable mismatch.');
        $assert($money($s['discount_amount'])===$discount&&$money($si['subtotal'])===$subtotal,'Discount/item subtotal mismatch.');
        $assert($money($si['profit'])===$total-$money($si['cost_price_at_sale'])*(int)$si['quantity'],'Profit mismatch.');
        $assert($s['user_id']===null&&$s['payment_method']==='cash'&&$s['status']==='completed'&&$money($s['change_amount'])===0,'Unexpected cashier/payment setup.');
        $assert($si['expiration_date_at_sale']>=substr($s['sale_date'],0,10),'Expired demo sale.');
        $p=$products[$si['product_id']];$offers=\App\Libraries\ProductPromotion::all($after['discounts'],[['product_id'=>(int)$si['product_id'],'category_id'=>(int)$p['category_id'],'subtotal'=>$subtotal/100]],substr($s['sale_date'],0,10));
        $assert($money(\App\Libraries\ProductPromotion::total($offers))===$discount,'Demo discount differs from actual promotion logic.');
        $metadata=json_decode($s['receipt_metadata'],true,512,JSON_THROW_ON_ERROR);
        $assert($metadata['product_promotions']==\App\Libraries\ProductPromotion::snapshot($offers),'Promotion snapshot mismatch.');
        $assert($metadata['serial']===null&&$metadata['tin']===''&&$metadata['tax_status']===''&&$metadata['demo_data']===$marker,'Invented seller/registration metadata.');
        $assert($si['generic_name_snapshot']===$p['generic_name']&&$si['strength_snapshot']===$p['strength']&&$si['tax_treatment_snapshot']===$p['tax_treatment'],'Product snapshot mismatch.');
        $quantity+=(int)$si['quantity'];$revenue+=$total;if($discount>0)$promoCount++;
    }
    $assert($quantity===$manifest['added_units']&&$revenue===$money($manifest['added_revenue'])&&$promoCount===$manifest['promo_sales'],'Added totals mismatch.');
    $checks[]='Sale/item totals, profits, product/delivery/expiry snapshots and actual automatic-promotion calculations agree.';
    $pairs=[];
    foreach($manifest['pairs'] as $pair){$key=$pair['branch_id'].':'.$pair['product_id'];$pairs[$key]=$pair;
        $logs=array_values(array_filter($after['stock_logs'],static fn($l)=>(int)$l['branch_id']===$pair['branch_id']&&(int)$l['product_id']===$pair['product_id']));
        usort($logs,static fn($a,$b)=>[$a['created_at'],(int)$a['id']]<=>[$b['created_at'],(int)$b['id']]);$balance=null;
        foreach($logs as $l){if($balance!==null)$assert((int)$l['previous_stock']===$balance,'Stock ledger discontinuity for '.$key);$balance=(int)$l['new_stock'];$assert($balance>=0,'Negative stock balance.');
            if(str_starts_with($l['remarks']??'',$marker)){$delta=$balance-(int)$l['previous_stock'];$assert($delta===($l['action_type']==='stock_in'?1:-1)*(int)$l['quantity'],'Demo stock movement does not balance.');}}
        $assert($balance===$pair['stock'],'Final stock changed for '.$key);
    }
    $checks[]='All original and added stock movements form continuous nonnegative ledgers ending at the unchanged branch balances.';
    foreach(['+00:00','+08:00'] as $zone){$connection->query("SET time_zone='{$zone}'");
        foreach($manifest['pairs'] as $pair){$sql="SELECT COUNT(DISTINCT DATE(s.sale_date)) AS days FROM sales s JOIN sale_items si ON si.sale_id=s.id LEFT JOIN (SELECT sale_item_id,SUM(quantity_refunded) AS qty FROM refund_items GROUP BY sale_item_id) r ON r.sale_item_id=si.id WHERE s.branch_id=? AND si.product_id=? AND s.sale_date>=? AND s.sale_date<=? AND s.status IN ('completed','partially_refunded') AND si.quantity-COALESCE(r.qty,0)>0";
            $days=$db->query($sql,[$pair['branch_id'],$pair['product_id'],$manifest['from'].' 00:00:00',$manifest['to'].' 23:59:59'])->getRowArray();$assert((int)$days['days']===30,'Incomplete 30-day series in '.$zone);}}
    $connection->query("SET time_zone='+00:00'");$checks[]='All 19 pairs have positive demand observations on all 30 days in both UTC and UTC+8.';

    $reflection=new ReflectionClass(\App\Libraries\ReorderForecastService::class);$service=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('db')->setValue($service,$db);
    $windows=[['recommended',$manifest['from'],$manifest['to']],['reports_default',date('Y-m-d',strtotime('-29 days')),date('Y-m-d')],['topbar_90_days',date('Y-m-d',strtotime('-89 days')),date('Y-m-d')],['current_month',date('Y-m-01'),date('Y-m-d')]];
    $forecasts=[];
    foreach($windows as [$label,$from,$to]){$all=$service->generate($from,$to,'',.3,.2,false,null);$selected=array_values(array_filter($all,static fn($r)=>isset($pairs[$r['branch_id'].':'.$r['product_id']])));
        $assert(count($selected)===19,'Missing forecast pairs in '.$label);foreach($selected as $r)$assert($r['suggested_qty']>0&&$r['days_left']!==null,'No positive reorder result in '.$label);
        $forecasts[$label]=['from'=>$from,'to'=>$to,'positive_pairs'=>count($selected),'urgencies'=>array_count_values(array_column($selected,'urgency'))];if($label==='recommended')$forecasts[$label]['results']=$selected;}
    foreach([1=>7,3=>12] as $branch=>$expected){$rows=$service->generate($manifest['from'],$manifest['to'],(string)$branch,.3,.2,false,null);$selected=array_filter($rows,static fn($r)=>isset($pairs[$r['branch_id'].':'.$r['product_id']]));$assert(count($selected)===$expected,'Branch filtering mismatch.');}
    $controllerClass=new ReflectionClass(\App\Controllers\Admin\ReportsController::class);$controller=$controllerClass->newInstanceWithoutConstructor();$controllerClass->getProperty('db')->setValue($controller,$db);$revenueForecasts=[];
    foreach(['','1','3'] as $branch){$history=$controllerClass->getMethod('buildPeriodHistory')->invoke($controller,$manifest['from'],$manifest['to'],$branch,'daily');$forecast=$controllerClass->getMethod('computeHoltForecast')->invoke($controller,$history,'daily');
        $values=array_column($forecast['futureForecast'],'forecast_value');$assert(count($values)===7&&min($values)>0,'Revenue forecast is missing/nonpositive.');$revenueForecasts[$branch===''?'all':$branch]=$forecast['futureForecast'];}
    $assert($snapshot()===$after,'Read-only forecasts changed application records.');
    $checks[]='Actual reorder service returns positive suggestions for 19 pairs in recommended/default/90-day/current-month windows and correct branch scopes.';
    $checks[]='Actual Reports controller produces seven positive daily sales forecasts for all branches and each branch.';

    $repeat=monthExecute($connection,$patchStatements,true);$assert($repeat[count($repeat)-1][0]['result']==='Already complete: no changes'&&$snapshot()===$after,'Repeat import is not a no-op.');
    $checks[]='phpMyAdmin statement extraction and repeated import with diagnostic queries leave all records unchanged.';
    $missing=$newItems[0];$connection->query('DELETE FROM sale_items WHERE id='.(int)$missing['id']);$partial=$snapshot();$retry=monthExecute($connection,$patchStatements,true);
    $assert(str_starts_with($retry[count($retry)-1][0]['result'],'ROLLED BACK')&&$snapshot()===$partial,'Partial fixture was not rejected.');
    $db->table('sale_items')->insert($missing);$assert($snapshot()===$after,'Partial scenario did not restore fixture.');
    $editId=(int)$newSales[0]['id'];$connection->query('UPDATE sales SET notes=CONCAT(notes,\' edited\') WHERE id='.$editId);$edited=$snapshot();$retry=monthExecute($connection,$patchStatements,true);
    $assert(str_starts_with($retry[count($retry)-1][0]['result'],'ROLLED BACK')&&$snapshot()===$edited,'Edited fixture was not rejected.');
    $checks[]='Partial or edited existing fixtures are rejected without overwriting or duplicating them.';
    $assert((int)$connection->query('SELECT @@foreign_key_checks')->fetch_row()[0]===1,'Foreign keys were disabled.');
    $report=['source_sha256'=>$manifest['source_sha256'],'import_sha256'=>$manifest['output_sha256'],'server_version'=>$connection->server_info,'phpmyadmin_parser'=>'PhpMyAdmin SqlParser BufferedQuery (local installation)','period'=>[$manifest['from'],$manifest['to']],'pairs'=>19,'added_sales'=>$n,'added_items'=>$n,'added_stock_movements'=>2*$n,'added_units'=>$quantity,'added_revenue'=>$revenue/100,'promo_sales'=>$promoCount,'reorder_forecasts'=>$forecasts,'next_sales_forecasts'=>$revenueForecasts,'checks'=>$checks,'application_database_modified'=>false,'original_export_modified'=>false];
    file_put_contents(__DIR__.'/verification.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
    unset($report['reorder_forecasts']['recommended']['results'],$report['next_sales_forecasts']);echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{
    if($db){$db->connID->rollback();$db->close();}
    if($created&&preg_match('/^phx_month_demo_review_[a-f0-9]{12}$/D',$schema))$admin->query('DROP DATABASE `'.$schema.'`');
    $admin->close();
}
