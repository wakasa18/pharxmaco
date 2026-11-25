<?php
$root = dirname(__DIR__, 2);
$files = ['app/Controllers/Cashier/SalesController.php', 'app/Views/cashier/sales/index.php'];
$target = $root . '/build/hostinger-pos-checkout-fix-2026-10-15.zip';
$guide = "# POS checkout fix - 15 October 2026\n\nUpload both application files to their matching paths in your existing Pharxmaco application. Install the controller and view together. This patch is for the current cashier workspace installation; no SQL import is needed for this fix.\n\nReload the POS after uploading. If no shift is open, choose Start shift to complete sale, count opening cash, and open an available drawer. No shift is opened automatically. The cart is retained, and edited quantities are synchronized before leaving the POS through that action.\n\nCheckout validation now returns its message directly to the browser rather than following an error redirect and losing flash feedback. Rejected sales keep the payment form and update CSRF/checkout tokens for retry. Successful checkout opens the completed-sale page. If the shift closes after the POS was loaded, checkout rolls back and opens Start Shift with the cart preserved.\n\nVerification: four focused controller tests passed with 52 assertions; eleven browser scenarios passed with stubbed responses and no real sale posted. Full unit suite: 199 tests, 187 passed, 9 failed and 3 errored. The twelve existing inventory/expiry audit issues remain outside this checkout patch. The local application database was not changed by this fix.\n";
$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create checkout package.');
$manifest = [];
foreach ($files as $file) {
    $manifest[$file] = hash_file('sha256', $root . '/' . $file);
    $zip->addFile($root . '/' . $file, $file);
}
$manifest['INSTALL.md'] = hash('sha256', $guide);
$zip->addFromString('INSTALL.md', $guide);
$zip->close();
if ($zip->open($target) !== true || $zip->numFiles !== count($manifest)) throw new RuntimeException('Checkout package count mismatch.');
foreach ($manifest as $file => $hash) {
    if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('Checkout package content mismatch.');
}
$zip->close();
$result = ['file' => $target, 'files' => count($manifest), 'bytes' => filesize($target), 'sha256' => hash_file('sha256', $target), 'entries' => $manifest];
file_put_contents(__DIR__ . '/package-checks.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(array_diff_key($result, ['entries' => true]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
