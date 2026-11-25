<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'admin', 'branch_id' => null, 'user_id' => 1, 'full_name' => 'Maria Reyes']);
$options = ['categories' => [['id' => 1, 'category_name' => 'Medicines']],
    'branches' => [['id' => 1, 'branch_name' => 'Main Branch']],
    'suppliers' => [['id' => 1, 'supplier_name' => 'Mercury Supply', 'contact_person' => 'Ana Cruz']],
    'validation' => service('validation'), 'isCashier' => false];
$product = ['id' => 2, 'product_name' => 'Biogesic 500 mg', 'sku' => 'BIO500-001', 'category_id' => 1, 'unit' => 'tablet',
    'cost_price' => '4.50', 'price' => '7.50', 'stock' => 120, 'reorder_level' => 20, 'manufacturer' => 'Unilab', 'supplier_id' => 1,
    'status' => 'active', 'generic_name' => 'Paracetamol', 'strength' => '500 mg', 'medicine_type' => 'branded', 'senior_pwd_eligible' => 1, 'tax_treatment' => 'taxable'];
$branch = ['id' => 1, 'branch_name' => 'Main Branch', 'branch_code' => 'MAIN', 'contact_number' => '+63 (917) 123-4567', 'address' => 'Unit #2, Main St.', 'status' => 'active'];
$supplier = ['id' => 1, 'supplier_name' => 'Mercury Supply', 'contact_person' => 'Ana Cruz', 'contact_number' => '09171234567', 'email' => 'orders+demo@example.com', 'address' => 'Unit #3, Main St.', 'status' => 'active'];
$user = ['id' => 2, 'full_name' => 'Ana Cruz', 'username' => 'ana.cruz', 'role' => 'cashier', 'branch_id' => 1, 'status' => 'active'];
$discount = ['id' => 1, 'discount_name' => 'Vitamin C + Zinc (20% off)', 'discount_type' => 'percentage', 'discount_value' => 20,
    'description' => '', 'applies_to' => 'all', 'category_id' => '', 'product_id' => '', 'start_date' => '', 'end_date' => '',
    'minimum_purchase' => 0, 'max_discount_amount' => '', 'status' => 'active'];
$views = [
    'product-create' => ['products/create', []],
    'product-edit' => ['products/edit', ['product' => $product, 'branchProduct' => $product + ['expiration_date' => '2027-10-20'], 'branchId' => 1]],
    'category-create' => ['categories/create', []], 'category-edit' => ['categories/edit', ['category' => ['id' => 1, 'category_name' => 'Medicines']]],
    'user-create' => ['admin/users/create', []], 'user-edit' => ['admin/users/edit', ['user' => $user]],
    'branch-create' => ['admin/branches/create', []], 'branch-edit' => ['admin/branches/edit', ['branch' => $branch]],
    'supplier-create' => ['admin/suppliers/create', []], 'supplier-edit' => ['admin/suppliers/edit', ['supplier' => $supplier]],
    'discount-create' => ['admin/discounts/create', ['form' => $discount, 'products' => [$product]]],
    'discount-edit' => ['admin/discounts/edit', ['form' => $discount, 'discount' => $discount, 'products' => [$product]]],
];
foreach ($views as $name => [$view, $data]) {
    \Config\Services::resetSingle('renderer');
    $html = view($view, $data + $options, ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~(<form\b[^>]*\baction=)"[^"]+"~', '$1"/products/store"', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    file_put_contents(__DIR__ . '/' . $name . '.html', $html);
}
echo 'Rendered ' . count($views) . " management form fixtures without application database access.\n";
