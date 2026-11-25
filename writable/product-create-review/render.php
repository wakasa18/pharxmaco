<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
$options = [
    'categories' => [['id' => 1, 'category_name' => 'Medicines']],
    'suppliers' => [['id' => 1, 'supplier_name' => 'Mercury Supply', 'contact_person' => 'Ana Cruz']],
    'branches' => [['id' => 1, 'branch_name' => 'Main Branch'], ['id' => 2, 'branch_name' => 'Patag Branch']],
];
foreach (['admin', 'cashier'] as $role) {
    \Config\Services::resetSingle('renderer');
    session()->set(['role' => $role, 'branch_id' => $role === 'cashier' ? 1 : null, 'full_name' => 'Maria Reyes']);
    $html = view('products/create', $options + ['isCashier' => $role === 'cashier', 'assignedBranch' => $role === 'cashier' ? $options['branches'][0] : null], ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~action="[^"]*/products/store"~', 'action="/products/store"', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    file_put_contents(__DIR__ . '/' . $role . '.html', $html);
}
echo "Rendered admin and cashier product forms; application database untouched.\n";
