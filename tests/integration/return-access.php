<?php
// Read-only checks of the current local accounts/branches. No schema or application-data writes.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
use App\Libraries\DamagedItems;
use App\Libraries\Quarantine;
use App\Libraries\ReturnRecordAccess;

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
$ids = static function (array $rows): array { $ids = array_map('intval', array_column($rows, 'id')); sort($ids); return $ids; };
try {
    $before = $snapshot($db);
    $admin = (int) $db->table('users')->where('role', 'admin')->where('status', 'active')->get(1)->getRowArray()['id'];
    $cashiers = $db->table('users u')->select('u.id, u.branch_id')->join('branches b', 'b.id=u.branch_id')->where('u.role', 'cashier')->where('u.status', 'active')->get()->getResultArray();
    if (!$cashiers) throw new RuntimeException('A local assigned cashier is required for this read-only check.');
    $services = ['quarantine' => new Quarantine($db), 'damaged' => new DamagedItems($db)];
    $crossDetails = 0;
    foreach ($cashiers as $cashier) {
        $scope = ReturnRecordAccess::scope($db, (int) $cashier['id'], 2147483647);
        $assert($scope['branch_id'] === (int) $cashier['branch_id'] && !$scope['can_inspect'] && !$scope['can_view_all_branches'], 'Current cashier ' . $cashier['id'] . ' is scoped to stored branch without inspection rights');
        foreach ($services as $kind => $service) {
            $all = $service->records($admin, 'all');
            $expected = array_values(array_filter($all, static fn ($record) => (int) $record['branch_id'] === (int) $cashier['branch_id']));
            $actual = $service->records((int) $cashier['id'], 'all', 2147483647);
            $assert($ids($actual) === $ids($expected), 'Forged branch cannot change ' . $kind . ' records for cashier ' . $cashier['id']);
            foreach ($actual as $record) {
                $detail = $service->item((int) $record['id'], (int) $cashier['id']);
                $detailId = $kind === 'quarantine' ? $detail['refund']['id'] : $detail['id'];
                $assert((int) $detailId === (int) $record['id'], 'Own-branch ' . $kind . ' detail ' . $record['id'] . ' is readable by cashier ' . $cashier['id']);
            }
            $foreign = array_values(array_filter($all, static fn ($record) => (int) $record['branch_id'] !== (int) $cashier['branch_id']));
            if ($foreign) {
                try { $service->item((int) $foreign[0]['id'], (int) $cashier['id']); throw new RuntimeException('Other-branch record exposed.'); }
                catch (InvalidArgumentException $e) { $assert(str_contains($e->getMessage(), 'not found'), 'Other-branch ' . $kind . ' detail denied to cashier ' . $cashier['id']); $crossDetails++; }
            }
        }
    }
    $assert($snapshot($db) === $before, 'All local table hashes and migration rows remain unchanged');
    $dir = WRITEPATH . 'return-access-review'; if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($dir . '/mysql-results.json', json_encode(['checks' => $checks, 'cashiers_checked' => count($cashiers), 'cross_branch_details_checked' => $crossDetails,
        'table_hashes_preserved' => count($before), 'database_writes' => false, 'hosting_changed' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'Passed ' . count($checks) . ' read-only MySQL access checks across ' . count($cashiers) . " cashiers; all table hashes preserved.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
finally { $db->close(); }
