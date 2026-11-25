<?php
// Export audit fixtures. All writes target a disposable local schema.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Local database required.');
$reviewDir = getenv('PHX_EXPORT_REVIEW_DIR') ?: 'export-audit-20261023';
if (!preg_match('/^[a-z0-9-]{1,80}$/D', $reviewDir)) throw new RuntimeException('Unsafe review directory.');
$dir = WRITEPATH . $reviewDir; if (!is_dir($dir)) mkdir($dir, 0777, true);
$statePath = $dir . '/state.json';
$quote = static fn ($name) => '`' . str_replace('`', '``', $name) . '`';
$snapshot = static function ($db) use ($quote): array {
    $result = [];
    foreach ($db->listTables() as $table) {
        $rows = [];
        foreach ($db->query('SELECT * FROM ' . $quote($table))->getResultArray() as $row) {
            foreach ($row as &$value) if ($value !== null) $value = (string) $value;
            unset($value); $rows[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        sort($rows); $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }
    ksort($result); return $result;
};
try {
    $source = \Config\Database::connect($settings, false); $source->initialize();
    if (($argv[1] ?? '') === 'reset') {
        $state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
        if (!preg_match('/^phx_exports_review_[0-9]+$/D', $state['schema'])) throw new RuntimeException('Unsafe schema.');
        $scratch = \Config\Database::connect(array_replace($settings, ['database' => $state['schema']]), false);
        $scratch->table('cashier_login_sessions')->whereIn('user_id', [$state['cashier'], $state['foreignCashier']])->delete();
        echo "Scratch cashier leases reset.\n"; exit(0);
    }
    if (($argv[1] ?? '') === 'cleanup') {
        $state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
        if (!preg_match('/^phx_exports_review_[0-9]+$/D', $state['schema'])) throw new RuntimeException('Unsafe schema.');
        $same = $snapshot($source) === $state['source_snapshot'];
        $source->query('DROP DATABASE ' . $quote($state['schema']));
        file_put_contents($dir . '/preservation.json', json_encode(['source_tables_preserved' => $same ? count($state['source_snapshot']) : false, 'scratch_schema_removed' => true], JSON_PRETTY_PRINT));
        rename($statePath, $dir . "/fixture-metadata.json");
        if (!$same) throw new RuntimeException('Source data changed during export audit; scratch schema removed and source changes retained.');
        echo "Scratch schema removed; all source table hashes preserved.\n"; exit(0);
    }
    if (is_file($statePath)) throw new RuntimeException('Clean up previous fixtures first.');
    $schema = 'phx_exports_review_' . getmypid(); $before = $snapshot($source);
    $source->query('CREATE DATABASE ' . $quote($schema) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    file_put_contents($statePath, json_encode(['schema' => $schema, 'source_snapshot' => $before]));
    $db = \Config\Database::connect(array_replace($settings, ['database' => $schema]), false); $db->initialize();
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($source->listTables() as $table) {
        $db->query($source->query('SHOW CREATE TABLE ' . $quote($table))->getRowArray()['Create Table']);
        $db->query('INSERT INTO ' . $quote($table) . ' SELECT * FROM ' . $quote($settings['database']) . '.' . $quote($table));
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $copy = static function (string $table, array $values) use ($db): int {
        $row = $db->table($table)->get(1)->getRowArray() ?? []; unset($row['id']);
        if (!$db->table($table)->insert(array_replace($row, $values))) throw new RuntimeException('Fixture insert failed: ' . $table);
        return (int) $db->insertID();
    };
    $now = \App\Libraries\CashierShift::now(); $today = substr($now, 0, 10); $day = date('Y-m-d', strtotime($today . ' -3 days'));
    $north = $copy('branches', ['branch_name' => 'Export QA North', 'branch_code' => 'QAN', 'status' => 'active']);
    $south = $copy('branches', ['branch_name' => 'Export QA South', 'branch_code' => 'QAS', 'status' => 'active']);
    $password = password_hash('Export-QA-2026!', PASSWORD_DEFAULT);
    $admin = $copy('users', ['full_name' => 'Export QA Administrator', 'username' => 'export_qa_admin', 'password' => $password, 'role' => 'admin', 'branch_id' => null, 'status' => 'active', 'session_version' => bin2hex(random_bytes(16))]);
    $cashier = $copy('users', ['full_name' => 'Export QA Cashier', 'username' => 'export_qa_cashier', 'password' => $password, 'role' => 'cashier', 'branch_id' => $north, 'status' => 'active', 'session_version' => bin2hex(random_bytes(16))]);
    $foreignCashier = $copy('users', ['full_name' => 'Export QA Other Cashier', 'username' => 'export_qa_other', 'password' => $password, 'role' => 'cashier', 'branch_id' => $south, 'status' => 'active', 'session_version' => bin2hex(random_bytes(16))]);
    $products = []; $inventory = [];
    foreach (['expired' => '-1 day', 'near' => '+10 days', 'good' => '+100 days', 'missing' => null] as $status => $offset) {
        $p = $copy('products', ['product_name' => 'Export QA ' . $status . ' Peña', 'sku' => 'EXPORT-QA-' . strtoupper($status), 'unit' => 'tablet', 'status' => 'active', 'is_deleted' => 0, 'is_permanently_deleted' => 0, 'deleted_at' => null]); $products[$status] = $p;
        $inventory[$status] = $copy('branch_products', ['product_id' => $p, 'branch_id' => $north, 'stock' => 15, 'price' => 112, 'cost_price' => 40, 'reorder_level' => 30, 'expiration_date' => $offset ? date('Y-m-d', strtotime($today . ' ' . $offset)) : null, 'stock_delivery_id' => bin2hex(random_bytes(16)), 'status' => 'active', 'is_deleted' => 0, 'is_permanently_deleted' => 0, 'deleted_at' => null]);
    }
    $copy('branch_products', ['product_id' => $products['good'], 'branch_id' => $south, 'stock' => 50, 'expiration_date' => date('Y-m-d', strtotime($today . ' +100 days')), 'stock_delivery_id' => bin2hex(random_bytes(16)), 'status' => 'active', 'is_deleted' => 0, 'is_permanently_deleted' => 0, 'deleted_at' => null]);
    $saleValues = ['user_id' => $cashier, 'branch_id' => $north, 'sale_date' => $day . ' 10:00:00', 'status' => 'partially_refunded', 'total_amount' => 224, 'discount_amount' => 40, 'final_total' => 160, 'payment_method' => 'cash', 'amount_paid' => 160, 'change_amount' => 0, 'checkout_token_hash' => null, 'receipt_metadata' => null];
    $sale = $copy('sales', array_replace($saleValues, ['invoice_no' => 'EXPORT-QA-INVOICE']));
    $foreignSale = $copy('sales', array_replace($saleValues, ['invoice_no' => 'EXPORT-QA-FOREIGN', 'branch_id' => $south, 'user_id' => $foreignCashier, 'status' => 'completed', 'total_amount' => 999, 'discount_amount' => 0, 'final_total' => 999]));
    $oldSale = $copy('sales', array_replace($saleValues, ['invoice_no' => 'EXPORT-QA-OLD', 'sale_date' => date('Y-m-d', strtotime($today . ' -70 days')) . ' 10:00:00', 'status' => 'completed']));
    $item = $copy('sale_items', ['sale_id' => $sale, 'product_id' => $products['good'], 'product_name_snapshot' => 'Export QA good Peña', 'quantity' => 2, 'price' => 112, 'subtotal' => 224, 'discount_applied' => 40, 'vat_exemption_amount' => 24, 'cost_price_at_sale' => 40, 'profit' => 80, 'stock_delivery_id' => null, 'expiration_date_at_sale' => null]);
    $refund = $copy('refund_items', ['sale_id' => $sale, 'sale_item_id' => $item, 'product_id' => $products['good'], 'quantity_refunded' => 1, 'refund_subtotal' => 80, 'refunded_by' => $cashier, 'created_at' => date('Y-m-d', strtotime($today . ' -2 days')) . ' 10:00:00', 'refund_method' => 'cash', 'refund_event_id' => 'export-qa-refund', 'return_condition' => 'quarantined', 'reason' => 'Export QA refund']);
    $logs = [];
    for ($i = 1; $i <= 55; $i++) $logs[] = $copy('stock_logs', ['product_id' => $products['good'], 'branch_id' => $north, 'user_id' => $cashier, 'action_type' => 'adjustment', 'quantity' => 1, 'previous_stock' => 20, 'new_stock' => 19, 'remarks' => $i === 1 ? '=SUM(A1:A2)' : ('EXPORT-QA-LOG-' . $i . ($i === 55 ? ' ' . str_repeat('Long export note with commas, quotes " and Peña. ', 15) . 'END-OF-NOTE' : '')), 'created_at' => $day . ' 10:00:00']);
    $foreignLog = $copy('stock_logs', ['product_id' => $products['good'], 'branch_id' => $south, 'user_id' => $foreignCashier, 'action_type' => 'adjustment', 'quantity' => 1, 'previous_stock' => 20, 'new_stock' => 19, 'remarks' => 'EXPORT-QA-FOREIGN-LOG', 'created_at' => $day . ' 10:00:00']);
    $db->table('cashier_drawers')->insert(['branch_id' => $north, 'drawer_name' => 'Export QA drawer', 'status' => 'active', 'created_at' => $now]); $drawer = (int) $db->insertID();
    $shifts = new \App\Libraries\CashierShift($db); $shift = $shifts->open($cashier, $drawer, '100', bin2hex(random_bytes(16)), 'Export QA opening count');
    $active = $shifts->activeFor($cashier); $shifts->recordSale($active, $sale); $summary = $shifts->summary($active);
    $shifts->close($shift, $cashier, '260', '100', 'Export QA closing count', $summary['revision'], null);
    $state = compact('schema', 'north', 'south', 'admin', 'cashier', 'foreignCashier', 'sale', 'foreignSale', 'oldSale', 'refund', 'shift', 'products', 'inventory', 'logs', 'foreignLog', 'day', 'today');
    $state['source_snapshot'] = $before; file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "Disposable export schema prepared.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
finally { if (isset($source)) $source->close(); }
