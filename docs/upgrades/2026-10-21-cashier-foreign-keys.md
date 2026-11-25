---
title: "Cashier foreign keys — 21 October 2026"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-21-cashier-foreign-keys.md"
source_sha256: 49265f7ff9138db150e4e65c96b7f8e30ed4c728bcbbc602a805f5d5a25f8b3c
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-21-cashier-foreign-keys.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Cashier foreign keys — 21 October 2026

Adds enforcement for 24 cashier, login and presence relationships. Existing rows and values are preserved. All links use **ON DELETE RESTRICT / ON UPDATE RESTRICT**; related history is not automatically deleted.

## Existing database: phpMyAdmin

**Recovery for a blank import page:** if the explicit-schema status check shows 24 FKs but no migration 000017, import `2026-10-21-finish-cashier-foreign-keys.sql` only. See [completion instructions](2026-10-21-finish-cashier-foreign-keys.md). The full import now caches metadata and groups ALTER statements; metadata-lock waits are limited to ten seconds.

1. Confirm cashier workspace **000014**, cashier login **000015** and presence **000016** are installed. Export a full SQL backup and pause application writes during the upgrade.
2. Upload the three application files from `build/hostinger-cashier-foreign-keys-2026-10-21.zip`, keeping their paths: the migration, `CashierForeignKeys.php` and `DatabaseBackupService.php`.
3. Select the existing application database in phpMyAdmin. Import **only `2026-10-21-cashier-foreign-keys.sql`** in its entirety. Stop on any SQL error.
4. Check for **Preflight passed**, `orphan_references = 0`, and the final result **Cashier foreign-key upgrade complete: 24 relationships enforced; existing records preserved**. Migration **000017** is registered once, only after every relationship is verified.
5. Resume use. Create a new application backup against the updated schema.

The SQL is repeatable. Do not import an older full export over existing data. Do not run the shift-history reset as part of this upgrade.

If preflight says **NOT APPLIED**, no upgrade DDL or record repair was performed. Check required tables, InnoDB engines, matching integer types/signedness, existing FK policies and orphan references. Keep `FOREIGN_KEY_CHECKS` enabled. A failure in a later ALTER can leave earlier schema changes committed; resolve the error and rerun. Stored procedures and routine-creation privileges are not required.

## Relationships

| Table | Enforced references |
| --- | --- |
| `cashier_drawers` | `branch_id` → branches; `active_shift_id`, `last_shift_id` → shifts |
| `cashier_shifts` | `drawer_id`, `active_drawer_id` → drawers; `branch_id` → branches; `user_id`, `active_user_id`, `handover_to_user_id` → users; `handover_accepted_shift_id` → shifts |
| `cashier_events` | `shift_id` → shifts; `branch_id` → branches; `user_id` → users; `sale_id` → sales |
| `cashier_held_sales` | `user_id` → users; `branch_id` → branches |
| `cashier_shift_reviews` | `shift_id` → shifts; `reviewed_by` → users |
| `cashier_event_reviews` | `event_id` → events; `reviewed_by` → users |
| `cashier_shift_actions` | `shift_id` → shifts; `user_id` → users |
| `cashier_login_sessions` | `user_id` → users |
| `user_presence_sessions` | `user_id` → users |

Each reference targets the parent's `id`. Optional NULL references remain allowed. `cashier_events.sale_id` is aligned to the integer type and signedness of `sales.id` after verifying every non-NULL reference. No ID values are changed. Required FK indexes are added by the database. Equivalent existing single-column restrictive constraints are retained without duplication; conflicting definitions cause refusal.

FKs enforce existence. Role/branch authorization, single-login ownership, transactions and unique active-user/drawer constraints continue to enforce workflow rules.

## Reset and recovery

The repository's explicit `2026-10-16-clear-cashier-shifts.sql` now detaches `handover_accepted_shift_id` before deleting shifts. Use that revised file if a deliberate history reset is requested later. This upgrade does not run a reset.

Application restore validates every installed FK, including composite and optional-NULL references, before committing. Invalid references roll back the replacement and leave FK checking enabled. `.pmbak` files still require matching schema metadata; keep the full pre-upgrade SQL export for recovery to its matching schema and create a new application backup after upgrading.

## Verification

Applied twice to local `pharxmaco_drugstore` after a full SQL backup. All rows in **25 existing data tables** and all previous migration records were preserved. Migration 000017 exists once. No shift reset was executed locally. Hosting remains unchanged until installation there.

**43 isolated MySQL checks** passed: preflight refusal, repeat imports/native migrations, all 24 invalid-reference writes, restrictive deletes, real shift/sale/cash-movement/close/handover workflows, backup restoration, composite keys and explicit reset compatibility. **62 focused PHP cases / 680 assertions** passed. The full application suite was not rerun.
