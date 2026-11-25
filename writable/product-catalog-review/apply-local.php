<?php
require dirname(__DIR__,2).'/system/Test/bootstrap.php';
require __DIR__.'/support.php';
set_exception_handler(static function(Throwable $error): void { fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1); });
$catalog=catalogManifest();
$verification=json_decode(file_get_contents(ROOTPATH.'writable/latest-database-review/verification.json'),true,512,JSON_THROW_ON_ERROR);
if (($verification['product_upgrade_sha256'] ?? '')!==$catalog['upgrade_sha256']
    || ($verification['upgrade_sha256'] ?? '')!==hash_file('sha256',$verification['upgrade'])) throw new RuntimeException('Verify the current combined import first.');
$settings=config(\Config\Database::class)->default;
if (!in_array($settings['hostname'],['localhost','127.0.0.1','::1'],true) || $settings['database']!=='pharxmaco_drugstore') throw new RuntimeException('Known local application database required.');
$db=\Config\Database::connect('default'); $db->initialize();
$sql=file_get_contents(ROOTPATH.$catalog['upgrade']);
preg_match_all('/^UPDATE `products` SET [^\r\n]+;$/m',$sql,$matches);
if (count($matches[0])!==count($catalog['plan'])) throw new RuntimeException('Unexpected product update format.');
$snapshot=static function() use($db): array {
    $state=[];
    foreach ($db->query('SHOW TABLES')->getResultArray() as $result) {
        $table=reset($result); if (!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Unexpected local table.');
        $primary=$db->query('SHOW KEYS FROM `'.$table.'` WHERE Key_name = \'PRIMARY\'')->getResultArray();
        usort($primary,static fn($a,$b)=>(int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);
        $query=$db->table($table);
        foreach ($primary as $key) $query->orderBy($key['Column_name']);
        $rows=$query->get()->getResultArray();
        $state[$table]=$table==='products'?$rows:hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    }
    return $state;
};
$db->transBegin();
try {
    $db->query('SELECT id FROM products ORDER BY id FOR UPDATE');
    $before=$snapshot(); $expected=$before; $expected['products']=catalogExpected($before['products'],$catalog);
    $stamp=date('Ymd-His').'-'.bin2hex(random_bytes(3));
    $backup=__DIR__.'/local-before-'.$stamp.'.json';
    file_put_contents($backup,json_encode($before,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    foreach ($matches[0] as $statement) $db->query($statement);
    $after=$snapshot(); if ($after!==$expected) throw new RuntimeException('An unexpected local field or table changed; rolling back.');
    $benefits=catalogBenefitChecks($db);
    foreach ($matches[0] as $statement) $db->query($statement);
    if ($snapshot()!==$after) throw new RuntimeException('Repeat import changed local data; rolling back.');
    $changes=0;
    foreach ($before['products'] as $i=>$row) if ($row!==$after['products'][$i]) $changes++;
    $types=[];$eligible=0;$pending=0;
    foreach ($after['products'] as $row) { $type=$row['medicine_type'] ?? 'unspecified';$types[$type]=($types[$type] ?? 0)+1;$eligible+=(int)$row['senior_pwd_eligible']===1?1:0;$pending+=$row['senior_pwd_eligible']===null?1:0; }
    if (!$db->transStatus()) throw new RuntimeException('Local transaction failed.');
    $db->transCommit();
    $report=['database'=>$settings['database'],'product_sql_sha256'=>$catalog['upgrade_sha256'],'products'=>count($after['products']),
        'updated_products'=>$changes,'types'=>$types,'eligible'=>$eligible,'not_confirmed'=>$pending,
        'other_product_fields_preserved'=>true,'all_other_tables_preserved'=>true,'repeat_import_unchanged'=>true,
        'benefit_checks'=>$benefits,'backup'=>$backup];
    file_put_contents(__DIR__.'/local-application.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) { $db->transRollback(); throw $error; }
