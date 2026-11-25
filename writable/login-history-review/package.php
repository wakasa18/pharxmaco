<?php
$root = dirname(__DIR__, 2);
$files = [
    'app/Libraries/AuthenticationAudit.php',
    'app/Controllers/Cashier/LoginHistoryController.php',
    'app/Views/cashier/login_history.php',
    'app/Config/Routes.php',
    'app/Views/layouts/sidebar.php',
    'app/Views/layouts/topbar.php',
    'public/assets/css/login-history.css',
];
$archive = $root . '/build/hostinger-cashier-login-history-2026-10-21.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create archive.');
$hashes = [];
foreach ($files as $file) {
    $hashes[$file] = hash_file('sha256', $root . '/' . $file);
    if (!$zip->addFile($root . '/' . $file, $file)) throw new RuntimeException('Cannot add ' . $file);
}
$zip->addFile($root . '/database/upgrades/2026-10-21-cashier-login-history.md', 'README.md');
$zip->addFromString('SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Cannot save archive.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== count($files) + 2) throw new RuntimeException('Archive integrity failed.');
foreach ($hashes as $file => $hash) if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('Hash mismatch: ' . $file);
$zip->close();
$result = ['archive' => $archive, 'runtime_files' => count($files), 'sql_files' => 0, 'bytes' => filesize($archive),
    'sha256' => hash_file('sha256', $archive), 'application_data_changed' => false, 'hosting_deployed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
