<?php
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--apply') exit(1);
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
set_exception_handler(static function (Throwable $error): void { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); });
$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)
    || $settings['database'] !== 'pharxmaco_drugstore') throw new RuntimeException('The known local application database is required.');
$db = \Config\Database::connect('default');
$db->initialize();
$tables = array_values(array_diff($db->listTables(), ['migrations']));
$snapshot = static function (array $tables) use ($db): array {
    $state = [];
    foreach ($tables as $table) {
        $rows = array_map(static fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $db->table($table)->get()->getResultArray());
        sort($rows, SORT_STRING);
        $state[$table] = ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }
    return $state;
};
$before = $snapshot($tables);
$oldMigrations = $db->table('migrations')->get()->getResultArray();
$sql = file_get_contents(ROOTPATH . 'database/upgrades/2026-10-20-cashier-single-login.sql');
$run = static function () use ($db, $sql): void {
    if (!$db->connID->multi_query($sql)) throw new RuntimeException('Cashier login schema import failed.');
    do {
        if ($result = $db->connID->store_result()) $result->free();
        if (!$db->connID->more_results()) break;
        if (!$db->connID->next_result()) throw new RuntimeException('Cashier login schema import stopped on an SQL error.');
    } while (true);
};
$run(); $run();
if ($snapshot($tables) !== $before) throw new RuntimeException('An existing application table changed during the schema update.');
foreach ($oldMigrations as $old) {
    if ($db->table('migrations')->where('id', $old['id'])->get()->getRowArray() !== $old) throw new RuntimeException('An old migration record changed.');
}
$migrationCount = $db->table('migrations')->where('version', '2026_10_20_000015')->where('namespace', 'App')->where('group', 'default')->countAllResults();
if ($migrationCount !== 1) throw new RuntimeException('The new migration must be recorded exactly once.');
$report = ['database' => $settings['database'], 'schema_applied' => true, 'repeated_import_passed' => true,
    'protected_existing_tables' => count($tables), 'existing_application_records_preserved' => true,
    'migration' => '2026_10_20_000015', 'hosted_database_changed' => false];
file_put_contents(__DIR__ . '/local-application-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
