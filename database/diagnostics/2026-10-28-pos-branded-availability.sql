-- Read-only diagnosis. Select the application database in phpMyAdmin first.
-- Each active branch is shown separately. No catalog, stock or expiry is changed.
-- POS sells only active, retained, positive-stock inventory after today's expiry cutoff.
SELECT b.id AS branch_id, b.branch_name, p.id AS product_id, p.product_name,
       p.medicine_type, bp.stock, bp.expiration_date,
       CASE
           WHEN bp.id IS NULL THEN 'Not assigned to this branch'
           WHEN p.status <> 'active' OR p.deleted_at IS NOT NULL OR p.is_deleted <> 0 OR p.is_permanently_deleted <> 0
               THEN 'Product is inactive or in Trash'
           WHEN bp.status <> 'active' OR bp.deleted_at IS NOT NULL OR bp.is_deleted <> 0 OR bp.is_permanently_deleted <> 0
               THEN 'Branch inventory is inactive or in Trash'
           WHEN bp.stock <= 0 THEN 'No available stock'
           WHEN bp.expiration_date IS NOT NULL AND bp.expiration_date <= CURDATE() THEN 'Expired stock; replace through the expiry workflow'
           ELSE 'Available in POS'
       END AS pos_availability
FROM branches b
CROSS JOIN products p
LEFT JOIN branch_products bp ON bp.branch_id = b.id AND bp.product_id = p.id
WHERE b.status = 'active' AND p.medicine_type = 'branded'
ORDER BY b.branch_name, p.product_name;
