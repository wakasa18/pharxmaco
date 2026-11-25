<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
require __DIR__.'/support.php';
set_exception_handler(static function(Throwable $error): void { fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1); });
$manifest=brandedManifest();
$verified=json_decode(file_get_contents(ROOTPATH.'writable/latest-database-review/verification.json'),true,512,JSON_THROW_ON_ERROR);
if (($verified['branded_seed_sha256'] ?? null)!==$manifest['upgrade_sha256'] || $verified['upgrade_sha256']!==hash_file('sha256',$verified['upgrade'])) throw new RuntimeException('Verify the current SQL import first.');
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true) || $settings['database']!=='pharxmaco_drugstore') throw new RuntimeException('Known local application database required.');
$db=\Config\Database::connect('default');$db->initialize();
$sql=file_get_contents(ROOTPATH.$manifest['upgrade']);
// This verified generated seed has one-line literal values; statement terminators end a line.
$sql=preg_replace('/^--[^\r\n]*(?:\r?\n|$)/m','',$sql);
$statements=array_values(array_filter(array_map('trim',preg_split('/;[ \t]*\r?\n/',$sql)),static fn($s)=>$s!=='' && !in_array(strtoupper($s),['START TRANSACTION','COMMIT'],true)));
foreach ($statements as $statement) if (!preg_match('/^(?:SELECT\b|SET @phx_brand_|INSERT INTO (?:products|branch_products|stock_logs)\b)/',$statement)) throw new RuntimeException('Unexpected seed operation.');
$snapshot=static function() use($db): array {
    $state=[];
    foreach ($db->query('SHOW TABLES')->getResultArray() as $result) {
        $table=reset($result);if (!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Unexpected table.');
        $state[$table]=$db->table($table)->orderBy($table==='receipt_invoice_sequences'?'series_key':'id')->get()->getResultArray();
    }
    return $state;
};
$run=static function() use($db,$statements): void { foreach ($statements as $statement) if ($db->query($statement)===false) throw new RuntimeException('Seed query failed: '.($db->error()['message'] ?? 'Unknown SQL error')); };
$db->transBegin();
try {
    $db->query('SELECT id FROM products ORDER BY id FOR UPDATE');
    $before=$snapshot();
    $backup=__DIR__.'/local-before-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
    $backupState=[];foreach ($before as $table=>$rows) $backupState[$table]=in_array($table,['products','branch_products','stock_logs'],true)?$rows:hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    file_put_contents($backup,json_encode($backupState,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $run();$after=$snapshot();
    foreach ($before as $table=>$rows) {
        if (in_array($table,['products','branch_products','stock_logs'],true)) {
            $indexed=array_column($after[$table],null,'id');
            foreach ($rows as $row) if (($indexed[$row['id']] ?? null)!==$row) throw new RuntimeException('Seed changed an existing record: '.$table);
        } elseif ($after[$table]!==$rows) throw new RuntimeException('Seed changed an unrelated table: '.$table);
    }
    $result=brandedChecks($db,$before,$after,$manifest,false);
    $run();if ($snapshot()!==$after) throw new RuntimeException('Repeat import replenished or modified existing data.');
    if (!$db->transStatus()) throw new RuntimeException('Local seed transaction failed.');
    $db->transCommit();
    $report=$result+['database'=>$settings['database'],'seed_sha256'=>$manifest['upgrade_sha256'],'total_products'=>count($after['products']),
        'total_branded'=>count(array_filter($after['products'],static fn($p)=>$p['medicine_type']==='branded')),
        'existing_records_preserved'=>true,'repeat_import_unchanged'=>true,'backup'=>$backup,
        'added_products'=>array_column(brandedNewRows($before,$after,'products'),'product_name')];
    file_put_contents(__DIR__.'/local-application.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) { $db->transRollback();throw $error; }
