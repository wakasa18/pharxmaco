<?php
// Imports only into a newly created isolated schema; never writes the app DB.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
});
$sourceFile = $argv[1] ?? throw new InvalidArgumentException('Provide the original export path.');
$exportFile = ROOTPATH . 'database/exports/u315645644_pharxmacotest-with-14-day-demo-2026-10-12.sql';
$fullExportFile = ROOTPATH . 'database/exports/u315645644_pharxmacotest-full-database-with-14-day-demo-2026-10-12.sql';
$existingDatabaseFile = ROOTPATH . 'database/exports/u315645644_pharxmacotest-add-14-day-demo-2026-10-12.sql';
$phpMyAdminDirectory = $argv[2] ?? null;
if ($phpMyAdminDirectory !== null) {
    // Load only the import parser to avoid mixing the apps' Composer dependencies.
    spl_autoload_register(static function (string $class) use ($phpMyAdminDirectory): void {
        $prefix = 'PhpMyAdmin\\SqlParser\\';
        if (!str_starts_with($class,$prefix)) return;
        $path = $phpMyAdminDirectory.'/vendor/phpmyadmin/sql-parser/src/'
            .str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if (is_file($path)) require_once $path;
    });
}
$addonFile = ROOTPATH . 'database/samples/holt-14-day-sales-stock-2026-10-12.sql';
$source = file_get_contents($sourceFile);
$sourceHash = hash('sha256', $source);
$addon = file_get_contents($addonFile);
$db = \Config\Database::connect('default');
$db->initialize();
$connection = $db->connID;
$originalDatabase = $db->database;
$schema = 'phx_holt_review_' . bin2hex(random_bytes(6));
$created = false;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$run = static function (string $sql, bool $sequential = false) use ($connection, $phpMyAdminDirectory): array {
    $output = [];
    if ($sequential || $phpMyAdminDirectory !== null) {
        $statements = [];
        if (!$sequential && $phpMyAdminDirectory !== null) {
            // This is the same statement extractor used by ImportSql::doImport().
            $buffer = new \PhpMyAdmin\SqlParser\Utils\BufferedQuery($sql);
            while (($statement = $buffer->extract(true)) !== false) {
                if (trim($statement) !== '') $statements[] = $statement;
            }
        } else {
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            preg_match_all("/(?:[^;']|'(?:''|[^'])*')+;/s", $sql, $matches);
            $statements = $matches[0];
        }
        foreach ($statements as $statement) {
            $result = $connection->query(trim($statement));
            if ($result === false) throw new RuntimeException($connection->error);
            if ($result instanceof mysqli_result) {
                $output[] = $result->fetch_all(MYSQLI_ASSOC);
                $result->free();
            }
            if ($phpMyAdminDirectory !== null) {
                // Client-side diagnostics must not corrupt the import's counts.
                $diagnostic = $connection->query('SELECT 1 AS client_diagnostic');
                $diagnostic->free();
            }
        }
        return $output;
    }
    if (!$connection->multi_query($sql)) throw new RuntimeException($connection->error);
    do {
        if ($result = $connection->store_result()) {
            $output[] = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        if (!$connection->more_results()) break;
        if (!$connection->next_result()) throw new RuntimeException($connection->error);
    } while (true);
    return $output;
};
$query = static function (string $sql) use ($connection): array {
    $result = $connection->query($sql);
    if ($result === false) throw new RuntimeException($connection->error);
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
};
$snapshot = static function () use ($query): array {
    $tables = [];
    foreach ($query('SHOW TABLES') as $row) {
        $table = reset($row);
        $tables[$table] = $query("SELECT * FROM `{$table}` ORDER BY id");
    }
    return $tables;
};
$fingerprint = static fn (array $tables): string => hash('sha256', serialize($tables));
try {
    $connection->query("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $connection->select_db($schema);
    $run($source);
    $baseline = $snapshot();
    // Reproduce the full-dump conflict without changing an existing table.
    $conflictCode = null;
    try {
        $run($source);
    } catch (mysqli_sql_exception $error) {
        $conflictCode = $error->getCode();
    }
    $assert($conflictCode===1050, 'Expected the existing-table error on a repeated full dump.');
    $connection->query('ROLLBACK');
    $assert($snapshot()===$baseline, 'Repeated full dump changed the isolated source.');
    $opening = [];
    $sampleIds = [138,139,149,150,160,171,172,176,177,178,187,188,189,198,199,200,209,210,211];
    foreach ($baseline['branch_products'] as $row) {
        if (in_array((int) $row['id'], $sampleIds, true)) $opening[$row['branch_id'].':'.$row['product_id']] = (int) $row['stock'];
    }

    // A changed source must fail without adding any rows or resetting stock.
    $connection->query('UPDATE branch_products SET stock=stock+1 WHERE id=138');
    $changed = $fingerprint($snapshot());
    $rejected = $run($addon);
    $assert(str_starts_with(end($rejected)[0]['result'], 'ROLLED BACK'), 'Changed inventory was accepted.');
    $assert($changed === $fingerprint($snapshot()), 'Rejected import modified data.');
    $connection->query('UPDATE branch_products SET stock=stock-1 WHERE id=138');
    $assert($baseline === $snapshot(), 'Source probe did not restore its isolated rows.');

    $first = $run($addon);
    $assert(end($first)[0]['result'] === 'Two-week demo import complete', 'Fixture import failed its preflight.');
    $after = $snapshot();
    foreach ($baseline as $table => $rows) {
        $indexed = array_column($after[$table], null, 'id');
        foreach ($rows as $row) {
            $new = $indexed[$row['id']] ?? null;
            $assert($new !== null, "Original row removed from {$table}.");
            if ($table === 'branch_products' && in_array((int) $row['id'], $sampleIds, true)) {
                $assert($new['updated_at'] === '2026-10-11 12:00:00', 'Inventory timestamp not advanced.');
                unset($row['updated_at'], $new['updated_at']);
            }
            $assert($row === $new, "Original row changed in {$table}.");
        }
        $expectedExtra = ['sales'=>266, 'sale_items'=>266, 'stock_logs'=>304][$table] ?? 0;
        $assert(count($after[$table]) === count($rows)+$expectedExtra, "Unexpected extra rows in {$table}.");
    }

    $items = $query("SELECT s.*, si.id AS item_id, si.product_id, si.product_name_snapshot, si.cost_price_at_sale, si.quantity, si.price, si.subtotal, si.discount_applied, si.profit, si.created_at AS item_created_at FROM sales s JOIN sale_items si ON si.sale_id=s.id WHERE s.notes LIKE 'PHX-DEMO-HOLT14-20261012:%' ORDER BY s.sale_date, s.branch_id, si.product_id");
    $assert(count($items) === 266, 'Expected 266 sample items.');
    $daily = [];
    $byInvoice = [];
    $sampleUnits = 0;
    $sampleRevenue = 0.0;
    foreach ($items as $row) {
        $amount = round((float) $row['price']*(int) $row['quantity'], 2);
        foreach (['total_amount','final_total','amount_paid','subtotal'] as $field) $assert(abs((float) $row[$field]-$amount)<.001, "Incorrect {$field}.");
        foreach (['discount_amount','change_amount','discount_applied'] as $field) $assert((float) $row[$field]===0.0, "Unexpected {$field}.");
        $assert(abs((float) $row['profit']-round(((float) $row['price']-(float) $row['cost_price_at_sale'])*(int) $row['quantity'],2))<.001, 'Incorrect profit.');
        $assert($row['status']==='completed' && $row['sale_date']===$row['created_at'] && $row['sale_date']===$row['updated_at'] && $row['sale_date']===$row['item_created_at'], 'Sale status/timestamps mismatch.');
        $key = $row['branch_id'].':'.$row['product_id'];
        $daily[$key][substr($row['sale_date'],0,10)] = (int) $row['quantity'];
        $byInvoice[$row['invoice_no']] = $row;
        $sampleUnits += (int) $row['quantity'];
        $sampleRevenue += $amount;
    }
    $assert(count($daily)===19, 'Expected 19 product/branch pairs.');
    foreach ($daily as $series) {
        $assert(count($series)===14 && array_key_first($series)==='2026-09-28' && array_key_last($series)==='2026-10-11', 'Incomplete daily series.');
    }

    $logs = $query("SELECT * FROM stock_logs WHERE remarks LIKE 'PHX-DEMO-HOLT14-20261012:%' ORDER BY created_at, id");
    $ledger = $opening;
    $sold = [];
    $delivered = 0;
    foreach ($logs as $row) {
        $key = $row['branch_id'].':'.$row['product_id'];
        $assert((int) $row['previous_stock']===$ledger[$key], 'Stock ledger continuity failed.');
        $quantity = (int) $row['quantity'];
        $assert($quantity>0 && (int) $row['new_stock']>=0, 'Non-positive movement or negative stock.');
        $expected = $ledger[$key]+($row['action_type']==='stock_in' ? $quantity : -$quantity);
        $assert((int) $row['new_stock']===$expected, 'Movement quantity does not balance.');
        $ledger[$key] = $expected;
        if ($row['action_type']==='stock_in') {
            $assert($row['supplier_id']!==null, 'Delivery has no supplier.');
            $delivered += $quantity;
        } else {
            $assert($row['action_type']==='stock_out', 'Unexpected movement type.');
            $invoice = substr($row['remarks'], strrpos($row['remarks'],'DEMO-HOLT14-'));
            $sale = $byInvoice[$invoice] ?? null;
            $assert($sale!==null && $sale['branch_id']===$row['branch_id'] && $sale['product_id']===$row['product_id'] && (int) $sale['quantity']===$quantity && $sale['sale_date']===$row['created_at'], 'Stock deduction does not match its sale.');
            $sold[$invoice] = ($sold[$invoice] ?? 0)+1;
        }
    }
    $assert($ledger===$opening && $delivered===$sampleUnits && count($sold)===266 && max($sold)===1, 'Closing stock, deliveries or deduction count mismatch.');
    $assert((int) $query('SELECT @@foreign_key_checks AS enabled')[0]['enabled']===1, 'Foreign keys were disabled.');

    $reflection = new ReflectionClass(\App\Libraries\ReorderForecastService::class);
    $service = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('db')->setValue($service,$db);
    $forecasts = $service->generate('2026-09-28','2026-10-11','',.3,.2,false,null);
    $ready = [];
    foreach ($forecasts as $forecast) {
        $key = $forecast['branch_id'].':'.$forecast['product_id'];
        if (!isset($daily[$key])) continue;
        // Independent recurrence verifies the actual service uses these weights.
        $values = array_values($daily[$key]);
        $level = $values[0]; $trend = $values[1]-$values[0];
        foreach (array_slice($values,1) as $value) {
            $previous = $level;
            $level = .3*$value+.7*($level+$trend);
            $trend = .2*($level-$previous)+.8*$trend;
        }
        $sum = 0.0;
        for ($day=1;$day<=30;++$day) $sum += max(0,$level+$day*$trend);
        $assert(abs($forecast['avg_daily_demand']-round($sum/30,2))<.001, 'Holt demand does not match alpha/beta.');
        $assert($forecast['suggested_qty']===max(0,(int) ceil($sum)-$opening[$key]) && $forecast['suggested_qty']>0 && $forecast['days_left']!==null, 'Invalid reorder suggestion.');
        $ready[$key] = $forecast;
    }
    $assert(count($ready)===19, 'Some sampled pairs have no forecast.');
    foreach (['1'=>7,'3'=>12] as $branch=>$count) {
        $rows = $service->generate('2026-09-28','2026-10-11',(string) $branch,.3,.2,false,null);
        $sampled = array_filter($rows,static fn ($row)=>isset($ready[$row['branch_id'].':'.$row['product_id']]));
        $assert(count($sampled)===$count, 'Branch-specific forecast is incomplete.');
    }
    foreach ([['2026-08-13','2026-10-12'],['2026-10-01','2026-10-12']] as [$from,$to]) {
        $rows = $service->generate($from,$to,'',.3,.2,false,null);
        $positive = array_filter($rows,static fn ($row)=>isset($ready[$row['branch_id'].':'.$row['product_id']]) && $row['suggested_qty']>0 && $row['days_left']!==null);
        $assert(count($positive)===19, 'Default-window forecast lost a sample result.');
    }

    $controllerClass = new ReflectionClass(\App\Controllers\Admin\ReportsController::class);
    $controller = $controllerClass->newInstanceWithoutConstructor();
    $controllerClass->getProperty('db')->setValue($controller,$db);
    $revenueForecasts = [];
    foreach (['','1','3'] as $branch) {
        $history = $controllerClass->getMethod('buildPeriodHistory')->invoke($controller,'2026-09-28','2026-10-11',$branch,'daily');
        $forecast = $controllerClass->getMethod('computeHoltForecast')->invoke($controller,$history,'daily');
        $assert(count($history)===14 && count($forecast['futureForecast'])===7 && min(array_column($forecast['futureForecast'],'forecast_value'))>0, 'Revenue forecast is empty.');
        $revenueForecasts[$branch==='' ? 'all_branches' : 'branch_'.$branch] = $forecast['nextForecastValue'];
    }

    $expectedFingerprint = $fingerprint($after);
    $repeat = $run($addon,true);
    $assert(end($repeat)[0]['result']==='Already complete: no changes' && $fingerprint($snapshot())===$expectedFingerprint, 'Reimport changed data.');
    // A partial fixture must also fail without silently filling or duplicating it.
    $connection->query('DELETE FROM sale_items WHERE id='.(int) $items[0]['item_id']);
    $partialFingerprint = $fingerprint($snapshot());
    $partial = $run($addon);
    $assert(str_starts_with(end($partial)[0]['result'],'ROLLED BACK') && $fingerprint($snapshot())===$partialFingerprint, 'Partial fixture was not rejected.');

    // Clearly distinguish a full restoration from a sample-only import.
    $fullHeader = "-- FULL DATABASE EXPORT: import into an EMPTY test database.\n"
        . "-- If activity_logs or other tables already exist, use the phpMyAdmin import:\n"
        . "-- u315645644_pharxmacotest-with-14-day-demo-2026-10-12.sql\n"
        . "-- This full file includes original records plus synthetic demonstration data.\n\n";
    $sampleHeader = "-- PHPMYADMIN IMPORT: TWO WEEKS OF SAMPLE DATA FOR AN EXISTING DATABASE.\n"
        . "-- Select your existing test database in phpMyAdmin, then Import this file.\n"
        . "-- Character set: utf-8. Format: SQL. Delimiter: ;. Keep other defaults.\n"
        . "-- Prepared for the original October 12 export; checks inventory before writes.\n"
        . "-- Does not create or drop application tables; existing data is checked first.\n"
        . "-- Success result: Two-week demo import complete (266 sales, 304 movements).\n"
        . "-- If already complete, no duplicates are added.\n\n";
    file_put_contents($fullExportFile,$fullHeader.$source."\n\n".$addon);
    file_put_contents($exportFile,$sampleHeader.$addon);
    file_put_contents($existingDatabaseFile,$sampleHeader.$addon);
    $connection->select_db($originalDatabase);
    $connection->query("DROP DATABASE `{$schema}`");
    $created = false;
    $connection->query("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $connection->select_db($schema);
    $run(file_get_contents($fullExportFile));
    $assert($fingerprint($snapshot())===$expectedFingerprint, 'Full packaged export differs from the verified fixture.');
    $assert(file_get_contents($existingDatabaseFile)===file_get_contents($exportFile), 'Sample-only alias differs from the phpMyAdmin import.');
    $sampleRepeat = $run(file_get_contents($exportFile));
    $assert(end($sampleRepeat)[0]['result']==='Already complete: no changes' && $fingerprint($snapshot())===$expectedFingerprint, 'Packaged sample-only import changed a complete fixture.');
    $connection->query("SET time_zone='+08:00'");
    $localForecasts = $service->generate('2026-09-28','2026-10-11','',.3,.2,false,null);
    $assert($localForecasts===$forecasts, 'Local timezone changed sample demand dates.');
    $assert(hash_file('sha256',$sourceFile)===$sourceHash, 'Original SQL file changed.');

    $report = [
        'sample_dates'=>['2026-09-28','2026-10-11'], 'alpha'=>.3, 'beta'=>.2,
        'added_sales'=>266, 'added_sale_items'=>266, 'stock_in'=>38, 'stock_out'=>266,
        'sold_units'=>$sampleUnits, 'delivered_units'=>$delivered, 'sample_revenue'=>round($sampleRevenue,2),
        'forecast_ready_pairs'=>19, 'main_branch_pairs'=>7, 'patag_branch_pairs'=>12,
        'next_daily_revenue_forecasts'=>$revenueForecasts,
        'sample_reorder_results'=>array_values($ready),
        'checks'=>['balanced ledger without negative stock','sale totals and profits','original rows preserved except 19 inventory timestamps',
            'Holt alpha/beta recurrence','branch filters','60-day and current-month windows','UTC and UTC+8 date grouping',
            'idempotent sequential reimport','changed source and partial fixture rejected','full packaged export imported successfully',
            'existing-table error 1050 reproduced; sample-only import avoids table creation','packaged sample-only repeat is a no-op','original file unchanged'],
        'local_app_database'=>'unchanged', 'server_version'=>$connection->server_info,
        'source_sha256'=>$sourceHash, 'export_sha256'=>hash_file('sha256',$exportFile),
        'full_export_sha256'=>hash_file('sha256',$fullExportFile),
        'sample_only_export_sha256'=>hash_file('sha256',$existingDatabaseFile),
    ];
    if ($phpMyAdminDirectory !== null) {
        $package = json_decode(file_get_contents($phpMyAdminDirectory.'/package.json'),true);
        $report['phpmyadmin_import_verification'] = [
            'version'=>$package['version'], 'extractor'=>'PhpMyAdmin\\SqlParser\\Utils\\BufferedQuery',
            'execution'=>'statement by statement on one connection, with interleaved client diagnostic SELECTs',
            'browser_import'=>'not performed',
        ];
        $report['checks'][] = 'phpMyAdmin import statement extraction and execution';
        $report['checks'][] = 'counts unaffected by extra client queries between statements';
        $report['checks'][] = 'completion SELECT is the final import statement';
    }
    file_put_contents(ROOTPATH.'database/samples/holt-14-day-verification-2026-10-12.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
    unset($report['sample_reorder_results']);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} finally {
    $connection->select_db($originalDatabase);
    if ($created) $connection->query("DROP DATABASE `{$schema}`");
}
