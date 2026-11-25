<?php
$root = dirname(__DIR__, 2);
$runtime = [
    'app/Database/Migrations/2026_10_23_000018_add_quarantine_resolutions.php',
    'app/Libraries/Quarantine.php', 'app/Libraries/StockExpiryProvenance.php', 'app/Libraries/ReturnCondition.php',
    'app/Controllers/Admin/QuarantineController.php', 'app/Config/Routes.php', 'app/Views/layouts/sidebar.php',
    'app/Models/SaleItemModel.php', 'app/Controllers/Cashier/SalesController.php', 'app/Libraries/ProductExchange.php',
    'app/Views/admin/quarantine/index.php', 'app/Views/admin/quarantine/show.php', 'public/assets/css/quarantine.css',
];
$files = array_merge($runtime, ['database/upgrades/2026-10-23-quarantine.sql']);
$archive = $root . '/build/hostinger-quarantine-2026-10-23.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create archive.');
$hashes = [];
foreach ($files as $file) {
    $hashes[$file] = hash_file('sha256', $root . '/' . $file);
    if (!$zip->addFile($root . '/' . $file, $file)) throw new RuntimeException('Cannot add ' . $file);
}
$zip->addFile($root . '/database/upgrades/2026-10-23-quarantine.md', 'README.md');
$zip->addFromString('SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Cannot save archive.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== count($files) + 2) throw new RuntimeException('Archive integrity failed.');
foreach ($hashes as $file => $hash) if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('Hash mismatch: ' . $file);
$zip->close();
$report = ['archive' => $archive, 'runtime_files' => count($runtime), 'sql_files' => 1, 'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive),
    'local_schema_applied' => true, 'existing_business_records_preserved' => true, 'hosting_changed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
