<?php
$root = dirname(__DIR__, 2);
$files = ['app/Database/Migrations/2026_10_20_000015_add_cashier_login_sessions.php',
    'app/Libraries/CashierLoginSession.php', 'app/Libraries/AccountSession.php', 'app/Libraries/AuthenticationAudit.php',
    'app/Controllers/Auth.php', 'app/Controllers/Admin/Users.php', 'app/Filters/AuthFilter.php',
    'app/Filters/AdminFilter.php', 'app/Filters/CashierFilter.php', 'app/Config/Routes.php',
    'app/Views/admin/users/index.php', 'app/Views/layouts/staff.php', 'public/assets/js/cashier-session.js',
    'app/Controllers/Cashier/SalesController.php', 'app/Views/cashier/sales/index.php',
    'app/Views/cashier/sales/_cart_lines.php', 'app/Views/cashier/sales/_benefit_products.php',
    'app/Views/cashier/sales/_held_sales.php', 'app/Views/cashier/sales/_discount_options.php',
    'database/upgrades/2026-10-20-cashier-single-login.sql'];
$zip = new ZipArchive();
$archive = $root . '/build/hostinger-cashier-single-login-2026-10-20.zip';
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create archive.');
$hashes = [];
foreach ($files as $file) {
    if (str_ends_with($file, '.php')) {
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file);
        exec($command, $lintOutput, $code);
        if ($code !== 0) throw new RuntimeException('PHP syntax check failed: ' . $file);
    }
    $hashes[$file] = hash_file('sha256', $root . '/' . $file);
    if (!$zip->addFile($root . '/' . $file, $file)) throw new RuntimeException('Could not add ' . $file);
}
$zip->addFile($root . '/database/upgrades/2026-10-20-cashier-single-login.md', 'README.md');
$zip->addFromString('SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Could not save archive.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== count($files) + 2) throw new RuntimeException('Archive integrity failed.');
foreach ($hashes as $file => $hash) if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('Archive/source mismatch: ' . $file);
$zip->close();
$result = ['archive' => $archive, 'verified_runtime_files' => count($files) - 1, 'sql_files' => 1,
    'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive), 'hosting_deployed' => false];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
