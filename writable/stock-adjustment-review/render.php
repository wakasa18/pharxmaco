<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'cashier', 'branch_id' => 1, 'user_id' => 2, 'full_name' => 'Maria Reyes']);
$products = [];
foreach ([['box', 35, '2026-04-07'], ['tablet', 1, '2027-01-01'], ['pcs', 2, '2027-03-01'], ['bottle', 0, null], ['mL', 50, null]] as $i => [$unit, $stock, $expiry]) {
    $products[] = ['id' => $i + 1, 'product_name' => 'Demo product ' . ($i + 1), 'branch_id' => 1, 'branch_name' => 'Main Branch',
        'category_name' => 'Medicines', 'manufacturer' => 'Demo Manufacturer', 'supplier_name' => 'Demo Supplier', 'sku' => 'DEMO-' . ($i + 1),
        'unit' => $unit, 'cost_price' => 10, 'price' => 15, 'stock' => $stock, 'expiration_date' => $expiry, 'status' => 'active'];
}
$html = view('products/index', ['products' => $products, 'medicineType' => '', 'keyword' => '', 'branchId' => 1], ['saveData' => false]);
$html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
$html = preg_replace('~action="[^"]*/products/update-stock"~', 'action="/fixture-stock"', $html);
$html = preg_replace('~data-activity-url="[^"]+"~', 'data-activity-url="/session/activity"', $html);
$html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
file_put_contents(__DIR__ . '/products.html', $html);
echo "Rendered stock adjustment fixture; no application database used.\n";
