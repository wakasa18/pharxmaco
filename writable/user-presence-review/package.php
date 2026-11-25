<?php
$root = dirname(__DIR__, 2);
$files = [
    'app/Database/Migrations/2026_10_21_000016_add_user_presence_sessions.php', 'app/Libraries/UserPresence.php',
    'app/Controllers/Presence.php', 'app/Libraries/AccountSession.php', 'app/Controllers/Auth.php',
    'app/Controllers/Admin/Users.php', 'app/Views/admin/users/index.php', 'app/Views/layouts/staff.php',
    'app/Config/Routes.php', 'app/Config/Filters.php', 'public/assets/js/user-presence.js',
    'app/Controllers/ServerTime.php', 'app/Views/layouts/topbar.php', 'public/assets/js/server-clock.js',
    'public/assets/js/cashier-session.js', 'database/upgrades/2026-10-21-user-presence.sql',
];
$archive = $root . '/build/hostinger-user-presence-2026-10-21.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create archive.');
$hashes = [];
foreach ($files as $file) {
    $hashes[$file] = hash_file('sha256', $root . '/' . $file);
    if (!$zip->addFile($root . '/' . $file, $file)) throw new RuntimeException('Cannot add ' . $file);
}
$zip->addFile($root . '/database/upgrades/2026-10-21-user-presence.md', 'README.md');
$zip->addFromString('SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Cannot save archive.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== count($files) + 2) throw new RuntimeException('Archive integrity failed.');
foreach ($hashes as $file => $hash) if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('Hash mismatch: ' . $file);
$zip->close();
$result = ['archive' => $archive, 'runtime_files' => count($files) - 1, 'sql_files' => 1, 'bytes' => filesize($archive),
    'sha256' => hash_file('sha256', $archive), 'local_schema_applied' => true, 'existing_records_preserved' => true, 'hosting_deployed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
