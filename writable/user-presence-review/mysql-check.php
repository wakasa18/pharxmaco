<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Local MySQL required.');
$schema = 'phx_presence_review_' . getmypid();
if (!preg_match('/^phx_presence_review_[0-9]+$/D', $schema) || $schema === $settings['database']) throw new RuntimeException('Unsafe scratch database name.');
$server = \Config\Database::connect($settings, false);
$server->initialize();
$server->query('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$connections = new ReflectionProperty(\CodeIgniter\Database\Config::class, 'instances');
$oldConnections = $connections->getValue();
$checks = [];
$assert = static function (bool $condition, string $name) use (&$checks): void { if (!$condition) throw new RuntimeException($name); $checks[] = $name; };
try {
    $db = \Config\Database::connect(array_replace($settings, ['database' => $schema]), false);
    $db->initialize();
    $connections->setValue(null, ['tests' => $db]);
    $db->query('CREATE TABLE users (id INT PRIMARY KEY, password VARCHAR(255), role VARCHAR(20), branch_id INT, status VARCHAR(20), session_version VARCHAR(64)) ENGINE=InnoDB');
    $db->query('CREATE TABLE migrations (id INT AUTO_INCREMENT PRIMARY KEY, version VARCHAR(255), class VARCHAR(255), `group` VARCHAR(255), namespace VARCHAR(255), time INT, batch INT) ENGINE=InnoDB');
    $db->table('users')->insertBatch([
        ['id' => 1, 'password' => 'scratch-hash', 'role' => 'admin', 'branch_id' => null, 'status' => 'active', 'session_version' => 'initial'],
        ['id' => 2, 'password' => 'scratch-hash', 'role' => 'cashier', 'branch_id' => 1, 'status' => 'active', 'session_version' => 'initial'],
    ]);
    require_once APPPATH . 'Database/Migrations/2026_10_20_000015_add_cashier_login_sessions.php';
    (new \App\Database\Migrations\AddCashierLoginSessions(new \CodeIgniter\Database\MySQLi\Forge($db)))->up();
    $run = static function () use ($db): void {
        if (!$db->connID->multi_query(file_get_contents(ROOTPATH . 'database/upgrades/2026-10-21-user-presence.sql'))) throw new RuntimeException('Import failed.');
        do { if ($r = $db->connID->store_result()) $r->free(); if (!$db->connID->more_results()) break;
            if (!$db->connID->next_result()) throw new RuntimeException('Import continuation failed.'); } while (true);
        $db->resetDataCache();
    };
    $run(); $run();
    $assert($db->table('migrations')->countAllResults() === 1 && $db->table('user_presence_sessions')->countAllResults() === 0, 'Repeatable phpMyAdmin import creates no fabricated presence');
    require_once APPPATH . 'Database/Migrations/2026_10_21_000016_add_user_presence_sessions.php';
    (new \App\Database\Migrations\AddUserPresenceSessions(new \CodeIgniter\Database\MySQLi\Forge($db)))->up();
    $assert(count($db->getFieldNames('user_presence_sessions')) === 8, 'Native migration agrees with imported schema');
    $service = new \App\Libraries\UserPresence($db);
    $admin = $db->table('users')->where('id', 1)->get()->getRowArray();
    session()->remove(array_keys(session()->get()));
    session()->set(['user_id' => 1]);
    \App\Libraries\UserPresence::attachCurrent($admin);
    $first = session('presence_token');
    session()->remove('presence_token');
    \App\Libraries\UserPresence::attachCurrent($admin);
    $second = session('presence_token');
    $assert($first !== $second && $service->summary()['online_count'] === 1, 'Multiple administrator sessions aggregate to one online user');
    $snapshot = $db->table('user_presence_sessions')->get()->getResultArray();
    $run();
    $assert($snapshot === $db->table('user_presence_sessions')->get()->getResultArray(), 'Reimport preserves active presence capabilities');
    $assert($service->heartbeat($second) && $service->heartbeat($second), 'Same-second MySQL heartbeats retain authorization');
    \App\Libraries\UserPresence::endCurrent();
    $assert(!$service->heartbeat($second) && $service->summary()['users'][1]['online'], 'Logout revokes one capability without ending another browser');
    session()->set(['user_id' => 2]); session()->remove('presence_token');
    $cashier = $db->table('users')->where('id', 2)->get()->getRowArray();
    $leaseToken = (new \App\Libraries\CashierLoginSession($db))->claim($cashier);
    session()->set('cashier_login_token', $leaseToken);
    \App\Libraries\UserPresence::attachCurrent($cashier);
    $token = session('presence_token');
    $lease = $db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray();
    $assert($service->heartbeat($token) && $lease === $db->table('cashier_login_sessions')->where('user_id', 2)->get()->getRowArray(), 'Presence never renews the cashier lease');
    $assert(!$service->heartbeat($token, (int) $lease['expires_at']), 'Expired cashier ownership blocks presence');
    $db->table('users')->where('id', 1)->update(['session_version' => 'revoked']);
    $assert(!$service->authorize($first) && !$service->summary()['users'][1]['online'], 'Account revocation immediately removes online status');
    $report = ['checks' => $checks, 'scratch_database_removed' => true, 'application_records_changed' => false];
    file_put_contents(__DIR__ . '/mysql-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} finally {
    $connections->setValue(null, $oldConnections);
    if (isset($db)) $db->close();
    $server->query('DROP DATABASE `' . $schema . '`');
    $server->close();
}
