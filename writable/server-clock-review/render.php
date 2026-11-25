<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
helper(['url', 'form']);
session()->set(['role' => 'admin', 'user_id' => 1, 'full_name' => 'Maria Reyes']);
$html = view('admin/users/password', ['user' => ['id' => 2, 'full_name' => 'Carlo Santos']], ['saveData' => false]);
$html = preg_replace('~https?://[^"\s]+/assets/~', '/assets/', $html);
$html = preg_replace('~data-sync-url="[^"]+"~', 'data-sync-url="/server-time"', $html);
$html = preg_replace('~<link[^>]*href="https://fonts\.[^>]*>~', '', $html);
file_put_contents(__DIR__ . '/clock.html', $html);
echo "Rendered clock fixture without application records.\n";
