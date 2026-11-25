-- Read-only. Run in phpMyAdmin SQL, even if information_schema is selected.
SELECT
 (SELECT COUNT(*) FROM `u315645644_pharxmacotest`.`migrations`
 WHERE version='2026_10_21_000017' AND namespace='App' AND `group`='default') AS migration_000017,
 (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA='u315645644_pharxmacotest' AND TABLE_NAME IN ('cashier_drawers','cashier_shifts','cashier_events','cashier_held_sales','cashier_shift_reviews','cashier_event_reviews','cashier_shift_actions','cashier_login_sessions','user_presence_sessions')) AS cashier_foreign_keys;
