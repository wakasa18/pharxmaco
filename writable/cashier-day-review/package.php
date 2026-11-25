<?php
$root=dirname(__DIR__,2); $stage=$root.'/build/cashier-workspace-2026-10-15';
$manifest=json_decode(file_get_contents(__DIR__.'/package-manifest.json'),true,512,JSON_THROW_ON_ERROR);
$target=$root.'/build/hostinger-cashier-workspace-2026-10-15.zip';
$zip=new ZipArchive(); if ($zip->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Could not create package.');
foreach ($manifest as $entry) {
    if (str_contains($entry['file'],'..') || !is_file($stage.'/'.$entry['file'])) throw new RuntimeException('Invalid package entry.');
    if (!$zip->addFile($stage.'/'.$entry['file'],$entry['file'])) throw new RuntimeException('Could not package file.');
}
if (!$zip->close()) throw new RuntimeException('Could not finish package.');
if ($zip->open($target)!==true || $zip->numFiles!==count($manifest)) throw new RuntimeException('Package count mismatch.');
foreach ($manifest as $entry) if (hash('sha256',$zip->getFromName($entry['file']))!==$entry['sha256']) throw new RuntimeException('Package content mismatch.');
if ($zip->locateName('app/Config/ReceiptInvoice.php')!==false) throw new RuntimeException('Live configuration must be excluded.');
$zip->close();
$report=['file'=>$target,'files'=>count($manifest),'bytes'=>filesize($target),'sha256'=>hash_file('sha256',$target),'source_hashes_verified'=>true,'invoice_configuration_excluded'=>true];
file_put_contents(__DIR__.'/package-checks.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
