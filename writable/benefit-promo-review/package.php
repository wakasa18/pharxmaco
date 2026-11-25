<?php
$root=dirname(__DIR__,2);
$files=['app/Controllers/Cashier/SalesController.php','app/Libraries/BenefitProductPromotion.php','app/Libraries/ProductPromotion.php','app/Libraries/CashierShift.php',
    'app/Libraries/ReceiptInvoice.php','app/Libraries/SaleCorrectionPricing.php','app/Views/cashier/sales/index.php','app/Views/cashier/sales/receipt.php',
    'app/Views/cashier/sales/details.php','public/assets/js/discount-math.js','database/upgrades/2026-10-16-reconcile-master-stock.sql',
    'database/upgrades/2026-10-16-reconcile-sale41-refunds.sql','database/upgrades/2026-10-16-reconcile-sale41-refunds.md',
    'app/Controllers/Cashier/WorkspaceController.php','app/Libraries/CashierDayReport.php','app/Views/cashier/workspace/print.php',
    'public/assets/css/shift-print.css','public/assets/js/shift-print.js','docs/reviews/2026-10-16-shift-print-update.md'];
$target=$root.'/build/hostinger-checkout-fixes-2026-10-16.zip';
$guide=file_get_contents($root.'/docs/reviews/2026-10-16-checkout-fixes.md');
$zip=new ZipArchive();if($zip->open($target,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create combined discount package.');
$manifest=[];
foreach($files as $file){$manifest[$file]=hash_file('sha256',$root.'/'.$file);$zip->addFile($root.'/'.$file,$file);}
$manifest['INSTALL.md']=hash('sha256',$guide);$zip->addFromString('INSTALL.md',$guide);$zip->close();
if($zip->open($target)!==true||$zip->numFiles!==count($manifest))throw new RuntimeException('Combined discount package count mismatch.');
foreach($manifest as $file=>$hash)if(hash('sha256',$zip->getFromName($file))!==$hash)throw new RuntimeException('Combined discount package mismatch: '.$file);
if($zip->locateName('app/Config/ReceiptInvoice.php')!==false)throw new RuntimeException('Seller configuration must be preserved.');
$zip->close();
if (!copy($target,$root.'/build/hostinger-automatic-promotions-2026-10-16.zip')) throw new RuntimeException('Could not refresh automatic-promotion archive.');
$result=['file'=>$target,'files'=>count($manifest),'bytes'=>filesize($target),'sha256'=>hash_file('sha256',$target),'entries'=>$manifest];
file_put_contents(__DIR__.'/package-checks.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(array_diff_key($result,['entries'=>true]),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
