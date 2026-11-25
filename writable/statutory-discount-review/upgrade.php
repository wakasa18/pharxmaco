<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$configuration = config(\Config\Database::class)->default;
if (!in_array($configuration['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Only the local database can be upgraded by this helper.');
$db = \Config\Database::connect('default');
$db->initialize();
$before = [];
foreach (['products', 'sales', 'sale_items'] as $table) $before[$table] = $db->table($table)->orderBy('id')->get()->getResultArray();
file_put_contents(__DIR__ . '/before-upgrade-' . date('Ymd-His') . '.json', json_encode($before, JSON_THROW_ON_ERROR));
$sql = file_get_contents(ROOTPATH . 'database/upgrades/2026-10-14-senior-pwd-discounts.sql');
for ($pass = 0; $pass < 2; $pass++) {
    if (!$db->connID->multi_query($sql)) throw new RuntimeException('Statutory discount upgrade failed.');
    do {
        if ($result = $db->connID->store_result()) $result->free();
        if (!$db->connID->more_results()) break;
        if (!$db->connID->next_result()) throw new RuntimeException('A statutory upgrade statement failed.');
    } while (true);
}
$db->resetDataCache();
foreach (['products' => ['senior_pwd_eligible'], 'sales' => [], 'sale_items' => ['vat_exemption_amount', 'statutory_discount_kind']] as $table => $fields) {
    $after = $db->table($table)->orderBy('id')->get()->getResultArray();
    $original = $before[$table];
    foreach ($after as &$row) foreach ($fields as $field) unset($row[$field]);
    unset($row);
    foreach ($original as &$row) foreach ($fields as $field) unset($row[$field]);
    unset($row);
    if ($after !== $original) throw new RuntimeException('Existing ' . $table . ' values changed.');
    echo $table . ': ' . count($after) . ' existing rows preserved.' . PHP_EOL;
}
echo 'Local schema upgraded; repeat import passed. Eligibility defaults to Not confirmed.' . PHP_EOL;
