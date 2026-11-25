---
title: "Checkout, cashier reporting and stock repairs — 16 October 2026"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "build/cashier-workspace-2026-10-15/docs/reviews/2026-10-16-checkout-fixes.md"
source_sha256: 3eafaeaf92f734f844d1f41748500012379f327bd1d5ae595a0a07ac257696ef
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `build/cashier-workspace-2026-10-15/docs/reviews/2026-10-16-checkout-fixes.md`. Findings and test counts refer to the original work. Read [Current state](../../../../../project/Current-State.md) and [Known issues](../../../../../project/Known-Issues.md) for subsequent changes.

# Checkout, cashier reporting and stock repairs — 16 October 2026

## Changes

The [shift printing update](../../../../../reviews/2026-10-16-shift-print-update.md) is now included in the refreshed application archives, with a compact summary and complete activity appendix.

- Recovering an old completed checkout preserves the next customer's cart and checkout token. Recovery still opens the existing invoice when the old cart is already empty.
- HTTP 5xx/408 responses and malformed checkout replies now trigger a saved-sale lookup. An unavailable, malformed or incomplete status reply retains recovery information and blocks Complete Sale. Only a confirmed saved invoice or an authoritative not-found reply releases recovery. A temporarily unavailable recovery schema cannot invite a duplicate payment.
- Quantity corrections now record product movements even if the amount and payment method stay unchanged. My Day retains original sold quantities, shows the quantity adjustments and recalculates product amounts without changing drawer cash.
- PHP and JavaScript compare Senior/PWD and promo combinations using integer centavos and five statutory rounding remainder states. The two-item VAT and Non-VAT examples now charge PHP 160.02. Equal total savings retain as many statutory lines as possible. Promotions and statutory benefits never stack on the same line.

## Local data repair

Nine derived `products.stock` totals were rebuilt from existing branch balances. There are now zero mismatches. Product IDs: 2, 4, 7, 8, 10, 11, 12, 14 and 15. Branch quantities, prices, tax classifications, timestamps and sale records were preserved.

The original product rows, branch balances and before/after values are saved under `writable/backups/stock-reconciliation-20261016-033828-5ec0835c.json`. An activity log contains the repair details and backup checksum. The importable stock SQL was rerun against the reconciled local database and made no further changes or audit entries.

The owner confirmed that sale #41's refunds were test records. Its six refunds were corrected from PHP 625.00 to PHP 500.00, matching the saved net invoice total. The PHP 125.00 excess is now zero. Original rows are retained in `phx_backup_20261016_sale41_refunds` and `writable/backups/sale41-refunds-20261016-110240-e8c7a040.json`; an activity log records the correction. Refund quantities, dates, reasons, sale items, invoice totals, stock and cashier events were preserved. The separate [refund repair guide](../../../../../upgrades/2026-10-16-reconcile-sale41-refunds.md) documents the matching phpMyAdmin import.

## Validation

- Six targeted normal-suite tests passed with 838 assertions, including saved invoices for both beneficiary types and VAT/Non-VAT centavo cases, stale checkout recovery and equal-total corrections. The independent pricing oracle enumerates all promo/statutory subsets for 96 small baskets.
- The three original fault reproductions now pass as post-fix regression checks (15 assertions); original observation JSON is retained.
- JavaScript discount and export checks passed; 144 combined cases compare PHP and JavaScript values and line allocations.
- Browser checkout checks: 21 passed, including recovered committed sales, unavailable/malformed status responses, explicit not-found retries, migration errors and renewed status checks. Combined promo/receipt/Sale Details checks: 42 passed. The two original browser fault cases also passed as regressions.
- All four changed PHP runtime files and the changed JavaScript parsed successfully.
- The exact refund SQL was applied to the local test records. All six corrected amounts/unit prices and per-item net totals were verified, unrelated records stayed unchanged, and reimport produced no additional changes or audit entries.
- Full suite: 222 tests, 23,221 assertions; 210 passed, 9 failures and 3 errors. These are the same pre-existing inventory/expiry safeguards from the earlier audit. This patch does not resolve those separate findings, duplicate legacy receiving or digital-payment validation.

Tests use isolated SQLite and stubbed browser requests. Actual payment settlement, MySQL concurrency scheduling and physical inventory were not tested.

## Hosting installation

1. Back up your current application files and database. Pause checkout while replacing the application files.
2. Extract `build/hostinger-checkout-fixes-2026-10-16.zip` into the existing project root, preserving the included `app/` and `public/` paths. It includes the recent automatic product/category promo runtime files and this checkout/cashier patch. Your `.env`, live `app/Config/ReceiptInvoice.php`, uploads and writable data are excluded.
3. Reload the POS page so its script uses the new cache version. Verify with a small test transaction in your demo database.
4. For hosted stock discrepancies, select the existing database in phpMyAdmin and import `database/upgrades/2026-10-16-reconcile-master-stock.sql` while checkout and stock receiving are paused. Keep its before/after result with your backup and stop on any SQL error. This file recalculates derived totals and writes an audit record; it does not change branch balances or refund amounts. The code fixes require no new schema migration.

5. For the same confirmed test refunds on hosting, import `database/upgrades/2026-10-16-reconcile-sale41-refunds.sql` separately after reading its companion guide. It targets the exact invoice and refund rows, creates a backup table and records the correction. Its final result should show PHP 500.00 refunded and PHP 0.00 excess.

The full cashier workspace archive is also refreshed. The separate October 15 schema/catalog/demo SQL imports retain their existing purpose and do not automatically alter historical refunds.
