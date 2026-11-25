<?php
$root = dirname(__DIR__, 2);
$runtime = ['app/Controllers/StockLogsController.php', 'app/Libraries/StockLogPdf.php', 'app/Libraries/PdfFactory.php', 'app/Libraries/ReportPdf.php'];
$files = array_combine($runtime, $runtime);
$files['README.md'] = 'docs/development/Stock-Log-PDF-Deployment.md';
$archive = $root . '/build/hostinger-stock-log-pdf-fix-2026-10-23.zip';
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
$result = ['archive' => $archive, 'runtime_files' => count($runtime), 'sql_files' => 0, 'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive), 'hosting_changed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
