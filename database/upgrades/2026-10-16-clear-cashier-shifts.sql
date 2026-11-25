-- Pharxmaco: clear ALL cashier shift history in the SELECTED existing database.
-- Prepared against u315645644_pharxmacotest (6).sql, October 16, 2026.
-- Export a backup first. Pause POS use and have cashiers sign out before importing.
-- phpMyAdmin: select the existing database, then import this entire file.
-- Deletes open/closed shifts, their action history and shift review notes.
-- Clears drawer assignments and detaches preserved payment events from deleted shifts.
-- Keeps sales, refunds, inventory, users, held carts, payment events and general audit logs.
-- Shift IDs are NOT reset or reused. No application migration is removed.
-- Import again only when another complete shift-history reset is intended.
-- Stop the import on any SQL error.

SELECT DATABASE() AS selected_database;
SELECT id, active_shift_id, last_shift_id FROM cashier_drawers LIMIT 0;
SELECT id, shift_id FROM cashier_events LIMIT 0;
SELECT id, shift_id FROM cashier_shift_reviews LIMIT 0;
SELECT id, shift_id FROM cashier_shift_actions LIMIT 0;
SELECT id, open_key, active_user_id, active_drawer_id FROM cashier_shifts LIMIT 0;

START TRANSACTION;
-- Opening/closing a shift locks its drawer too. Keep drawers locked until reset commits.
SELECT id FROM cashier_drawers ORDER BY id FOR UPDATE;

SET @phx_removed_shifts = (SELECT COUNT(*) FROM cashier_shifts);
SET @phx_removed_shift_actions = (SELECT COUNT(*) FROM cashier_shift_actions);
SET @phx_removed_shift_reviews = (SELECT COUNT(*) FROM cashier_shift_reviews);

UPDATE cashier_drawers SET active_shift_id = NULL, last_shift_id = NULL
WHERE active_shift_id IS NOT NULL OR last_shift_id IS NOT NULL;
UPDATE cashier_events SET shift_id = NULL WHERE shift_id IS NOT NULL;
-- Clear same-table handover links before deleting their referenced shifts (upgrade 000017).
UPDATE cashier_shifts SET handover_accepted_shift_id = NULL WHERE handover_accepted_shift_id IS NOT NULL;
DELETE FROM cashier_shift_reviews;
DELETE FROM cashier_shift_actions;
DELETE FROM cashier_shifts;
COMMIT;

SELECT 'Cashier shift history cleared; start and count a new shift before selling' AS reset_status,
       @phx_removed_shifts AS shifts_removed,
       @phx_removed_shift_actions AS shift_actions_removed,
       @phx_removed_shift_reviews AS shift_reviews_removed;
SELECT COUNT(*) AS shifts_remaining FROM cashier_shifts;
SELECT COUNT(*) AS assigned_drawers_remaining FROM cashier_drawers
WHERE active_shift_id IS NOT NULL OR last_shift_id IS NOT NULL;
SELECT COUNT(*) AS events_with_shift_remaining FROM cashier_events WHERE shift_id IS NOT NULL;
SET @phx_removed_shifts = NULL, @phx_removed_shift_actions = NULL, @phx_removed_shift_reviews = NULL;
