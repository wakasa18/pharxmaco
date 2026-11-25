<?php
$root = dirname(__DIR__, 2);
$runtime = [
    'app/Database/Migrations/2026_10_23_000018_add_quarantine_resolutions.php',
    'app/Libraries/Quarantine.php', 'app/Libraries/StockExpiryProvenance.php', 'app/Libraries/ReturnCondition.php',
    'app/Controllers/Admin/QuarantineController.php', 'app/Config/Routes.php', 'app/Views/layouts/sidebar.php',
    'app/Models/SaleItemModel.php', 'app/Controllers/Cashier/SalesController.php', 'app/Libraries/ProductExchange.php',
    'app/Views/admin/quarantine/index.php', 'app/Views/admin/quarantine/show.php', 'public/assets/css/quarantine.css',
    'app/Libraries/DamagedItems.php', 'app/Controllers/Admin/DamagedItemsController.php',
    'app/Views/admin/damaged_items/index.php', 'app/Views/admin/damaged_items/show.php', 'public/assets/css/damaged-items.css',
];
$files = array_combine($runtime, $runtime);
$files['database/upgrades/2026-10-23-quarantine.sql'] = 'database/upgrades/2026-10-23-quarantine.sql';
$files['database/upgrades/2026-10-23-quarantine.md'] = 'database/upgrades/2026-10-23-quarantine.md';
$files['README.md'] = 'docs/development/Damaged-Items-Deployment.md';
$archive = $root . '/build/hostinger-damaged-items-2026-10-23.zip';
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
$report = ['archive' => $archive, 'runtime_files' => count($runtime), 'new_sql_updates' => 0, 'existing_quarantine_sql_included' => true,
    'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive), 'existing_business_records_preserved' => true, 'hosting_changed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
