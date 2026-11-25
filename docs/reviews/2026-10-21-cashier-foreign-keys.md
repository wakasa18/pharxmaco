---
title: Enforced cashier foreign keys
type: implementation-review
status: completed
updated: 2026-10-21
tags: [database, cashier, foreign-keys, recovery, testing]
---
# Enforced cashier foreign keys

The user authorized enforcing the cashier-related references after inspection found no FKs on those tables. The upgrade covers the seven cashier workspace tables, cashier login sessions and user presence sessions.

## Schema and preservation

Migration **2026_10_21_000017 / EnforceCashierForeignKeys** and its generated phpMyAdmin SQL share 24 definitions in `app/Libraries/CashierForeignKeys.php`. Every link uses restrictive delete/update actions. Required user/branch/drawer/sale references and nullable active/historical/handover pointers are included. No historical reference is fabricated or automatically detached.

Local preflight found zero orphans across all 24 links. `cashier_events.sale_id` was BIGINT UNSIGNED while `sales.id` was signed INT. The upgrade aligns that child column to the parent type/signedness after validating references; values remain identical. Other mismatches, missing/non-InnoDB tables, disabled FK checks, non-single-column parent primary keys and conflicting policies cause refusal before upgrade DDL. Equivalent restrictive single-column FKs keep their existing names. Native migration targets MySQL/MariaDB/InnoDB explicitly.

The SQL initializes guard variables before inspection and gates each DDL step on successful preflight. Final success and migration registration require all expected restrictive relationships. Repeated imports/native migrations do not add duplicates. DDL commits independently; a later error requires correction and rerun. The guide requires paused writes and a full SQL backup.

## Workflows and recovery

Normal shift services insert a shift after its drawer exists, update drawer pointers afterward, and preserve users/branches through status changes. Circular drawer/shift references therefore work without changing sale calculations or cash ownership.

The historical explicit shift reset now clears `handover_accepted_shift_id` before deleting shifts. It already cleared drawer pointers, detached preserved events, and deleted child actions/reviews first. Reset testing ran only in a disposable database, not the application database.

`DatabaseBackupService::restoreBackup()` validates all installed FK relationships before re-enabling checks and committing. Composite keys respect optional-NULL semantics. Invalid references roll back all replacement rows. Enabling MySQL FK checks alone does not scan previously loaded rows. [MySQL documentation](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html).

## Verification

- **43 isolated MySQL checks passed** in `tests/integration/cashier-foreign-keys.php`. It copies the local database into a guarded temporary schema and removes it afterward. Checks cover orphan/type/disabled-check/conflicting-policy refusal, unchanged rows on refused imports, existing FK names, repeated SQL/native upgrades, one registration, matching sale types, all 24 invalid-reference updates, restrictive deletes, optional NULLs, and real open/sale/cash-movement/close/handover operations.
- Backup round trips preserve circular handovers and composite/partially NULL references. Corrupted cashier/composite references cause full rollback with FK checks enabled. Repeated explicit resets preserve sales/unrelated records and retain all constraints.
- **62 focused PHP cases / 680 assertions passed**, covering cashier workflows, authentication, login history, administrator sessions, presence and relevant recovery checks. The broader selection exposed stale renderer/GET state in a presence-view fixture; it now resets both before rendering. The final run passed. The full application suite was not rerun.
- Changed/new PHP sources passed syntax checks. Committed SQL matches the deterministic generator `tools/database/build-cashier-foreign-keys.php`.

Evidence: `writable/cashier-fk-review/preflight.json`, `mysql-results.json`, `focused-junit.xml`, `local-upgrade-results.json` and `package-verification.json`. SQLite regression results are not presented as FK-engine evidence.

## Local application and delivery

Before local DDL, saved `writable/backups/pharxmaco-pre-cashier-fk-20261021-111346-e870c4.sql` with native mysqldump: 504,772 bytes, SHA-256 `c9ee5e0241acde1aac2f188998e150d79e400b77f4304ae446659d45fb39e2ac`. Temporary client credentials were removed; the backup is excluded from delivery.

Imported twice into local `pharxmaco_drugstore`. All rows in **25 existing non-migration tables** retained identical hashes; previous migration rows were preserved. Migration 000017 is registered once and all 24 added constraints use RESTRICT for update/delete. No users/passwords, stock, sales, shifts, events, login or presence values changed. No local reset ran.

[phpMyAdmin/upload guide](../upgrades/2026-10-21-cashier-foreign-keys.md). The hosting ZIP contains three runtime files, the additive SQL, README and hash manifest. Hosting was not changed automatically. Earlier dated reviews retain their historical schema findings; this note records later enforcement.
