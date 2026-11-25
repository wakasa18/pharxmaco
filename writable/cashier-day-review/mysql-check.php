<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
use App\Libraries\CashierShift;
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); });
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Local server required.');
$connect=static function(string $schema) use($settings) { $settings['database']=$schema; $db=\Config\Database::connect($settings,false); $db->initialize(); return $db; };
if (($argv[1] ?? '')==='worker') {
    $schema=$argv[2] ?? ''; if (!preg_match('/^phx_cashier_money_review_[a-f0-9]{12}$/D',$schema)) throw new RuntimeException('Invalid worker schema.');
    $db=$connect($schema); $db->query('SET SESSION innodb_lock_wait_timeout = 5');
    file_put_contents(__DIR__.'/'.$schema.'-'.$argv[3].'.ready','ready');
    try { (new CashierShift($db))->open((int)$argv[3],1,'100',$argv[4]); echo "OPEN\n"; }
    catch(InvalidArgumentException $e) { echo "REJECT\n"; }
    $db->close(); exit(0);
}
$base=\Config\Database::connect('default'); $base->initialize();
$schema='phx_cashier_money_review_'.bin2hex(random_bytes(6)); $created=false; $connections=[]; $processes=[];
$assert=static function(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); };
try {
    $base->query('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    $one=$connect($schema); $two=$connect($schema); $connections=[$one,$two];
    $one->query('CREATE TABLE users (id INT PRIMARY KEY, full_name VARCHAR(100), role VARCHAR(20), status VARCHAR(20), branch_id INT) ENGINE=InnoDB');
    $one->query('CREATE TABLE branches (id INT PRIMARY KEY, branch_name VARCHAR(100), status VARCHAR(20)) ENGINE=InnoDB');
    $one->query('CREATE TABLE activity_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, activity TEXT, log_time DATETIME) ENGINE=InnoDB');
    foreach ([1,2] as $id) $one->table('branches')->insert(['id'=>$id,'branch_name'=>'Branch '.$id,'status'=>'active']);
    foreach ([1,2,3] as $id) $one->table('users')->insert(['id'=>$id,'full_name'=>'Cashier '.$id,'role'=>$id===1?'admin':'cashier','status'=>'active','branch_id'=>1]);
    require_once APPPATH.'Database/Migrations/2026_10_15_000014_add_cashier_shifts.php';
    (new \App\Database\Migrations\AddCashierShifts(new \CodeIgniter\Database\MySQLi\Forge($one)))->up();
    $a=new CashierShift($one); $b=new CashierShift($two);
    $id=$a->open(1,1,'100',str_repeat('a',32)); $old=$a->summary($a->activeFor(1));
    $one->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $one->transBegin(); $one->query('SELECT id FROM cashier_events')->getResultArray();
    $b->cashMovement(1,1,'cash_in','20','Concurrent top-up',str_repeat('b',32));
    try { $a->close($id,1,'100','100','',$old['revision'],null); throw new RuntimeException('Stale snapshot closed a drawer.'); }
    catch(InvalidArgumentException $e) { $assert(str_contains($e->getMessage(),'New drawer activity'),'Wrong stale count rejection.'); }
    $one->transRollback();
    $current=$a->summary($a->activeFor(1)); $assert($current['expected_cash']===120.0,'Missing committed money.');
    $one->transBegin(); $one->query('SELECT id FROM cashier_shifts')->getResultArray();
    $b->close($id,1,'120','100','Counted handover',$current['revision'],2);
    try { $a->open(3,1,'100',str_repeat('c',32)); throw new RuntimeException('Stale snapshot bypassed handover.'); }
    catch(InvalidArgumentException $e) { $assert(str_contains($e->getMessage(),'reserved'),'Wrong handover rejection.'); }
    $one->transRollback();
    $next=$a->open(2,1,'100',str_repeat('d',32)); $summary=$a->summary($a->activeFor(2));
    $a->close($next,2,'100','100','',$summary['revision'],null);
    // Hold the drawer while two independent PHP connections attempt to open it.
    $one->transBegin(); $one->query('SELECT id FROM cashier_drawers WHERE id=1 FOR UPDATE');
    foreach ([2,3] as $user) {
        $pipes=[]; $process=proc_open([PHP_BINARY,__FILE__,'worker',$schema,(string)$user,str_repeat((string)$user,32)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOTPATH);
        if (!is_resource($process)) throw new RuntimeException('Could not start concurrency worker.');
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
        $processes[]=['process'=>$process,'out'=>$pipes[1],'err'=>$pipes[2],'text'=>'','ready'=>__DIR__.'/'.$schema.'-'.$user.'.ready'];
    }
    $deadline=microtime(true)+4;
    do {
        $ready=true;
        foreach ($processes as $worker) $ready=$ready && is_file($worker['ready']);
        if ($ready) break;
        usleep(50000);
    } while (microtime(true)<$deadline);
    $one->transCommit();
    if (!$ready) {
        foreach ($processes as $worker) fwrite(STDERR,$worker['text'].stream_get_contents($worker['err']));
        throw new RuntimeException('Concurrency workers did not start.');
    }
    $results=[];
    foreach ($processes as &$worker) {
        stream_set_blocking($worker['out'],true); $worker['text'].=stream_get_contents($worker['out']); $error=stream_get_contents($worker['err']);
        fclose($worker['out']); fclose($worker['err']); $exit=proc_close($worker['process']); $worker['process']=null;
        $assert($exit===0 && $error==='', 'Concurrency worker failed: '.$error); $results[]=$worker['text'];
    } unset($worker);
    $assert(count(array_filter($results,static fn($result)=>str_contains($result,'OPEN')))===1,'Two cashiers opened the shared drawer.');
    $assert(count(array_filter($results,static fn($result)=>str_contains($result,'REJECT')))===1,'Competing cashier was not rejected.');
    $assert($one->table('cashier_shifts')->where('status','open')->countAllResults()===1,'Invalid open-shift count.');
    file_put_contents(__DIR__.'/mysql-checks.json',json_encode(['passed'=>['Stale repeatable-read snapshots detect newly committed money.','Named handover cannot be bypassed by an older snapshot.','Two simultaneous opening requests produce exactly one active cashier.'],'live_sales_modified'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo "Passed native MySQL snapshot, handover and concurrent drawer opening checks.\n";
} finally {
    foreach ($processes as $worker) {
        if (is_resource($worker['process'])) proc_terminate($worker['process']);
        if (is_file($worker['ready'])) unlink($worker['ready']);
    }
    foreach ($connections as $db) $db->close();
    if ($created && preg_match('/^phx_cashier_money_review_[a-f0-9]{12}$/D',$schema)) $base->query('DROP DATABASE `'.$schema.'`');
}
