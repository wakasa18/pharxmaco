<?php
// Isolated report evidence. All controller writes use in-memory SQLite.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
require ROOTPATH . 'tests/unit/BusinessLogicTest.php';
date_default_timezone_set('Asia/Manila');
$fixture = new BusinessLogicTest('testStatutoryProductReportDeductsVatReliefAndActualRefunds');
$call = static fn(string $name, mixed ...$args) => (new ReflectionMethod(BusinessLogicTest::class, $name))->invoke($fixture, ...$args);
$call('setUp');
try {
    $db = (new ReflectionProperty(BusinessLogicTest::class, 'db'))->getValue($fixture);
    $post = $call('statutoryCheckoutPost', 'pwd', ['items' => [1 => 2], 'amount_paid' => '224']);
    $call('controller', \App\Controllers\Cashier\SalesController::class, $post)->checkout();
    $sale = $db->table('sales')->where('id', 2)->get()->getRowArray();
    if (!$sale || (float)$sale['final_total'] !== 160.0) throw new RuntimeException('Synthetic checkout failed: ' . session()->getFlashdata('error'));
    $item = $db->table('sale_items')->where('sale_id', 2)->get()->getRowArray();
    session()->set('refund_token_2', 'pdf-review-only');
    $call('controller', \App\Controllers\Cashier\SalesController::class, [
        '_refund_token' => 'pdf-review-only', 'reason' => 'Synthetic PDF review', 'refund_method' => 'cash',
        'refund' => [$item['id'] => 1], 'return_condition' => [$item['id'] => 'damaged'],
    ])->refundPartial(2);
    $refund = $db->table('refund_items')->where('sale_id', 2)->get()->getRowArray();
    if (!$refund || (float)$refund['refund_subtotal'] !== 80.0) throw new RuntimeException('Synthetic refund failed');
    $controller = $call('controller', \App\Controllers\Admin\ReportsController::class, []);
    $today = date('Y-m-d');
    $data = (new ReflectionMethod($controller, 'buildReportData'))->invoke($controller, $today, $today, $today, $today, '1');
    $result = ['database' => 'isolated in-memory SQLite', 'sale' => array_intersect_key($sale, array_flip(['total_amount', 'discount_amount', 'final_total'])),
        'vat_relief' => $item['vat_exemption_amount'], 'refund' => $refund['refund_subtotal'], 'printed_summary' => $data['salesSummary'],
        'displayed_gross_minus_discount' => (float)$data['salesSummary']['gross_sales'] - (float)$data['salesSummary']['total_discount'],
        'printed_net' => (float)$data['salesSummary']['net_sales']];
    file_put_contents(__DIR__ . '/financial-probe.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents(__DIR__ . '/financial-data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
} finally { $call('tearDown'); }
