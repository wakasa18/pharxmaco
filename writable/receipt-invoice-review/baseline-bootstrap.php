<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
foreach (['app/Controllers/Products.php', 'app/Controllers/Cashier/SalesController.php', 'app/Libraries/ProductExchange.php'] as $file) {
    require __DIR__ . '/originals/' . $file;
}
