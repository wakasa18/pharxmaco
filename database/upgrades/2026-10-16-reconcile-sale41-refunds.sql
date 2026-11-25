-- Pharxmaco: correction of CONFIRMED TEST refund records on sale #41.
-- The owner confirmed that refunds should total PHP 500, not PHP 625.
-- Select the existing database in phpMyAdmin, back it up, then import this entire file.
-- Pause refunds during import. Stop on any SQL error and ROLLBACK before retrying.
-- Runs only against the exact invoice, items and six original legacy refund records below.
-- Original refund rows are retained in phx_backup_20261016_sale41_refunds.
-- Changes only refund_subtotal and its net unit price; preserves dates, reasons, stock and sales.
-- Repeat imports do not change amounts or add duplicate audit records.
SELECT id,invoice_no,final_total FROM sales LIMIT 0;
SELECT id,sale_id,quantity,price,subtotal,discount_applied,vat_exemption_amount FROM sale_items LIMIT 0;
SELECT id,sale_id,sale_item_id,product_id,quantity_refunded,price_at_sale,refund_subtotal,
       refund_method,refund_event_id,return_condition,created_at FROM refund_items LIMIT 0;
SELECT user_id,activity,log_time FROM activity_logs LIMIT 0;
CREATE TABLE IF NOT EXISTS phx_backup_20261016_sale41_refunds LIKE refund_items;
CREATE TEMPORARY TABLE phx_sale41_refund_plan (
  id INT PRIMARY KEY, item_id INT NOT NULL, product_id INT NOT NULL, qty INT NOT NULL,
  original_price DECIMAL(10,2) NOT NULL, original_amount DECIMAL(10,2) NOT NULL,
  net_price DECIMAL(10,2) NOT NULL, net_amount DECIMAL(10,2) NOT NULL, refunded_at DATETIME NOT NULL
) ENGINE=InnoDB;
INSERT INTO phx_sale41_refund_plan VALUES
 (3,58,10,2,50.00,100.00,40.00,80.00,'2026-06-17 10:35:40'),
 (4,57,7,1,75.00,75.00,60.00,60.00,'2026-06-17 10:42:51'),
 (5,57,7,1,75.00,75.00,60.00,60.00,'2026-06-17 10:48:39'),
 (6,57,7,1,75.00,75.00,60.00,60.00,'2026-06-26 10:47:59'),
 (7,58,10,2,50.00,100.00,40.00,80.00,'2026-06-26 10:47:59'),
 (8,59,12,1,200.00,200.00,160.00,160.00,'2026-06-26 10:47:59');

START TRANSACTION;
SELECT id FROM sales WHERE id=41 FOR UPDATE;
SELECT id FROM sale_items WHERE sale_id=41 ORDER BY id FOR UPDATE;
SELECT id FROM refund_items WHERE sale_id=41 ORDER BY id FOR UPDATE;
SELECT id FROM cashier_events WHERE sale_id=41 ORDER BY id FOR UPDATE;
SET @phx_sale41_actor=(SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1);
SET @phx_sale41_identity=(
 (SELECT COUNT(*) FROM sales WHERE id=41 AND invoice_no='INV-20260617103518-97E5EE'
   AND total_amount=625.00 AND discount_amount=125.00 AND final_total=500.00
   AND payment_method='cash' AND amount_paid=1000.00 AND change_amount=500.00 AND status='refunded')=1
 AND (SELECT COUNT(*) FROM sale_items WHERE sale_id=41)=3
 AND (SELECT COUNT(*) FROM sale_items WHERE sale_id=41 AND vat_exemption_amount=0 AND
    ((id=57 AND product_id=7 AND quantity=3 AND price=75 AND subtotal=225 AND discount_applied=45)
     OR (id=58 AND product_id=10 AND quantity=4 AND price=50 AND subtotal=200 AND discount_applied=40)
     OR (id=59 AND product_id=12 AND quantity=1 AND price=200 AND subtotal=200 AND discount_applied=40)))=3
 AND (SELECT COUNT(*) FROM refund_items WHERE sale_id=41)=6
 AND NOT EXISTS (SELECT 1 FROM cashier_events WHERE sale_id=41)
 AND @phx_sale41_actor IS NOT NULL
);
SET @phx_sale41_original=(SELECT COUNT(*) FROM refund_items r JOIN phx_sale41_refund_plan p ON p.id=r.id
 WHERE r.sale_id=41 AND r.sale_item_id=p.item_id AND r.product_id=p.product_id AND r.quantity_refunded=p.qty
 AND r.price_at_sale=p.original_price AND r.refund_subtotal=p.original_amount AND r.created_at=p.refunded_at
 AND r.refund_method IS NULL AND r.refund_event_id IS NULL AND r.return_condition='resellable');
SET @phx_sale41_apply=(@phx_sale41_identity AND @phx_sale41_original=6);
INSERT INTO phx_backup_20261016_sale41_refunds
SELECT r.* FROM refund_items r JOIN phx_sale41_refund_plan p ON p.id=r.id
WHERE @phx_sale41_apply AND NOT EXISTS (SELECT 1 FROM phx_backup_20261016_sale41_refunds b WHERE b.id=r.id);
-- Refuse to overwrite if an earlier backup has different original values.
SET @phx_sale41_apply=(@phx_sale41_apply AND
 (SELECT COUNT(*) FROM phx_backup_20261016_sale41_refunds b JOIN phx_sale41_refund_plan p ON p.id=b.id
  WHERE b.sale_id=41 AND b.sale_item_id=p.item_id AND b.product_id=p.product_id AND b.quantity_refunded=p.qty
  AND b.price_at_sale=p.original_price AND b.refund_subtotal=p.original_amount AND b.created_at=p.refunded_at)=6);
INSERT INTO activity_logs (user_id,activity,log_time)
SELECT @phx_sale41_actor,
 '2026-10-16 owner-confirmed TEST data repair: sale #41 / INV-20260617103518-97E5EE refunds corrected from PHP 625.00 to net PHP 500.00 (PHP 125.00 excess removed). Original rows: phx_backup_20261016_sale41_refunds. Refund IDs 3/7: 100->80 each; 4/5/6: 75->60 each; 8: 200->160. Net refund unit prices corrected; quantities, stock, dates, reasons and invoice unchanged.',
 UTC_TIMESTAMP()+INTERVAL 8 HOUR WHERE @phx_sale41_apply;
SET @phx_sale41_audited=ROW_COUNT();
UPDATE refund_items r JOIN phx_sale41_refund_plan p ON p.id=r.id
SET r.price_at_sale=p.net_price,r.refund_subtotal=p.net_amount
WHERE @phx_sale41_apply AND @phx_sale41_audited=1 AND r.sale_id=41;
SET @phx_sale41_changed=ROW_COUNT();
COMMIT;
SET @phx_sale41_corrected=(SELECT COUNT(*) FROM refund_items r JOIN phx_sale41_refund_plan p ON p.id=r.id
 WHERE r.sale_id=41 AND r.sale_item_id=p.item_id AND r.product_id=p.product_id AND r.quantity_refunded=p.qty
 AND r.price_at_sale=p.net_price AND r.refund_subtotal=p.net_amount AND r.created_at=p.refunded_at
 AND r.refund_method IS NULL AND r.refund_event_id IS NULL AND r.return_condition='resellable');
SELECT CASE WHEN @phx_sale41_changed=6 THEN 'Sale #41 test refunds corrected to PHP 500.00'
 WHEN @phx_sale41_identity AND @phx_sale41_corrected=6 THEN 'Already corrected; no refund amounts changed'
 ELSE 'Not applied: invoice, refund records, audit actor or backup did not match; review before changing anything' END AS repair_status;
SELECT r.id,r.sale_item_id,r.quantity_refunded,r.price_at_sale,r.refund_subtotal
FROM refund_items r WHERE r.sale_id=41 ORDER BY r.id;
SELECT s.id,s.invoice_no,s.final_total,COALESCE(SUM(r.refund_subtotal),0) total_refunded,
       COALESCE(SUM(r.refund_subtotal),0)-s.final_total refund_excess
FROM sales s LEFT JOIN refund_items r ON r.sale_id=s.id WHERE s.id=41 GROUP BY s.id,s.invoice_no,s.final_total;
DROP TEMPORARY TABLE phx_sale41_refund_plan;
SET @phx_sale41_actor=NULL,@phx_sale41_identity=NULL,@phx_sale41_original=NULL,@phx_sale41_apply=NULL,
    @phx_sale41_audited=NULL,@phx_sale41_changed=NULL,@phx_sale41_corrected=NULL;
