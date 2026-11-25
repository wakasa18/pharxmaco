---
title: Latest existing-database upgrade for phpMyAdmin export 8
type: database-review
status: completed
updated: 2026-10-21
tags: [database, phpmyadmin, foreign-keys, verification]
---
# Latest existing-database upgrade for phpMyAdmin export 8

The user supplied `u315645644_pharxmacotest (8).sql` as the phpMyAdmin database following the FK implementation and asked to continue. Prepared a targeted existing-database import verified against that export. The original download was not edited and neither the hosted nor local application database was overwritten.

## Baseline and scope

The export was generated on 21 October 2026 from MariaDB 11.8.9. It contains 25 application tables and 19 historical migration registrations, including cashier workspace 000014, login ownership 000015 and presence 000016. It lacks FK migration 000017. The existing cashier tables have indexes and unique ownership keys but no FK constraints.

The data contains 1,079 sales, 1,631 sale items, 69 products, 119 branch-product records, 8 users, 3 shifts, 4 cashier events, 1,930 stock logs, one login lease and two presence records. All 24 proposed cashier/login/presence references have valid parents. No data repair is needed for this upgrade.

Application table columns were compared against the local schema. The only difference was the unused legacy `sales.discount_snapshot` column in local development. Current code no longer reads/writes that field, as recorded in the [discount deployment guide](../upgrades/2026-09-20-discount-deployment.md). It was correctly excluded from required columns and not added to the export.

`database/upgrades/2026-10-21-latest-existing-database.sql` contains export provenance plus the same deterministic update 000017 as the generic cashier FK import. It adds 24 restrictive FKs, aligns the event's sale reference type to `sales.id`, and registers 000017 once. No source rows are reinserted, deleted, detached, repriced, restocked or renamed. Existing optional NULL relationships remain allowed.

## Verification

`writable/export8-fk-review/verify.php` checks the source hash, parses quoted values/comments as data and accepts only the known export statement types, table names and session settings before importing into a guarded disposable schema. Cross-database identifiers and server/file/routine operations are rejected. No instructions from SQL comments are followed.

**Eleven checks passed on local MySQL 8.2**:

1. The complete supplied export imported successfully.
2. All 25 application table column sets match current requirements, excluding the retired local snapshot column.
3. The baseline includes 000016 and lacks 000017.
4. All 24 cashier references have valid parents.
5. The first upgrade preserves hashes of every row in all 24 non-migration tables.
6. One migration row is added; all 19 previous registrations remain unchanged.
7. All 24 cashier FKs use RESTRICT for update/delete.
8. The event sale reference matches the actual parent type and signedness.
9. Reimport changes no rows or migration history.
10. Importing the generic patch after the export-specific file also changes no rows.
11. The source download retains its original hash.

The temporary database was removed. No hosted MariaDB execution or additional application/browser regression run was performed for this file-only preparation. See the [FK implementation review](2026-10-21-cashier-foreign-keys.md) for the separate 43 MySQL and 62 PHP cases and the earlier local schema application.

Evidence: `writable/export8-fk-review/verification-results.json` includes table counts/hashes, reference counts, FK definitions and file hashes. Source SHA-256: `817c0021bacd63215125a5ff791a43d55ab858c26d4b8320d46fbb07712ca8b9`.

[Import instructions](../upgrades/2026-10-21-latest-existing-database.md). Use the export-specific SQL or the generic FK SQL, not two separate required migrations. Matching application files remain in the previously prepared `build/hostinger-cashier-foreign-keys-2026-10-21.zip`.
