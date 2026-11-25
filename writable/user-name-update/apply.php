<?php
// Apply the owner-requested full-name-only data update to the known local database.
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if (($settings['database'] ?? '') !== 'pharxmaco_drugstore'
    || !in_array($settings['hostname'] ?? '', ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Known local application database required.');
}
$path = ROOTPATH . 'database/upgrades/2026-10-17-user-full-names.sql';
$sql = file_get_contents($path);
if (!preg_match('/^UPDATE `users`[\s\S]*?;/m', $sql, $match)) {
    throw new RuntimeException('Expected user-name update statement missing.');
}
$names = [
    'admin' => ['System Administrator', 'Juan Miguel Santos'],
    'cashier' => ['Main Cashier', 'Maria Angela Reyes'],
    'cashier2' => ['Patag Cashier', 'Carlo Mendoza'],
    'cashierss' => ['rqweqw', 'Rhea Castillo'],
    'demo_admin' => ['Demo Administrator', 'Patricia Garcia'],
    'demo_cashier1' => ['Demo Cashier One', 'Test Cashier', 'Daniel Cruz'],
    'demo_cashier2' => ['Demo Cashier Two', 'Sofia Ramos'],
];
$db = \Config\Database::connect($settings, false);
$db->transException(true);
$db->transBegin();
try {
    $before = $db->query('SELECT * FROM users ORDER BY id FOR UPDATE')->getResultArray();
    $changes = [];
    foreach ($before as $row) {
        $options = $names[$row['username']] ?? [];
        $newName = $options ? array_pop($options) : null;
        if (in_array($row['full_name'], $options, true)) {
            $changes[] = ['id' => $row['id'], 'username' => $row['username'],
                'old_name' => $row['full_name'], 'new_name' => $newName, 'updated_at_before' => $row['updated_at']];
        }
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $backup = null;
    if ($changes) {
        $dir = WRITEPATH . 'backups/';
        if (!is_dir($dir) && !mkdir($dir, 0770, true)) throw new RuntimeException('Backup directory unavailable.');
        $backup = $dir . 'user-full-names-' . $now->format('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
        $json = json_encode(['database' => $settings['database'], 'created_at' => $now->format('c'),
            'changes' => $changes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($backup, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException('Name backup could not be saved.');
    }
    $db->query($match[0]);
    $changedCount = $db->affectedRows();
    $after = $db->query('SELECT * FROM users ORDER BY id')->getResultArray();
    if (count($after) !== count($before) || $changedCount !== count($changes)) throw new RuntimeException('Unexpected user update count.');
    $byId = array_column($changes, null, 'id');
    foreach ($before as $index => $row) {
        $expected = $row;
        if (isset($byId[$row['id']])) {
            $expected['full_name'] = $byId[$row['id']]['new_name'];
            $expected['updated_at'] = $after[$index]['updated_at'];
        }
        if ($after[$index] !== $expected) throw new RuntimeException('Unexpected user field change.');
    }
    if ($changes) {
        $actor = $db->query("SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1")->getRowArray();
        if (!$actor) throw new RuntimeException('An active administrator is required for the audit record.');
        $db->table('activity_logs')->insert(['user_id' => $actor['id'],
            'activity' => 'Owner-requested sample full-name update. Login details and permissions preserved. '
                . json_encode(['backup' => basename($backup), 'changes' => $changes], JSON_THROW_ON_ERROR),
            'log_time' => $now->format('Y-m-d H:i:s')]);
    }
    if (!$db->transStatus()) throw new RuntimeException('User-name update failed.');
    $db->transCommit();
    $result = ['database' => $settings['database'], 'updated' => $changedCount,
        'changes' => $changes, 'backup' => $backup, 'all_other_user_fields_verified_unchanged' => true];
    file_put_contents(__DIR__ . '/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    $db->transRollback();
    throw $error;
}
