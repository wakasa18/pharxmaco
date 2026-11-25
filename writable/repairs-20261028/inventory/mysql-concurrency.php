<?php
// Synthetic scratch-only real MySQL concurrency audit; never select or copy application data.
require dirname(__DIR__,3) . '/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
$dir=__DIR__;$file=$dir.'/mysql-concurrency.json';
if(!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Loopback database required.');
$safe=static function(string $s):string{if(!preg_match('/^phx_repair_20261028_[a-f0-9]{8}$/D',$s))throw new RuntimeException('Unsafe scratch schema.');return '`'.$s.'`';};
$connect=static function(string $schema)use($settings,$safe){$safe($schema);if($schema===$settings['database'])throw new RuntimeException('Application database refused.');$db=\Config\Database::connect(array_replace($settings,['database'=>$schema,'DBPrefix'=>'','pConnect'=>false,'DBDebug'=>true]),false);$db->initialize();if($db->query('SELECT DATABASE() AS d')->getRowArray()['d']!==$schema)throw new RuntimeException('Unexpected database.');(new ReflectionProperty(\CodeIgniter\Database\Config::class,'instances'))->setValue(null,['tests'=>$db]);return $db;};
$wait=static function(callable $test):void{$deadline=microtime(true)+15;while(!$test()){if(microtime(true)>$deadline)throw new RuntimeException('Bounded concurrency barrier timed out.');usleep(20000);}};
if(($argv[1]??'')==='--worker'){
 [$script,$mode,$schema,$scenario,$index]=$argv;$safe($schema);$db=$connect($schema);$prefix=$dir.'/'.$schema.'-'.$scenario;
 try {
  session()->set(['isLoggedIn'=>true,'user_id'=>(int)$index,'role'=>'admin','branch_id'=>1,'refund_token_1'=>'mysql-refund-'.$index,'stock_adjustment_1_1'=>str_repeat('9',48)]);
  $db->transBegin();$db->query('SELECT COUNT(*) AS n FROM refund_items')->getRowArray(); // Force the earlier RR snapshot.
  file_put_contents($prefix.'-ready-'.$index,'ready');$wait(static fn()=>is_file($prefix.'-go'));
  $post=$scenario==='refund' ? ['_refund_token'=>'mysql-refund-'.$index,'refund'=>[1=>3],'return_condition'=>[1=>'resellable'],'reason'=>'Synthetic concurrent return','refund_method'=>'cash']
   : ['product_id'=>1,'branch_id'=>1,'stock'=>5,'old_stock'=>10,'stock_mode'=>'add','remarks'=>'Synthetic retry delivery','stock_token'=>str_repeat('9',48)];
  $request=new \CodeIgniter\HTTP\IncomingRequest(new \Config\App(),new \CodeIgniter\HTTP\SiteURI(new \Config\App()),null,new \CodeIgniter\HTTP\UserAgent());$request->setMethod('POST');$request->setGlobal('post',$post);$request->setGlobal('request',$post);\Config\Services::injectMock('request',$request);
  $class=$scenario==='refund'?\App\Controllers\Cashier\SalesController::class:\App\Controllers\Products::class;$controller=new $class();$controller->initController($request,service('response'),service('logger'));
  $response=$scenario==='refund'?$controller->refundPartial(1):$controller->updateStock();
  $db->transCommit();echo json_encode(['worker'=>(int)$index,'status'=>$response->getStatusCode(),'error'=>session()->getFlashdata('error'),'success'=>session()->getFlashdata('success')],JSON_THROW_ON_ERROR);exit(0);
 }catch(Throwable $e){$db->transRollback();echo json_encode(['worker'=>(int)$index,'exception'=>get_class($e),'code'=>$e->getCode(),'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);exit(1);}
}
$out=['audit_date'=>'2026-10-28','source_application_database_accessed'=>false,'source_application_database_written'=>false,'source_records_copied'=>false,'synthetic_records_only'=>true,'services_started'=>false,'schema_removed'=>false,'status'=>'not_checked'];
mysqli_report(MYSQLI_REPORT_OFF);$control=mysqli_init();$control->options(MYSQLI_OPT_CONNECT_TIMEOUT,3);
if(!@$control->real_connect($settings['hostname'],$settings['username'],$settings['password'],'',(int)($settings['port']??3306))){$out['reason']='Local MySQL unavailable; no service started.';file_put_contents($file,json_encode($out,JSON_PRETTY_PRINT));echo json_encode($out);exit(0);}
$schema='phx_repair_20261028_'.bin2hex(random_bytes(4));$quoted=$safe($schema);$created=false;$processes=[];
try {
 if(!$control->query('CREATE DATABASE '.$quoted.' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'))throw new RuntimeException('Could not create synthetic scratch schema.');$created=true;$out['scratch_schema']=$schema;$db=$connect($schema);
 $out['mysql_version']=$db->query('SELECT VERSION() AS v')->getRowArray()['v'];$out['transaction_isolation']=$db->query('SELECT @@transaction_isolation AS i')->getRowArray()['i'];
 $ddl=[
 'users'=>'id INT PRIMARY KEY,full_name VARCHAR(100),username VARCHAR(100),password VARCHAR(100),role VARCHAR(20),branch_id INT,status VARCHAR(20),session_version VARCHAR(32),updated_at DATETIME',
 'branches'=>'id INT PRIMARY KEY,branch_name VARCHAR(100),status VARCHAR(20)',
 'products'=>'id INT PRIMARY KEY,product_name VARCHAR(100),sku VARCHAR(50),category_id INT,unit VARCHAR(20),stock INT,cost_price DECIMAL(12,2),price DECIMAL(12,2),reorder_level INT,manufacturer VARCHAR(100),supplier_id INT,status VARCHAR(20),deleted_at DATETIME,is_deleted INT DEFAULT 0,is_permanently_deleted INT DEFAULT 0,permanently_deleted_at DATETIME,created_at DATETIME,updated_at DATETIME',
 'branch_products'=>'id INT AUTO_INCREMENT PRIMARY KEY,product_id INT,branch_id INT,stock INT,reorder_level INT,price DECIMAL(12,2),cost_price DECIMAL(12,2),expiration_date DATE,stock_delivery_id VARCHAR(32),status VARCHAR(20),updated_at DATETIME,deleted_at DATETIME,is_deleted INT DEFAULT 0,is_permanently_deleted INT DEFAULT 0,permanently_deleted_at DATETIME',
 'sales'=>'id INT PRIMARY KEY,invoice_no VARCHAR(100),user_id INT,branch_id INT,discount_id INT,total_amount DECIMAL(12,2),discount_amount DECIMAL(12,2),final_total DECIMAL(12,2),amount_paid DECIMAL(12,2),change_amount DECIMAL(12,2),payment_method VARCHAR(20),reference_no VARCHAR(100),status VARCHAR(20),notes TEXT,sale_date DATETIME,updated_at DATETIME',
 'sale_items'=>'id INT PRIMARY KEY,sale_id INT,product_id INT,product_name_snapshot VARCHAR(100),quantity INT,price DECIMAL(12,2),cost_price_at_sale DECIMAL(12,2),subtotal DECIMAL(12,2),discount_applied DECIMAL(12,2),vat_exemption_amount DECIMAL(12,2),profit DECIMAL(12,2),stock_delivery_id VARCHAR(32),expiration_date_at_sale DATE',
 'refund_items'=>'id INT AUTO_INCREMENT PRIMARY KEY,sale_id INT,sale_item_id INT,product_id INT,product_name_snapshot VARCHAR(100),quantity_refunded INT,price_at_sale DECIMAL(12,2),refund_subtotal DECIMAL(12,2),refunded_by INT,reason TEXT,return_condition VARCHAR(20),refund_method VARCHAR(20),refund_event_id VARCHAR(32),created_at DATETIME,INDEX(sale_id)',
 'stock_logs'=>'id INT AUTO_INCREMENT PRIMARY KEY,product_id INT,branch_id INT,user_id INT,action_type VARCHAR(20),quantity INT,previous_stock INT,new_stock INT,remarks VARCHAR(255),supplier_id INT,manufacturer VARCHAR(100),created_at DATETIME',
 'activity_logs'=>'id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,activity TEXT,log_time DATETIME'
 ];
 foreach($ddl as $t=>$columns)$db->query('CREATE TABLE `'.$t.'` ('.$columns.') ENGINE=InnoDB');
 foreach([1,2] as $id)$db->table('users')->insert(['id'=>$id,'full_name'=>'Synthetic Operator '.$id,'username'=>'synthetic-'.$id,'role'=>'admin','branch_id'=>1,'status'=>'active']);
 $db->table('branches')->insert(['id'=>1,'branch_name'=>'Synthetic Branch','status'=>'active']);
 $db->table('products')->insert(['id'=>1,'product_name'=>'Synthetic Product','sku'=>'SYNTHETIC','unit'=>'tablet','stock'=>10,'price'=>10,'cost_price'=>4,'status'=>'active']);
 $db->table('branch_products')->insert(['id'=>1,'product_id'=>1,'branch_id'=>1,'stock'=>10,'price'=>10,'cost_price'=>4,'status'=>'active','stock_delivery_id'=>str_repeat('a',32),'expiration_date'=>'2099-01-01']);
 $db->table('sales')->insert(['id'=>1,'invoice_no'=>'SYNTHETIC-1','user_id'=>1,'branch_id'=>1,'total_amount'=>50,'discount_amount'=>0,'final_total'=>50,'amount_paid'=>50,'change_amount'=>0,'payment_method'=>'cash','status'=>'completed','sale_date'=>'2026-10-28 10:00:00']);
 $db->table('sale_items')->insert(['id'=>1,'sale_id'=>1,'product_id'=>1,'product_name_snapshot'=>'Synthetic Product','quantity'=>5,'price'=>10,'cost_price_at_sale'=>4,'subtotal'=>50,'discount_applied'=>0,'vat_exemption_amount'=>0,'profit'=>30,'stock_delivery_id'=>str_repeat('a',32),'expiration_date_at_sale'=>'2099-01-01']);
 require_once APPPATH.'Database/Migrations/2026_10_15_000014_add_cashier_shifts.php';(new \App\Database\Migrations\AddCashierShifts(new \CodeIgniter\Database\MySQLi\Forge($db)))->up();
 $db->table('cashier_drawers')->insert(['id'=>2,'branch_id'=>1,'drawer_name'=>'Second synthetic drawer','status'=>'active']);$shifts=new \App\Libraries\CashierShift($db);foreach([1,2] as $id)$shifts->open($id,$id,'100',str_repeat((string)$id,32));
 foreach(['refund','receiving'] as $scenario){
  if($scenario==='receiving'){$db->table('branch_products')->where('id',1)->update(['stock'=>10]);$db->table('products')->where('id',1)->update(['stock'=>10]);$db->query('DELETE FROM stock_logs');}
  $prefix=$dir.'/'.$schema.'-'.$scenario;$processes=[];
  foreach([1,2] as $index){$stdout=$prefix.'-out-'.$index.'.json';$stderr=$prefix.'-err-'.$index.'.txt';$p=proc_open([PHP_BINARY,__FILE__,'--worker',$schema,$scenario,(string)$index],[0=>['pipe','r'],1=>['file',$stdout,'w'],2=>['file',$stderr,'w']],$pipes,ROOTPATH,null,['bypass_shell'=>true]);if(!is_resource($p))throw new RuntimeException('Could not spawn worker.');fclose($pipes[0]);$processes[$index]=[$p,$stdout,$stderr];}
  $wait(static fn()=>is_file($prefix.'-ready-1')&&is_file($prefix.'-ready-2'));file_put_contents($prefix.'-go','go');$workers=[];
  foreach($processes as $index=>[$p,$stdout,$stderr]){$exit=proc_close($p);$workers[$index]=['exit'=>$exit,'result'=>json_decode(file_get_contents($stdout),true),'stderr_bytes'=>filesize($stderr)];} $processes=[];
  $accepted=count(array_filter($workers,static fn($w)=>$w['exit']===0&&is_array($w['result'])&&empty($w['result']['error'])));
  $stock=(int)$db->table('branch_products')->where('id',1)->get()->getRowArray()['stock'];$moves=$db->table('stock_logs')->countAllResults();
  if($scenario==='refund'){$r=$db->query('SELECT COUNT(*) AS n,SUM(quantity_refunded) AS qty,SUM(refund_subtotal) AS amount FROM refund_items')->getRowArray();$pass=$accepted===1&&(int)$r['n']===1&&(int)$r['qty']===3&&(float)$r['amount']===30.0&&$stock===13&&$moves===1;$out[$scenario]=['passed'=>$pass,'workers'=>$workers,'accepted'=>$accepted,'refunded_quantity'=>(int)$r['qty'],'refunded_amount'=>(float)$r['amount'],'stock'=>$stock,'stock_logs'=>$moves];}
  else{$pass=$accepted===1&&$stock===15&&$moves===1;$out[$scenario]=['passed'=>$pass,'workers'=>$workers,'accepted'=>$accepted,'stock'=>$stock,'stock_logs'=>$moves];}
  if(!$pass)throw new RuntimeException('Synthetic '.$scenario.' concurrency check failed.');
 }
 $out['status']='passed';
}catch(Throwable $e){$out['status']='failed';$out['reason']=$e->getMessage();}
finally{foreach($processes as [$p])if(is_resource($p)){proc_terminate($p);proc_close($p);}if(isset($db))$db->close();if($created){$safe($schema);$out['schema_removed']=(bool)$control->query('DROP DATABASE '.$quoted);}$control->close();file_put_contents($file,json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit($out['status']==='passed'?0:1);
