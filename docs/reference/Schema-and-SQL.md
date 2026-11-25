---
title: "Migration and SQL inventory"
type: reference
status: generated
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
tags: [documentation, index]
---
# Migration and SQL inventory

These are source files, not proof of applied migrations. SQL bodies and raw exports remain outside the vault. Use [Database and recovery](../system/Database-and-Recovery.md) to distinguish upgrade, seed, repair, reset and restore operations.

## Source migrations

- `app/Database/Migrations/2026_06_17_000001_create_refund_items_table.php`
- `app/Database/Migrations/2026_08_13_000002_create_system_settings_table.php`
- `app/Database/Migrations/2026_08_14_000003_add_checkout_recovery_token.php`
- `app/Database/Migrations/2026_08_20_000004_add_email_to_suppliers.php`
- `app/Database/Migrations/2026_09_16_000005_add_logic_safeguards.php`
- `app/Database/Migrations/2026_10_03_000006_add_branch_product_trash.php`
- `app/Database/Migrations/2026_10_03_000007_add_soft_delete_flags.php`
- `app/Database/Migrations/2026_10_03_000008_add_permanent_delete_markers.php`
- `app/Database/Migrations/2026_10_03_000009_add_permanent_delete_flags.php`
- `app/Database/Migrations/2026_10_04_000010_add_stock_expiry_provenance.php`
- `app/Database/Migrations/2026_10_14_000011_add_receipt_invoice_fields.php`
- `app/Database/Migrations/2026_10_14_000012_add_product_medicine_type.php`
- `app/Database/Migrations/2026_10_14_000013_add_statutory_discount_fields.php`
- `app/Database/Migrations/2026_10_15_000014_add_cashier_shifts.php`
- `app/Database/Migrations/2026_10_20_000015_add_cashier_login_sessions.php`
- `app/Database/Migrations/2026_10_21_000016_add_user_presence_sessions.php`
- `app/Database/Migrations/2026_10_21_000017_enforce_cashier_foreign_keys.php`
- `app/Database/Migrations/2026_10_23_000018_add_quarantine_resolutions.php`

## Existing-database SQL files

| Repository file | Intent | Guide |
| --- | --- | --- |
| `2026-09-17-latest-changes.sql` | Baseline-specific combined upgrade | Read the SQL header and prerequisites |
| `2026-09-17-verify-only.sql` | Verification queries | Read the SQL header and prerequisites |
| `2026-10-03-all-latest-database-updates.sql` | Baseline-specific combined upgrade | [Guide](../upgrades/2026-10-03-all-latest-database-updates.md) |
| `2026-10-03-branch-product-trash.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-03-branch-product-trash.md) |
| `2026-10-03-permanent-delete-flags.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-03-permanent-delete-flags.md) |
| `2026-10-03-retained-permanent-deletion.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-03-retained-permanent-deletion.md) |
| `2026-10-03-soft-delete-flags.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-03-soft-delete-flags.md) |
| `2026-10-04-stock-expiry-safeguards.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-04-stock-expiry-safeguards.md) |
| `2026-10-14-latest-existing-database.sql` | Baseline-specific combined upgrade | [Guide](../upgrades/2026-10-14-latest-existing-database.md) |
| `2026-10-14-product-invoice-data.sql` | Scoped data update/seed | Read the SQL header and prerequisites |
| `2026-10-14-product-medicine-types.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-14-product-medicine-types.md) |
| `2026-10-14-products-vatable.sql` | Scoped data update/seed | Read the SQL header and prerequisites |
| `2026-10-14-receipt-invoice-fields.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-14-receipt-invoice-fields.md) |
| `2026-10-14-senior-pwd-discounts.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-14-senior-pwd-discounts.md) |
| `2026-10-15-branded-products.sql` | Scoped data update/seed | [Guide](../upgrades/2026-10-15-branded-products.md) |
| `2026-10-15-cashier-workspace.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-15-cashier-workspace.md) |
| `2026-10-15-demo-users.sql` | Scoped data update/seed | [Guide](../upgrades/2026-10-15-demo-users.md) |
| `2026-10-15-latest-existing-database.sql` | Baseline-specific combined upgrade | [Guide](../upgrades/2026-10-15-latest-existing-database.md) |
| `2026-10-15-product-catalog.sql` | Scoped data update/seed | [Guide](../upgrades/2026-10-15-product-catalog.md) |
| `2026-10-16-clear-cashier-shifts.sql` | Deliberate history reset | [Guide](../upgrades/2026-10-16-clear-cashier-shifts.md) |
| `2026-10-16-reconcile-master-stock.sql` | Targeted data repair | Read the SQL header and prerequisites |
| `2026-10-16-reconcile-sale41-refunds.sql` | Targeted data repair | [Guide](../upgrades/2026-10-16-reconcile-sale41-refunds.md) |
| `2026-10-17-user-full-names.sql` | Scoped data update/seed | [Guide](../upgrades/2026-10-17-user-full-names.md) |
| `2026-10-20-cashier-single-login.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-20-cashier-single-login.md) |
| `2026-10-21-cashier-foreign-keys.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-21-cashier-foreign-keys.md) |
| `2026-10-21-cashier-upgrade-status.sql` | Schema/safeguard update; read prerequisites | Read the SQL header and prerequisites |
| `2026-10-21-finish-cashier-foreign-keys.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-21-finish-cashier-foreign-keys.md) |
| `2026-10-21-latest-existing-database.sql` | Baseline-specific combined upgrade | [Guide](../upgrades/2026-10-21-latest-existing-database.md) |
| `2026-10-21-user-presence.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-21-user-presence.md) |
| `2026-10-23-quarantine.sql` | Schema/safeguard update; read prerequisites | [Guide](../upgrades/2026-10-23-quarantine.md) |

The files in the table are under `database/upgrades`. Full exports under `database/exports` are intentionally not copied into this vault.

[Home](../Home.md)
