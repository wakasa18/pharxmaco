<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, 'Isolated login verification failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
$settings = config(\Config\Database::class)->default;
$isolatedPort = getenv('PHX_LOGIN_REVIEW_MYSQL_PORT');
if ($isolatedPort !== false && $isolatedPort !== '') {
    if (!ctype_digit($isolatedPort) || (int) $isolatedPort < 1024 || (int) $isolatedPort > 65535) throw new RuntimeException('Invalid isolated MySQL port.');
    $settings = array_replace($settings, ['hostname' => '127.0.0.1', 'port' => (int) $isolatedPort,
        'username' => 'root', 'password' => '', 'database' => 'mysql']);
}
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('A local MySQL server is required.');
$worker = ($argv[1] ?? '') === '--worker';
$schema = $worker ? (string) ($argv[2] ?? '') : 'phx_cashier_login_review_' . getmypid();
if (!preg_match('/^phx_cashier_login_review_[0-9]+$/D', $schema) || $schema === $settings['database']) throw new RuntimeException('Unsafe isolated schema name.');
$taskSettings = $settings;
$taskSettings['database'] = $schema;
if ($worker) {
    $db = \Config\Database::connect($taskSettings, false);
    $db->initialize();
    $start = (float) $argv[3];
    while (microtime(true) < $start) usleep(1000);
    $user = $db->table('users')->where('id', (int) ($argv[4] ?? 1))->get()->getRowArray();
    $token = (new \App\Libraries\CashierLoginSession($db))->claim($user);
    echo json_encode(['claimed' => $token !== null, 'token' => $token], JSON_THROW_ON_ERROR);
    exit;
}
$server = \Config\Database::connect($settings, false);
$server->initialize();
$server->query('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$checks = [];
try {
    $db = \Config\Database::connect($taskSettings, false);
    $db->initialize();
    $db->query('CREATE TABLE users (id INT PRIMARY KEY, password VARCHAR(255), role VARCHAR(20), branch_id INT, status VARCHAR(20), session_version VARCHAR(64)) ENGINE=InnoDB');
    $db->query('CREATE TABLE migrations (id INT AUTO_INCREMENT PRIMARY KEY, version VARCHAR(255), class VARCHAR(255), `group` VARCHAR(255), namespace VARCHAR(255), time INT, batch INT) ENGINE=InnoDB');
    $db->table('users')->insert(['id' => 1, 'password' => 'isolated-fixture-hash', 'role' => 'cashier', 'branch_id' => 1, 'status' => 'active', 'session_version' => 'initial']);
    $db->table('users')->insert(['id' => 2, 'password' => 'isolated-admin-hash', 'role' => 'admin', 'branch_id' => null, 'status' => 'active', 'session_version' => 'initial']);
    $sql = file_get_contents(ROOTPATH . 'database/upgrades/2026-10-20-cashier-single-login.sql');
    $run = static function () use ($db, $sql): void {
        if (!$db->connID->multi_query($sql)) throw new RuntimeException('SQL import failed.');
        do {
            if ($result = $db->connID->store_result()) $result->free();
            if (!$db->connID->more_results()) break;
            if (!$db->connID->next_result()) throw new RuntimeException('SQL import continuation failed.');
        } while (true);
    };
    $run(); $run();
    if ($db->table('migrations')->countAllResults() !== 1) throw new RuntimeException('Repeated SQL import duplicated migration history.');
    $checks[] = 'Repeatable phpMyAdmin-compatible import';
    require_once APPPATH . 'Database/Migrations/2026_10_20_000015_add_cashier_login_sessions.php';
    (new \App\Database\Migrations\AddCashierLoginSessions(new \CodeIgniter\Database\MySQLi\Forge($db)))->up();
    $checks[] = 'Native migration agrees with imported schema';
    $engine = $db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$schema, 'cashier_login_sessions'])->getRowArray();
    if ($engine['ENGINE'] !== 'InnoDB') throw new RuntimeException('Transactional storage required.');
    $processes = [];
    $start = microtime(true) + 2;
    for ($i = 0; $i < 8; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $schema, (string) $start],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('Could not start competing login worker.');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    $winners = [];
    foreach ($processes as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('A competing login worker failed: ' . $errors);
        $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        if ($result['claimed']) $winners[] = $result['token'];
    }
    if (count($winners) !== 1) throw new RuntimeException('Concurrent logins did not produce exactly one owner.');
    $checks[] = 'Eight competing PHP processes produce exactly one successful login';
    $leases = new \App\Libraries\CashierLoginSession($db);
    try {
        $leases->claim($db->table('users')->where('id', 2)->get()->getRowArray());
        throw new LogicException('Administrator account incorrectly acquired cashier ownership.');
    } catch (InvalidArgumentException $error) {
        if ($db->table('cashier_login_sessions')->where('user_id', 2)->countAllResults() !== 0) throw new RuntimeException('Administrator lease was created.');
    }
    $checks[] = 'Administrator accounts do not acquire cashier login leases';
    $user = $db->table('users')->where('id', 1)->get()->getRowArray();
    if (!$leases->check($user, $winners[0]) || !$leases->check($user, $winners[0])) throw new RuntimeException('Repeated same-second ownership checks failed.');
    $checks[] = 'MySQL zero-changed-row checks retain ownership';
    $before = $db->table('cashier_login_sessions')->get()->getResultArray();
    $run();
    if ($before !== $db->table('cashier_login_sessions')->get()->getResultArray()) throw new RuntimeException('Reimport reset an active lease.');
    $checks[] = 'Reimport preserves the active login';
    $db->table('cashier_login_sessions')->where('user_id', 1)->update(['expires_at' => time() - 1]);
    $new = $leases->claim($user);
    if (!$new || $leases->check($user, $winners[0])) throw new RuntimeException('Expired login revived.');
    $leases->release(1, $winners[0]);
    if (!$leases->check($user, $new)) throw new RuntimeException('Stale logout released the new login.');
    $checks[] = 'Expired/replaced tokens cannot renew or release their replacement';
    $db->table('users')->where('id', 1)->update(['session_version' => 'revoked']);
    $fresh = $db->table('users')->where('id', 1)->get()->getRowArray();
    $freshToken = $leases->claim($fresh);
    try { $leases->claim($user); throw new LogicException('A stale verified account replaced fresh ownership.'); }
    catch (RuntimeException $error) { if ($error instanceof LogicException) throw $error; }
    if (!$leases->check($fresh, $freshToken)) throw new RuntimeException('Fresh login lost ownership.');
    $checks[] = 'Account row locking rejects stale credential snapshots';
    $report = ['checks' => $checks, 'competing_processes' => 8, 'cashier_successful_claims' => count($winners),
        'administrator_lease_acquisition_rejected' => true, 'application_database_changed' => false];
    file_put_contents(__DIR__ . '/mysql-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} finally {
    if (isset($db)) $db->close();
    $server->query('DROP DATABASE `' . $schema . '`');
    $server->close();
}
