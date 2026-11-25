-- Pharxmaco: realistic sample user full names, October 17, 2026.
-- Select the existing application database in phpMyAdmin, then import this file.
-- Changes only matching placeholder/test full names and their updated_at timestamps.
-- Usernames, passwords, roles, branch assignments, status and saved transactions are preserved.
-- These are fictional sample names. Custom names are skipped; repeat imports make no further changes.
START TRANSACTION;
UPDATE `users`
SET `full_name` = CASE `username`
    WHEN 'admin' THEN 'Juan Miguel Santos'
    WHEN 'cashier' THEN 'Maria Angela Reyes'
    WHEN 'cashier2' THEN 'Carlo Mendoza'
    WHEN 'cashierss' THEN 'Rhea Castillo'
    WHEN 'demo_admin' THEN 'Patricia Garcia'
    WHEN 'demo_cashier1' THEN 'Daniel Cruz'
    WHEN 'demo_cashier2' THEN 'Sofia Ramos'
    ELSE `full_name`
END,
`updated_at` = NOW()
WHERE (`username` = 'admin' AND `full_name` = 'System Administrator')
   OR (`username` = 'cashier' AND `full_name` = 'Main Cashier')
   OR (`username` = 'cashier2' AND `full_name` = 'Patag Cashier')
   OR (`username` = 'cashierss' AND `full_name` = 'rqweqw')
   OR (`username` = 'demo_admin' AND `full_name` = 'Demo Administrator')
   OR (`username` = 'demo_cashier1' AND `full_name` IN ('Demo Cashier One', 'Test Cashier'))
   OR (`username` = 'demo_cashier2' AND `full_name` = 'Demo Cashier Two');
SELECT ROW_COUNT() AS full_names_updated;
COMMIT;
SELECT `id`, `username`, `full_name`, `role`, `status` FROM `users`
WHERE `username` IN ('admin', 'cashier', 'cashier2', 'cashierss', 'demo_admin', 'demo_cashier1', 'demo_cashier2')
ORDER BY `id`;
