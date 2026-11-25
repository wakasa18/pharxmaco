<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
foreach (['admin', 'cashier'] as $role) {
    session()->set(['role' => $role, 'branch_id' => null, 'full_name' => 'Maria Santos']);
    $html = view('admin/settings/index', [], ['saveData' => false]);
    $html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
    file_put_contents(__DIR__ . '/settings-' . $role . '.html', $html);
}
echo "Rendered admin and cashier settings fixtures.\n";
foreach ([1, 2] as $userId) {
    session()->set(['role' => 'admin', 'user_id' => $userId, 'full_name' => 'Preview User ' . $userId]);
    $html = view('admin/settings/index', [], ['saveData' => false]);
    file_put_contents(__DIR__ . '/settings-user-' . $userId . '.html', preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html));
}
echo "Rendered two account-specific settings fixtures.\n";
