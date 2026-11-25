<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$configuration = config(\Config\Database::class)->default;
if (!in_array($configuration['hostname'], ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('This helper only upgrades the local application database.');
}
$db = \Config\Database::connect('default');
$db->initialize();
$counts = [];
foreach (['products', 'sales', 'sale_items'] as $table) $counts[$table] = $db->table($table)->countAllResults();
if (!in_array('--apply', $argv, true)) {
    echo json_encode(['local_database' => true, 'driver' => $db->DBDriver, 'row_counts' => $counts,
        'invoice_columns_present' => $db->fieldExists('receipt_metadata', 'sales')], JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
require_once APPPATH . 'Database/Migrations/2026_10_14_000011_add_receipt_invoice_fields.php';
(new \App\Database\Migrations\AddReceiptInvoiceFields(\Config\Database::forge('default')))->up();
if ($db->tableExists('migrations') && !$db->table('migrations')->where('version', '2026_10_14_000011')->where('namespace', 'App')->where('group', 'default')->countAllResults()) {
    $batch = $db->table('migrations')->selectMax('batch', 'latest')->get()->getRowArray();
    $db->table('migrations')->insert(['version' => '2026_10_14_000011',
        'class' => \App\Database\Migrations\AddReceiptInvoiceFields::class, 'group' => 'default',
        'namespace' => 'App', 'time' => time(), 'batch' => (int) ($batch['latest'] ?? 0) + 1]);
}
foreach ($counts as $table => $count) {
    if ($db->table($table)->countAllResults() !== $count) throw new RuntimeException('Unexpected row count change: ' . $table);
}
echo 'Applied the invoice migration to the local database; existing product and sale row counts are unchanged.' . PHP_EOL;
