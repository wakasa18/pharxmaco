---
title: "Clear cashier shifts — October 16, 2026"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-16-clear-cashier-shifts.md"
source_sha256: ac751e76577de1868dd84e77485b18b5b73ac0e17d741422ab98907b0335bf22
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-16-clear-cashier-shifts.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Clear cashier shifts — October 16, 2026

For your **existing database**, import `2026-10-16-clear-cashier-shifts.sql` in phpMyAdmin. This is a deliberate history reset, not a routine schema upgrade.

1. Export a backup, pause POS transactions and have cashiers sign out.
2. Select your application database in phpMyAdmin, open **Import**, choose the SQL file and import the entire file. Stop on any SQL error.
3. Check that `shifts_remaining`, `assigned_drawers_remaining` and `events_with_shift_remaining` are all **0**.
4. Sign in again and start a new shift using the actual counted opening cash.

Compatibility update, 21 October 2026: the SQL also clears `handover_accepted_shift_id` before deleting shifts, so the deliberate reset works with FK upgrade 000017. This reset is not part of installing foreign keys and was not run on the local application database during that upgrade.

The file deletes every open/closed cashier shift, its action history and its shift review notes. Drawer assignments and previous-shift links are cleared. Existing payment events are kept with their `shift_id` set to `NULL`, so sales and daily payment/product totals remain available without attaching them to a new shift. Products, inventory, sales, refund records, invoices, accounts, held carts, event reviews, general activity logs and migrations are retained. IDs continue from their previous sequences; old shift IDs are not reused. Importing again also clears shifts created since the last import.

The supplied `u315645644_pharxmacotest (6).sql` contains **2 shifts** (one open and one closed), **3 shift actions**, **0 shift reviews**, **780 sales**, **1,330 sale items** and **66 products**.

A cleaned **full backup** is also available at `database/exports/u315645644_pharxmacotest-without-cashier-shifts-2026-10-16.sql`. Restore that file into an **empty database**. Use the smaller reset file above for a populated database; do not import the full backup over existing tables.

Both files were verified against isolated MySQL 8.2 databases. The reset was imported twice, the cleaned backup restored to exactly the same data, all unrelated table fields were compared, daily totals were checked, and a fresh shift opened without old cash or a reused ID. The source download and local application database were not modified.

Source SHA-256: `34bcab5ab03ff7aa542e39afe6d5be400fe2c918081988bee0a10515c46fc4cc`.

Cleaned backup SHA-256: `9c58f73341bbc5f77d70a471e4d1c8df18f2b3a77bd0f90f48edc524655b56c0`.
