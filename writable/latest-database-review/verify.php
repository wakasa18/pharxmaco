<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
set_exception_handler(static function(Throwable $error): void { fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1); });
$manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$source=file_get_contents($manifest['source_path']); $patch=file_get_contents($manifest['upgrade']);
require ROOTPATH.'writable/product-catalog-review/support.php';
$catalog=catalogManifest();
$catalogStatements=latestSqlStatements(file_get_contents(ROOTPATH.$catalog['upgrade']));
require ROOTPATH.'writable/branded-products-review/support.php';
$branded=brandedManifest();
$brandedStatements=latestSqlStatements(file_get_contents(ROOTPATH.$branded['upgrade']));
if (hash('sha256',$source)!==$manifest['source_sha256']) throw new RuntimeException('Source export changed after preparation.');

// Quoted values and comments are data, not instructions or statement boundaries.
function latestSqlStatements(string $sql): array {
    $result=[]; $buffer=''; $quote=null; $length=strlen($sql);
    for ($i=0;$i<$length;$i++) {
        $ch=$sql[$i];
        if ($quote!==null) {
            $buffer.=$ch;
            if ($ch==='\\' && $quote!=='`' && $i+1<$length) $buffer.=$sql[++$i];
            elseif ($ch===$quote) { if ($i+1<$length && $sql[$i+1]===$quote) $buffer.=$sql[++$i]; else $quote=null; }
        } elseif (in_array($ch,["'",'"','`'],true)) { $quote=$ch; $buffer.=$ch; }
        elseif ($ch==='#' || (substr($sql,$i,2)==='--' && ctype_space($sql[$i+2] ?? ' '))) {
            $end=strpos($sql,"\n",$i); $i=$end===false?$length:$end; $buffer.=' ';
        } elseif (substr($sql,$i,2)==='/*') {
            $end=strpos($sql,'*/',$i+2); if ($end===false) throw new RuntimeException('Unclosed SQL comment.');
            if (($sql[$i+2] ?? '')==='!') {
                $body=preg_replace('/^\d+\s*/','',substr($sql,$i+3,$end-$i-3));
                if (!preg_match('/^SET\s/i',$body) || str_contains($body,';')) throw new RuntimeException('Unexpected executable export comment.');
                $buffer.=' '.$body.' ';
            } elseif (($sql[$i+2] ?? '')==='M') throw new RuntimeException('Unsupported executable MariaDB comment.');
            $i=$end+1;
        } elseif ($ch===';') { if (trim($buffer)!=='') $result[]=trim($buffer); $buffer=''; }
        else $buffer.=$ch;
    }
    if ($quote!==null) throw new RuntimeException('Unclosed SQL value.');
    if (trim($buffer)!=='') $result[]=trim($buffer);
    return $result;
}
function latestSqlSyntax(string $statement): string {
    $result=''; $quote=null;
    for ($i=0,$length=strlen($statement);$i<$length;$i++) {
        $ch=$statement[$i];
        if ($quote!==null) {
            if ($ch==='\\') $i++;
            elseif ($ch===$quote) { if (($statement[$i+1] ?? '')===$quote) $i++; else $quote=null; }
        } elseif ($ch==="'" || $ch==='"') { $quote=$ch; $result.="''"; }
        else $result.=$ch;
    }
    return $result;
}
$allowedTables=['activity_logs','branches','branch_products','categories','discounts','forecasting_data','migrations','products','receipt_invoice_sequences','refund_items','report_exports','sales','sale_items','stock_logs','suppliers','users'];
foreach (latestSqlStatements($source) as $statement) {
    $syntax=latestSqlSyntax($statement);
    if (preg_match('/\b(?:OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|GLOBAL|PERSIST|TRIGGER|PROCEDURE|FUNCTION|EVENT|DATABASE|SCHEMA)\b/i',$syntax)) throw new RuntimeException('Unsafe export operation.');
    if (preg_match('/(?:`[^`]+`|\b[a-z_][a-z_0-9]*\b)\s*\.\s*(?:`[^`]+`|[a-z_][a-z_0-9]*)/i',$syntax)) throw new RuntimeException('Cross-database export identifier.');
    if (preg_match('/^(?:CREATE TABLE|ALTER TABLE|INSERT INTO)\s+`([a-z_]+)`(?=\s|\()/i',$statement,$match)) {
        if (!in_array($match[1],$allowedTables,true)) throw new RuntimeException('Unexpected export table.');
    } elseif (preg_match('/^SET\s+(?:SQL_MODE|time_zone|NAMES|@OLD_CHARACTER_SET_CLIENT|@OLD_CHARACTER_SET_RESULTS|@OLD_COLLATION_CONNECTION|CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION)\b/i',$statement)) {
        // Supported phpMyAdmin session settings only.
    } elseif (!in_array(strtoupper($statement),['START TRANSACTION','COMMIT'],true)) throw new RuntimeException('Unsupported export statement.');
}
foreach (latestSqlStatements($patch) as $statement) {
    if (preg_match('/^(?:USE|DROP|TRUNCATE|DELETE|REPLACE|ALTER|CREATE DATABASE)\b/i',$statement)) throw new RuntimeException('Upgrade has a destructive operation.');
    if (preg_match('/^UPDATE\b/i',$statement) && !in_array($statement,$catalogStatements,true)) throw new RuntimeException('Upgrade has an unreviewed product modification.');
    if (preg_match('/^CREATE TABLE IF NOT EXISTS `([a-z_]+)`/i',$statement,$match) && !in_array($match[1],$manifest['expected_new_tables'],true)) throw new RuntimeException('Unexpected new upgrade table.');
    if (preg_match('/^INSERT INTO\s+`?([a-z_]+)`?/i',$statement,$match) && !in_array($match[1],['migrations','cashier_drawers','users'],true)
        && !(in_array($match[1],['products','branch_products','stock_logs'],true) && in_array($statement,$brandedStatements,true))) throw new RuntimeException('Unexpected upgrade insertion.');
}
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Local verification server required.');
$base=\Config\Database::connect('default'); $base->initialize();
$schema='phx_latest_upgrade_review_'.bin2hex(random_bytes(6)); $created=false; $db=null;
$assert=static function(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); };
try {
    $base->query('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    $settings['database']=$schema; $db=\Config\Database::connect($settings,false); $db->initialize();
    $run=static function(string $sql) use($db): void {
        if (!$db->connID->multi_query($sql)) throw new RuntimeException($db->connID->error);
        do { if ($result=$db->connID->store_result()) $result->free(); if (!$db->connID->more_results()) break; if (!$db->connID->next_result()) throw new RuntimeException($db->connID->error); } while(true);
    };
    $snapshot=static function() use($db): array {
        $state=[];
        foreach ($db->query('SHOW TABLES')->getResultArray() as $row) {
            $table=reset($row); if (!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Unexpected table name.');
            $state[$table]=$db->table($table)->orderBy($table==='receipt_invoice_sequences'?'series_key':'id')->get()->getResultArray();
        }
        return $state;
    };
    $run($source);
    $assert($db->query('SELECT DATABASE() AS name')->getRowArray()['name']===$schema,'Export changed selected database.');
    $before=$snapshot();
    $importedCounts=array_map('count',$before); $expectedCounts=$manifest['original_row_counts']; ksort($importedCounts); ksort($expectedCounts);
    $assert($importedCounts===$expectedCounts,'Parsed export row counts do not match the imported database: '.json_encode($importedCounts));
    $run($patch); $db->resetDataCache(); $after=$snapshot();
    $newTables=array_values(array_diff(array_keys($after),array_keys($before))); $expected=$manifest['expected_new_tables']; sort($newTables); sort($expected);
    $assert($newTables===$expected,'Unexpected new tables.');
    foreach ($before as $table=>$oldRows) {
        if ($table==='products') {
            $oldIds=array_column($oldRows,'id');
            $retained=array_values(array_filter($after[$table],static fn($row)=>in_array($row['id'],$oldIds,true)));
            $assert($retained===catalogExpected($oldRows,$catalog),'Unexpected original product field changed.');
        }
        elseif (!in_array($table,['migrations','users','branch_products','stock_logs'],true)) $assert($after[$table]===$oldRows,'Existing application data changed: '.$table);
        else {
            $indexed=array_column($after[$table],null,'id');
            foreach ($oldRows as $row) $assert(($indexed[$row['id']] ?? null)===$row,'Existing record changed: '.$table);
        }
    }
    $assert(count($after['migrations'])===count($before['migrations'])+1,'Expected exactly one new migration.');
    $matches=array_filter($after['migrations'],static fn($m)=>$m['version']==='2026_10_15_000014' && $m['namespace']==='App' && $m['group']==='default');
    $assert(count($matches)===1,'Cashier migration must be registered once.');
    $assert(count($after['cashier_drawers'])===count($after['branches']),'Expected one shared drawer per branch.');
    foreach ($after['cashier_drawers'] as $drawer) $assert($drawer['active_shift_id']===null && $drawer['last_shift_id']===null,'Historical drawer ownership was invented.');
    foreach (array_diff($expected,['cashier_drawers']) as $table) $assert($after[$table]===[],'Historical shift or sale data was invented.');
    $credentials=json_decode(file_get_contents(ROOTPATH.'writable/demo-user-review/credentials.json'),true,512,JSON_THROW_ON_ERROR);
    $oldIds=array_column($before['users'],'id'); $newUsers=array_values(array_filter($after['users'],static fn($user)=>!in_array($user['id'],$oldIds,true)));
    $assert(count($newUsers)===3,'Expected three missing demo accounts.');
    $demo=[];
    foreach ($newUsers as $user) {
        $assert(in_array($user['username'],$manifest['demo_usernames'],true),'Unexpected demo user.');
        $assert(password_verify($credentials['password'],$user['password']),'Demo password is invalid.');
        $assert($user['status']==='active','New demo account is not active.'); $demo[$user['username']]=$user;
    }
    $assert($demo['demo_admin']['role']==='admin' && $demo['demo_admin']['branch_id']===null,'Demo administrator setup is invalid.');
    foreach (['demo_cashier1','demo_cashier2'] as $name) $assert($demo[$name]['role']==='cashier' && $demo[$name]['branch_id']===$demo['demo_cashier1']['branch_id'],'Demo cashiers must share a branch.');
    $run($patch); $assert($snapshot()===$after,'Second import changed records.');
    $benefitChecks=catalogBenefitChecks($db);
    $brandedResult=brandedChecks($db,$before,$after,$branded);
    $brandedSql=file_get_contents(ROOTPATH.$branded['upgrade']);
    $run($brandedSql);$assert($snapshot()===$after,'Standalone branded import duplicated or reset records.');
    $productSql=file_get_contents(ROOTPATH.$catalog['upgrade']);
    $run($productSql); $assert($snapshot()===$after,'Standalone product import changed the already upgraded catalog.');
    $assert(count(array_filter($after['products'],static fn($p)=>$p['medicine_type']===null))===0,'Some source products remain unclassified.');

    // Simulate later use, then ensure repeating the upgrade preserves its new history too.
    $branch=(int)$demo['demo_cashier1']['branch_id'];
    $drawer=$db->table('cashier_drawers')->where('branch_id',$branch)->get()->getRowArray();
    $workflow=new \App\Libraries\CashierShift($db);
    $shiftId=$workflow->open((int)$demo['demo_cashier1']['id'],(int)$drawer['id'],'100',str_repeat('a',32));
    $workflow->cashMovement((int)$demo['demo_cashier1']['id'],$branch,'cash_in','20','Verification float',str_repeat('b',32));
    $summary=$workflow->summary($workflow->activeFor((int)$demo['demo_cashier1']['id'],$branch));
    $workflow->close($shiftId,(int)$demo['demo_cashier1']['id'],'120','100','Verification handover',$summary['revision'],(int)$demo['demo_cashier2']['id']);
    $workflow->review((int)$demo['demo_admin']['id'],'shift',$shiftId,'Verified closing count');
    $workflow->hold((int)$demo['demo_cashier2']['id'],$branch,[6=>['product_id'=>6,'quantity'=>1]],'Preservation check');
    $db->table('users')->where('id',$demo['demo_admin']['id'])->update(['password'=>password_hash('ChangedDemoOwnerPassword!',PASSWORD_DEFAULT),'role'=>'cashier','status'=>'inactive','branch_id'=>$branch,'session_version'=>str_repeat('f',32)]);
    $db->table('products')->where('id',6)->update(['generic_name'=>'Reviewed medicine','medicine_type'=>'generic','senior_pwd_eligible'=>1,'tax_treatment'=>'vat_exempt']);
    $db->table('receipt_invoice_sequences')->insert(['series_key'=>'latest-preservation-check','next_number'=>123]);
    $brandedId=$db->table('products')->where('sku',$branded['items'][0]['sku'])->get()->getRowArray()['id'];
    $db->table('products')->where('id',$brandedId)->update(['product_name'=>'Owner reviewed brand','status'=>'inactive','price'=>99,'stock'=>3]);
    $db->table('branch_products')->where('product_id',$brandedId)->update(['stock'=>3,'price'=>99,'expiration_date'=>'2028-01-01']);
    $used=$snapshot(); $run($patch);
    $assert($snapshot()===$used,'Reimport changed later shifts, handovers, held carts, user edits, medicine settings or invoice sequence.');
    // A product whose name or SKU differs only by case must also be skipped.
    foreach ([10=>'product_name',11=>'sku'] as $guardId=>$guardField) {
        $guardRow=array_values(array_filter($before['products'],static fn($p)=>(int)$p['id']===$guardId))[0];
        $guardRow[$guardField]=$guardField==='sku'?strtolower($guardRow[$guardField]):strtoupper($guardRow[$guardField]);
        $db->table('products')->where('id',$guardId)->update($guardRow);
        $guardState=$snapshot(); $run($productSql);
        $assert($snapshot()===$guardState,'Product ID or case-insensitive matching overwrote a different record.');
    }
    // Exercise missing prerequisites and collisions in rollback-only scenarios.
    $seedBody=implode(';',array_filter($brandedStatements,static fn($s)=>!in_array(strtoupper($s),['START TRANSACTION','COMMIT'],true))).';';
    $seedIds=[];foreach ($branded['items'] as $item) $seedIds[]=$db->table('products')->where('sku',$item['sku'])->get()->getRowArray()['id'];
    $freeIdentities=static function() use($db,$seedIds): void {
        foreach ($seedIds as $index=>$id) $db->table('products')->where('id',$id)->update(['sku'=>'VERIFY-FREE-'.$index,'product_name'=>'Verification placeholder '.$index]);
    };
    $scenarioChecks=[];
    foreach (['no_active_branch','deleted_category','existing_sku_and_name'] as $scenario) {
        $db->transBegin();
        try {
            $freeIdentities();
            if ($scenario==='no_active_branch') $db->table('branches')->update(['status'=>'inactive']);
            elseif ($scenario==='deleted_category') $db->table('categories')->where('category_name','Pain Relief')->update(['is_deleted'=>1]);
            else {
                $db->table('products')->where('id',$seedIds[0])->update(['sku'=>'OWNER-BIOFLU','product_name'=>'Bioflu','status'=>'inactive','is_deleted'=>1,'deleted_at'=>'2026-10-15 00:00:00']);
                $db->table('products')->where('id',$seedIds[1])->update(['sku'=>$branded['items'][1]['sku'],'product_name'=>'Owner inventory item','status'=>'inactive']);
            }
            $scenarioBefore=$snapshot();$run($seedBody);$scenarioAfter=$snapshot();
            if ($scenario==='no_active_branch') $assert($scenarioAfter===$scenarioBefore,'An active branch is required for the seed.');
            else {
                $scenarioAdded=brandedChecks($db,$scenarioBefore,$scenarioAfter,$branded,false);
                $assert($scenarioAdded['products_added']===($scenario==='deleted_category'?8:10),'Unexpected missing-category or collision result.');
                foreach ($scenarioBefore as $table=>$rows) {
                    $retained=array_column($scenarioAfter[$table],null,$table==='receipt_invoice_sequences'?'series_key':'id');
                    foreach ($rows as $row) $assert(($retained[$row[$table==='receipt_invoice_sequences'?'series_key':'id']] ?? null)===$row,'Seed changed an existing collision scenario row.');
                }
                $run($seedBody);$assert($snapshot()===$scenarioAfter,'Collision scenario reimport changed records.');
            }
            $scenarioChecks[]=$scenario;
        } finally { $db->transRollback(); }
    }
    $assert(hash_file('sha256',$manifest['source_path'])===$manifest['source_sha256'],'Original export file changed.');
    $report=['source_sha256'=>$manifest['source_sha256'],'upgrade'=>$manifest['upgrade'],'upgrade_sha256'=>hash('sha256',$patch),
        'original_counts'=>array_map('count',$before),'upgraded_counts'=>array_map('count',$after),'added_migration'=>'2026_10_15_000014',
        'created_demo_accounts'=>array_column($newUsers,'username'),'checks'=>[
        'Only the five reviewed product fields changed; every other original product field and every existing sales/invoice field was preserved.',
        'All 54 source products classified; source prices, stock, timestamps, SKUs and tax classifications preserved.',
            'Exactly seven cashier tables added; one shared drawer per existing branch.',
            'Twelve requested demo medicine entries, inventories and clearly labeled stock-in logs added; no sales, shifts or beneficiary history fabricated.',
            'Three missing demo accounts added without changing any original account.',
            'Second import left all records unchanged.',
            'Later closing reports, handovers, reviews, held carts, edited accounts, product classifications and invoice numbering survived reimport.',
            'Source export unchanged; only an isolated local verification database was used.'],
        'product_catalog'=>$catalog['hosting'],'product_upgrade_sha256'=>$catalog['upgrade_sha256'],
        'case_sensitive_product_guards_verified'=>true,'benefit_checks'=>$benefitChecks,'application_database_modified'=>false];
    $report['branded_seed']=$brandedResult;$report['branded_seed_sha256']=$branded['upgrade_sha256'];
    $report['branded_scenarios_verified']=$scenarioChecks;
    file_put_contents(__DIR__.'/verification.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    if ($db) $db->close();
    if ($created && preg_match('/^phx_latest_upgrade_review_[a-f0-9]{12}$/D',$schema)) $base->query('DROP DATABASE `'.$schema.'`');
}
