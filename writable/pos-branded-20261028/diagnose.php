<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if (!in_array($settings['hostname'], ['localhost','127.0.0.1','::1'], true) || $settings['database'] !== 'pharxmaco_drugstore') throw new RuntimeException('Only the configured loopback application database is allowed.');
mysqli_report(MYSQLI_REPORT_OFF);
$db = mysqli_init(); $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
if (!@$db->real_connect($settings['hostname'], $settings['username'], $settings['password'], $settings['database'], (int)($settings['port'] ?? 3306))) throw new RuntimeException('Local database unavailable.');
$out = ['scope'=>'read-only local product catalog diagnosis', 'application_writes'=>false];
try {
    if (!$db->query('START TRANSACTION READ ONLY')) throw new RuntimeException('Read-only transaction unavailable.');
    $out['types'] = $db->query("SELECT COALESCE(medicine_type,'NULL') AS medicine_type, COUNT(*) AS products FROM products GROUP BY medicine_type")->fetch_all(MYSQLI_ASSOC);
    $out['branded_inventory'] = $db->query("SELECT p.id, p.product_name, p.medicine_type, p.status AS product_status, p.deleted_at AS product_deleted_at, p.is_deleted AS product_deleted, p.is_permanently_deleted AS product_permanent, b.branch_name, bp.branch_id, bp.stock, bp.expiration_date, bp.status AS inventory_status, bp.deleted_at AS inventory_deleted_at, bp.is_deleted AS inventory_deleted, bp.is_permanently_deleted AS inventory_permanent FROM products p LEFT JOIN branch_products bp ON bp.product_id=p.id LEFT JOIN branches b ON b.id=bp.branch_id WHERE LOWER(TRIM(p.medicine_type))='branded' ORDER BY p.id,bp.branch_id")->fetch_all(MYSQLI_ASSOC);
    $out['eligible_by_type_branch'] = $db->query("SELECT bp.branch_id, COALESCE(p.medicine_type,'NULL') AS medicine_type, COUNT(*) AS eligible FROM products p JOIN branch_products bp ON bp.product_id=p.id WHERE bp.status='active' AND bp.deleted_at IS NULL AND bp.is_deleted=0 AND bp.is_permanently_deleted=0 AND p.status='active' AND p.deleted_at IS NULL AND p.is_deleted=0 AND p.is_permanently_deleted=0 AND bp.stock>0 AND (bp.expiration_date IS NULL OR bp.expiration_date>CURDATE()) GROUP BY bp.branch_id,p.medicine_type ORDER BY bp.branch_id,p.medicine_type")->fetch_all(MYSQLI_ASSOC);
} finally { $db->rollback(); $db->close(); }
file_put_contents(__DIR__.'/diagnosis.json', json_encode($out, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($out, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . "\n";
