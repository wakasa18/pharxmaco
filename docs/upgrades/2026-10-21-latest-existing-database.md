---
title: "Existing phpMyAdmin database update — 21 October 2026"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-21-latest-existing-database.md"
source_sha256: 029e64c85d27dc25606c08370f7e4900287a6552aed9801b5b058d4395c6461c
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-21-latest-existing-database.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Existing phpMyAdmin database update — 21 October 2026

Prepared against **`u315645644_pharxmacotest (8).sql`**. This file upgrades the existing database; it does not restore the supplied export over it.

## Import

**If your status check already shows 24 cashier FKs and migration 000017 = 0, use `2026-10-21-finish-cashier-foreign-keys.sql` instead of this full upgrade.** It verifies the installed keys and records completion without rerunning ALTER statements. See [completion instructions](2026-10-21-finish-cashier-foreign-keys.md).

1. Keep the supplied full export as a backup and pause application writes.
2. Use the matching application files in `build/hostinger-cashier-foreign-keys-2026-10-21.zip` if they have not been uploaded yet. Preserve the `app` directory paths.
3. In phpMyAdmin, select **`u315645644_pharxmacotest`** or the actual existing application database used on your host. Open **Import** and select **`2026-10-21-latest-existing-database.sql`**. Import the entire file, stopping on any SQL error.
4. Confirm **Preflight passed**, `orphan_references = 0`, and the final message **Cashier foreign-key upgrade complete: 24 relationships enforced; existing records preserved**.
5. Resume normal use and create a new application backup against the updated schema.

The supplied export already contains updates **000014, 000015 and 000016**, including login ownership and presence. The only new migration needed is **000017**, enforcing 24 cashier/workspace/login/presence references. It also aligns `cashier_events.sale_id` to the existing signed `sales.id` type without changing its values. Existing optional NULL references remain valid. Every new FK has restrictive update/delete rules.

This file and `2026-10-21-cashier-foreign-keys.sql` perform the same upgrade. **Use either one**, not both as separate required updates. Repeating either is safe; verification confirmed that a second import does not change records or add duplicate migration history. If an error occurs partway through DDL, fix the cause before rerunning. [Detailed FK guide](2026-10-21-cashier-foreign-keys.md).

## What was verified

The export contains **25 tables**, with **1,079 sales**, **1,631 sale items**, **69 products**, **119 branch-product records**, **8 users**, **3 shifts**, **4 cashier events** and **1,930 stock logs**. It also contains one login lease and two presence records. All existing row values are preserved by the update; no demo records, fabricated history, stock adjustments, password resets or shift clearing are included.

Eleven checks passed in an isolated local **MySQL 8.2** database: complete source import, required application columns, migration baseline, all 24 orphan checks, first import with hashes of all 24 non-migration tables preserved, existing migration preservation, 24 restrictive FKs, matching sale-reference types, repeat import, interchangeability with the generic FK patch, and unchanged source file. The source was exported by **MariaDB 11.8.9**; this verification did not connect to or change that hosted server.

The only extra column found in the local development schema was the retired, unused `sales.discount_snapshot`. Current application code does not need it, so this import does not add it. Existing migrations and columns are not removed.

No local application database was overwritten or changed during this export verification. Temporary verification databases were removed.

Source SHA-256: `817c0021bacd63215125a5ff791a43d55ab858c26d4b8320d46fbb07712ca8b9`.
