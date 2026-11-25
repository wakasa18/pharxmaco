<?php
$root = dirname(__DIR__, 2);
$files = ['app/Views/admin/reports/index.php', 'app/Views/admin/reports/_sales_summary.php',
    'app/Libraries/ReportPdf.php', 'public/assets/css/reports.css'];
$hashes = [];
$archive = $root . '/build/hostinger-reports-reconciliation-2026-10-20.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create ZIP.');
foreach ($files as $file) {
    $hashes[$file] = hash_file('sha256', $root . '/' . $file);
    if (!$zip->addFile($root . '/' . $file, $file)) throw new RuntimeException('Could not package ' . $file);
}
$zip->addFile(__DIR__ . '/README-hosting.md', 'README.md');
$zip->addFromString('SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Could not save ZIP.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== 6) throw new RuntimeException('ZIP integrity check failed.');
foreach ($hashes as $file => $hash) {
    if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('ZIP hash mismatch: ' . $file);
}
$zip->close();
$result = ['archive' => $archive, 'verified_runtime_files' => count($files), 'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive)];
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
file_put_contents(__DIR__ . '/package-verification.json', $json);
echo $json;
