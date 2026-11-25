<?php
// Read-only application views and real Dompdf, supplied synthetic data only.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$out = ROOTPATH . 'tmp/pdfs/pdf-export-update';
if (!is_dir($out)) mkdir($out, 0775, true);
$base = ['branchName' => 'Main Branch - Santa Maria', 'generatedBy' => 'Synthetic Export Review',
    'generatedAt' => 'October 16, 2026 02:00 PM', 'filterNotices' => []];
$product = ['product_name' => 'Ascorbic Acid + Zinc 500 mg / 10 mg Film-coated Tablet', 'sku' => 'VIT-C-ZINC-001',
    'manufacturer' => 'Sample Healthcare Corporation', 'supplier_name' => 'Sample Medical Supplies',
    'branch_name' => $base['branchName'], 'id' => 10, 'product_id' => 1, 'stock_delivery_id' => str_repeat('a',32), 'stock' => 15, 'unit' => 'tablet', 'expiration_date' => '2026-11-01',
    'reorder_level' => 30, 'days_left' => 3, 'suggested_qty' => 120, 'urgency' => 'critical'];
$sales = array_replace(json_decode(file_get_contents(ROOTPATH . 'writable/pdf-export-review/financial-data.json'), true, 512, JSON_THROW_ON_ERROR), $base);
// Preserve the actual report/controller amounts, changing only identifying labels for layout QA.
$sales['recentSales'][0]['invoice_no'] = 'SYNTHETIC-INV-20261016-000001';
$sales['recentSales'][0]['full_name'] = 'Synthetic Cashier';
$sales['recentSales'][0]['branch_name'] = $base['branchName'];
$sales['topProducts'][0]['product_name_snapshot'] = $product['product_name'];
$sales['reorderForecast'] = [$product];
$sales['reorderCoverageDays'] = 30;
$weekly = array_replace($sales, ['reportType' => 'weekly', 'forecastType' => 'weekly',
    'reportDateFrom' => '2026-10-12', 'reportDateTo' => '2026-10-18',
    'forecastDateFrom' => '2026-10-12', 'forecastDateTo' => '2026-10-18']);
foreach (['reportHistory', 'forecastHistory'] as $key) foreach ($weekly[$key] as &$row) $row['report_date'] = '2026-W42';
unset($row);
$weekly['futureForecast'] = [['forecast_date' => '2026-W43', 'forecast_value' => 80.0]];
$monthly = array_replace($sales, ['reportType' => 'monthly', 'forecastType' => 'monthly',
    'reportDateFrom' => '2026-10-01', 'reportDateTo' => '2026-10-31',
    'forecastDateFrom' => '2026-10-01', 'forecastDateTo' => '2026-10-31']);
foreach (['reportHistory', 'forecastHistory'] as $key) foreach ($monthly[$key] as &$row) $row['report_date'] = '2026-10';
unset($row);
$monthly['futureForecast'] = [['forecast_date' => '2026-11', 'forecast_value' => 80.0]];
$emptySales = array_replace($sales, ['salesSummary' => [], 'profitSummary' => [], 'averageTransactionValue' => 0,
    'grossProfitMargin' => 0, 'nextForecastValue' => 0, 'cashMovements' => ['rows' => [], 'returns' => [], 'totals' => []],
    'reportHistory' => [], 'forecastHistory' => [], 'futureForecast' => [], 'topProducts' => [], 'recentSales' => [], 'reorderForecast' => []]);
$longSales = array_replace($sales, ['reportDateFrom' => '2026-10-01', 'reportDateTo' => '2026-10-14',
    'forecastDateFrom' => '2026-10-01', 'forecastDateTo' => '2026-10-14']);
$periods = []; $movements = [];
for ($i = 1; $i <= 14; $i++) {
    $date = '2026-10-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
    $periods[] = ['report_date' => $date, 'total_transactions' => 1, 'gross_sales' => 112, 'original_sales' => 112, 'total_vat_relief' => 0, 'total_refunds' => 0, 'total_discount' => 0,
        'net_sales' => 112, 'cogs' => 40, 'gross_profit' => 72, 'forecast' => 110];
    $movements[] = ['date' => $date, 'method' => $i % 2 ? 'cash' : 'gcash', 'receipts' => 112, 'refunds' => 0, 'net' => 112];
}
$longSales['reportHistory'] = $longSales['forecastHistory'] = $periods;
$longSales['salesSummary'] = ['original_sales' => 1568, 'total_vat_relief' => 0, 'total_refunds' => 0, 'gross_sales' => 1568, 'net_sales' => 1568, 'total_discount' => 0, 'total_transactions' => 14];
$longSales['profitSummary'] = ['cogs' => 560, 'gross_profit' => 1008];
$longSales['averageTransactionValue'] = 112;
$longSales['grossProfitMargin'] = 64.29;
$longSales['nextForecastValue'] = 112;
$longSales['cashMovements'] = ['rows' => $movements, 'returns' => [], 'totals' => ['receipts' => 1568, 'refunds' => 0, 'net' => 1568, 'cash_net' => 784, 'unknown_refunds' => 0]];
$longSales['recentSales'] = []; $longSales['topProducts'] = []; $longSales['reorderForecast'] = [];
for ($i = 1; $i <= 10; $i++) {
    $longSales['recentSales'][] = ['invoice_no' => 'SYNTHETIC-INV-20261014-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
        'branch_name' => $base['branchName'], 'full_name' => 'Synthetic Cashier With Longer Name',
        'status' => 'completed', 'remaining_total' => 112, 'refunded_amount' => 0, 'sale_date' => '2026-10-14 19:30:05'];
    $longSales['topProducts'][] = ['product_name_snapshot' => $product['product_name'] . ' - demo ' . $i,
        'total_qty_sold' => $i <= 4 ? 2 : 1, 'total_sales' => $i <= 4 ? 224 : 112];
    $longSales['reorderForecast'][] = array_replace($product, ['sku' => 'SYNTHETIC-' . $i, 'urgency' => $i % 2 ? 'critical' : 'warning']);
}
$longSales['futureForecast'] = array_map(fn($i) => ['forecast_date' => '2026-10-' . (14 + $i), 'forecast_value' => 112], range(1, 7));
$expiry = $base + ['summary' => ['total' => 4, 'expired' => 1, 'near' => 1, 'good' => 1, 'missing' => 1],
    'products' => array_map(fn($date) => array_replace($product, ['expiration_date' => $date]), ['2026-10-01', '2026-11-01', '2027-02-01', null]),
    'today' => '2026-10-16', 'nearDate' => '2026-11-15', 'nearExpiryDays' => 30, 'filterStatusLabel' => 'All Statuses'];
$expiryLong = array_replace($expiry, ['summary' => ['total' => 36, 'expired' => 9, 'near' => 9, 'good' => 9, 'missing' => 9], 'products' => []]);
for ($i = 0; $i < 36; $i++) $expiryLong['products'][] = array_replace($expiry['products'][$i % 4], ['sku' => 'SYNTHETIC-' . str_pad((string)($i+1), 3, '0', STR_PAD_LEFT)]);
$log = ['id' => 123, 'product_id' => 1, 'sku' => 'VIT-C-ZINC-001', 'unit' => 'tablet', 'product_name' => $product['product_name'], 'branch_name' => $base['branchName'],
    'full_name' => 'Synthetic Administrator', 'action_type' => 'stock_adjustment', 'quantity' => -2,
    'previous_stock' => 17, 'new_stock' => 15, 'remarks' => 'Physical stock count adjusted after branch inventory review.', 'created_at' => '2026-10-16 13:30:05'];
$stock = $base + ['logs' => [$log], 'dateFrom' => '2026-10-01', 'dateTo' => '2026-10-16', 'search' => '', 'actionTypeLabel' => 'All Types'];
$stockLong = array_replace($stock, ['logs' => []]);
for ($i = 1; $i <= 35; $i++) $stockLong['logs'][] = array_replace($log, ['id' => $i, 'remarks' => $i % 7 === 0
    ? str_repeat('Recount completed after checking the receiving note and shelf quantities. ', 7)
    : 'Row ' . $i . ': Physical stock count adjusted after inventory review.']);
$cases = [
    'sales-daily' => ['admin/reports/reports_pdf', $sales],
    'sales-weekly' => ['admin/reports/reports_pdf', $weekly],
    'sales-monthly' => ['admin/reports/reports_pdf', $monthly],
    'sales-empty' => ['admin/reports/reports_pdf', $emptySales],
    'sales-long' => ['admin/reports/reports_pdf', $longSales],
    'expiry-normal' => ['admin/reports/expiry_report_pdf', $expiry],
    'expiry-empty' => ['admin/reports/expiry_report_pdf', array_replace($expiry, ['products' => [], 'summary' => []])],
    'expiry-long' => ['admin/reports/expiry_report_pdf', $expiryLong],
    'stock-normal' => ['stock_logs/pdf', $stock],
    'stock-empty' => ['stock_logs/pdf', array_replace($stock, ['logs' => []])],
    'stock-long' => ['stock_logs/pdf', $stockLong],
];
$cases['sales-summary'] = ['admin/reports/reports_pdf', array_replace($sales, ['pdfMode' => 'summary'])];
$cases['sales-long-summary'] = ['admin/reports/reports_pdf', array_replace($longSales, ['pdfMode' => 'summary'])];
$legacyData = array_replace($sales, ['salesSummary' => array_replace($sales['salesSummary'], ['total_vat_relief' => 20])]);
foreach ($legacyData['reportHistory'] as &$legacyRow) $legacyRow['total_vat_relief'] = 20;
unset($legacyRow);
$cases['sales-legacy-adjustment'] = ['admin/reports/reports_pdf', $legacyData];
$results = [];
foreach ($cases as $name => [$view, $data]) {
    $data['pdfMode'] ??= 'full';
    $title = str_starts_with($name, 'stock') ? 'Stock Logs Report' : (str_starts_with($name, 'expiry') ? 'Expiry Report' : ($data['pdfMode'] === 'summary' ? 'Sales Summary' : 'Sales and Forecast Report'));
    $scope = str_starts_with($name, 'expiry') ? 'As of 2026-10-16 | All Statuses' : '2026-10-01 to 2026-10-16 | Synthetic review';
    $data = array_replace($data, \App\Libraries\ReportPdf::context($title, $base['branchName'], $scope, 'Synthetic Export Review'));
    $pdf = \App\Libraries\PdfFactory::create();
    $html = view($view, $data, ['saveData' => false]);
    $pdf->loadHtml($html, 'UTF-8'); $pdf->setPaper('A4', 'landscape'); $pdf->render();
    $isStock = str_starts_with($name, 'stock');
    $class = $isStock ? \App\Controllers\StockLogsController::class : \App\Controllers\Admin\ReportsController::class;
    $controller = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    (new ReflectionMethod($class, $isStock ? 'addPageNumbers' : 'addPdfPageNumbers'))->invoke($controller, $pdf, $data);
    file_put_contents("$out/$name.html", $html);
    file_put_contents("$out/$name.pdf", $pdf->output());
    $results[] = ['name' => $name, 'pages' => $pdf->getCanvas()->get_page_count(), 'bytes' => filesize("$out/$name.pdf"),
        'width_pt' => $pdf->getCanvas()->get_width(), 'height_pt' => $pdf->getCanvas()->get_height()];
}
file_put_contents(__DIR__ . '/manifest.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";

