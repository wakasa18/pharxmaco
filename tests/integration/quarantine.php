<?php
// Only scratch-schema writes. The application database is read to create a test copy.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';

use App\Libraries\Quarantine;

$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Local database required.');
$dir = WRITEPATH . 'quarantine-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
if (($argv[1] ?? '') === '--worker') {
    try {
        [$script, $mode, $schema, $id, $admin, $revision, $index] = $argv;
        if (!preg_match('/^phx_quarantine_review_[0-9]+$/D', $schema)) throw new RuntimeException('Unsafe worker schema.');
        $db = \Config\Database::connect(array_replace($settings, ['database' => $schema]), false);
        file_put_contents($dir . '/' . $schema . '-ready-' . $index, 'ready');
        $deadline = microtime(true) + 15;
        while (!is_file($dir . '/' . $schema . '-go')) { if (microtime(true) > $deadline) throw new RuntimeException('Barrier timeout.'); usleep(20000); }
        $saved = (new Quarantine($db))->resolve((int) $id, (int) $admin, ['outcome' => 'released', 'note' => 'Concurrent inspection test', 'confirmed' => '1'], $revision);
        echo json_encode(['saved' => $saved], JSON_THROW_ON_ERROR); exit(0);
    } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()); exit(1); }
}
$schema = 'phx_quarantine_review_' . getmypid();
$quote = static fn ($name) => '`' . str_replace('`', '``', $name) . '`';
$source = \Config\Database::connect($settings, false); $source->initialize();
$source->query('CREATE DATABASE ' . $quote($schema) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$checks = [];
$assert = static function (bool $ok, string $name) use (&$checks): void { if (!$ok) throw new RuntimeException($name); $checks[] = $name; };
$snapshot = static function ($db) use ($quote): array {
    $db->resetDataCache(); $result = [];
    foreach ($db->listTables() as $table) {
        $rows = $db->query('SELECT * FROM ' . $quote($table))->getResultArray(); $encoded = [];
        foreach ($rows as $row) { foreach ($row as &$value) if ($value !== null) $value = (string) $value; unset($value); $encoded[] = json_encode($row, JSON_THROW_ON_ERROR); }
        sort($encoded); $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $encoded))];
    }
    ksort($result); return $result;
};
$import = static function ($db): array {
    $sql = file_get_contents(ROOTPATH . 'database/upgrades/2026-10-23-quarantine.sql');
    if (!$db->connID->multi_query($sql)) throw new RuntimeException('Import failed.');
    $rows = [];
    do {
        if ($result = $db->connID->store_result()) { $rows = array_merge($rows, $result->fetch_all(MYSQLI_ASSOC)); $result->free(); }
        if (!$db->connID->more_results()) break;
        if (!$db->connID->next_result()) throw new RuntimeException('Import failed.');
    } while (true);
    $db->resetDataCache(); return $rows;
};
$input = ['outcome' => 'released', 'note' => 'Checked all units, packaging, storage and delivery.', 'confirmed' => '1'];
$processes = [];
try {
    $db = \Config\Database::connect(array_replace($settings, ['database' => $schema]), false); $db->initialize();
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($source->listTables() as $table) {
        $db->query($source->query('SHOW CREATE TABLE ' . $quote($table))->getRowArray()['Create Table']);
        $db->query('INSERT INTO ' . $quote($table) . ' SELECT * FROM ' . $quote($settings['database']) . '.' . $quote($table));
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $before = $snapshot($db); $result = $import($db); $after = $snapshot($db);
    unset($before['migrations'], $after['migrations'], $before['quarantine_resolutions'], $after['quarantine_resolutions']);
    $assert($before === $after && str_contains(json_encode($result), 'Quarantine upgrade complete'), 'phpMyAdmin import preserves every existing business record');
    $first = $snapshot($db); $import($db);
    $assert($first === $snapshot($db), 'Repeat import changes no existing rows or migration registrations');
    require_once APPPATH . 'Database/Migrations/2026_10_23_000018_add_quarantine_resolutions.php';
    (new \App\Database\Migrations\AddQuarantineResolutions(new \CodeIgniter\Database\MySQLi\Forge($db)))->up();
    $assert($first === $snapshot($db), 'Native migration safely follows the manual import');
    $assert((int) $db->query("SELECT COUNT(*) n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='quarantine_resolutions' AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT'")->getRowArray()['n'] === 3, 'Three restrictive audit foreign keys are enforced');
    $assert((int) $db->table('migrations')->where('version', '2026_10_23_000018')->countAllResults() === 1, 'Migration 000018 recorded once');

    $admin = (int) $db->table('users')->where('role', 'admin')->where('status', 'active')->get(1)->getRowArray()['id'];
    $bp = $db->table('branch_products bp')->select('bp.*')->join('products p', 'p.id=bp.product_id')->join('branches b', 'b.id=bp.branch_id')->where('bp.status', 'active')->where('p.status', 'active')->where('b.status', 'active')->get(1)->getRowArray();
    $db->table('branch_products')->where('id', $bp['id'])->update(['stock' => 10, 'stock_delivery_id' => str_repeat('a', 32), 'expiration_date' => '2099-01-01', 'deleted_at' => null, 'is_deleted' => 0, 'is_permanently_deleted' => 0]);
    $db->table('products')->where('id', $bp['product_id'])->update(['deleted_at' => null, 'is_deleted' => 0, 'is_permanently_deleted' => 0]);
    $sale = $db->table('sales')->get(1)->getRowArray(); unset($sale['id']);
    $sale = array_replace($sale, ['invoice_no' => 'QUARANTINE-REVIEW-' . getmypid(), 'branch_id' => $bp['branch_id'], 'user_id' => $admin, 'checkout_token_hash' => null]);
    $db->table('sales')->insert($sale); $saleId = (int) $db->insertID();
    $saleItem = $db->table('sale_items')->get(1)->getRowArray(); unset($saleItem['id']);
    $db->table('sale_items')->insert(array_replace($saleItem, ['sale_id' => $saleId, 'product_id' => $bp['product_id'], 'quantity' => 10, 'stock_delivery_id' => str_repeat('a', 32), 'expiration_date_at_sale' => '2099-01-01']));
    $itemId = (int) $db->insertID();
    $newReturn = static function (int $qty = 2) use ($db, $saleId, $itemId, $bp, $admin): int {
        $db->table('refund_items')->insert(['sale_id' => $saleId, 'sale_item_id' => $itemId, 'product_id' => $bp['product_id'], 'product_name_snapshot' => 'Quarantine review item', 'quantity_refunded' => $qty,
            'price_at_sale' => 10, 'refund_subtotal' => $qty * 10, 'refunded_by' => $admin, 'reason' => 'Isolated inspection test', 'return_condition' => 'quarantined', 'refund_method' => 'cash', 'refund_event_id' => bin2hex(random_bytes(16)), 'created_at' => date('Y-m-d H:i:s')]);
        return (int) $db->insertID();
    };
    $service = new Quarantine($db); $id = $newReturn();
    $financial = [$db->table('sales')->where('id', $saleId)->get()->getRowArray(), $db->table('refund_items')->where('id', $id)->get()->getRowArray()];
    $revision = Quarantine::revision($service->item($id, $admin));
    $assert($service->resolve($id, $admin, $input, $revision), 'Real MySQL release commits stock and inspection');
    $assert(!$service->resolve($id, $admin, $input, $revision), 'Retried release returns already recorded');
    $assert((int) $db->table('branch_products')->where('id', $bp['id'])->get()->getRowArray()['stock'] === 12, 'Released units added exactly once to the original branch');
    $assert($financial === [$db->table('sales')->where('id', $saleId)->get()->getRowArray(), $db->table('refund_items')->where('id', $id)->get()->getRowArray()], 'Original refund and sale amounts/condition unchanged');
    $assert((int) $db->table('products')->where('id', $bp['product_id'])->get()->getRowArray()['stock'] === (int) $db->table('branch_products')->selectSum('stock', 'n')->where('product_id', $bp['product_id'])->get()->getRowArray()['n'], 'Master stock matches branch balances');
    foreach (['damaged', 'expired'] as $outcome) {
        $other = $newReturn(1); $record = $service->item($other, $admin);
        $service->resolve($other, $admin, array_replace($input, ['outcome' => $outcome]), Quarantine::revision($record));
        $assert((int) $db->table('branch_products')->where('id', $bp['id'])->get()->getRowArray()['stock'] === 12, $outcome . ' disposition does not deduct stock again');
    }
    $rollbackId = $newReturn(1); $record = $service->item($rollbackId, $admin);
    $db->query("CREATE TRIGGER phx_q_fail_audit BEFORE INSERT ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='inspection audit unavailable'");
    $failed = false; try { $service->resolve($rollbackId, $admin, $input, Quarantine::revision($record)); } catch (Throwable $e) { $failed = true; }
    $db->query('DROP TRIGGER phx_q_fail_audit');
    $assert($failed && !$service->item($rollbackId, $admin)['resolution'] && (int) $db->table('branch_products')->where('id', $bp['id'])->get()->getRowArray()['stock'] === 12, 'MySQL audit failure rolls back stock, log and resolution atomically');

    $concurrent = $newReturn(1); $revision = Quarantine::revision($service->item($concurrent, $admin));
    for ($i = 0; $i < 4; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $schema, (string) $concurrent, (string) $admin, $revision, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('Could not start inspection worker.');
        fclose($pipes[0]); $processes[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 15;
    while (count(glob($dir . '/' . $schema . '-ready-*')) !== 4) { if (microtime(true) > $deadline) throw new RuntimeException('Workers not ready.'); usleep(20000); }
    file_put_contents($dir . '/' . $schema . '-go', 'go');
    $saved = 0;
    foreach ($processes as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Inspection worker failed: ' . $error);
        $saved += (int) json_decode($out, true, 512, JSON_THROW_ON_ERROR)['saved'];
    }
    $processes = [];
    $assert($saved === 1 && (int) $db->table('branch_products')->where('id', $bp['id'])->get()->getRowArray()['stock'] === 13, 'Four simultaneous inspectors produce one outcome and one stock increment');
    $assert($db->table('quarantine_resolutions')->where('refund_item_id', $concurrent)->countAllResults() === 1, 'Unique return constraint retains one durable resolution');
    $last = $snapshot($db); $import($db);
    $assert($last === $snapshot($db), 'Upgrade reimport preserves completed inspections and all stock logs');
    // On this disposable copy, recreate only the new table to exercise a fresh native migration.
    $db->query('DROP TABLE quarantine_resolutions'); $db->resetDataCache();
    $native = new \App\Database\Migrations\AddQuarantineResolutions(new \CodeIgniter\Database\MySQLi\Forge($db));
    $native->up(); $native->up(); $db->resetDataCache();
    $assert($db->tableExists('quarantine_resolutions') && (int) $db->query("SELECT COUNT(*) n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='quarantine_resolutions'")->getRowArray()['n'] === 3, 'Fresh native migration creates and repeats with matching parent key types');
    $assert(str_contains(json_encode($import($db)), 'Quarantine upgrade complete'), 'Manual import also follows a fresh native migration');
    $report = ['checks' => $checks, 'source_business_records_preserved' => true, 'source_database_written' => false, 'concurrent_inspectors' => 4];
    file_put_contents($dir . '/mysql-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'Passed ' . count($checks) . " MySQL quarantine checks.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"); exit(1);
} finally {
    foreach ($processes as [$process, $pipes]) { proc_terminate($process); foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe); proc_close($process); }
    $source->query('DROP DATABASE IF EXISTS ' . $quote($schema));
    foreach (glob($dir . '/' . $schema . '-*') as $file) unlink($file);
}
