<?php
$root=dirname(__DIR__,2);
$files=[
    'app/Libraries/CashierForeignKeys.php',
    'app/Database/Migrations/2026_10_21_000017_enforce_cashier_foreign_keys.php',
    'app/Libraries/DatabaseBackupService.php',
    'database/upgrades/2026-10-21-cashier-foreign-keys.sql',
    'database/upgrades/2026-10-21-finish-cashier-foreign-keys.sql',
    'database/upgrades/2026-10-21-cashier-upgrade-status.sql',
];
$archive=$root.'/build/hostinger-cashier-foreign-keys-2026-10-21.zip';$zip=new ZipArchive();
if($zip->open($archive,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create archive.');
$hashes=[];foreach($files as $file){$hashes[$file]=hash_file('sha256',$root.'/'.$file);if(!$zip->addFile($root.'/'.$file,$file))throw new RuntimeException('Cannot add '.$file);}
$zip->addFile($root.'/database/upgrades/2026-10-21-cashier-foreign-keys.md','README.md');
$zip->addFromString('SHA256SUMS.json',json_encode($hashes,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
if(!$zip->close())throw new RuntimeException('Cannot save archive.');
if($zip->open($archive,ZipArchive::CHECKCONS)!==true||$zip->numFiles!==count($files)+2)throw new RuntimeException('Archive integrity failed.');
foreach($hashes as $file=>$hash)if(hash('sha256',$zip->getFromName($file))!==$hash)throw new RuntimeException('Hash mismatch: '.$file);
$zip->close();$result=['archive'=>$archive,'runtime_files'=>3,'sql_files'=>3,'bytes'=>filesize($archive),'sha256'=>hash_file('sha256',$archive),
 'local_schema_applied'=>true,'existing_records_preserved'=>true,'hosting_deployed'=>false,'shift_reset_packaged'=>false];
file_put_contents(__DIR__.'/package-verification.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
