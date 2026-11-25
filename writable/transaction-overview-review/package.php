<?php
$root = dirname(__DIR__, 2);
$runtime = [
    'app/Controllers/Cashier/SalesController.php', 'app/Controllers/Cashier/ExchangeController.php',
    'app/Controllers/Admin/SaleCorrection.php', 'app/Libraries/SaleCorrectionPricing.php',
    'app/Views/cashier/sales/refund_partial.php', 'app/Views/cashier/sales/exchange.php',
    'app/Views/admin/sale_correction/edit.php', 'app/Views/partials/transaction_overview.php',
    'public/assets/js/transaction-overview.js', 'public/assets/css/transaction-overview.css',
    'public/assets/js/modals.js', 'public/assets/js/discount-math.js',
];
$files = array_combine($runtime, $runtime);
$files['README.md'] = 'docs/development/Transaction-Overviews-Deployment.md';
$archive = $root . '/build/hostinger-transaction-overviews-2026-10-23.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create archive.');
$hashes = [];
foreach ($files as $entry => $source) {
    $hashes[$entry] = hash_file('sha256', $root . '/' . $source);
    if (!$zip->addFile($root . '/' . $source, $entry)) throw new RuntimeException('Cannot add ' . $entry);
}
$zip->addFromString('SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Cannot save archive.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== count($files) + 1) throw new RuntimeException('Archive integrity failed.');
foreach ($hashes as $entry => $hash) if (hash('sha256', $zip->getFromName($entry)) !== $hash) throw new RuntimeException('Hash mismatch: ' . $entry);
$zip->close();
$report = ['archive' => $archive, 'runtime_files' => count($runtime), 'sql_files' => 0, 'bytes' => filesize($archive),
    'sha256' => hash_file('sha256', $archive), 'application_database_changed' => false, 'hosting_changed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
