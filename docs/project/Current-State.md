---
title: Current system state
type: checkpoint
status: current
updated: 2026-10-29
tags: [project-memory, status]
---
# Current system state

Latest POS interface fix: **29 October 2026 shift banner alignment**. The shift icon now participates in the message row instead of being pinned to the banner's top edge. The action moves below the message on phones. 101 browser checks pass across five widths, both states and both themes; the original 11-pixel offset is now zero. No SQL/data/hosting changes. [Verification and upload](../reviews/2026-10-29-pos-shift-banner-alignment.md).

Latest interface fix: **29 October 2026 floating spacer**. A shared `.sp` class collision styled ordinary empty spacers as fixed dropdown shells after refresh. The dropdown root is now `phx-select-popup`; matching CSS/JS and layout asset versions changed. The exact 160 × 14 pixel artifact was reproduced before the fix, and 42 browser checks pass after it. No SQL/data/hosting changes. [Verification and upload](../reviews/2026-10-29-floating-spacer-fix.md).

Latest checkpoint: **29 October 2026 product table background filters**. Manufacturer, Stock, Medicine type and Apply/Clear update table rows without navigating the workspace. Search/sort/column controls, branch scope and stock drafts are retained; failed or superseded requests cannot erase the current results. Ten PHP cases / 68 assertions and 270 desktop/tablet/mobile browser checks pass. Full 435 / 25,493 retains the same three A05 issues. No SQL/data/hosting changes. [Implementation and upload](../reviews/2026-10-29-product-background-filters.md).

Earlier on 29 October, POS catalog, payments and reorder planning were updated. POS shows its selling branch, ingredient-aware branded search and clear/reset without a hidden server keyword. Local Main Branch has 12 eligible branded products; Patag's branded stock is expired/unassigned, so records were preserved. Product reorder suggestions/calculations/exports are removed; sales forecasts and threshold alerts remain. GCash uses the supported 13-digit confirmation ID and cards use provider receipt references. 27 focused cases / 216 assertions and 120 browser checks pass. Full 425 / 25,425 retains only the same three A05 issues; report PDFs and the updated 37-page manual were checked. No SQL/data/hosting changes. [Implementation and upload](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md).

Earlier on 28 October, expiry calendar bounds were repaired. Existing valid dates retain their input bounds before interaction. The themed calendar reads server range metadata, so years/months/days and navigation are limited before any selection; opening a legacy date does not rewrite it. 289 actual calendar/browser checks and 25 focused PHP cases / 320 assertions pass. The earlier A05 expiry-correction issues remain open. No SQL, application records or hosting changed. [Calendar repair and upload](../reviews/2026-10-28-expiry-calendar-bounds.md).

Earlier on 28 October, the expiry override was removed. The owner removed administrator review. New/changed dates must be after today and no later than December 31 of the Philippine year plus ten (2036 this year), for every account. Existing dates left unchanged during editing are retained; replacement always validates its incoming date. No SQL, application records or hosting changed. 16 focused cases / 98 assertions and 116 desktop/mobile browser checks pass. Full suite: 398 cases / 25,210 assertions, 395 pass. The three existing A05 expiry-correction test issues remain. [Implementation and current upload](../reviews/2026-10-28-expiry-limit-only.md).

Earlier on 28 October, selected audit repairs were completed. A02/A03/A04/A06/A08/A09/A10/A11/A13/A15 are implemented locally across 13 runtime files, with 24 runtime files in the dependency-complete hosting package. Fifty-one focused cases / 450 assertions, 20 desktop/mobile browser checks and two real MySQL races pass. Full suite: 382 cases / 25,112 assertions; 379 pass and the three remaining failures/errors are unselected A05 expiry editing. No new SQL, historical data or hosted changes. [Repair and limits](../reviews/2026-10-28-selected-audit-repairs.md); [open issues](Known-Issues.md).

The preceding checkpoint is the **27 October 2026 whole-system audit**. [Full report](../reviews/2026-10-27-whole-system-audit.md) compares Odoo/ERPNext/Square and records confirmed local file exposure, refund duplication, unsafe receiving/restocking, component advisories, pricing and reporting bugs. The 331-case suite has 10 failures and 2 errors, including one clock test that passes alone. Current local balances/FKs are clean in a read-only health check, but historical source snapshots are mostly missing. No runtime/data/host repair was made. Read [Known issues](Known-Issues.md) before claiming readiness.

The preceding checkpoint was **23 October 2026**. Latest work applies the owner-selected expiry cutoff: items expiring today are Expired and blocked from sale, using the server Philippine date. POS/exchanges, held carts, resellable returns, quarantine release, labels and expiry reporting agree. No SQL/data/hosting change; 38 focused tests / 397 assertions pass, with the same 11 known broader inventory failures/errors retained. [Expiry update](../reviews/2026-10-23-expiry-date-cutoff.md). The preceding work fixes complete Stock Logs PDF memory exhaustion: all 1,087 selected records download at a 128 MB limit with a 28 MiB peak; a 10,000-record stress test uses 44 MiB. [Repair](../reviews/2026-10-23-stock-log-pdf-memory-fix.md). Sales CSV failure and the Summary PDF filename remain open from the [export audit](../reviews/2026-10-23-export-functionality-audit.md). Transaction confirmation overviews and cashier visibility for Quarantine/Damaged Items remain implemented. The earlier quarantine audit-table migration remains recorded below. Verification scope is stated per change; this is not a clean whole-system audit or a hosted deployment verification.

Administrators have **Inventory → Quarantine**, with existing pending returns, DataTables controls, branch/status filters and final Released/Damaged/Expired outcomes. Release verifies source delivery/expiry, updates original-branch/master stock once and creates its audit/stock log atomically. Damaged/Expired leave available stock unchanged. Migration 000018 is applied locally after backup, preserving all 25 existing data-table hashes and prior migrations; three original returns remain pending. Hosting update/import prepared. Focused checks pass; the broader 248-case run still has nine failures and two errors in other inventory safeguards. [Implementation and limitations](../reviews/2026-10-23-quarantine-page.md).

Sales History now uses one database date range with Apply/Clear, a quiet record-limit note and loaded-only quick search. Five PHP tests / 34 assertions and 22 browser scenarios passed; the existing 1,000-record cap remains. A six-file upload package is ready. [Update record](../reviews/2026-10-21-sales-history-filters.md).

## Implemented workflows

Refunds and sale corrections now show confirmation overviews before their existing POSTs, including exact amounts, before/after changes and planned stock effects. The existing server exchange quote is presented as **Exchange overview**. Back to edit preserves entries; changed payloads require another acknowledgement and repeated confirmation cannot post twice. Refund preview uses exact remaining net cents. Twenty PHP cases / 179 assertions and 40 browser scenarios pass, with three fixture-only POSTs. No SQL or application data change. [Review](../reviews/2026-10-23-transaction-overviews.md).

Cashiers now see **Quarantine** and **Damaged Items** in Inventory. They can view their current assigned branch's lists, details and completed inspection outcomes; branches cannot be changed via filters/IDs. Administrators retain all-branch access and quarantine approval/release. Twenty PHP cases / 235 assertions, 27 read-only MySQL access checks and 74 browser scenarios pass; existing quarantine/damaged MySQL checks also pass. No new SQL or data changes; hosting update prepared. [Current access review](../reviews/2026-10-23-cashier-return-pages.md).

Administrators also have **Inventory → Damaged Items**, combining original Damaged refund/exchange lines with Quarantined returns later marked damaged. Source/branch filters, DataTables, quantities and read-only details trace the original sale, exact return slip and inspection. No new SQL or data write; existing update 000018 enables inspection sources. Fifteen focused PHP cases / 182 assertions, seven read-only MySQL checks and 61 browser checks pass. All 27 local table hashes preserved; one existing damaged return is visible. [Review](../reviews/2026-10-23-damaged-items-page.md).

The user's hosted import installed the 24 cashier FKs but did not register migration 000017, confirmed by an explicit-schema query. A [completion-only file](../upgrades/2026-10-21-finish-cashier-foreign-keys.md) is prepared and tested; hosted completion is not yet verified. Full imports now use metadata caches, grouped ALTERs and ten-second metadata-lock waits. [Recovery review](../reviews/2026-10-21-cashier-import-recovery.md).

The user's latest phpMyAdmin export `(8).sql` already includes migrations through 000016. A [source-specific import for 000017](../upgrades/2026-10-21-latest-existing-database.md) was verified in an isolated copy with eleven checks. It preserves all existing records, including 1,079 sales, 69 products, 8 users and 3 shifts. This later preparation did not change the source, local application or host. [Verification](../reviews/2026-10-21-export8-database-upgrade.md).

Cashier relationships now have **24 enforced RESTRICT foreign keys** through migration 000017. Preflight, sale-ID type alignment, handover-reset ordering and restore verification are included. Applied locally twice after backup; all 25 existing data-table hashes and previous migrations were preserved. Forty-three isolated MySQL checks and 62 PHP cases / 680 assertions passed; hosting unchanged. [Review and import guide](../reviews/2026-10-21-cashier-foreign-keys.md).

Cashiers can view **My Login History** from their account menu or Preferences. This is self-only, read-only existing authentication history with time/result/IP, server date filtering and DataTables. It retains the cashier single-login rule and needs no SQL. Thirty-seven focused PHP cases / 355 assertions and 17 browser scenarios passed; application data/hosting unchanged. [Review and upload](../reviews/2026-10-21-cashier-login-history.md).

| Area | Current behavior | Detail |
| --- | --- | --- |
| Accounts | Administrator/cashier roles; branch restrictions; session revalidation | [Access](../system/Roles-and-Access.md) |
| Selling | Cart, payment recording, checkout recovery, invoices, held carts and sale history | [POS](../system/POS-and-Discounts.md) |
| Discounts | Product/category promos apply automatically; Senior/PWD comparison selects a favorable treatment; all-product offers are manual | [Discount decisions](Decisions.md) |
| After-sale work | Sale Details, beneficiary information, partial refunds, exchanges and administrator corrections | [POS](../system/POS-and-Discounts.md) |
| Inventory | Shared catalog with branch balances, classifications, adjustments, expiry actions, trash/restore and logs | [Inventory](../system/Inventory-and-Expiry.md) |
| Cashier operations | Start Shift, personal My Day totals, cash movements, close/count, handover and administrator review | [Cashier shifts](../system/Cashier-Shifts.md) |
| Reporting | Sales/forecast, expiry and stock-log reports; CSV/PDF exports; shift print reports | [Reporting](../system/Reports-and-Forecasts.md) |
| Administration | Users, branches, categories, suppliers, promotions, audit records and application backup/restore | [Recovery](../system/Database-and-Recovery.md) |

## Recent fixes and data work

Administrator Users now shows an Online now count, Presence and Last seen, refreshing every 30 seconds. Online means a valid session checked in within two minutes; it is separate from account status. Admin sessions aggregate and cashier idle limits remain unchanged. Additive schema 000016 is applied locally with existing records preserved; hosting import/upload is prepared. [Presence update](../reviews/2026-10-21-user-presence.md).

The topbar clock now synchronizes with the PHP server and uses a monotonic timer between samples, displayed in Philippine time. Device clock changes do not affect it. Clock requests omit login credentials/session activity. Endpoint, authentication/CSRF, JS and actual-server browser checks passed. [Server clock](../reviews/2026-10-21-server-clock.md).

Products stock controls now use clear Quantity/reason labels, correct unit plurals, an unclipped selector and grouped expiry details. Invalid inputs disable Add/Remove, with existing server safeguards retained. Four stock tests / 26 assertions and 18 browser scenarios passed. [UI update](../reviews/2026-10-21-stock-adjustment-ui.md).

Administrator accounts now allow independent simultaneous logins, following the user's 21 October request. Cashier-only lease/idle rules and cashier End login remain; administrator password/account checks are retained. The [policy removal](../reviews/2026-10-21-admin-multiple-logins.md) passed 34 focused PHP tests / 263 assertions, 18 JS checks and two POS scenarios. No database or hosted deployment change was made.

Edit Product reviews changed values; New/Edit Discount reviews the offer before posting. Branch price defaults and unspecified tax handling on product edit are corrected. Those parts of the [20 October extension](../reviews/2026-10-20-admin-login-submit-review.md) remain implemented; its administrator single-login policy is superseded.

Cashier accounts now allow [one active login](../reviews/2026-10-20-cashier-single-login.md), with idle expiry, sign-out release and administrator End login. Real activity renews access; background polls do not. Migration 000015 is applied locally and all existing operational records were preserved. Forty-two focused tests / 492 assertions, 15 JS checks, 30 POS scenarios and seven isolated MySQL groups passed. The hosted deployment remains unchanged; import the new SQL before uploading its package.

POS cart actions now [update relevant elements through AJAX](../reviews/2026-10-20-pos-ajax-actions.md). Ordinary edits retain payment/beneficiary details and filters; customer transitions reset those details and issue new checkout tokens. Nineteen focused tests / 327 assertions, 28 browser action scenarios and 68 layout scenarios passed. A six-file hosting ZIP is prepared; no application data or hosted deployment changed.

SKU is now required on product create/edit. All six management modules use [field-specific browser/server character rules](../reviews/2026-10-20-management-form-validation.md), and supplier save errors remain visible after redirects. Eighteen focused tests / 391 assertions, 55 management browser checks and 32 product overview checks passed. A hosting ZIP is prepared; existing records and hosted files were not changed.

Add Product now includes a [review dialog before saving](../reviews/2026-10-20-product-create-overview.md) for both roles. It shows the entered classifications, branch, prices and stock while retaining existing save/authorization rules. The SKU optional wording was replaced with an example. Thirty-two browser checks and six focused business tests / 61 assertions passed; no application data or hosted deployment changed.

The [Reports overview repair](../reviews/2026-10-20-report-reconciliation.md) exposes recorded VAT exemption and refunds separately from discounts. The owner's supplied screenshot reconciles exactly: PHP 238,422.00 after refunds minus PHP 779.02 discounts minus PHP 5.89 VAT exemption equals PHP 237,637.09 net sales. Four focused tests / 107 assertions and 18 browser scenarios passed. A hosting ZIP is prepared; no application data or hosted installation was changed.

Administrator POS entry now directs to Start Shift when no shift is active. The selected drawer supplies the selling branch, including for administrators with no assigned account branch. Opening/resuming a shift continues to POS for both roles. The [repair](../reviews/2026-10-20-admin-pos-branch.md) passed 28 focused tests / 273 assertions; no application database or hosted deployment was changed.

An [additive forecast demo import](../reviews/2026-10-19-month-forecast-demo.md) is prepared for the supplied `(7).sql` export. It fills 30 recent days for 19 product/branch pairs with 297 sales/items and 594 balanced stock movements. The user selected file preparation only; the local and hosted application databases are unchanged. Existing restock and reorder features remain enabled following cancellation of the earlier removal request.

Old-checkout retries preserve a newer cart. Ambiguous checkout responses retain recovery state and request saved-sale status. Equal-total quantity corrections record product movements. Senior/PWD and promotion rounding uses a favorable combination in the confirmed centavo case. See the [checkout repair report](../reviews/2026-10-16-checkout-fixes.md).

The nine local derived-stock discrepancies were reconciled, and the owner-confirmed test refunds on sale #41 were corrected to PHP 500. These were local data repairs, with backups and audit records. Their hosted import patches are separate from application deployment.

The seven local account names were updated on 17 October. The [user-name patch](../upgrades/2026-10-17-user-full-names.md) changes matching placeholder names and modification timestamps, while preserving credentials and permissions. The cashier-shift reset remains a prepared file operation; it was not applied locally by its preparation task.

## Remaining work

Original delivery/expiry provenance, ordinary expiry editing, legacy receiving retries and digital-payment validation remain open from the last recorded full review. [Known issues](Known-Issues.md) distinguishes these defects from future capabilities such as batches/FEFO, prescribing records, purchasing and payment integration.

The invoice configuration still has an empty TIN and registered series disabled (`serialEnd = 0`) in the current source. Real registration and product-specific tax/benefit classification require owner-supplied values and verification. The current report format and calculation fields do not establish regulatory accreditation.

## Verification and deployment boundaries

- On 20 October, the business regression file ran **191 tests: 179 passed, 9 failed and 3 errored**. The twelve unsuccessful cases remain in the tracked inventory/expiry area; this is not a full App-suite pass. Management validation's focused checks passed. [Validation record](../reviews/2026-10-20-management-form-validation.md).
- The later recorded full run in the checkout repair report had **222 tests: 210 passed, 9 failed and 3 errored**. It is historical evidence, not a run performed for this documentation task.
- Later recorded targeted results include PDF exports, checkout fixes, 13 Cashier Shifts browser scenarios and 15 My Day DataTables scenarios. See [Testing and evidence](../development/Testing-and-Evidence.md).
- The current manual omits the requested administrator-information section, numbered revision labels and issue date. It retains black text, unshaded grid tables, 34 chapters and 37 pages. Nine document checks and eight responsive HTML checks passed; pages 1, 2, 20 and 37 were rendered and reviewed after date removal. The vault includes a matching PDF copy. See the [manual update record](../reviews/2026-10-18-user-manual-monochrome-update.md).
- Older deployment ZIPs may precede the latest table and name changes. Rebuild and compare package contents against current source before release; see [Deployment](../development/Deployment.md).

Related: [Home](../Home.md) · [Project memory](../PROJECT-MEMORY.md) · [Review index](../reviews/README.md).
