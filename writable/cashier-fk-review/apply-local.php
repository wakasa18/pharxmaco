<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
$settings=config(\Config\Database::class)->default;
if(!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local database required.');
$db=\Config\Database::connect($settings,false);$db->initialize();
$dir=__DIR__;$backupDir=WRITEPATH.'backups';if(!is_dir($backupDir))mkdir($backupDir,0777,true);
$stamp=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Ymd-His').'-'.bin2hex(random_bytes(3));
$backup=$backupDir.'/pharxmaco-pre-cashier-fk-'.$stamp.'.sql';
$optionFile=$dir.'/mysql-options-'.$stamp.'.cnf';
$iniValue=static fn($value)=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','\\n','\\r'],(string)$value).'"';
$snapshot=static function($db):array{
    $db->resetDataCache();$tables=[];
    foreach($db->listTables() as $table){
        $encoded=[];$rows=$db->query('SELECT * FROM `'.str_replace('`','``',$table).'`')->getResultArray();
        foreach($rows as $row){foreach($row as &$value)if($value!==null)$value=(string)$value;unset($value);$encoded[]=json_encode($row,JSON_THROW_ON_ERROR);}
        sort($encoded,SORT_STRING);$tables[$table]=['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$encoded))];
    }ksort($tables);return $tables;
};
try{
    foreach(\App\Libraries\CashierForeignKeys::preflightStatements() as $sql)$db->query($sql);
    if((int)$db->query('SELECT @phx_fk_ready n')->getRowArray()['n']!==1)throw new RuntimeException('Local preflight refused.');
    $before=$snapshot($db);$migrations=$db->table('migrations')->orderBy('id')->get()->getResultArray();
    $options="[client]\nuser=".$iniValue($settings['username'])."\npassword=".$iniValue($settings['password'])."\nhost=".$iniValue($settings['hostname'])."\nport=".(int)($settings['port']??3306)."\ndefault-character-set=utf8mb4\n";
    file_put_contents($optionFile,$options);
    $process=proc_open(['C:/UniServerZ/core/mysql/bin/mysqldump.exe','--defaults-extra-file='.$optionFile,'--single-transaction','--quick','--skip-lock-tables','--no-tablespaces','--set-gtid-purged=OFF','--result-file='.$backup,$settings['database']],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true,'suppress_errors'=>true]);
    if(!is_resource($process))throw new RuntimeException('Could not start local backup.');
    fclose($pipes[0]);stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    if($status!==0||!is_file($backup)||filesize($backup)<1000)throw new RuntimeException('Full SQL backup failed; upgrade was not started.');
    unlink($optionFile);
    $run=static function($db):void{
        if(!$db->connID->multi_query(file_get_contents(ROOTPATH.'database/upgrades/2026-10-21-cashier-foreign-keys.sql')))throw new RuntimeException('Local import failed.');
        do{if($result=$db->connID->store_result())$result->free();if(!$db->connID->more_results())break;if(!$db->connID->next_result())throw new RuntimeException('Local import continuation failed.');}while(true);
        $db->resetDataCache();
    };
    $run($db);$run($db);
    $after=$snapshot($db);$beforeData=$before;$afterData=$after;unset($beforeData['migrations'],$afterData['migrations']);
    $nowMigrations=$db->table('migrations')->orderBy('id')->get()->getResultArray();
    $keys=$db->query("SELECT TABLE_NAME,CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'cashier\_%' OR TABLE_NAME='user_presence_sessions') ORDER BY TABLE_NAME,CONSTRAINT_NAME")->getResultArray();
    if($beforeData!==$afterData||array_slice($nowMigrations,0,count($migrations))!==$migrations||count($keys)!==24
        ||(int)$db->table('migrations')->where('version','2026_10_21_000017')->where('namespace','App')->where('group','default')->countAllResults()!==1)throw new RuntimeException('Local post-upgrade verification failed. Keep the pre-upgrade SQL backup.');
    foreach($keys as $key)if($key['DELETE_RULE']!=='RESTRICT'||$key['UPDATE_RULE']!=='RESTRICT')throw new RuntimeException('Unexpected FK policy.');
    $result=['database'=>$settings['database'],'backup'=>$backup,'backup_bytes'=>filesize($backup),'backup_sha256'=>hash_file('sha256',$backup),
        'imports'=>2,'existing_data_tables_preserved'=>count($beforeData),'prior_migrations_preserved'=>true,'migration_000017_count'=>1,
        'constraints'=>$keys,'before'=>$before,'after'=>$after,'hosting_changed'=>false,'shift_reset_executed'=>false];
    file_put_contents($dir.'/local-upgrade-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
    echo json_encode(['database'=>$settings['database'],'constraints'=>count($keys),'data_tables_preserved'=>count($beforeData),'backup'=>$backup,'imports'=>2,'hosting_changed'=>false,'shift_reset_executed'=>false],JSON_PRETTY_PRINT),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");$failed=true;}
finally{if(is_file($optionFile))unlink($optionFile);$db->close();}
if(!empty($failed))exit(1);
