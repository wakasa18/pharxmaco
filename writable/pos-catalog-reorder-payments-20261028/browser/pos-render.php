<?php
require dirname(__DIR__, 3) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
$database = config('Database');
$database->tests = ['database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '', 'DBDebug' => false];
$database->defaultGroup = 'tests';
$db = \Config\Database::connect('tests');
if ($db->database !== ':memory:') throw new RuntimeException('Synthetic rendering cannot use application data.');
session()->set(['role' => 'cashier', 'branch_id' => 1, 'user_id' => 1, 'full_name' => 'Maria Santos', 'branch_name' => 'Main Branch']);
session()->remove(['_ci_old_input', 'cart', 'held_sales', 'error', 'success', 'warning']);
config(\Config\ReceiptInvoice::class)->taxStatus = 'vat';
$common = ['category_id' => 1, 'category_name' => 'Medicines', 'cost_price' => 10, 'stock' => 20, 'quantity' => 1,
    'expiration_date' => '2030-01-01', 'status' => 'active', 'branch_id' => 1, 'unit' => 'tablet',
    'tax_treatment' => 'taxable', 'senior_pwd_eligible' => 1];
$products = [
    1 => $common + ['id' => 1, 'product_id' => 1, 'product_name' => 'Paracetamol 500 mg tablet', 'generic_name' => 'Paracetamol', 'sku' => 'GEN-PARA-500', 'price' => 112, 'subtotal' => 112, 'medicine_type' => 'generic'],
    2 => $common + ['id' => 2, 'product_id' => 2, 'product_name' => 'Bioflu tablet', 'generic_name' => 'Phenylephrine + Paracetamol', 'sku' => 'BRD-BIOFLU', 'price' => 50, 'subtotal' => 50, 'medicine_type' => 'branded'],
    3 => array_replace($common, ['id' => 3, 'product_id' => 3, 'product_name' => 'Sterile gauze pads', 'generic_name' => '', 'sku' => 'SUP-GAUZE', 'price' => 20, 'subtotal' => 20, 'medicine_type' => 'non_medicine', 'category_id' => 2, 'category_name' => 'Supplies', 'senior_pwd_eligible' => 0]),
];
foreach (['pos-catalog', 'pos-empty-branded', 'pos-query'] as $name) {
    \Config\Services::resetSingle('renderer');
    $data = ['products' => $name === 'pos-empty-branded' ? [$products[1], $products[3]] : array_values($products),
        'cart' => [1 => $products[1]], 'discounts' => [], 'heldSales' => [], 'keyword' => $name === 'pos-query' ? 'Paracetamol' : '',
        'sellingBranch' => ['id' => 1, 'branch_name' => 'Main Branch'], 'sellingBranchName' => 'Main Branch'];
    $html = view('cashier/sales/index', $data, ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    file_put_contents(__DIR__ . '/' . $name . '.html', $html);
}
echo "Rendered three synthetic POS catalog/payment fixtures.\n";
