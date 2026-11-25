<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$databaseConfig = config(\Config\Database::class)->default;
if (!in_array($databaseConfig['hostname'], ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('This check only reads the local application database.');
}
$db = \Config\Database::connect('default');
$db->initialize();
$config = config(\Config\ReceiptInvoice::class);
if ($config->serialEnd !== 0) throw new RuntimeException('An environment override is still enabling invoice range allocation.');
$saleCount = $db->table('sales')->countAllResults();
$counters = $db->table('receipt_invoice_sequences')->orderBy('series_key')->get()->getResultArray();
$db->transBegin();
try {
    $snapshot = json_decode(\App\Libraries\ReceiptInvoice::capture($db), true, 512, JSON_THROW_ON_ERROR);
    if ($snapshot['serial'] !== null) throw new RuntimeException('Registered serial numbering is still enabled.');
} finally {
    $db->transRollback();
}
if ($saleCount !== $db->table('sales')->countAllResults()
    || $counters !== $db->table('receipt_invoice_sequences')->orderBy('series_key')->get()->getResultArray()) {
    throw new RuntimeException('Verification unexpectedly changed saved sales or counters.');
}
echo 'Effective local invoice configuration: serialEnd = 0; capture succeeds. Existing sales and serial counters are unchanged.' . PHP_EOL;
