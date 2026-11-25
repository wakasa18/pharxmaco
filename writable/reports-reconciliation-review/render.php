<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'admin', 'branch_id' => null, 'full_name' => 'Demo Administrator']);
$summary = json_decode(file_get_contents(__DIR__ . '/source-audit.json'), true)['with_prepared_demo'];
$summary['total_transactions'] = $summary['transactions'];
$summary['total_discount'] = $summary['discounts'];
$summary['total_vat_relief'] = $summary['vat_exemption'];
$summary['total_refunds'] = $summary['refunds'];
$summary['gross_sales'] = $summary['gross_after_refunds'];
$data = ['branchName' => 'All Branches', 'branches' => [], 'branchId' => '', 'reportType' => 'daily', 'forecastType' => 'daily',
    'reportDateFrom' => '2026-09-21', 'reportDateTo' => '2026-10-20', 'forecastDateFrom' => '2026-09-21', 'forecastDateTo' => '2026-10-20',
    'salesSummary' => $summary, 'profitSummary' => ['cogs' => 167034, 'gross_profit' => 70603.09],
    'grossProfitMargin' => 29.7, 'averageTransactionValue' => 375.41, 'nextForecastValue' => 3212.45,
    'reportHistory' => [], 'forecastHistory' => [], 'futureForecast' => [], 'topProducts' => [], 'recentSales' => [],
    'reorderForecast' => [], 'exportHistory' => [], 'forecastingDataLog' => [], 'cashMovements' => null];
foreach (['screenshot', 'legacy', 'empty'] as $state) {
    \Config\Services::resetSingle('renderer');
    $fixture = $data;
    if ($state === 'legacy') $fixture['salesSummary']['net_sales'] -= 4;
    if ($state === 'empty') {
        $fixture['salesSummary'] = [];
        $fixture['profitSummary'] = [];
        $fixture['averageTransactionValue'] = 0;
        $fixture['nextForecastValue'] = 0;
    }
    $html = view('admin/reports/index', $fixture, ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~<script src="https://cdn.jsdelivr.net/npm/chart.js[^>]*></script>~', '', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    file_put_contents(__DIR__ . '/' . $state . '.html', $html);
}
echo "Rendered three report fixtures without accessing the application database.\n";
