<?php
$root=dirname(__DIR__,2);
$files=['app/Views/cashier/workspace/index.php','app/Views/cashier/workspace/_tables.php','app/Views/layouts/staff.php',
    'public/assets/js/cashier-workspace.js','public/assets/js/datatables-init.js'];
$target=$root.'/build/hostinger-my-day-datatables-2026-10-15.zip';
$guide="# My Day DataTables - October 15, 2026\n\nUpload the five application files in this ZIP to the matching paths in your existing Pharxmaco installation. Then reload My Day. This UI update uses the existing locally served DataTables library and theme; no SQL import is needed.\n\nPayment breakdown, Products sold and returned, Recent activity and Your recent shifts now have quick search, sorting, rows per page and pagination. The three live report tables retain search, order and page length during refresh; the current page is kept when it still exists. Empty days can receive their first live rows. The date selector still controls the reporting period, and Recent activity still covers the latest 100 movements. Printable shift reports retain complete ordinary tables.\n\nVerified at 320, 390, 768 and 1440 pixels in light/dark themes, including paging, currency/date sorting, empty/refilled tables, safe text rendering and live refresh.\n";
$zip=new ZipArchive();if ($zip->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Cannot create UI package.');
$manifest=[];
foreach ($files as $file) {
    $source=realpath($root.'/'.$file);
    if ($source===false || !str_starts_with(strtolower($source),strtolower(realpath($root).DIRECTORY_SEPARATOR))) throw new RuntimeException('Package source outside workspace.');
    $manifest[$file]=hash_file('sha256',$source);$zip->addFile($source,$file);
}
$zip->addFromString('INSTALL.md',$guide);$manifest['INSTALL.md']=hash('sha256',$guide);$zip->close();
if ($zip->open($target)!==true || $zip->numFiles!==count($manifest)) throw new RuntimeException('UI package file count mismatch.');
foreach ($manifest as $file=>$hash) if (hash('sha256',$zip->getFromName($file))!==$hash) throw new RuntimeException('UI package hash mismatch: '.$file);
$zip->close();
$report=['file'=>$target,'files'=>count($manifest),'bytes'=>filesize($target),'sha256'=>hash_file('sha256',$target),'entries'=>$manifest];
file_put_contents(__DIR__.'/datatables-package.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(array_diff_key($report,['entries'=>true]),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
