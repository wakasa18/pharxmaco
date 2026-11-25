<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'admin', 'user_id' => 1, 'full_name' => 'Maria Reyes', 'presence_token' => str_repeat('a', 64)]);
$users = $states = [];
for ($id = 1; $id <= 25; $id++) {
    $users[] = ['id' => $id, 'full_name' => 'Demo user ' . str_pad((string) $id, 2, '0', STR_PAD_LEFT), 'username' => 'demo' . $id,
        'role' => $id === 1 ? 'admin' : 'cashier', 'branch_name' => 'Main Branch', 'status' => 'active', 'created_at' => '2026-10-01 10:00:00'];
    $states[$id] = ['online' => $id <= 15, 'last_seen_at' => time() - $id, 'last_seen_label' => \App\Libraries\DisplayDate::dateTime(time() - $id)];
}
$data = ['users' => $users, 'totalUsers' => 25, 'activeUsers' => 25, 'inactiveUsers' => 0, 'adminUsers' => 1, 'cashierUsers' => 24,
    'presence' => ['available' => true, 'users' => $states, 'online_count' => 15]];
foreach (['users', 'unavailable', 'cashier'] as $name) {
    \Config\Services::resetSingle('renderer');
    session()->set('role', $name === 'cashier' ? 'cashier' : 'admin');
    $fixture = $name === 'unavailable' ? array_replace($data, ['presence' => ['available' => false, 'users' => [], 'online_count' => null]]) : $data;
    $html = $name === 'cashier'
        ? view('cashier/sales/history', ['sales' => [], 'dateFrom' => '', 'dateTo' => '', 'rowLimit' => 1000], ['saveData' => false])
        : view('admin/users/index', $fixture, ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    $html = preg_replace('~data-heartbeat-url="[^"]+"~', 'data-heartbeat-url="/presence/heartbeat"', $html);
    $html = preg_replace('~data-presence-users-url="[^"]+"~', 'data-presence-users-url="/presence/users"', $html);
    $html = preg_replace('~data-sync-url="[^"]+"~', 'data-sync-url="/server-time"', $html);
    $html = preg_replace('~data-activity-url="[^"]+"~', 'data-activity-url="/session/activity"', $html);
    $html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
    file_put_contents(__DIR__ . '/' . $name . '.html', $html);
}
file_put_contents(__DIR__ . '/snapshot.json', json_encode(['success' => true, 'users' => $states, 'online_count' => 15, 'as_of_label' => \App\Libraries\DisplayDate::dateTime(time())], JSON_THROW_ON_ERROR));
echo "Rendered Users/presence fixtures without application records.\n";
