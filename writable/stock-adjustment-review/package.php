<?php
$root = dirname(__DIR__, 2);
$file = 'app/Views/products/index.php';
$archive = $root . '/build/hostinger-stock-adjustment-ui-2026-10-21.zip';
$hash = hash_file('sha256', $root . '/' . $file);
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create archive.');
$zip->addFile($root . '/' . $file, $file);
$zip->addFile($root . '/database/upgrades/2026-10-21-stock-adjustment-ui.md', 'README.md');
$zip->addFromString('SHA256SUMS.json', json_encode([$file => $hash], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Cannot save archive.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== 3 || hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('Archive integrity failed.');
$zip->close();
$result = ['archive' => $archive, 'runtime_files' => 1, 'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive),
    'application_database_changed' => false, 'hosting_deployed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
