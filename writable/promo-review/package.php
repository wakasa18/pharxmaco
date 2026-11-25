<?php
$root = dirname(__DIR__, 2);
$files = ['app/Controllers/Cashier/SalesController.php', 'app/Views/cashier/sales/index.php',
    'app/Libraries/ProductPromotion.php', 'public/assets/js/discount-math.js'];
$target = $root . '/build/hostinger-automatic-product-promos-2026-10-15.zip';
$guide = "# Automatic product promos - 15 October 2026\n\nUpload all four application files to their matching paths in your existing cashier workspace installation. Install the controller, view, ProductPromotion library and discount-math.js together. Reload the POS after uploading. No SQL import is required. This patch includes the preceding checkout feedback/Start Shift fix.\n\nAutomatic product promo is the default choice. It selects the largest qualifying product-specific promo saving, counting only matching items toward its minimum and respecting the maximum discount. Equal savings use the lowest promotion ID. The current POS supports one discount per sale, so promotions do not stack with each other or Senior/PWD. Category/store-wide promotions remain manual choices.\n\nThe payment summary and matching cart line show the deduction. Cashiers may choose No Discount, another promotion or Senior/PWD. Explicit choices persist across adding/removing products within the current cart and reset for a new checkout. Auto mode recalculates when quantities change. Checkout verifies the displayed promo ID and amount and saves the normal numeric discount ID/receipt snapshot; stale automatic quotes require reviewing refreshed totals.\n\nVerified against the local 20% bandage offer: PHP 55 gives PHP 11 off; two units give PHP 22 off; ten units respect the PHP 100 cap. Other products retain their normal amounts. No application database records or promo definitions were changed.\n\nValidation: 14 focused PHP tests / 150 assertions passed; shared PHP/JS selection and centavo cases passed; 13 automatic promo browser scenarios passed. Full PHP suite: 204 tests / 22,142 assertions, 192 passed, with the same 9 failures and 3 errors from the earlier inventory/expiry audit. Browser checkout uses stubbed responses and controller tests use in-memory SQLite; no real demo sale was posted.\n";
$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create promo package.');
$manifest = [];
foreach ($files as $file) {
    $manifest[$file] = hash_file('sha256', $root . '/' . $file);
    $zip->addFile($root . '/' . $file, $file);
}
$manifest['INSTALL.md'] = hash('sha256', $guide);
$zip->addFromString('INSTALL.md', $guide);$zip->close();
if ($zip->open($target) !== true || $zip->numFiles !== count($manifest)) throw new RuntimeException('Promo package count mismatch.');
foreach ($manifest as $file=>$hash) if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('Promo package content mismatch: '.$file);
$zip->close();
$result = ['file'=>$target, 'files'=>count($manifest), 'bytes'=>filesize($target), 'sha256'=>hash_file('sha256',$target), 'entries'=>$manifest];
file_put_contents(__DIR__.'/package-checks.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(array_diff_key($result, ['entries'=>true]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
