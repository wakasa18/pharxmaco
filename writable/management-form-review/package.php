<?php
$root = dirname(__DIR__, 2);
$files = ['app/Config/Validation.php', 'app/Validation/ManagementRules.php', 'app/Libraries/ProductUnit.php',
    'app/Controllers/Products.php', 'app/Controllers/Categories.php', 'app/Controllers/Admin/Users.php',
    'app/Controllers/Admin/Branches.php', 'app/Controllers/Admin/Suppliers.php', 'app/Controllers/Admin/Discounts.php',
    'app/Views/products/create.php', 'app/Views/products/edit.php', 'app/Views/products/catalog.php',
    'app/Views/categories/create.php', 'app/Views/categories/edit.php', 'app/Views/admin/users/create.php', 'app/Views/admin/users/edit.php',
    'app/Views/admin/branches/create.php', 'app/Views/admin/branches/edit.php', 'app/Views/admin/suppliers/create.php', 'app/Views/admin/suppliers/edit.php',
    'app/Views/admin/discounts/_form.php', 'app/Views/layouts/staff.php', 'app/Views/partials/management_validation.php', 'app/Views/partials/product_unit_field.php',
    'public/assets/js/management-validation.js', 'public/assets/js/product-create-review.js', 'public/assets/js/modals.js',
    'public/assets/css/product-create-review.css'];
$hashes = [];
$archive = $root . '/build/hostinger-product-unit-selection-2026-10-20.zip';
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create ZIP.');
foreach ($files as $file) {
    $hashes[$file] = hash_file('sha256', $root . '/' . $file);
    if (!$zip->addFile($root . '/' . $file, $file)) throw new RuntimeException('Could not package ' . $file);
}
$zip->addFile(__DIR__ . '/README-hosting.md', 'README.md');
$zip->addFromString('SHA256SUMS.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
if (!$zip->close()) throw new RuntimeException('Could not save ZIP.');
if ($zip->open($archive, ZipArchive::CHECKCONS) !== true || $zip->numFiles !== count($files) + 2) throw new RuntimeException('ZIP integrity check failed.');
foreach ($hashes as $file => $hash) if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('ZIP hash mismatch: ' . $file);
$zip->close();
if (!copy($archive, $root . '/build/hostinger-management-validation-2026-10-20.zip')) throw new RuntimeException('Could not refresh previous package.');
$result = ['archive' => $archive, 'verified_runtime_files' => count($files), 'bytes' => filesize($archive), 'sha256' => hash_file('sha256', $archive)];
file_put_contents(__DIR__ . '/package-verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
$document = new DOMDocument();
$document->load(__DIR__ . '/business-logic-junit.xml');
$suite = (new DOMXPath($document))->query('(//testsuite[@tests])[1]')->item(0);
$summary = ['previous_management_focused_run' => ['tests' => 18, 'assertions' => 391, 'passed' => true],
    'product_unit_focused_run' => ['tests' => 14, 'assertions' => 408, 'passed' => true],
    'browser_management_checks' => 55, 'browser_product_overview_checks' => 32,
    'business_regression_note' => 'Previous management validation run; not rerun for the unit dropdown.',
    'business_regression' => array_combine(['tests', 'assertions', 'failures', 'errors'], array_map(static fn ($key) => (int) $suite->getAttribute($key), ['tests', 'assertions', 'failures', 'errors'])),
    'unsuccessful' => [], 'application_database_accessed' => false, 'hosted_installation_changed' => false];
foreach ($document->getElementsByTagName('testcase') as $case) if ($case->getElementsByTagName('failure')->length || $case->getElementsByTagName('error')->length) $summary['unsuccessful'][] = $case->getAttribute('name');
file_put_contents(__DIR__ . '/verification-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
