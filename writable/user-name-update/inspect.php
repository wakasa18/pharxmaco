<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Local database required.\n"); exit(1);
}
try {
    $db = \Config\Database::connect($settings, false);
    $db->initialize();
    echo json_encode(['database' => $settings['database'], 'users' => $db->table('users')
        ->select('id, username, full_name, role, status')->orderBy('id')->get()->getResultArray()],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);
}
