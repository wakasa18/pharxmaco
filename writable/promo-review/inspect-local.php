<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if ($settings['database'] !== 'pharxmaco_drugstore' || !in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Known local database required.');
$db = \Config\Database::connect('default');
$db->query('START TRANSACTION READ ONLY');
try {
    $rows = $db->query('SELECT d.id,d.discount_name,d.discount_type,d.discount_value,d.applies_to,d.product_id,d.category_id,d.minimum_purchase,d.max_discount_amount,d.start_date,d.end_date,d.status,d.deleted_at,d.is_deleted,d.is_permanently_deleted,p.product_name,p.sku,p.status product_status FROM discounts d LEFT JOIN products p ON p.id=d.product_id ORDER BY d.id DESC')->getResultArray();
    $inventory = $db->query("SELECT bp.product_id,bp.branch_id,bp.price,bp.stock,bp.status FROM branch_products bp WHERE bp.product_id IN (SELECT product_id FROM discounts WHERE applies_to='product' AND product_id IS NOT NULL) ORDER BY bp.product_id,bp.branch_id")->getResultArray();
    echo json_encode(['date' => date('Y-m-d'), 'promotions' => $rows, 'target_inventory' => $inventory], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally { $db->query('ROLLBACK'); }
