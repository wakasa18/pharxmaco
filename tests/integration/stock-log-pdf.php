<?php
// Synthetic renderer stress checks only. Does not connect to an application database.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
try {
    $count = (int) ($argv[1] ?? 10000);
    if ($count < 0 || $count > 100000) throw new RuntimeException('Use 0 to 100,000 synthetic records.');
    $out = ROOTPATH . 'tmp/pdfs/stock-pdf-memory-fix';
    if (!is_dir($out)) mkdir($out, 0777, true);
    $rows = (static function () use ($count) {
        for ($id = $count; $id >= 1; $id--) {
            yield ['id' => $id, 'product_id' => $id, 'product_name' => "Synthetic tablet Pe\u{00f1}a & Mu\u{00f1}oz", 'sku' => 'STRESS-' . sprintf('%06d', $id),
                'branch_name' => 'Synthetic Branch', 'full_name' => 'Synthetic Staff', 'unit' => 'tablet',
                'action_type' => $id % 2 ? 'stock_out' : 'stock_in', 'previous_stock' => 20, 'new_stock' => $id % 2 ? 18 : 22,
                'remarks' => $id % 137 === 0 ? ('START-NOTE-' . $id . "\n" . str_repeat('Full note: "quotes", <script>literal</script> and ' . "\u{20b1}" . '1.00. ', 30) . ' END-NOTE-' . $id) : 'Complete synthetic movement',
                'created_at' => '2026-10-23 08:15:00'];
        }
    })();
    $context = \App\Libraries\ReportPdf::context('Stock Logs Report', 'Synthetic Branch', 'All dates | Stress test', 'Synthetic Staff');
    $started = microtime(true);
    $pdf = (new \App\Libraries\StockLogPdf())->render($rows, $count, $context);
    $bytes = $pdf->output();
    if (!str_starts_with($bytes, '%PDF-')) throw new RuntimeException('Invalid PDF signature.');
    file_put_contents($out . '/stress-' . $count . '.pdf', $bytes);
    $result = ['records' => $count, 'pages' => $pdf->getCanvas()->get_page_count(), 'bytes' => strlen($bytes),
        'memory_limit' => ini_get('memory_limit'), 'peak_bytes' => memory_get_peak_usage(true), 'seconds' => round(microtime(true) - $started, 3)];
    file_put_contents($out . '/stress-' . $count . '.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
