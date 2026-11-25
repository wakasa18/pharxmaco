---
title: Quarantine inspection page
type: implementation-review
status: implemented-locally
updated: 2026-10-23
tags: [inventory, quarantine, returns, audit]
---
# Quarantine inspection page

## Behavior

Administrators have **Inventory → Quarantine** at `admin/quarantine`. Pending inspections are the default. Branch and outcome filters query the database; DataTables provides loaded-list search, sorting, page length and pagination. Existing quarantined refund lines are listed automatically, including exchange returns, with product/SKU, branch, quantity, invoice and return date. Separate inspection and read-only outcome pages avoid treating invoice numbers as action controls.

An administrator resolves the entire returned line as Released to stock, Damaged or Expired, with a required note and physical inspection confirmation. The outcome is final through this interface. It stores the inspector, timestamp, quantity, reason and source snapshot in a new `quarantine_resolutions` audit table, uniquely linked to its refund line. Three RESTRICT foreign keys retain the original refund, inspector and release log.

The refund's original `return_condition`, quantity, payment and amount are unchanged. Sales/refund reports and printed slips continue describing the original return event. The Quarantine page describes the later inspection; no historical cash event is fabricated.

## Stock safeguards

- Release requires known original delivery/expiry matching the current branch inventory, a valid non-expired recorded date under existing stock rules, and active product/branch/inventory records without deletion markers.
- Unknown/mismatched legacy sources cannot be released. There is no inference or backfill from today's inventory. Damaged/Expired remain available without stock movement, including when current branch inventory is absent.
- Delivery and expiry snapshots are now saved for new POS sale lines and exchange replacement lines; `SaleItemModel` permits those existing columns. Broader resellable-refund/exchange/correction provenance problems remain open.
- A server-issued, two-hour review token ties the POST to the reviewed return/revision. Multiple review tabs are supported within a bounded session draft list. Validation errors preserve the note and require physical confirmation again.
- Refund row locks serialize competing inspectors. A unique refund reference provides durable duplicate protection. An already completed inspection returns its saved outcome without moving stock again, even if a retried request asks for a different outcome.
- Release locks branch inventory and product records, increments the original branch, recomputes the master from current locked balances, and creates one `returned` stock log. The inspection and activity log commit in the same transaction. Failure rolls everything back.
- Damaged/Expired do not subtract stock again, since quarantined units were excluded from available stock at return time. These outcomes classify the inspection and do not represent physical destruction or supplier credit.
- Existing global CSRF protection covers inspection POSTs. The admin route filter revalidates the account; the service also requires an active administrator in the database. GETs do not resolve anything.

## Installation and local database

New native migration: `app/Database/Migrations/2026_10_23_000018_add_quarantine_resolutions.php`. Native migration and manual SQL adapt the three reference column types to existing parents. Manual SQL verifies links and a single-column unique refund key before registering 000018. DDL is additive and automatically commits; no business records are edited by the import.

Import and hosting instructions: [Quarantine upgrade guide](../upgrades/2026-10-23-quarantine.md). Hosting ZIP: `build/hostinger-quarantine-2026-10-23.zip`; runtime paths and SHA-256 manifest are inside. No credentials, backups, demo data or reset scripts are packaged. This is an incremental update over the current application.

Local `pharxmaco_drugstore` was backed up to `writable/backups/pharxmaco-pre-quarantine-20261023-094611-253551.sql` before importing twice. Backup size: 511,907 bytes; SHA-256: `b6c9438a99e1c9afee9f068bf21813c33d9ec2b3943954ce4baa40b3b008a63d`. All 25 existing non-migration table hashes and prior migration rows are preserved. The new audit table is empty and migration 000018 is recorded once. Three original quarantined returns remain pending. No application stock, sale, refund, shift or user record was edited, and no hosted deployment/import occurred.

## Verification

| Check | Result and boundary |
| --- | --- |
| Focused PHP/controller cases | 15 cases / 189 assertions pass. Covers release, no second deduction, stock/master/log effects, unchanged financial history, stale/invalid/repeated forms, authorization, missing setup and related checkout/exchange regressions. |
| Real MySQL integration | 18 checks pass in a disposable copy. Full import/reimport, native/manual migration interchange, key signedness, three restrictive FKs, financial preservation, master balance, damaged/expired, audit rollback and four concurrent release processes. Exactly one process commits an outcome/increment. |
| Browser | 59 scenarios pass using actual rendered fixtures/assets and headless Chrome. DataTables controls; branch/status form; required confirmation; unknown-source release disabled; completed read-only state; empty/setup states; 320/390/768/1440 widths in light/dark; no page overflow or JavaScript exceptions. Desktop/mobile images visually inspected. No real inspection submitted. |
| Syntax | All 12 changed/new PHP runtime and test files parse. |
| Broad business regression | 248 cases / 23,253 assertions: 237 pass, 9 fail, 2 error. Not a clean whole-system audit. Remaining failures/errors concern existing resellable refund/exchange delivery checks, manual replenishment, quantity correction and expiry editing/correction. |

Commands:

```powershell
$env:PHX_QUARANTINE_FIXTURES='1'
& 'C:\UniServerZ\core\php83\php.exe' -d extension=sqlite3 vendor\bin\phpunit tests\unit\BusinessLogicTest.php --filter '::testQuarantine|testCheckoutRecordsLockedDelivery|testCashierCheckout|testAjaxCheckout|testCashierExchange|testExchangeCommitsLinked|testExchangeCheaperReplacement' --no-coverage
& 'C:\UniServerZ\core\php83\php.exe' tests\integration\quarantine.php
node writable\quarantine-review\check.cjs
```

Evidence: `writable/quarantine-review/focused-junit.xml`, `mysql-results.json`, `browser-results.json`, `business-junit.xml`, `local-upgrade-results.json`, screenshots and package verification. Only the explicit local apply script writes additive application schema; unit/browser/integration tests do not write application business data.

## Boundaries

Whole-line, single final outcomes only. No partial disposition, reversal, new delivery creation, multi-lot/FEFO allocation, manufacturer batch tracking, physical disposal certificate, supplier return credit or dispensing approval is implemented. Existing expiry-date semantics accept the current date; release uses the same rule. A missing source record requires a separate data review, not a guessed stock restoration. Other open inventory safeguards are listed in [Known issues](../project/Known-Issues.md).
