<?php
require dirname(__DIR__, 2) . '/system/Test/bootstrap.php';
$settings = config(\Config\Database::class)->default;
if ($settings['database'] !== 'pharxmaco_drugstore'
    || !in_array($settings['hostname'], ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Known local application database required.');
}
$db = \Config\Database::connect('default');
$queries = [
    'counts' => 'SELECT (SELECT COUNT(*) FROM products) products, (SELECT COUNT(*) FROM branch_products) branch_inventory_rows, (SELECT COUNT(*) FROM sales) sales, (SELECT COUNT(*) FROM sale_items) sale_items',
    'negative_stock' => 'SELECT COUNT(*) count FROM branch_products WHERE stock < 0',
    'master_stock_mismatches' => 'SELECT COUNT(*) count FROM products p LEFT JOIN (SELECT product_id, SUM(stock) qty FROM branch_products GROUP BY product_id) b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.qty,0)',
    'master_stock_mismatch_details' => 'SELECT p.id,p.product_name,p.stock master_stock,COALESCE(b.qty,0) branch_sum FROM products p LEFT JOIN (SELECT product_id, SUM(stock) qty FROM branch_products GROUP BY product_id) b ON b.product_id=p.id WHERE p.stock<>COALESCE(b.qty,0) ORDER BY p.id',
    'duplicate_inventory_pairs' => 'SELECT COUNT(*) count FROM (SELECT branch_id,product_id FROM branch_products GROUP BY branch_id,product_id HAVING COUNT(*)>1) x',
    'orphan_sale_items' => 'SELECT COUNT(*) count FROM sale_items i LEFT JOIN sales s ON s.id=i.sale_id WHERE s.id IS NULL',
    'duplicate_invoices' => 'SELECT COUNT(*) count FROM (SELECT invoice_no FROM sales GROUP BY invoice_no HAVING COUNT(*)>1) x',
    'invoice_total_mismatches' => 'SELECT COUNT(*) count FROM sales s LEFT JOIN (SELECT sale_id,SUM(subtotal) subtotal,SUM(discount_applied) discount,SUM(vat_exemption_amount) relief FROM sale_items GROUP BY sale_id) i ON i.sale_id=s.id WHERE ABS(s.total_amount-COALESCE(i.subtotal,0))>0.01 OR ABS(s.discount_amount-COALESCE(i.discount,0))>0.01 OR ABS(s.final_total-(s.total_amount-s.discount_amount-COALESCE(i.relief,0)))>0.01',
    'refund_quantity_or_value_overages' => 'SELECT COUNT(*) count FROM (SELECT i.id FROM sale_items i JOIN refund_items r ON r.sale_item_id=i.id GROUP BY i.id,i.quantity,i.subtotal,i.discount_applied,i.vat_exemption_amount HAVING SUM(r.quantity_refunded)>i.quantity OR SUM(r.refund_subtotal)>i.subtotal-i.discount_applied-i.vat_exemption_amount+0.01) x',
    'refund_overage_details' => 'SELECT i.id sale_item_id,i.sale_id,i.quantity sold_quantity,SUM(r.quantity_refunded) refunded_quantity,i.subtotal-i.discount_applied-i.vat_exemption_amount net_item_amount,SUM(r.refund_subtotal) refunded_amount FROM sale_items i JOIN refund_items r ON r.sale_item_id=i.id GROUP BY i.id,i.sale_id,i.quantity,i.subtotal,i.discount_applied,i.vat_exemption_amount HAVING SUM(r.quantity_refunded)>i.quantity OR SUM(r.refund_subtotal)>i.subtotal-i.discount_applied-i.vat_exemption_amount+0.01 ORDER BY i.id',
    'settlement_mismatches' => 'SELECT COUNT(*) count FROM sales WHERE ABS(amount_paid-change_amount-final_total)>0.01',
    'noncash_missing_reference' => "SELECT COUNT(*) count FROM sales WHERE payment_method IN ('card','gcash') AND (reference_no IS NULL OR TRIM(reference_no)='')",
    'noncash_with_change' => "SELECT COUNT(*) count FROM sales WHERE payment_method IN ('card','gcash') AND change_amount>0",
    'sale_items_without_delivery_snapshot' => "SELECT COUNT(*) count FROM sale_items WHERE stock_delivery_id IS NULL OR TRIM(stock_delivery_id)='' OR expiration_date_at_sale IS NULL",
    'stock_without_delivery_id' => "SELECT COUNT(*) count FROM branch_products WHERE stock>0 AND (stock_delivery_id IS NULL OR TRIM(stock_delivery_id)='')",
    'active_stock_unknown_expiry' => "SELECT COUNT(*) count FROM branch_products b JOIN products p ON p.id=b.product_id WHERE b.stock>0 AND b.status='active' AND b.deleted_at IS NULL AND p.status='active' AND p.deleted_at IS NULL AND p.medicine_type IN ('branded','generic') AND b.expiration_date IS NULL",
    'classification' => 'SELECT medicine_type,tax_treatment,senior_pwd_eligible,COUNT(*) count FROM products GROUP BY medicine_type,tax_treatment,senior_pwd_eligible',
    'migration_versions' => 'SELECT version FROM migrations ORDER BY version',
    'tables' => 'SHOW TABLES',
];
$result = ['database' => $settings['database'], 'read_only' => true, 'audited_on' => '2026-10-15'];
$db->query('START TRANSACTION READ ONLY');
try {
    foreach ($queries as $key => $sql) $result[$key] = $db->query($sql)->getResultArray();
} finally {
    $db->query('ROLLBACK');
}
$invoice = config(\Config\ReceiptInvoice::class);
$result['invoice_configuration'] = ['tin_configured' => trim($invoice->tin) !== '',
    'tax_status' => $invoice->taxStatus, 'registered_serial_enabled' => $invoice->serialEnd > 0];
file_put_contents(__DIR__ . '/local-data-results.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
