<?php
require dirname(__DIR__, 2) . '/app/Libraries/CashierForeignKeys.php';
$target = dirname(__DIR__, 2) . '/database/upgrades/2026-10-21-cashier-foreign-keys.sql';
file_put_contents($target, \App\Libraries\CashierForeignKeys::importSql());
echo 'Generated ', basename($target), PHP_EOL;
