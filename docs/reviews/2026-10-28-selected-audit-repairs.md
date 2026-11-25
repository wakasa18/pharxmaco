---
title: Selected POS inventory and report audit repairs
type: implementation-review
status: verified-locally
updated: 2026-10-28
tags: [repairs, pos, inventory, reporting, security]
---
# Selected POS inventory and report audit repairs

Implemented the owner's selected findings **A02, A03, A04, A06, A08, A09, A10, A11, A13 and A15** from the [27 October audit](2026-10-27-whole-system-audit.md). Hosting is unchanged. Existing application transactions, quantities and historical delivery/expiry snapshots were not rewritten.

## Resulting behavior

| Finding | Repair |
| --- | --- |
| A02 | Refunds reject noncanonical or duplicate item IDs. After locking the sale, current refund rows are locked and aggregated in cents; both item quantity/net balances and the invoice's remaining total cap the new payout. Concurrent requests cannot reuse an earlier remaining balance. |
| A03 | Resellable refunds, exchange returns and quantity corrections use the original recorded delivery and expiry. Missing, expired or mismatched source data blocks a return to available stock. Quarantined, Damaged and Expired returns remain recordable without restocking. The refund form explains an unavailable Resellable choice. |
| A04 | Creating/assigning inventory and receiving/replacing/increasing stock assigns a new internal delivery identity. Replacement rotates identity even if quantity and expiry match the old record. Removal/decreases preserve identity. New POS/exchange items keep their actual snapshots; historical rows are not backfilled. |
| A06 | The unused legacy receive payload is rejected. Modern Add/Remove use one-time session tokens and locked old counts; absolute Count uses its locked old count. A retry makes no second balance or log change. |
| A08 | Product/category offers are computed on the server for default/blank selection and Senior/PWD, independent of hidden flags. Supplied previews must agree, and current locked offer rules are checked again before commit. Missing preview fields cannot turn off automatic pricing. Legacy product/category selections cannot suppress other matching offers. |
| A09 | Exchange replacements use the POS promotion engine, show and save offer details, and preserve existing fixed/capped/minimum allowances across the exchange lineage. The return credit stays based on original paid value. A changed delivery, expiry, quantity or promo requires a fresh review. |
| A10 | GCash/Card require exact final tender and a nonempty text reference, with zero cash change. Invalid decimal/structured inputs are rejected. The browser requires the reference and recalculates its read-only exact amount as quantities or benefits change. Cash still accepts tender above the total and returns change. |
| A11 | Reports recognize a Released outcome for an originally Quarantined return as recovered inventory cost once. Totals, grouped history and CSV/PDF share that calculation. Damaged/Expired/pending returns retain their cost loss; original refunds and sale items remain unchanged. |
| A13 | Sales CSV has its own filename and completes byte generation before recording export history. Failed generation creates no success record or retry receipt. The response uses attachment, no-store and nosniff headers. |
| A15 | Full PDF uses sales_report_; Summary PDF uses sales_summary_. Both generate their bytes before history insertion. History confirms generation, not that a browser saved the file. |

Product/category promos remain automatic. Explicit all-product discounts remain manual and replace automatic offers rather than stacking. Senior/PWD retains the owner's favorable-treatment-per-item rule. Statutory exchanges still use a refund and new POS sale so beneficiary/eligibility can be checked again.

## Data and accounting boundaries

The inventory model still holds one current quantity/expiry per product per branch. Adding a new delivery conservatively invalidates older returns against the combined balance; this update does not introduce concurrent manufacturer batches or FEFO. Unknown historical provenance is preserved and cannot be inferred from current catalog data. A refund can still be recorded under a nonresellable condition.

Quarantine cost recovery follows the existing **original sale-date cohort with current return outcomes**. A later inspection can therefore restate the derived cost/profit for an earlier sale period; it does not create an inspection-date accounting journal. The report and exports now state this basis. A missing quarantine table retains legacy resellable-only reporting; released outcomes require the existing 000018 quarantine schema.

No new SQL or migration is required for the current matching application/schema. The earlier local data-health counts remain historical; this repair did not reconcile or change application data.

## Verification

- New focused repair tests: **51 cases / 450 assertions passed**.
- Full suite: **382 tests / 25,112 assertions; 379 pass, 2 failures and 1 error**. The three unsuccessful cases are the explicitly unselected A05 expiry-edit/correction issue. They are retained as failing safeguards rather than hidden or weakened.
- Older test fixtures now use real recorded delivery snapshots for valid corrections, valid category records, guarded Add stock and mandatory automatic pricing. Clock tests reset their shared response/filter state; production clock code was not changed.
- Two real MySQL 8.2/InnoDB REPEATABLE-READ races passed using synthetic records and separate workers: refund 3 of 5 concurrently commits once (3 units/PHP 30); Add 5 to stock 10 concurrently increments once (15 units/one log). The guarded disposable schema was removed; application records were not accessed or copied.
- Twenty desktop/mobile headless-browser payment checks passed with no JavaScript exceptions. Required references, exact tender, quantity/benefit updates, cash switching and screenshots use rendered synthetic fixtures.
- Actual framework routes/admin filters returned CSV, Full PDF and Summary PDF attachments and rejected guests using SQLite/mock sessions. A forced CSV generation failure created no history, and a retry succeeded.
- Full PDF (three pages) and Summary PDF (one page) were rendered and visually reviewed. Recovered cost PHP 12/profit PHP 18, labels, page identity and text bounds were verified.
- Runtime syntax checks and the archive's per-file SHA-256 verification are retained with the evidence below.

Evidence: `writable/repairs-20261028/selected-repairs.xml`, `full-suite.xml`, `checkout/browser-results.json`, `inventory/mysql-concurrency.json`, `reports/pdf-results.json`, `syntax.json`, `summary.json` and `package-verification.json`. New traits: InventoryRepairCases, ExchangeRepairCases, CheckoutRepairCases and ReportRepairCases.

## Remaining scope

The owner did not select A01 public/private deployment exposure, A05 expiry editing, A07 dependency upgrades, A12 expired-stock reorder calculation or A14 bcrypt input length. Those remain open in [Known issues](../project/Known-Issues.md). This is not a clean whole-system or hosted-deployment sign-off.

[Hosting instructions](../development/Selected-Audit-Repairs-Deployment.md) · [Inventory](../system/Inventory-and-Expiry.md) · [POS](../system/POS-and-Discounts.md) · [Reports](../system/Reports-and-Forecasts.md).

Upload package: `build/hostinger-selected-audit-repairs-2026-10-28.zip`, 130,647 bytes, 13 changed plus 11 current dependency runtime files. SHA-256: `25df9aff856971ad8c433e0e85fb2e6bf872d37705209a81ea597654857d4a02`. All 26 ZIP entries (24 runtime, guide and manifest) were checked.
