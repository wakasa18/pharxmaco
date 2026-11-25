<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
$database = config('Database');
$database->tests = ['database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '', 'DBDebug' => false];
$database->defaultGroup = 'tests';
$db = \Config\Database::connect('tests');
if ($db->database !== ':memory:') throw new RuntimeException('Synthetic render must not use an application database.');
$options = ['categories' => [['id' => 1, 'category_name' => 'Medicines']],
    'branches' => [['id' => 1, 'branch_name' => 'Main Branch']],
    'suppliers' => [['id' => 1, 'supplier_name' => 'Demo Supplier', 'contact_person' => 'Ana Cruz']],
    'validation' => service('validation'), 'branchId' => 1];
$product = ['id' => 1, 'product_name' => 'Biogesic 500 mg', 'sku' => 'BIO500-001', 'category_id' => 1, 'unit' => 'tablet',
    'cost_price' => '4.50', 'price' => '7.50', 'stock' => 120, 'reorder_level' => 20, 'manufacturer' => 'Unilab', 'supplier_id' => 1,
    'status' => 'active', 'generic_name' => 'Paracetamol', 'strength' => '500 mg', 'medicine_type' => 'branded', 'senior_pwd_eligible' => 1, 'tax_treatment' => 'taxable'];
$row = $product + ['product_id' => 1, 'branch_id' => 1, 'product_status' => 'active', 'branch_status' => 'active',
    'deleted_at' => null, 'branch_name' => 'Main Branch', 'expiration_date' => '2020-01-01'];
foreach (['admin-create', 'cashier-create', 'admin-edit-normal', 'admin-edit-legacy', 'admin-edit-expired', 'catalog', 'admin-replace', 'cashier-replace', 'admin-redisplay'] as $name) {
    $cashier = str_starts_with($name, 'cashier') || $name === 'catalog';
    session()->set(['role' => $cashier ? 'cashier' : 'admin', 'branch_id' => 1, 'user_id' => 1, 'full_name' => 'Maria Reyes']);
    session()->remove('_ci_old_input');
    $view = 'products/create'; $data = $options + ['isCashier' => $cashier, 'assignedBranch' => $options['branches'][0]];
    $post = [];
    if (str_contains($name, 'edit')) {
        $view = 'products/edit';
        $data += ['product' => $product, 'branchProduct' => $product + ['expiration_date' => $name === 'admin-edit-expired' ? '2000-01-01' : ($name === 'admin-edit-normal' ? '2028-06-12' : '2099-01-01')]];
    } elseif ($name === 'catalog') {
        $view = 'products/catalog';
        $data += ['selected' => $product + ['category_name' => 'Medicines', 'supplier_name' => 'Demo Supplier'], 'branch' => $options['branches'][0], 'keyword' => '', 'products' => [], 'pager' => null];
    } elseif (str_contains($name, 'replace')) {
        $view = 'admin/reports/expiry_action';
        $data += ['row' => $row, 'action' => 'replace', 'input' => [], 'error' => null, 'history' => [], 'token' => str_repeat('b', 48)];
    } else {
        $post = $product + ['branch_id' => 1, 'expiration_date' => ''];
        // Retain old override fields in this rejected-request fixture to prove
        // they cannot restore the removed controls or override the date cap.
        if ($name === 'admin-redisplay') $post = array_replace($post, ['expiration_date' => '2099-01-01', 'expiry_range_reviewed' => '1', 'expiry_range_reason' => 'Verified "sealed" package <script>not executable</script>']);
    }
    if ($post) session()->setFlashdata('_ci_old_input', ['get' => [], 'post' => $post]);
    \Config\Services::resetSingle('renderer');
    $html = view($view, $data, ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~action="[^"]+"~', 'action="/fixture-submit"', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    file_put_contents(__DIR__ . '/' . $name . '.html', $html);
}
file_put_contents(__DIR__ . '/limits.json', json_encode(\App\Libraries\ExpiryDatePolicy::limits(), JSON_PRETTY_PRINT));
echo "Rendered nine actual form views using an empty in-memory database.\n";
