<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
set_exception_handler(static function (Throwable $error): void { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); });
$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$source = file_get_contents($manifest['source_path']);
$patch = file_get_contents($manifest['upgrade']);
if (hash('sha256', $source) !== $manifest['source_sha256']) throw new RuntimeException('Source backup changed after preparation.');

// Inspect executable SQL, treating quoted data and ordinary comments as data.
$statements = static function (string $sql): array {
    $result = []; $buffer = ''; $quote = null; $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        if ($quote !== null) {
            $buffer .= $ch;
            if ($ch === '\\' && $quote !== '`' && $i + 1 < $length) $buffer .= $sql[++$i];
            elseif ($ch === $quote) {
                if ($i + 1 < $length && $sql[$i + 1] === $quote) $buffer .= $sql[++$i];
                else $quote = null;
            }
        } elseif (in_array($ch, ["'", '"', '`'], true)) { $quote = $ch; $buffer .= $ch; }
        elseif ($ch === '#' || (substr($sql, $i, 2) === '--' && ctype_space($sql[$i + 2] ?? ' '))) {
            $end = strpos($sql, "\n", $i); $i = $end === false ? $length : $end; $buffer .= ' ';
        } elseif (substr($sql, $i, 2) === '/*') {
            $end = strpos($sql, '*/', $i + 2);
            if ($end === false) throw new RuntimeException('Unclosed SQL comment.');
            if (($sql[$i + 2] ?? '') === '!') {
                $body = preg_replace('/^\d+\s*/', '', substr($sql, $i + 3, $end - $i - 3));
                if (!preg_match('/^SET\s/i', $body) || str_contains($body, ';')) throw new RuntimeException('Unexpected executable dump comment.');
                $buffer .= ' ' . $body . ' ';
            } else $buffer .= ' ';
            $i = $end + 1;
        } elseif ($ch === ';') {
            if (trim($buffer) !== '') $result[] = trim($buffer);
            $buffer = '';
        } else $buffer .= $ch;
    }
    if ($quote !== null) throw new RuntimeException('Unclosed SQL literal.');
    if (trim($buffer) !== '') $result[] = trim($buffer);
    return $result;
};
$sourceTables = [];
foreach ($statements($source) as $statement) {
    if (preg_match('/^(?:CREATE TABLE|ALTER TABLE|INSERT INTO)\s+`([a-z_]+)`(?=\s|\()/i', $statement, $match)) {
        if (!in_array($match[1], ['activity_logs','branches','branch_products','categories','discounts','forecasting_data','migrations','products','refund_items','report_exports','sales','sale_items','stock_logs','suppliers','users'], true)) {
            throw new RuntimeException('Unexpected dump table.');
        }
        if (preg_match('/`\s*\.\s*`/', $statement)) throw new RuntimeException('Cross-database identifier in dump.');
        if (str_starts_with(strtoupper($statement), 'CREATE TABLE')) $sourceTables[] = $match[1];
    } elseif (preg_match('/^SET\s+(?:SQL_MODE|time_zone|NAMES|@OLD_CHARACTER_SET_CLIENT|@OLD_CHARACTER_SET_RESULTS|@OLD_COLLATION_CONNECTION|CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION)\b/i', $statement)) {
        if (preg_match('/\b(?:GLOBAL|PERSIST|LOAD_FILE|OUTFILE|DUMPFILE)\b/i', $statement)) throw new RuntimeException('Unsafe dump setting.');
    } elseif (!in_array(strtoupper($statement), ['START TRANSACTION', 'COMMIT'], true)) throw new RuntimeException('Unexpected dump statement type.');
}
foreach ($statements($patch) as $statement) {
    if (preg_match('/^(?:USE|DROP|TRUNCATE|DELETE|REPLACE|CREATE DATABASE)\b/i', $statement)) throw new RuntimeException('Unsafe existing-database upgrade.');
    if (preg_match('/^(?:INSERT INTO|UPDATE)\s+`?([a-z_]+)`?/i', $statement, $match) && !in_array($match[1], ['products','migrations','cashier_drawers'], true)) {
        throw new RuntimeException('Upgrade attempts to replace application records.');
    }
}
$configuration = config(\Config\Database::class)->default;
if (!in_array($configuration['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Use only the local verification server.');
$db = \Config\Database::connect('default'); $db->initialize();
$connection = $db->connID; $originalDatabase = $db->database;
$run = static function (string $sql) use ($connection): void {
    if (!$connection->multi_query($sql)) throw new RuntimeException($connection->error);
    do {
        if ($result = $connection->store_result()) $result->free();
        if (!$connection->more_results()) break;
        if (!$connection->next_result()) throw new RuntimeException($connection->error);
    } while (true);
};
$rows = static function (string $sql) use ($connection): array {
    $result = $connection->query($sql);
    if (!$result instanceof mysqli_result) throw new RuntimeException('Expected rows.');
    return $result->fetch_all(MYSQLI_ASSOC);
};
$snapshot = static function () use ($rows): array {
    $state = [];
    foreach ($rows('SHOW TABLES') as $entry) {
        $table = reset($entry);
        if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException('Unexpected table identifier.');
        $key = $table === 'receipt_invoice_sequences' ? 'series_key' : 'id';
        $state[$table] = $rows("SELECT * FROM `{$table}` ORDER BY `{$key}`");
    }
    return $state;
};
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$schema = 'phx_existing_upgrade_review_' . bin2hex(random_bytes(6));
$created = false;
try {
    $connection->query("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true; $connection->select_db($schema);
    $run($source);
    $assert($rows('SELECT DATABASE() AS current_database')[0]['current_database'] === $schema, 'Dump changed the selected database.');
    $before = $snapshot();
    $run($patch);
    $after = $snapshot();
    $expectedTables=['receipt_invoice_sequences','cashier_drawers','cashier_shifts','cashier_events','cashier_held_sales','cashier_shift_reviews','cashier_event_reviews','cashier_shift_actions'];
    $actualTables=array_values(array_diff(array_keys($after),array_keys($before))); sort($expectedTables); sort($actualTables);
    $assert($actualTables===$expectedTables,'Unexpected new table.');
    $assert(count($after['cashier_drawers'])===count($after['branches']),'Every branch must have a shared drawer.');
    foreach (array_diff($expectedTables,['cashier_drawers']) as $table) $assert($after[$table]===[],'Historical cashier data invented.');
    $assert(count($after['products']) === $manifest['product_count'], 'Product count changed.');
    foreach ($before as $table => $oldRows) {
        $newRows = array_column($after[$table], null, 'id');
        if ($table !== 'migrations') $assert(count($newRows) === count($oldRows), 'Row count changed: ' . $table);
        foreach ($oldRows as $oldRow) {
            $assert(isset($newRows[$oldRow['id']]), 'Original row missing: ' . $table);
            foreach ($oldRow as $field => $value) $assert($newRows[$oldRow['id']][$field] === $value, 'Existing value changed: ' . $table . '.' . $field);
        }
    }
    $updates = array_column($manifest['updates'], null, 'id');
    $genericCount = 0; $strengthCount = 0;
    foreach ($after['products'] as $product) {
        $expected = $updates[$product['id']] ?? [];
        $assert($product['generic_name'] === ($expected['generic_name'] ?? null), 'Unexpected generic detail.');
        $assert($product['strength'] === ($expected['strength'] ?? null), 'Unexpected strength.');
        $assert($product['tax_treatment'] === 'taxable', 'VATable request not applied.');
        $assert($product['medicine_type'] === null && $product['senior_pwd_eligible'] === null, 'Unconfirmed classification was invented.');
        if ($product['generic_name'] !== null) $genericCount++;
        if ($product['strength'] !== null) $strengthCount++;
    }
    foreach (['branch_products' => ['stock_delivery_id'], 'sales' => ['receipt_metadata'],
        'sale_items' => ['stock_delivery_id','expiration_date_at_sale','generic_name_snapshot','strength_snapshot','tax_treatment_snapshot','statutory_discount_kind']] as $table => $fields) {
        foreach ($after[$table] as $row) foreach ($fields as $field) $assert($row[$field] === null, 'Historical detail was invented: ' . $table . '.' . $field);
    }
    foreach ($after['sale_items'] as $row) $assert((float) $row['vat_exemption_amount'] === 0.0, 'Historical VAT relief changed.');
    $assert($after['receipt_invoice_sequences'] === [], 'Invoice sequence was invented.');
    $latestVersions = ['2026_10_04_000010','2026_10_14_000011','2026_10_14_000012','2026_10_14_000013','2026_10_15_000014'];
    foreach ($latestVersions as $version) {
        $matches = array_filter($after['migrations'], static fn ($row) => $row['version'] === $version && $row['namespace'] === 'App' && $row['group'] === 'default');
        $assert(count($matches) === 1, 'Latest migration not recorded once: ' . $version);
    }
    $run($patch);
    $assert($snapshot() === $after, 'Second upgrade import changed results.');
    // Confirm a partially upgraded installation retains staff-entered details and invoice snapshots.
    $connection->query("UPDATE products SET generic_name = 'Reviewed ingredient', strength = 'Reviewed strength', medicine_type = 'branded', senior_pwd_eligible = 1, updated_at = updated_at WHERE id = 6");
    $connection->query("UPDATE sales SET receipt_metadata = '{\"preservation_check\":true}', updated_at = updated_at WHERE id = 1");
    $connection->query("UPDATE sale_items SET generic_name_snapshot = 'Recorded ingredient', strength_snapshot = 'Recorded strength', tax_treatment_snapshot = 'vat_exempt', statutory_discount_kind = 'pwd', vat_exemption_amount = 1.23 WHERE id = 1");
    $connection->query("INSERT INTO receipt_invoice_sequences (series_key, next_number) VALUES ('preservation-check', 123)");
    $partial = $snapshot(); $run($patch);
    $assert($snapshot() === $partial, 'Already entered details or invoice sequence changed.');
    $assert(hash_file('sha256', $manifest['source_path']) === $manifest['source_sha256'], 'Source file was changed.');
    $report = ['source_sha256' => $manifest['source_sha256'], 'upgrade' => $manifest['upgrade'],
        'added_columns' => count($manifest['missing_columns']), 'added_migrations' => $latestVersions,
        'row_counts' => array_map('count', $after), 'generic_names_filled' => $genericCount, 'strengths_filled' => $strengthCount,
        'checks' => ['Original dump imported into an isolated local MySQL database.', 'Every existing field and record was preserved.',
            'Identifiable medicine details filled and all products VATable.', 'Historical invoice details remain unfilled; medicine type/benefit eligibility remain unconfirmed.',
            'Repeated import produced identical results.', 'Staff-entered details, saved invoices and invoice sequence survive reimport.', 'Original source file preserved.'],
        'local_application_database_updated' => false];
    file_put_contents(__DIR__ . '/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $connection->select_db($originalDatabase);
    if ($created && preg_match('/^phx_existing_upgrade_review_[a-f0-9]{12}$/D', $schema)) $connection->query("DROP DATABASE `{$schema}`");
}
