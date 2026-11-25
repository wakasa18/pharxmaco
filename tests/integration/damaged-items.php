<?php
// Read-only query verification against local MySQL. No fixtures or schema/data writes.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
use App\Libraries\DamagedItems;

$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Local database required.');
$db = \Config\Database::connect($settings, false); $db->initialize();
$checks = [];
$assert = static function (bool $ok, string $name) use (&$checks): void { if (!$ok) throw new RuntimeException($name); $checks[] = $name; };
$snapshot = static function ($db): array {
    $result = [];
    foreach ($db->listTables() as $table) {
        $rows = [];
        foreach ($db->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->getResultArray() as $row) {
            foreach ($row as &$value) if ($value !== null) $value = (string) $value; unset($value);
            $rows[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        sort($rows); $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }
    ksort($result); return $result;
};
try {
    $before = $snapshot($db);
    $admin = (int) $db->table('users')->where('role', 'admin')->where('status', 'active')->get(1)->getRowArray()['id'];
    $service = new DamagedItems($db); $records = $service->records($admin);
    $inspection = $service->includesInspections();
    $expected = $inspection
        ? $db->query("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN r.return_condition='quarantined' THEN q.quantity ELSE r.quantity_refunded END),0) qty FROM refund_items r JOIN sales s ON s.id=r.sale_id LEFT JOIN quarantine_resolutions q ON q.refund_item_id=r.id WHERE r.return_condition='damaged' OR (r.return_condition='quarantined' AND q.outcome='damaged')")->getRowArray()
        : $db->query("SELECT COUNT(*) n, COALESCE(SUM(r.quantity_refunded),0) qty FROM refund_items r JOIN sales s ON s.id=r.sale_id WHERE r.return_condition='damaged'")->getRowArray();
    $assert(count($records) === (int) $expected['n'], 'Real MySQL list matches explicit damaged-return/inspection criteria');
    $assert(array_sum(array_column($records, 'damaged_quantity')) === (int) $expected['qty'], 'Real MySQL damaged quantity matches source ledger');
    $assert(count(array_unique(array_column($records, 'id'))) === count($records), 'No returned line is duplicated');
    $filtered = [];
    foreach (array_keys(DamagedItems::SOURCES) as $source) $filtered = array_merge($filtered, array_column($service->records($admin, $source), 'id'));
    $all = array_column($records, 'id'); sort($all); sort($filtered);
    $assert($all === $filtered, 'Three source filters partition the full list exactly');
    foreach (array_unique(array_column($records, 'branch_id')) as $branch) {
        $actual = array_column($service->records($admin, 'all', (int) $branch), 'id');
        $expectedIds = array_column(array_filter($records, static fn ($record) => (int) $record['branch_id'] === (int) $branch), 'id');
        $assert($actual === $expectedIds, 'Branch filter matches original selling branch ' . $branch);
    }
    foreach ($records as $record) $assert($service->item((int) $record['id'], $admin) === $record, 'Detail matches list for damaged return ' . $record['id']);
    $after = $snapshot($db);
    $assert($before === $after, 'All application tables and migration rows unchanged by list/filter/detail reads');
    $dir = WRITEPATH . 'damaged-items-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
    $report = ['checks' => $checks, 'damaged_records' => count($records), 'damaged_units' => (int) $expected['qty'],
        'quarantine_inspections_included' => $inspection, 'table_hashes_preserved' => count($before), 'database_writes' => false, 'hosting_changed' => false];
    file_put_contents($dir . '/mysql-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'Passed ' . count($checks) . ' read-only MySQL checks; ' . count($records) . " damaged returns; all table hashes preserved.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
finally { $db->close(); }
