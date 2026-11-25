<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); });
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true) || $settings['database']!=='pharxmaco_drugstore') throw new RuntimeException('Known local application database required.');
$credentials=json_decode(file_get_contents(__DIR__.'/credentials.json'),true,512,JSON_THROW_ON_ERROR);
$sql=file_get_contents(ROOTPATH.'database/upgrades/2026-10-15-demo-users.sql');
if (preg_match('/^\s*(?:UPDATE|DELETE|DROP|ALTER|REPLACE|TRUNCATE|USE|CREATE)\b/im',$sql)) throw new RuntimeException('Demo seed must only add missing accounts.');
$run=static function($db) use($sql): void {
    if (!$db->connID->multi_query($sql)) throw new RuntimeException($db->connID->error);
    do { if ($result=$db->connID->store_result()) $result->free(); if (!$db->connID->more_results()) break; if (!$db->connID->next_result()) throw new RuntimeException($db->connID->error); } while(true);
};
$snapshot=static function($db): array {
    $state=[];
    foreach ($db->query('SHOW TABLES')->getResultArray() as $row) {
        $table=reset($row); if (!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Unexpected table name.');
        $state[$table]=$db->table($table)->orderBy($table==='receipt_invoice_sequences'?'series_key':'id')->get()->getResultArray();
    }
    return $state;
};
$assert=static function(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); };
$base=\Config\Database::connect('default'); $base->initialize();
if (($argv[1] ?? '')==='--apply-local') {
    if (!is_file(__DIR__.'/verification.json')) throw new RuntimeException('Verify the seed in isolation first.');
    $verification=json_decode(file_get_contents(__DIR__.'/verification.json'),true,512,JSON_THROW_ON_ERROR);
    $assert($verification['sql_sha256']===hash('sha256',$sql),'Seed changed after verification.');
    $before=$snapshot($base); $run($base); $after=$snapshot($base);
    foreach ($before as $table=>$rows) {
        if ($table!=='users') $assert($after[$table]===$rows,'Other application data changed: '.$table);
        else { $indexed=array_column($after['users'],null,'id'); foreach ($rows as $row) $assert($indexed[$row['id']]===$row,'Existing user changed.'); }
    }
    $run($base); $assert($snapshot($base)===$after,'Reimport changed account data.');
    $beforeIds=array_column($before['users'],'id'); $created=[];
    foreach ($after['users'] as $row) if (!in_array($row['id'],$beforeIds,true)) {
        $assert(in_array($row['username'],array_column($credentials['accounts'],'username'),true),'Unexpected new account.');
        $assert(password_verify($credentials['password'],$row['password']),'New account password is invalid.');
        $created[]=$row['username'];
    }
    $report=['database'=>$base->database,'created'=>$created,'existing_users_preserved'=>count($before['users']),
        'other_application_records_preserved'=>true,'reimport_unchanged'=>true,'sql_sha256'=>hash('sha256',$sql)];
    file_put_contents(__DIR__.'/local-application.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL; exit;
}
$schema='phx_demo_users_review_'.bin2hex(random_bytes(6)); $created=false; $db=null;
$instances=new ReflectionProperty(\CodeIgniter\Database\Config::class,'instances'); $oldInstances=$instances->getValue();
try {
    $base->query('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    $settings['database']=$schema; $db=\Config\Database::connect($settings,false); $db->initialize();
    $db->query('CREATE TABLE branches (id INT PRIMARY KEY, branch_name VARCHAR(100), status VARCHAR(20)) ENGINE=InnoDB');
    $db->query('CREATE TABLE users LIKE `pharxmaco_drugstore`.users');
    $db->query('CREATE TABLE activity_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, activity TEXT, log_time DATETIME) ENGINE=InnoDB');
    foreach ([[1,'Inactive branch','inactive'],[10,'Other branch','active'],[20,'Main Branch','active']] as [$id,$name,$status]) $db->table('branches')->insert(['id'=>$id,'branch_name'=>$name,'status'=>$status]);
    $db->table('users')->insert(['full_name'=>'Existing Staff','username'=>'existing_staff','password'=>password_hash('ExistingFixtureOnly!',PASSWORD_DEFAULT),'role'=>'cashier','branch_id'=>10,'status'=>'active','session_version'=>str_repeat('a',32)]);
    $original=$snapshot($db); $run($db); $first=$snapshot($db);
    $assert(count($first['users'])===4,'Expected exactly three demo accounts.');
    $assert($first['users'][0]===$original['users'][0],'Existing user was overwritten.');
    foreach ($credentials['accounts'] as $account) {
        $row=$db->table('users')->where('username',$account['username'])->get()->getRowArray();
        $assert($row['role']===$account['role'] && $row['status']==='active','Demo role or status is invalid.');
        $assert($account['role']==='admin' ? $row['branch_id']===null : (int)$row['branch_id']===20,'Active Main Branch was not selected correctly.');
        $assert(password_verify($credentials['password'],$row['password']),'Demo password is incompatible.');
    }
    $run($db); $assert($snapshot($db)===$first,'Repeated import changed existing accounts.');
    // Run the real authentication controller with an isolated database and dummy throttle cache.
    $instances->setValue(null,['tests'=>$db]);
    \Config\Services::injectMock('cache',new \CodeIgniter\Cache\Handlers\DummyHandler(config(\Config\Cache::class)));
    $sessionConfig=config(\Config\Session::class);
    \Config\Services::injectMock('session',new \CodeIgniter\Test\Mock\MockSession(new \CodeIgniter\Session\Handlers\ArrayHandler($sessionConfig,'0.0.0.0'),$sessionConfig));
    helper(['url','form']);
    foreach ($credentials['accounts'] as $account) {
        $app=new \Config\App(); $request=new \CodeIgniter\HTTP\IncomingRequest($app,new \CodeIgniter\HTTP\SiteURI($app),null,new \CodeIgniter\HTTP\UserAgent());
        $post=['username'=>$account['username'],'password'=>$credentials['password']];
        $request->setMethod('POST'); $request->setGlobal('post',$post); $request->setGlobal('request',$post);
        \Config\Services::injectMock('request',$request); service('validation')->reset();
        $auth=new \App\Controllers\Auth(); $auth->initController($request,service('response'),service('logger'));
        $response=$auth->attemptLogin();
        $assert(session('username')===$account['username'] && \App\Libraries\AccountSession::check(),'Real demo login failed: '.(string)session()->getFlashdata('error'));
        $assert(str_ends_with($response->getHeaderLine('Location'),$account['role']==='admin'?'/dashboard':'/cashier/start-shift'),'Wrong login destination.');
    }
    $db->table('users')->where('username','demo_admin')->update(['password'=>password_hash('ChangedByOwner!',PASSWORD_DEFAULT),'role'=>'cashier','branch_id'=>10,'status'=>'inactive','session_version'=>str_repeat('f',32)]);
    $edited=$snapshot($db); $run($db); $assert($snapshot($db)===$edited,'Existing edited or inactive demo user was reset.');
    $db->table('branches')->update(['status'=>'inactive']);
    $db->table('users')->where('username','demo_cashier2')->update(['username'=>'previous_demo_cashier2']);
    $inactive=$snapshot($db); $run($db); $assert($snapshot($db)===$inactive,'Created users without an active branch.');
    $report=['sql_sha256'=>hash('sha256',$sql),'demo_accounts'=>3,'real_logins_verified'=>array_column($credentials['accounts'],'username'),
        'checks'=>['Existing users preserved.','Both cashiers assigned to active Main Branch.','Reimport leaves accounts and passwords unchanged.',
            'Owner-edited permissions, password and inactive status preserved.','No active branch creates no accounts.','Real Auth controller and session validation passed for all three accounts.'],
        'live_application_modified'=>false];
    file_put_contents(__DIR__.'/verification.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $instances->setValue(null,$oldInstances);
    if ($db) $db->close();
    if ($created && preg_match('/^phx_demo_users_review_[a-f0-9]{12}$/D',$schema)) $base->query('DROP DATABASE `'.$schema.'`');
}
