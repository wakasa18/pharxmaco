<?php
$root = dirname(__DIR__, 2);
$runtime = json_decode(file_get_contents(__DIR__ . '/package-files.json'), true, 512, JSON_THROW_ON_ERROR);
$files = array_combine($runtime, $runtime);
$files['README.md'] = 'docs/development/Product-Filters-Deployment.md';
$archive = $root . '/build/hostinger-product-filters-2026-10-29.zip';
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
$result = ['archive' => $archive, 'runtime_files' => count($runtime), 'changed_runtime_files' => count(json_decode(file_get_contents(__DIR__ . '/changed-files.json'), true)), 'sql_files' => 0, 'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive), 'hosting_changed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
