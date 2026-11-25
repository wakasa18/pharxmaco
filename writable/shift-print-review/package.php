<?php
$root=dirname(__DIR__,2);
$files=['app/Controllers/Cashier/WorkspaceController.php','app/Libraries/CashierDayReport.php','app/Libraries/CashierShift.php',
    'app/Views/cashier/workspace/print.php','public/assets/css/shift-print.css','public/assets/js/shift-print.js'];
$target=$root.'/build/hostinger-shift-print-2026-10-16.zip';
$guide=file_get_contents($root.'/docs/reviews/2026-10-16-shift-print-update.md');
$zip=new ZipArchive();if($zip->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create shift print package.');
$manifest=[];
foreach($files as $file){$manifest[$file]=hash_file('sha256',$root.'/'.$file);$zip->addFile($root.'/'.$file,$file);}
$manifest['INSTALL.md']=hash('sha256',$guide);$zip->addFromString('INSTALL.md',$guide);$zip->close();
if($zip->open($target)!==true||$zip->numFiles!==count($manifest))throw new RuntimeException('Print package count mismatch.');
foreach($manifest as $file=>$hash)if(hash('sha256',$zip->getFromName($file))!==$hash)throw new RuntimeException('Print package content mismatch: '.$file);
$zip->close();
$result=['file'=>$target,'files'=>count($manifest),'bytes'=>filesize($target),'sha256'=>hash_file('sha256',$target),'source_hashes_verified'=>true];
file_put_contents(__DIR__.'/package-checks.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
