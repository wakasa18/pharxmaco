-- Pharxmaco OPTIONAL demo login accounts, October 15, 2026.
-- Select the existing database. This is a data seed, not a schema migration.
-- Creates one administrator and two cashiers; passwords are listed in the companion .md file.
-- Requires the existing users.session_version field.
-- Reimport skips existing usernames, including inactive or edited demo accounts.
-- Does not reset passwords, change permissions, or create sales, shifts, stock or branches.
-- Demo accounts use normal application permissions and the selected database.
SELECT id, full_name, username, password, role, branch_id, status, session_version, created_at, updated_at FROM users LIMIT 0;
SELECT id, branch_name, status FROM branches LIMIT 0;

START TRANSACTION;
-- Prefer the active Main Branch; otherwise use the first active branch.
-- To choose a specific branch, replace this assignment with its active branch ID.
SET @phx_demo_branch_id = (SELECT id FROM branches WHERE status = 'active'
    ORDER BY CASE WHEN branch_name = 'Main Branch' THEN 0 ELSE 1 END, id LIMIT 1);
SET @phx_demo_created_at = NOW();

INSERT INTO users (full_name, username, password, role, branch_id, status, session_version, created_at, updated_at)
SELECT 'Patricia Garcia', 'demo_admin', '$2y$12$yAgS.GHxsDFUo/OPar4OPeKzl6BVFyE8GpDshdHc16pYe0gbZhyS6', 'admin', NULL, 'active', REPLACE(UUID(), '-', ''), @phx_demo_created_at, @phx_demo_created_at
WHERE EXISTS (SELECT 1 FROM branches WHERE id = @phx_demo_branch_id AND status = 'active')
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'demo_admin');
SET @phx_demo_added_0 = ROW_COUNT();

INSERT INTO users (full_name, username, password, role, branch_id, status, session_version, created_at, updated_at)
SELECT 'Daniel Cruz', 'demo_cashier1', '$2y$12$yAgS.GHxsDFUo/OPar4OPeKzl6BVFyE8GpDshdHc16pYe0gbZhyS6', 'cashier', @phx_demo_branch_id, 'active', REPLACE(UUID(), '-', ''), @phx_demo_created_at, @phx_demo_created_at
WHERE EXISTS (SELECT 1 FROM branches WHERE id = @phx_demo_branch_id AND status = 'active')
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'demo_cashier1');
SET @phx_demo_added_1 = ROW_COUNT();

INSERT INTO users (full_name, username, password, role, branch_id, status, session_version, created_at, updated_at)
SELECT 'Sofia Ramos', 'demo_cashier2', '$2y$12$yAgS.GHxsDFUo/OPar4OPeKzl6BVFyE8GpDshdHc16pYe0gbZhyS6', 'cashier', @phx_demo_branch_id, 'active', REPLACE(UUID(), '-', ''), @phx_demo_created_at, @phx_demo_created_at
WHERE EXISTS (SELECT 1 FROM branches WHERE id = @phx_demo_branch_id AND status = 'active')
AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'demo_cashier2');
SET @phx_demo_added_2 = ROW_COUNT();

COMMIT;
SELECT CASE WHEN @phx_demo_branch_id IS NULL THEN 'No active branch found; no demo accounts created' ELSE 'Demo account import completed; existing usernames preserved' END AS demo_status,
    @phx_demo_branch_id AS cashier_branch_id,
    (@phx_demo_added_0 + @phx_demo_added_1 + @phx_demo_added_2) AS accounts_created;
SELECT u.id, u.full_name, u.username, u.role, u.status, b.branch_name FROM users u
LEFT JOIN branches b ON b.id = u.branch_id
WHERE u.username IN ('demo_admin', 'demo_cashier1', 'demo_cashier2') ORDER BY u.username;
SET @phx_demo_branch_id = NULL, @phx_demo_created_at = NULL,
    @phx_demo_added_0 = NULL, @phx_demo_added_1 = NULL, @phx_demo_added_2 = NULL;
