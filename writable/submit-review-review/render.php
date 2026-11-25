<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url','form']);
session()->set(['role' => 'admin', 'user_id' => 1, 'full_name' => 'Maria Reyes']);
$options = ['categories' => [['id' => 1, 'category_name' => 'Medicines'], ['id' => 2, 'category_name' => 'Supplies']],
    'branches' => [['id' => 1, 'branch_name' => 'Main Branch']],
    'suppliers' => [['id' => 1, 'supplier_name' => 'Mercury Supply', 'contact_person' => 'Ana Cruz']],
    'validation' => service('validation')];
$product = ['id' => 2, 'product_name' => 'Biogesic 500 mg', 'sku' => 'BIO500-001', 'category_id' => 1, 'unit' => 'tablet',
    'cost_price' => '4.50', 'price' => '7.50', 'stock' => 120, 'reorder_level' => 20, 'manufacturer' => 'Unilab', 'supplier_id' => 1,
    'status' => 'active', 'generic_name' => 'Paracetamol', 'strength' => '500 mg', 'medicine_type' => 'branded', 'senior_pwd_eligible' => 1, 'tax_treatment' => null];
$branchProduct = array_replace($product, ['cost_price' => '8.00', 'price' => '12.00', 'expiration_date' => '2027-10-20']);
$discount = ['id' => 1, 'discount_name' => 'Medicine offer', 'description' => 'Weekend offer', 'discount_type' => 'percentage', 'discount_value' => '20.00',
    'applies_to' => 'product', 'product_id' => 2, 'category_id' => null, 'minimum_purchase' => '100.00', 'max_discount_amount' => '50.00',
    'start_date' => '2026-10-20', 'end_date' => '2026-11-20', 'status' => 'active'];
$defaults = ['discount_name' => '', 'description' => '', 'discount_type' => 'percentage', 'discount_value' => '', 'applies_to' => 'all',
    'product_id' => '', 'category_id' => '', 'minimum_purchase' => '', 'max_discount_amount' => '', 'start_date' => '', 'end_date' => '', 'status' => 'active'];
$views = [
    'product-edit' => ['products/edit', ['product' => $product, 'branchProduct' => $branchProduct, 'branchId' => 1]],
    'product-redisplay' => ['products/edit', ['product' => $product, 'branchProduct' => $branchProduct, 'branchId' => 1]],
    'discount-create' => ['admin/discounts/create', ['form' => $defaults, 'products' => [$product]]],
    'discount-edit' => ['admin/discounts/edit', ['form' => $discount, 'discount' => $discount, 'products' => [$product]]],
    'discount-redisplay' => ['admin/discounts/edit', ['form' => array_replace($discount, ['discount_value' => '30.00']), 'discount' => $discount, 'products' => [$product]]],
];
foreach ($views as $name => [$view, $data]) {
    session()->remove('_ci_old_input');
    if ($name === 'product-redisplay') session()->setFlashdata('_ci_old_input', ['get' => [], 'post' => ['price' => '15.00']]);
    \Config\Services::resetSingle('renderer');
    $html = view($view, $data + $options, ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~action="[^"]+"~', 'action="/fixture-submit"', $html);
    $html = preg_replace('~data-activity-url="[^"]+"~', 'data-activity-url="/session/activity"', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    file_put_contents(__DIR__ . '/' . $name . '.html', $html);
}
echo "Rendered five review fixtures; no application database used.\n";
