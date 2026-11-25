<?php
// Synthetic local values only. No application bootstrap, credentials or database.
$prefix = str_repeat('a', 72);
$submitted = $prefix . 'X';
$alternate = $prefix . 'Y';
$hash = password_hash($submitted, PASSWORD_DEFAULT);
$result = [
    'php_version' => PHP_VERSION,
    'algorithm' => password_get_info($hash)['algoName'],
    'submitted_bytes' => strlen($submitted),
    'alternate_bytes' => strlen($alternate),
    'within_controller_8_to_255_limit' => strlen($submitted) >= 8 && strlen($submitted) <= 255,
    'submitted_matches' => password_verify($submitted, $hash),
    'different_suffix_matches' => password_verify($alternate, $hash),
    'first_72_bytes_alone_match' => password_verify($prefix, $hash),
    'database_accessed' => false,
];
file_put_contents(__DIR__ . '/password-boundary.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
