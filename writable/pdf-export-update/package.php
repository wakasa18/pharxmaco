<?php
$root = dirname(__DIR__, 2);
$files = ['app/Controllers/Admin/ReportsController.php', 'app/Controllers/StockLogsController.php', 'app/Libraries/ReportPdf.php',
    'app/Views/exports/pdf_styles.php', 'app/Views/exports/pdf_header.php', 'app/Views/exports/pdf_table.php', 'app/Views/exports/cash_movements_pdf.php',
    'app/Views/admin/reports/index.php', 'app/Views/admin/reports/reports_pdf.php', 'app/Views/admin/reports/expiry_report_pdf.php', 'app/Views/stock_logs/pdf.php'];
$target = $root . '/build/hostinger-pdf-exports-2026-10-16.zip';
$guide = file_get_contents($root . '/docs/reviews/2026-10-16-pdf-export-update.md');
$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Could not create PDF update package.');
$manifest = [];
foreach ($files as $file) {
    $manifest[$file] = hash_file('sha256', $root . '/' . $file);
    if (!$zip->addFile($root . '/' . $file, $file)) throw new RuntimeException('Could not add ' . $file);
}
$manifest['INSTALL.md'] = hash('sha256', $guide);
$zip->addFromString('INSTALL.md', $guide); $zip->close();
if ($zip->open($target) !== true || $zip->numFiles !== count($manifest)) throw new RuntimeException('PDF package entry count mismatch.');
foreach ($manifest as $file => $hash) if (hash('sha256', $zip->getFromName($file)) !== $hash) throw new RuntimeException('PDF package mismatch: ' . $file);
$zip->close();
$result = ['file' => $target, 'files' => count($manifest), 'bytes' => filesize($target), 'sha256' => hash_file('sha256', $target), 'source_hashes_verified' => true];
file_put_contents(__DIR__ . '/package-checks.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
