<?php
require dirname(__DIR__, 3) . '/system/Test/bootstrap.php';
require ROOTPATH . 'tests/unit/BusinessLogicTest.php';
$results = [];
$fixture = static function (): BusinessLogicTest { $t = new BusinessLogicTest('testQuarantineReleaseUpdatesCorrectStockAndKeepsOriginalFinancialRecords'); (new ReflectionMethod($t, 'setUp'))->invoke($t); return $t; };
$call = static fn($t,$name,...$args) => (new ReflectionMethod($t,$name))->invoke($t,...$args);
$dbOf = static fn($t) => (new ReflectionProperty($t,'db'))->getValue($t);
$close = static fn($t) => (new ReflectionMethod($t,'tearDown'))->invoke($t);
$t = $fixture();
try {
 $db=$dbOf($t); $q=$call($t,'quarantineFixture');
 $db->table('sales')->where('id',1)->update(['status'=>'partially_refunded']);
 $report=$call($t,'controller',App\Controllers\Admin\ReportsController::class,[]);
 $sql=(new ReflectionMethod($report,'refundedCogsProfitJoinSql'))->invoke($report);
 $summary=static fn()=>$db->query('SELECT SUM(si.cost_price_at_sale*si.quantity-COALESCE(rf.refunded_cogs,0)) cogs, SUM(si.profit-COALESCE(rf.refunded_profit,0)) gross_profit FROM sale_items si LEFT JOIN '.$sql.' ON rf.sale_item_id=si.id')->getRowArray();
 $before=$summary();
 $q->resolve(1,1,$call($t,'quarantineInput'),App\Libraries\Quarantine::revision($q->item(1,1)));
 $after=$summary();
 $results['quarantine_release_reporting']=['stock_before'=>10,'stock_after'=>(int)$db->table('branch_products')->where('id',1)->get()->getRow('stock'),'refund_condition'=>$db->table('refund_items')->where('id',1)->get()->getRow('return_condition'),'resolution'=>$q->item(1,1)['resolution']['outcome'],'before'=>$before,'after'=>$after,'expected_recovered_cost'=>8,'expected_cogs_after'=>12,'expected_gross_profit_after'=>18];
} finally {$close($t);}
foreach(['gcash','card'] as $method) {
 $t=$fixture();
 try {
  $db=$dbOf($t);$token=str_repeat('b',32);session()->set(['cart'=>[1=>['product_id'=>1,'quantity'=>1]],'checkout_token'=>$token]);
  $c=$call($t,'controller',App\Controllers\Cashier\SalesController::class,['_checkout_token'=>$token,'items'=>[1=>1],'payment_method'=>$method,'amount_paid'=>'100','reference_no'=>'']);
  $c->checkout();$s=$db->table('sales')->where('id',2)->get()->getRowArray();
  $results['digital_'.$method]=array_intersect_key($s??[],array_flip(['id','final_total','amount_paid','change_amount','payment_method','reference_no','status']));
 } finally {$close($t);}
}
$t=$fixture();
try {
 $db=$dbOf($t);$db->table('discounts')->insert(['id'=>1,'discount_name'=>'Automatic product promo','discount_type'=>'percentage','discount_value'=>50,'applies_to'=>'product','product_id'=>1,'minimum_purchase'=>0,'status'=>'active']);
 $token=str_repeat('c',32);session()->set(['cart'=>[1=>['product_id'=>1,'quantity'=>1]],'checkout_token'=>$token]);
 $call($t,'controller',App\Controllers\Cashier\SalesController::class,['_checkout_token'=>$token,'items'=>[1=>1],'discount_id'=>'','payment_method'=>'cash','amount_paid'=>'10'])->checkout();
 $s=$db->table('sales')->where('id',2)->get()->getRowArray();
 $results['automatic_promo_empty_selection']=array_intersect_key($s??[],array_flip(['id','total_amount','discount_amount','final_total','status']));
} finally {$close($t);}
$t=$fixture();
try {
 $db=$dbOf($t);$service=new App\Libraries\ReorderForecastService();
 foreach ([['future_low',10,'2099-01-01'],['expired_low',10,date('Y-m-d')],['expired_above_threshold',150,date('Y-m-d')]] as [$label,$stock,$expiry]) {
  $db->table('branch_products')->where('id',1)->update(['stock'=>$stock,'reorder_level'=>100,'expiration_date'=>$expiry]);
  $rows=$service->generate('2026-09-14','2026-09-14','1',0.3,0.2,false,null);
  $results['reorder_'.$label]=$rows;
 }
} finally {$close($t);}
$t=$fixture();
try {
 $db=$dbOf($t);$post=$call($t,'statutoryCheckoutPost','pwd',['include_product_promos'=>'0']);
 $db->table('discounts')->insert(['id'=>1,'discount_name'=>'Half price eligible product','discount_type'=>'percentage','discount_value'=>50,'applies_to'=>'product','product_id'=>1,'minimum_purchase'=>0,'status'=>'active']);
 $call($t,'controller',App\Controllers\Cashier\SalesController::class,$post)->checkout();
 $s=$db->table('sales')->where('id',2)->get()->getRowArray();
 $results['pwd_promo_flag_disabled']=array_intersect_key($s??[],array_flip(['id','total_amount','discount_amount','final_total','status']));
} finally {$close($t);}
$t=$fixture();
try {
 $db=$dbOf($t);$db->query('CREATE TABLE report_exports (id INTEGER PRIMARY KEY, user_id INTEGER, branch_id INTEGER, export_type TEXT, date_from TEXT, date_to TEXT, gross_sales NUMERIC, net_sales NUMERIC, total_discount NUMERIC, cogs NUMERIC, gross_profit NUMERIC, total_transactions INTEGER, created_at TEXT)');
 $c=$call($t,'controller',App\Controllers\Admin\ReportsController::class,[]);
 set_error_handler(static function($n,$message,$file,$line){throw new ErrorException($message,0,$n,$file,$line);});
 try {$c->exportExcel();$error=null;} catch(Throwable $e){$error=['message'=>$e->getMessage(),'file'=>str_replace(ROOTPATH,'',$e->getFile()),'line'=>$e->getLine()];} finally{restore_error_handler();}
 $results['sales_csv']=['error'=>$error,'history_rows_after_failure'=>$db->table('report_exports')->countAllResults()];
} finally {$close($t);}
$t=$fixture();
try {
 $db=$dbOf($t);$db->table('sales')->where('id',1)->update(['status'=>'refunded']);
 $db->table('refund_items')->insert(['sale_id'=>1,'sale_item_id'=>1,'product_id'=>1,'quantity_refunded'=>5,'refund_subtotal'=>50,'return_condition'=>'damaged','refund_method'=>'cash','created_at'=>'2026-09-14 11:00:00']);
 $r=$call($t,'controller',App\Controllers\Admin\ReportsController::class,[]);
 $data=(new ReflectionMethod($r,'buildReportData'))->invoke($r,'2026-09-14','2026-09-14','2026-09-14','2026-09-14','1');
 $results['fully_refunded_damaged_report']=['salesSummary'=>$data['salesSummary'],'profitSummary'=>$data['profitSummary'],'reportHistory'=>$data['reportHistory'],'cash_movements'=>$data['cashMovements']];
} finally {$close($t);}
file_put_contents(__DIR__.'/results.json',json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
