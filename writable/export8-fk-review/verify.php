<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
set_exception_handler(static function(Throwable $error):void{fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);});
$sourcePath='C:/Users/warre/Downloads/u315645644_pharxmacotest (8).sql';
$source=file_get_contents($sourcePath);
$sourceHash='817c0021bacd63215125a5ff791a43d55ab858c26d4b8320d46fbb07712ca8b9';
if(hash('sha256',$source)!==$sourceHash)throw new RuntimeException('The supplied export has changed.');

// Values/comments remain data. Only the known export statement types may run in the disposable schema.
function export8Statements(string $sql):array{
    $result=[];$buffer='';$quote=null;$length=strlen($sql);
    for($i=0;$i<$length;$i++){
        $ch=$sql[$i];
        if($quote!==null){
            $buffer.=$ch;
            if($ch==='\\'&&$quote!=='`'&&$i+1<$length)$buffer.=$sql[++$i];
            elseif($ch===$quote){if($i+1<$length&&$sql[$i+1]===$quote)$buffer.=$sql[++$i];else$quote=null;}
        }elseif(in_array($ch,["'",'"','`'],true)){$quote=$ch;$buffer.=$ch;}
        elseif($ch==='#'||(substr($sql,$i,2)==='--'&&ctype_space($sql[$i+2]??' '))){$end=strpos($sql,"\n",$i);$i=$end===false?$length:$end;$buffer.=' ';}
        elseif(substr($sql,$i,2)==='/*'){
            $end=strpos($sql,'*/',$i+2);if($end===false)throw new RuntimeException('Unclosed SQL comment.');
            if(($sql[$i+2]??'')==='!'){
                $body=preg_replace('/^\d+\s*/','',substr($sql,$i+3,$end-$i-3));
                if(!preg_match('/^SET\s/i',$body)||str_contains($body,';'))throw new RuntimeException('Unexpected executable export comment.');
                $buffer.=' '.$body.' ';
            }elseif(($sql[$i+2]??'')==='M')throw new RuntimeException('Unsupported executable MariaDB comment.');
            $i=$end+1;
        }elseif($ch===';'){if(trim($buffer)!=='')$result[]=trim($buffer);$buffer='';}
        else$buffer.=$ch;
    }
    if($quote!==null)throw new RuntimeException('Unclosed SQL value.');if(trim($buffer)!=='')$result[]=trim($buffer);return$result;
}
function export8Syntax(string $sql):string{
    $result='';$quote=null;
    for($i=0,$length=strlen($sql);$i<$length;$i++){
        $ch=$sql[$i];if($quote!==null){if($ch==='\\')$i++;elseif($ch===$quote){if(($sql[$i+1]??'')===$quote)$i++;else$quote=null;}}
        elseif($ch==="'"||$ch==='"'){$quote=$ch;$result.="''";}else$result.=$ch;
    }return$result;
}
$tables=['activity_logs','branches','branch_products','cashier_drawers','cashier_events','cashier_event_reviews','cashier_held_sales','cashier_login_sessions','cashier_shifts','cashier_shift_actions','cashier_shift_reviews','categories','discounts','forecasting_data','migrations','products','receipt_invoice_sequences','refund_items','report_exports','sales','sale_items','stock_logs','suppliers','users','user_presence_sessions'];
$statements=export8Statements($source);$statementCounts=[];
foreach($statements as $statement){
    $syntax=export8Syntax($statement);
    if(preg_match('/\b(?:OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|GLOBAL|PERSIST|TRIGGER|PROCEDURE|FUNCTION|EVENT|DATABASE|SCHEMA)\b/i',$syntax))throw new RuntimeException('Unsafe export operation.');
    if(preg_match('/(?:`[^`]+`|\b[a-z_][a-z_0-9]*\b)\s*\.\s*(?:`[^`]+`|[a-z_][a-z_0-9]*)/i',$syntax))throw new RuntimeException('Cross-database export identifier.');
    if(preg_match('/^(CREATE TABLE|ALTER TABLE|INSERT INTO)\s+`([a-z_]+)`(?=\s|\()/i',$statement,$match)){
        if(!in_array($match[2],$tables,true))throw new RuntimeException('Unexpected export table.');$kind=strtoupper($match[1]);
    }elseif(preg_match('/^SET\s+(?:SQL_MODE|time_zone|NAMES|@OLD_CHARACTER_SET_CLIENT|@OLD_CHARACTER_SET_RESULTS|@OLD_COLLATION_CONNECTION|CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION)\b/i',$statement))$kind='SET';
    elseif(in_array(strtoupper($statement),['START TRANSACTION','COMMIT'],true))$kind=strtoupper($statement);
    else throw new RuntimeException('Unsupported export statement.');
    $statementCounts[$kind]=($statementCounts[$kind]??0)+1;
}
$header="-- Pharxmaco: latest upgrade for the EXISTING database, October 21, 2026.\n"
    ."-- Prepared and verified against u315645644_pharxmacotest (8).sql.\n"
    ."-- Source SHA-256: $sourceHash\n"
    ."-- The export already contains migrations 000014 through 000016. This file adds only 000017.\n"
    ."-- Select your existing database in phpMyAdmin; import this ENTIRE upgrade file.\n"
    ."-- Does not reinsert the export, add demo data, clear shifts or change existing record values.\n\n";
$target=ROOTPATH.'database/upgrades/2026-10-21-latest-existing-database.sql';
$patch=$header.\App\Libraries\CashierForeignKeys::importSql();file_put_contents($target,$patch);
$settings=config(\Config\Database::class)->default;
if(!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local MySQL required.');
$name='phx_export8_fks_'.getmypid().'_'.bin2hex(random_bytes(3));
if(!preg_match('/^phx_export8_fks_[0-9]+_[a-f0-9]{6}$/D',$name)||$name===$settings['database'])throw new RuntimeException('Unsafe temporary name.');
$server=\Config\Database::connect($settings,false);$server->initialize();$server->query('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$checks=[];$assert=static function(bool $condition,string $message)use(&$checks):void{if(!$condition)throw new RuntimeException($message);$checks[]=$message;};
$run=static function($db,string $sql):array{
    $output=[];if(!$db->connID->multi_query($sql))throw new RuntimeException('Import failed.');
    do{if($r=$db->connID->store_result()){$output=array_merge($output,$r->fetch_all(MYSQLI_ASSOC));$r->free();}if(!$db->connID->more_results())break;if(!$db->connID->next_result())throw new RuntimeException('Import continuation failed.');}while(true);
    $db->resetDataCache();return$output;
};
$snapshot=static function($db)use($tables):array{
    $result=[];foreach($tables as $table){$rows=$db->table($table)->get()->getResultArray();$values=[];
        foreach($rows as $row){foreach($row as &$value)if($value!==null)$value=(string)$value;unset($value);$values[]=json_encode($row,JSON_THROW_ON_ERROR);}
        sort($values,SORT_STRING);$result[$table]=['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$values))];
    }return$result;
};
try{
    $db=\Config\Database::connect(array_replace($settings,['database'=>$name]),false);$db->initialize();
    foreach($statements as $statement)$db->query($statement);
    $assert(count($db->listTables())===count($tables),'Supplied phpMyAdmin export imports completely into a disposable database');
    foreach($tables as $table){
        $localColumns=$server->getFieldNames($table);$exportColumns=$db->getFieldNames($table);
        // Retired September snapshot experiment is not read or written by current application code.
        if($table==='sales')$localColumns=array_values(array_diff($localColumns,['discount_snapshot']));
        sort($localColumns);sort($exportColumns);
        if($localColumns!==$exportColumns)throw new RuntimeException('Current application/export column mismatch in '.$table);
    }
    $assert(true,'All 25 application table column sets match current requirements; retired discount_snapshot is not added');
    $before=$snapshot($db);$oldMigrations=$db->table('migrations')->orderBy('id')->get()->getResultArray();
    $assert($db->table('migrations')->where('version','2026_10_21_000016')->countAllResults()===1
        &&$db->table('migrations')->where('version','2026_10_21_000017')->countAllResults()===0,'Export contains 000016 and is missing only latest schema update 000017');
    $orphans=[];foreach(\App\Libraries\CashierForeignKeys::definitions() as [, $table,$column,$parent]){
        $orphans[$table.'.'.$column]=(int)$db->query("SELECT COUNT(*) n FROM `$table` c LEFT JOIN `$parent` p ON p.id=c.`$column` WHERE c.`$column` IS NOT NULL AND p.id IS NULL")->getRowArray()['n'];
    }
    $assert(array_sum($orphans)===0,'All 24 cashier/login/presence references in the supplied export have valid parents');
    $results=$run($db,$patch);$after=$snapshot($db);$beforeData=$before;$afterData=$after;unset($beforeData['migrations'],$afterData['migrations']);
    $assert(str_contains(json_encode($results),'Cashier foreign-key upgrade complete')&&$beforeData===$afterData,'First import completes and preserves every row in all 24 non-migration tables');
    $newMigrations=$db->table('migrations')->orderBy('id')->get()->getResultArray();
    $assert(count($newMigrations)===count($oldMigrations)+1&&array_slice($newMigrations,0,count($oldMigrations))===$oldMigrations,'Exactly migration 000017 is added; all previous migration rows remain unchanged');
    $keys=$db->query("SELECT TABLE_NAME,CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'cashier\_%' OR TABLE_NAME='user_presence_sessions') ORDER BY TABLE_NAME,CONSTRAINT_NAME")->getResultArray();
    $restrictive=count($keys)===24;foreach($keys as $key)$restrictive=$restrictive&&$key['DELETE_RULE']==='RESTRICT'&&$key['UPDATE_RULE']==='RESTRICT';
    $assert($restrictive,'All 24 cashier constraints are enforced with restrictive update/delete rules');
    $types=$db->query("SELECT TABLE_NAME,DATA_TYPE,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='sales' AND COLUMN_NAME='id') OR (TABLE_NAME='cashier_events' AND COLUMN_NAME='sale_id')) ORDER BY TABLE_NAME")->getResultArray();
    $assert($types[0]['COLUMN_TYPE']===$types[1]['COLUMN_TYPE'],'Cashier event sale reference matches the actual parent type and signedness');
    $run($db,$patch);
    $assert($after===$snapshot($db),'Second import changes no rows and creates no duplicate migration records');
    $run($db,file_get_contents(ROOTPATH.'database/upgrades/2026-10-21-cashier-foreign-keys.sql'));
    $assert($after===$snapshot($db),'Generic and export-specific upgrade files are interchangeable without duplicate data');
    $assert(hash_file('sha256',$sourcePath)===$sourceHash,'Original download is unchanged');
    $report=['source'=>basename($sourcePath),'source_sha256'=>$sourceHash,'export_server'=>'MariaDB 11.8.9','verification_server'=>$db->query('SELECT VERSION() v')->getRowArray()['v'],
        'statement_counts'=>$statementCounts,'checks'=>$checks,'counts'=>array_map(static fn($item)=>$item['rows'],$before),
        'before'=>$before,'after'=>$after,'orphan_counts'=>$orphans,'foreign_keys'=>$keys,'upgrade'=>str_replace('\\','/',$target),'upgrade_sha256'=>hash_file('sha256',$target),
        'local_application_database_changed'=>false,'hosting_changed'=>false,'temporary_database_removed'=>true];
    file_put_contents(__DIR__.'/verification-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
    echo json_encode(['passed'=>count($checks),'counts'=>$report['counts'],'orphan_references'=>array_sum($orphans),'constraints'=>count($keys),'verification_server'=>$report['verification_server'],'local_application_database_changed'=>false,'hosting_changed'=>false],JSON_PRETTY_PRINT),PHP_EOL;
}finally{if(isset($db))$db->close();$server->query('DROP DATABASE `'.$name.'`');$server->close();}
