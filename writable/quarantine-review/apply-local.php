<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local database required.');
$db = \Config\Database::connect($settings, false); $db->initialize();
$stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Ymd-His') . '-' . bin2hex(random_bytes(3));
$backup = WRITEPATH . 'backups/pharxmaco-pre-quarantine-' . $stamp . '.sql';
$optionFile = __DIR__ . '/mysql-options-' . $stamp . '.cnf';
$iniValue = static fn ($value) => '"' . str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\n', '\r'], (string) $value) . '"';
$snapshot = static function ($db): array {
    $db->resetDataCache(); $tables = [];
    foreach ($db->listTables() as $table) {
        $encoded = [];
        foreach ($db->query('SELECT * FROM `' . str_replace('`','``',$table) . '`')->getResultArray() as $row) {
            foreach ($row as &$value) if ($value !== null) $value = (string) $value; unset($value);
            $encoded[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        sort($encoded, SORT_STRING); $tables[$table] = ['rows' => count($encoded), 'sha256' => hash('sha256', implode("\n", $encoded))];
    }
    ksort($tables); return $tables;
};
try {
    $before = $snapshot($db); $migrations = $db->table('migrations')->orderBy('id')->get()->getResultArray();
    file_put_contents($optionFile, "[client]\nuser=" . $iniValue($settings['username']) . "\npassword=" . $iniValue($settings['password'])
        . "\nhost=" . $iniValue($settings['hostname']) . "\nport=" . (int) ($settings['port'] ?? 3306) . "\ndefault-character-set=utf8mb4\n");
    $process = proc_open(['C:/UniServerZ/core/mysql/bin/mysqldump.exe', '--defaults-extra-file=' . $optionFile, '--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--result-file=' . $backup, $settings['database']],
        [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null, ['bypass_shell' => true, 'suppress_errors' => true]);
    if (!is_resource($process)) throw new RuntimeException('Could not create backup; upgrade not started.');
    fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || !is_file($backup) || filesize($backup) < 1000) throw new RuntimeException('Backup failed; upgrade not started.');
    unlink($optionFile);
    $run = static function ($db): void {
        if (!$db->connID->multi_query(file_get_contents(ROOTPATH . 'database/upgrades/2026-10-23-quarantine.sql'))) throw new RuntimeException('Import failed.');
        do { if ($result = $db->connID->store_result()) $result->free(); if (!$db->connID->more_results()) break;
            if (!$db->connID->next_result()) throw new RuntimeException('Import continuation failed.');
        } while (true);
        $db->resetDataCache();
    };
    $run($db); $run($db);
    $after = $snapshot($db); $beforeData = $before; $afterData = $after;
    unset($beforeData['migrations'], $afterData['migrations'], $beforeData['quarantine_resolutions'], $afterData['quarantine_resolutions']);
    $newMigrations = $db->table('migrations')->orderBy('id')->get()->getResultArray();
    if ($beforeData !== $afterData || array_slice($newMigrations, 0, count($migrations)) !== $migrations
        || (int) $db->table('migrations')->where('version', '2026_10_23_000018')->countAllResults() !== 1
        || $db->table('quarantine_resolutions')->countAllResults() !== 0) throw new RuntimeException('Local upgrade verification failed. Retain backup.');
    $report = ['database' => $settings['database'], 'backup' => $backup, 'backup_bytes' => filesize($backup), 'backup_sha256' => hash_file('sha256', $backup),
        'imports' => 2, 'existing_data_tables_preserved' => count($beforeData), 'prior_migrations_preserved' => true, 'migration_000018_count' => 1,
        'pending_returns' => $db->table('refund_items')->where('return_condition', 'quarantined')->countAllResults(),
        'before' => $before, 'after' => $after, 'hosting_changed' => false, 'business_records_changed' => false];
    file_put_contents(__DIR__ . '/local-upgrade-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(array_intersect_key($report, array_flip(['database','backup','existing_data_tables_preserved','pending_returns','hosting_changed','business_records_changed'])), JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); $failed = true; }
finally { if (is_file($optionFile)) unlink($optionFile); $db->close(); }
if (!empty($failed)) exit(1);
