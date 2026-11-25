<?php

require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
require dirname(__DIR__, 2) . '/tests/unit/BusinessLogicTest.php';

function fixtureCall(BusinessLogicTest $fixture, string $method, ...$arguments)
{
    return (new ReflectionMethod($fixture, $method))->invoke($fixture, ...$arguments);
}

function reviewProbe(string $name, callable $scenario): array
{
    $fixture = new BusinessLogicTest('testValidCorrectionAdjustsStockOnce');
    fixtureCall($fixture, 'setUp');
    try {
        $database = (new ReflectionProperty($fixture, 'db'))->getValue($fixture);
        return ['check' => $name, 'observed' => $scenario($fixture, $database)];
    } finally {
        fixtureCall($fixture, 'tearDown');
    }
}

function refundOne(BusinessLogicTest $fixture): void
{
    session()->set('refund_token_1', str_repeat('b', 32));
    fixtureCall($fixture, 'controller', \App\Controllers\Cashier\SalesController::class, [
        '_refund_token' => str_repeat('b', 32), 'refund' => [1 => '1'],
        'return_condition' => [1 => 'resellable'], 'refund_method' => 'cash',
        'reason' => 'Customer returned original package',
    ])->refundPartial(1);
}

$observations = [];
$observations[] = reviewProbe('correction_payment_date', function ($fixture, $database): array {
    $report = new \App\Libraries\CashMovementReport($database);
    $original = $report->generate('2026-09-14', '2026-09-14');
    $post = fixtureCall($fixture, 'correctionPost', ['items' => [1 => 3]]);
    fixtureCall($fixture, 'controller', \App\Controllers\Admin\SaleCorrection::class, $post)->update(1);
    return [
        'original_day_receipts_before' => $original['totals']['receipts'],
        'original_day_receipts_after' => $report->generate('2026-09-14', '2026-09-14')['totals']['receipts'],
        'today_report' => $report->generate(date('Y-m-d'), date('Y-m-d'))['totals'],
        'today_correction_activity' => fixtureCall($fixture, 'row', 'activity_logs')['activity'],
    ];
});
$observations[] = reviewProbe('correction_mixed_payment_methods', function ($fixture, $database): array {
    $post = fixtureCall($fixture, 'correctionPost', ['items' => [1 => 6], 'payment_method' => 'gcash', 'reference_no' => 'DEMO-EXTRA-10']);
    fixtureCall($fixture, 'controller', \App\Controllers\Admin\SaleCorrection::class, $post)->update(1);
    return [
        'original_sale_cash_received' => 50,
        'additional_gcash_confirmed' => 10,
        'stored_sale_method' => fixtureCall($fixture, 'row', 'sales')['payment_method'],
        'original_day_report' => (new \App\Libraries\CashMovementReport($database))->generate('2026-09-14', '2026-09-14')['rows'],
    ];
});
$observations[] = reviewProbe('returned_old_lot_after_stock_replacement', function ($fixture, $database): array {
    $database->table('branch_products')->where('id', 1)->update(['expiration_date' => '2026-09-15']);
    $service = new \App\Libraries\ExpiryStockResolution($database);
    $inventory = $service->inventory(1, 'admin', 1);
    $service->resolve(1, 'replace', ['outcome' => 'disposed', 'new_quantity' => '10', 'new_expiry' => '2099-01-01', 'confirmed' => '1', 'reason' => 'Old delivery expired'], 'admin', 1, 1, str_repeat('c', 48), $service::revision($inventory));
    refundOne($fixture);
    return [
        'original_lot_expiry' => '2026-09-15',
        'current_delivery_expiry' => fixtureCall($fixture, 'row', 'branch_products')['expiration_date'],
        'current_delivery_stock_after_old_return' => fixtureCall($fixture, 'row', 'branch_products')['stock'],
        'refund_records' => $database->table('refund_items')->countAllResults(),
        'error' => session()->getFlashdata('error'),
    ];
});
$observations[] = reviewProbe('expired_stock_above_reorder_level', function ($fixture, $database): array {
    $database->table('branch_products')->where('id', 1)->update(['expiration_date' => '2000-01-01', 'reorder_level' => 5]);
    $results = (new \App\Libraries\ReorderForecastService())->generate('2026-09-14', '2026-09-14', '', 0.3, 0.2);
    return ['physical_stock' => 10, 'sellable_stock' => 0, 'reorder_level' => 5, 'suggestion_count' => count($results)];
});
$observations[] = reviewProbe('product_edit_activity_failure', function ($fixture, $database): array {
    fixtureCall($fixture, 'retainedModels');
    $database->table('products')->where('id', 1)->update(['category_id' => 1, 'unit' => 'box']);
    $database->query("CREATE TRIGGER fail_product_audit BEFORE INSERT ON activity_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
    $error = null;
    try {
        fixtureCall($fixture, 'controller', \App\Controllers\Products::class, [
            'product_name' => 'Test Item', 'category_id' => '1', 'sku' => 'TEST', 'unit' => 'box',
            'cost_price' => '4', 'price' => '10', 'stock' => '12', 'old_stock' => '10',
            'reorder_level' => '100', 'expiration_date' => '2099-01-01', 'branch_id' => '1',
            'manufacturer' => '', 'supplier_id' => '', 'status' => 'active', 'stock_reason' => 'Physical stock check',
        ])->update(1);
    } catch (Throwable $failure) {
        $error = $failure->getMessage();
    }
    return ['initial_stock' => 10, 'stock_after_error' => fixtureCall($fixture, 'row', 'branch_products')['stock'],
        'stock_logs_after_error' => $database->table('stock_logs')->countAllResults(),
        'activity_logs' => $database->table('activity_logs')->countAllResults(), 'exception' => $error];
});
$observations[] = reviewProbe('expiry_changed_without_stock_disposition', function ($fixture, $database): array {
    fixtureCall($fixture, 'retainedModels');
    $database->table('products')->where('id', 1)->update(['category_id' => 1, 'unit' => 'box']);
    $database->table('branch_products')->where('id', 1)->update(['expiration_date' => '2000-01-01']);
    fixtureCall($fixture, 'controller', \App\Controllers\Products::class, [
        'product_name' => 'Test Item', 'category_id' => '1', 'sku' => 'TEST', 'unit' => 'box',
        'cost_price' => '4', 'price' => '10', 'stock' => '10', 'old_stock' => '10',
        'reorder_level' => '100', 'expiration_date' => '2099-01-01', 'branch_id' => '1',
        'manufacturer' => '', 'supplier_id' => '', 'status' => 'active',
    ])->update(1);
    return ['previous_expiry' => '2000-01-01', 'new_expiry' => fixtureCall($fixture, 'row', 'branch_products')['expiration_date'],
        'stock_retained' => fixtureCall($fixture, 'row', 'branch_products')['stock'],
        'stock_logs' => $database->table('stock_logs')->countAllResults(),
        'activity' => fixtureCall($fixture, 'row', 'activity_logs')['activity']];
});
$observations[] = reviewProbe('discount_rule_change_with_same_original_amount', function ($fixture, $database): array {
    $database->table('sales')->where('id', 1)->update(['discount_id' => 1, 'discount_amount' => 10, 'final_total' => 40, 'change_amount' => 60]);
    $database->table('sale_items')->where('id', 1)->update(['discount_applied' => 10, 'profit' => 20]);
    $database->table('discounts')->insert(['id' => 1, 'discount_name' => 'Original fixed ten', 'discount_type' => 'fixed', 'discount_value' => 10, 'minimum_purchase' => 0, 'applies_to' => 'all']);
    $database->table('discounts')->where('id', 1)->update(['discount_type' => 'percentage', 'discount_value' => 20]);
    $sale = fixtureCall($fixture, 'row', 'sales');
    $items = $database->table('sale_items')->where('sale_id', 1)->get()->getResultArray();
    $pricing = new \App\Libraries\SaleCorrectionPricing($database);
    $context = $pricing->context($sale, $items);
    $result = $pricing->calculate($sale, $items, [1 => 6], $context);
    return ['original_rule' => 'Fixed PHP 10', 'current_rule' => '20 percent', 'context_error' => $context['error'],
        'corrected_discount_using_current_rule' => $result['discount'], 'discount_using_original_rule' => 10];
});
$observations[] = reviewProbe('login_throttle_with_configured_dummy_fallback', function ($fixture, $database): array {
    \Config\Services::injectMock('cache', new \CodeIgniter\Cache\Handlers\DummyHandler(new \Config\Cache()));
    session()->remove('isLoggedIn');
    for ($attempt = 0; $attempt < 6; $attempt++) {
        fixtureCall($fixture, 'controller', \App\Controllers\Auth::class, ['username' => 'staff', 'password' => 'review-invalid-credential'])->attemptLogin();
    }
    $blocked = (new \App\Filters\LoginThrottleFilter())->before(service('request'));
    return ['failed_attempts' => 6, 'filter_blocked' => $blocked !== null,
        'last_error' => session()->getFlashdata('error'), 'recorded_attempts' => $database->table('activity_logs')->countAllResults()];
});
$observations[] = reviewProbe('forecast_historical_origin_uses_later_refunds', function ($fixture, $database): array {
    $service = new \App\Libraries\ReorderForecastService();
    $before = $service->generate('2026-09-14', '2026-09-14', '', 0.3, 0.2);
    $database->table('sales')->where('id', 1)->update(['status' => 'partially_refunded']);
    $database->table('refund_items')->insert(['sale_id' => 1, 'sale_item_id' => 1, 'quantity_refunded' => 1, 'refund_subtotal' => 10, 'created_at' => '2026-10-04 12:00:00']);
    $after = $service->generate('2026-09-14', '2026-09-14', '', 0.3, 0.2);
    return ['forecast_origin' => '2026-09-14', 'refund_occurred' => '2026-10-04',
        'estimated_daily_quantity_before_later_refund' => $before[0]['avg_daily_demand'],
        'estimated_daily_quantity_after_later_refund' => $after[0]['avg_daily_demand']];
});

file_put_contents(__DIR__ . '/panel-review-observations.json', json_encode($observations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo json_encode($observations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
