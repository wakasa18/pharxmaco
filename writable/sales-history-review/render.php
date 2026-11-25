<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'admin', 'user_id' => 1, 'full_name' => 'Maria Reyes']);
$sales = [];
for ($i = 1; $i <= 25; $i++) {
    $sales[] = ['id' => $i, 'invoice_no' => 'DEMO-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'branch_name' => $i % 2 ? 'Main Branch' : 'Second Branch', 'full_name' => $i % 2 ? 'Maria Reyes' : 'Carlo Santos',
        'final_total' => $i * 11.25, 'payment_method' => 'cash', 'status' => 'completed', 'sale_date' => '2026-10-20 10:00:00'];
}
$data = ['sales' => $sales, 'tableTruncated' => true, 'rowLimit' => 1000, 'dateFrom' => '', 'dateTo' => '', 'dateRangeError' => ''];
foreach (['history', 'range', 'empty', 'generic'] as $name) {
    \Config\Services::resetSingle('renderer');
    $fixture = $data;
    if ($name === 'range') $fixture = array_replace($fixture, ['dateFrom' => '2026-10-01', 'dateTo' => '2026-10-20', 'tableTruncated' => false]);
    if ($name === 'empty') $fixture = array_replace($fixture, ['sales' => [], 'tableTruncated' => false]);
    $html = view('cashier/sales/history', $fixture, ['saveData' => false]);
    $html = str_replace(site_url('cashier/sales/history'), '/history.html', $html);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~data-activity-url="[^"]+"~', 'data-activity-url="/session/activity"', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    if ($name === 'generic') $html = str_replace(['data-dt-date-filter="off"', 'data-dt-link-ignore="date_from,date_to"'], '', $html);
    file_put_contents(__DIR__ . '/' . $name . '.html', $html);
}
echo "Rendered four sales-history fixtures; no application database access.\n";
