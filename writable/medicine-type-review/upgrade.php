<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$configuration = config(\Config\Database::class)->default;
if (!in_array($configuration['hostname'], ['localhost','127.0.0.1','::1'], true)) {
    throw new RuntimeException('This helper only upgrades the local database.');
}
$db = \Config\Database::connect('default');
$db->initialize();
$before = $db->table('products')->orderBy('id')->get()->getResultArray();
$sql = file_get_contents(ROOTPATH . 'database/upgrades/2026-10-14-product-medicine-types.sql');
for ($pass = 0; $pass < 2; $pass++) {
    if (!$db->connID->multi_query($sql)) throw new RuntimeException('Medicine type upgrade failed.');
    do {
        if ($result = $db->connID->store_result()) $result->free();
        if (!$db->connID->more_results()) break;
        if (!$db->connID->next_result()) throw new RuntimeException('Medicine type upgrade statement failed.');
    } while (true);
}
$db->resetDataCache();
$after = $db->table('products')->orderBy('id')->get()->getResultArray();
foreach ($after as &$product) unset($product['medicine_type']);
unset($product);
foreach ($before as &$product) unset($product['medicine_type']);
unset($product);
if ($before !== $after) throw new RuntimeException('An existing product value changed.');
echo 'Medicine type column added locally; repeat import passed and existing product values are unchanged.' . PHP_EOL;
