---
title: Cashier FK import blank page and completion recovery
type: repair-review
status: prepared
updated: 2026-10-21
tags: [database, phpmyadmin, foreign-keys, recovery, testing]
---
# Cashier FK import blank page and completion recovery

The user reported a blank phpMyAdmin import page after waiting several minutes. A first screenshot showed `migration_000017 = 0`, an apparent zero FK count, and no import query in the displayed process list. That connection was using `information_schema`; the count used `DATABASE()`, so it could not establish the application schema's FK state. A subsequent query with explicit schema names confirmed **24 cashier FKs and no migration 000017 record**.

## Recovery

Prepared `database/upgrades/2026-10-21-finish-cashier-foreign-keys.sql` for that confirmed state. It explicitly targets `u315645644_pharxmacotest`, verifies all expected column/parent relationships and restrictive actions using a single grouped metadata query, and records the missing migration only if every expected definition matches. It performs no ALTER, DROP, DELETE or business-record updates. Repeating it preserves all rows. Wrong or incomplete definitions produce NOT COMPLETE without a registration.

The accompanying read-only status SQL also names the schema explicitly, so navigation to `information_schema` cannot produce a misleading zero from `DATABASE()`. The user should import the completion-only file for the 24/0 state, rather than rerun the full upgrade. [Completion instructions](../upgrades/2026-10-21-finish-cashier-foreign-keys.md).

This preparation does not prove why the original HTTP request ended. The keys were installed but registration was not completed. No further hosted write or recovery success is claimed until the user imports the completion file and sees verified_foreign_keys=24, migration_000017=1 and Upgrade complete.

## Full import improvements

The original full import performed repeated information_schema joins and correlated subqueries. `CashierForeignKeys.php` now reads metadata into four session JSON caches per pass; compatibility checks use those caches. FK additions are grouped by table into at most nine ALTER statements, including the sale-reference type adjustment. Equivalent existing constraints and partial-state resume remain supported. Metadata-lock waits are limited to ten seconds and restored afterward; no global server settings change. Snapshot comparisons use explicit UTF-8/binary conversions to avoid MySQL metadata-collation and JSON character-set ambiguities.

Both generated full imports were refreshed. The native migration retains preflight refusal, no data repair, final definition verification and protection against duplicate registration. No schema change was reapplied to the local application database for this revision. Future full imports still require the intended database to be selected before starting; the completion/status files do not depend on that selection.

MariaDB can wait for a metadata lock during ALTER until `lock_wait_timeout`; a short session limit makes that failure visible sooner. [MariaDB metadata-lock documentation](https://mariadb.com/docs/server/reference/sql-statements/transactions/metadata-locking). The screenshot did not establish that a metadata lock caused this particular blank page.

## Verification

- **49 isolated MySQL checks passed**, including the earlier 43 checks plus six recovery cases: registration-only completion, repeat no-op, explicit target while information_schema is selected, refusal at 23 constraints, native partial resume, and refusal when a 24-count schema contains a cascade action.
- **62 focused PHP cases / 680 assertions passed** after the helper revision.
- **11 export-specific checks passed again** against the supplied `(8).sql` in a disposable database. All 24 data-table hashes and original migration rows were preserved on first/repeat optimized imports.
- Generic SQL matches its generator; completion/status SQL are generated from the same FK manifest. Runtime PHP syntax and archive checks passed. All database execution for this revision used isolated/local MySQL 8.2, not hosted MariaDB.

Evidence remains in `writable/cashier-fk-review/mysql-results.json`, `focused-junit.xml`, `package-verification.json` and `writable/export8-fk-review/verification-results.json`. The application/host data were not modified and no ongoing hosted query was killed.
