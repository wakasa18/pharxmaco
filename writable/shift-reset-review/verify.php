<?php
require dirname(__DIR__, 2).'/system/Test/bootstrap.php';
set_exception_handler(static function (Throwable $error): void { fwrite(STDERR, $error->getMessage().PHP_EOL); exit(1); });
$manifest = json_decode(file_get_contents(__DIR__.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$source = file_get_contents($manifest['source_path']);
$cleaned = file_get_contents($manifest['cleaned_export']);
$patch = file_get_contents($manifest['patch']);
if (hash('sha256', $source) !== $manifest['source_sha256'] || hash('sha256', $cleaned) !== $manifest['cleaned_sha256']) throw new RuntimeException('Prepared files changed.');

function resetSqlStatements(string $sql): array
{
    $result = []; $buffer = ''; $quote = null; $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        if ($quote !== null) {
            $buffer .= $ch;
            if ($ch === '\\' && $quote !== '`' && $i + 1 < $length) $buffer .= $sql[++$i];
            elseif ($ch === $quote) { if (($sql[$i + 1] ?? '') === $quote) $buffer .= $sql[++$i]; else $quote = null; }
        } elseif (in_array($ch, ["'", '"', '`'], true)) { $quote = $ch; $buffer .= $ch; }
        elseif ($ch === '#' || (substr($sql, $i, 2) === '--' && ctype_space($sql[$i + 2] ?? ' '))) {
            $end = strpos($sql, "\n", $i); $i = $end === false ? $length : $end; $buffer .= ' ';
        } elseif (substr($sql, $i, 2) === '/*') {
            $end = strpos($sql, '*/', $i + 2); if ($end === false) throw new RuntimeException('Unclosed SQL comment.');
            if (($sql[$i + 2] ?? '') === '!') {
                $body = preg_replace('/^\d+\s*/', '', substr($sql, $i + 3, $end - $i - 3));
                if (!preg_match('/^SET\s/i', $body) || str_contains($body, ';')) throw new RuntimeException('Unexpected executable comment.');
                $buffer .= ' '.$body.' ';
            } elseif (($sql[$i + 2] ?? '') === 'M') throw new RuntimeException('Unsupported executable comment.');
            $i = $end + 1;
        } elseif ($ch === ';') { if (trim($buffer) !== '') $result[] = trim($buffer); $buffer = ''; }
        else $buffer .= $ch;
    }
    if ($quote !== null) throw new RuntimeException('Unclosed SQL value.');
    if (trim($buffer) !== '') $result[] = trim($buffer);
    return $result;
}
function resetSqlSyntax(string $sql): string
{
    $result = ''; $quote = null;
    for ($i = 0, $length = strlen($sql); $i < $length; $i++) {
        $ch = $sql[$i];
        if ($quote !== null) {
            if ($ch === '\\') $i++;
            elseif ($ch === $quote) { if (($sql[$i + 1] ?? '') === $quote) $i++; else $quote = null; }
        } elseif ($ch === "'" || $ch === '"') { $quote = $ch; $result .= "''"; }
        else $result .= $ch;
    }
    return $result;
}
$allowed = ['activity_logs','branches','branch_products','cashier_drawers','cashier_events','cashier_event_reviews','cashier_held_sales','cashier_shifts','cashier_shift_actions','cashier_shift_reviews','categories','discounts','forecasting_data','migrations','products','receipt_invoice_sequences','refund_items','report_exports','sales','sale_items','stock_logs','suppliers','users'];
foreach ([$source, $cleaned] as $sql) foreach (resetSqlStatements($sql) as $statement) {
    $syntax = resetSqlSyntax($statement);
    if (preg_match('/\b(?:OUTFILE|DUMPFILE|LOAD_FILE|SLEEP|BENCHMARK|GLOBAL|PERSIST|TRIGGER|PROCEDURE|FUNCTION|EVENT|DATABASE|SCHEMA|EXECUTE|PREPARE|CALL)\b/i', $syntax)) throw new RuntimeException('Unsafe export operation.');
    if (preg_match('/(?:`[^`]+`|\b[a-z_][a-z_0-9]*\b)\s*\.\s*(?:`[^`]+`|[a-z_][a-z_0-9]*)/i', $syntax)) throw new RuntimeException('Cross-database identifier.');
    if (preg_match('/^(?:CREATE TABLE|ALTER TABLE|INSERT INTO)\s+`([a-z_]+)`(?=\s|\()/i', $statement, $match)) {
        if (!in_array($match[1], $allowed, true)) throw new RuntimeException('Unexpected export table.');
        if (str_starts_with($syntax, 'INSERT INTO') && !preg_match("/^INSERT INTO `[a-z_]+`\\s*\\((?:`[a-z_]+`[\\s,]*)+\\)\\s+VALUES\\s+[()\\s,0-9.\\-'NULL]+$/i", $syntax)) throw new RuntimeException('Only literal VALUES inserts supported.');
    } elseif (preg_match('/^SET\s+(?:SQL_MODE|time_zone|NAMES|@OLD_CHARACTER_SET_CLIENT|@OLD_CHARACTER_SET_RESULTS|@OLD_COLLATION_CONNECTION|CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION)\b/i', $statement)) {
        // Only phpMyAdmin session settings are accepted.
    } elseif (!in_array(strtoupper($statement), ['START TRANSACTION', 'COMMIT'], true)) throw new RuntimeException('Unsupported export statement.');
}
$settings = config(\Config\Database::class)->default;
$isolatedPort = $argv[1] ?? '';
if ($isolatedPort !== '') {
    if (!preg_match('/^--isolated-port=(1[3-9][0-9]{3})$/D', $isolatedPort, $port)) throw new RuntimeException('Invalid isolated test server port.');
    $settings = array_replace($settings, ['hostname'=>'127.0.0.1', 'port'=>(int)$port[1], 'database'=>'mysql', 'username'=>'root', 'password'=>'', 'DBDriver'=>'MySQLi', 'pConnect'=>false]);
}
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Local verification server required.');
$base = \Config\Database::connect($settings, false); $base->initialize();
$schemas = []; $connections = [];
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$run = static function ($db, string $sql): void {
    if (!$db->connID->multi_query($sql)) throw new RuntimeException($db->connID->error);
    do {
        if ($result = $db->connID->store_result()) $result->free();
        if (!$db->connID->more_results()) break;
        if (!$db->connID->next_result()) throw new RuntimeException($db->connID->error);
    } while (true);
};
$snapshot = static function ($db) use ($allowed): array {
    $state = [];
    foreach ($allowed as $table) $state[$table] = $db->table($table)->orderBy($table === 'receipt_invoice_sequences' ? 'series_key' : 'id')->get()->getResultArray();
    return $state;
};
try {
    foreach ([$source, $cleaned] as $index => $sql) {
        $schema = 'phx_shift_reset_review_'.bin2hex(random_bytes(6));
        $base->query('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $schemas[] = $schema;
        $settings['database'] = $schema; $db = \Config\Database::connect($settings, false); $db->initialize(); $connections[] = $db;
        $run($db, $sql); $assert($db->database === $schema, 'Unexpected database selection.');
    }
    [$db, $restored] = $connections;
    $before = $snapshot($db);
    $report = new \App\Libraries\CashierDayReport($db);
    $dayBefore = $report->forUser(2, 1, '2026-10-15', '2026-10-16'); unset($dayBefore['generated_at']);
    $run($db, $patch); $after = $snapshot($db);
    foreach ($before as $table => $rows) {
        if (in_array($table, ['cashier_shifts','cashier_shift_actions','cashier_shift_reviews'], true)) $expected = [];
        elseif ($table === 'cashier_drawers') $expected = array_map(static fn ($row) => array_replace($row, ['active_shift_id'=>null,'last_shift_id'=>null]), $rows);
        elseif ($table === 'cashier_events') $expected = array_map(static fn ($row) => array_replace($row, ['shift_id'=>null]), $rows);
        else $expected = $rows;
        $assert($after[$table] === $expected, 'Unexpected data change: '.$table);
    }
    $dayAfter = $report->forUser(2, 1, '2026-10-15', '2026-10-16'); unset($dayAfter['generated_at']);
    $assert($dayAfter === $dayBefore, 'Daily sales/payment/product totals changed.');
    $assert($snapshot($restored) === $after, 'Cleaned full export differs from patched existing database.');
    $run($db, $patch); $assert($snapshot($db) === $after, 'Second import changed unrelated data.');
    $shifts = new \App\Libraries\CashierShift($db);
    $assert($shifts->activeFor(2) === null, 'Old cashier is still assigned.');
    $newId = $shifts->open(2, 1, '500.00', str_repeat('e', 32));
    $assert($newId > 2, 'A removed shift ID was reused.');
    $new = $shifts->activeFor(2); $summary = $shifts->summary($new);
    $assert($new !== null && $summary['expected_cash'] === 500.0, 'New shift inherited old cash or sale events.');
    $result = [
        'source_sha256'=>$manifest['source_sha256'], 'cleaned_sha256'=>$manifest['cleaned_sha256'],
        'shifts_removed'=>count($before['cashier_shifts']), 'actions_removed'=>count($before['cashier_shift_actions']),
        'shift_reviews_removed'=>count($before['cashier_shift_reviews']),
        'preserved_row_counts'=>array_map('count', array_diff_key($after, array_flip(['cashier_shifts','cashier_shift_actions','cashier_shift_reviews']))),
        'verified'=>['Existing-database patch imported twice.','All fields outside shift records and explicit shift links preserved.','Daily sales, payment and product totals unchanged.','Cleaned full export restores to exactly the patched data.','A cashier can open a new shift without old cash or reused shift IDs.'],
        'local_application_database_modified'=>false,
    ];
    file_put_contents(__DIR__.'/checks.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    foreach ($connections as $db) { if ($db->connID) { $db->connID->rollback(); $db->close(); } }
    foreach ($schemas as $schema) if (preg_match('/^phx_shift_reset_review_[a-f0-9]{12}$/D', $schema)) $base->query('DROP DATABASE `'.$schema.'`');
}
