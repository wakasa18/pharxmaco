---
title: Testing and recorded evidence
type: verification-guide
status: current
updated: 2026-10-29
tags: [testing, evidence]
---
# Testing and recorded evidence

This page indexes existing results. Creating the documentation vault did not rerun application tests, submit a sale, import SQL or verify hosted installation.

## Recorded results

| Work | Recorded evidence | Boundary |
| --- | --- | --- |
| Product stock/manufacturer filters, 29 October | 7 cases / 35 assertions; 72 browser checks at 1440/768/390 for both roles; full 432 / 25,460 retains the same 2 failures + 1 error in A05 | Isolated SQLite and controller-rendered pages; reduced-motion browser QA; no stock/app records or hosting changed. [Review](../reviews/2026-10-29-product-stock-manufacturer-filters.md) |
| POS catalog, references and reorder removal, 29 October | 27 focused cases / 216 assertions; 120 browser checks; full 425 / 25,425 has the same 2 failures + 1 error in A05; 48 PHP + 5 JS syntax checks; four report PDF pages and 37 manual pages verified | In-memory SQLite and synthetic desktop/mobile forms; local stock diagnosis was read-only. No SQL/data/hosting changes. [Review](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md) |
| Expiry calendar bounds, 28 October | 289 actual calendar/browser checks; 25 focused PHP cases / 320 assertions; 33 PHP + 2 JS syntax checks. Disabled years before selection, legacy fields and ordinary report calendars verified | Actual calendar icon/year/month/day/keyboard interaction with synthetic forms and empty SQLite only; no POSTs or application-data/host changes. [Review](../reviews/2026-10-28-expiry-calendar-bounds.md) |
| Expiry date limit only, 28 October | 16 focused cases / 98 assertions; 116 desktop/mobile browser checks; 33 PHP + 1 JS syntax checks. Full 398 / 25,210 retains the same 2 failures + 1 error in A05 | Isolated fixtures and synthetic rendered forms; no SQL or application records/hosting changed. [Current review](../reviews/2026-10-28-expiry-limit-only.md) |
| Earlier expiry range/review, superseded | 19 new cases / 129 assertions; 63 desktop/mobile browser checks; 36 PHP + 1 JS syntax checks; full 401 / 25,241 retains the same 2 failures + 1 error in A05 | In-memory SQLite and synthetic rendered forms only; zero browser POSTs. No SQL or application records/hosting changed. [Review](../reviews/2026-10-28-expiry-year-range.md) |
| Selected audit repairs, 28 October | 51 new cases / 450 assertions pass; full 382 / 25,112 retains only 2 failures + 1 error in unselected A05; 20 browser checks; 2 real MySQL races; 4 PDF pages; 24 runtime + 8 test PHP syntax checks | Synthetic SQLite/framework routes/headless Chrome and disposable MySQL schema only. Application data/hosting unchanged; no new SQL. [Repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| Whole-system audit, 27 October | 331 tests / 24,628 assertions: 10 failures, 2 errors; 258 PHP / 29 JS syntax checks pass; 47 focused security cases / 398 assertions pass; 7 defect characterizations / 22 assertions; 10 operations observations; 98 read-only DB queries across 27 tables / 50 FKs; public advisory range comparison | Existing business defects remain. Clock passes 2/19 alone, identifying shared test-state failure. Local static exposure confirmed by unauthenticated HEAD; hosted state unknown. Reproducing bad behavior is not a fix. [Full report](../reviews/2026-10-27-whole-system-audit.md) |
| Expiry start-of-date cutoff | 38 focused cases / 397 assertions, including 15 new cases / 99 assertions; 18 syntax checks; two visually reviewed PDF pages | In-memory SQLite and synthetic PDF; 280-case broader run retains 9 failures / 2 errors in the same known inventory cases. No application database or hosted deployment. [Review](../reviews/2026-10-23-expiry-date-cutoff.md) |
| Stock Logs PDF memory fix | 29 PHP cases / 283 assertions; 25/25 routed HTTP checks; 1,087 complete records at 28 MiB, 10,000 synthetic records at 44 MiB under a 128 MB limit; ordered IDs, notes, page identity and rendered samples | Guarded MySQL copy and synthetic renderer. Stock PDF finding repaired; other export findings remain open. [Review](../reviews/2026-10-23-stock-log-pdf-memory-fix.md) |
| Export functionality audit | 25 PHP cases / 270 assertions; 179 HTTP checks (152 pass / 27 failed assertions for three findings); 21 PDFs / 71 rendered pages; five print actions; backup format/count checks | Actual routed downloads against a disposable MySQL copy. Sales CSV fails; large stock PDF fails at 128/256 MB; Summary filename ambiguous. Hosted deployment and physical printers untested. [Review](../reviews/2026-10-23-export-functionality-audit.md) |
| Transaction overviews | 20 PHP cases / 179 assertions; 40 browser scenarios and three fixture-only POSTs; syntax checks | Refund/correction dialogs, existing server exchange overview, exact refund amounts compared to seven saved controller payouts, stale/no-op/duplicate/keyboard/mobile cases; no application database/hosting changes; [review](../reviews/2026-10-23-transaction-overviews.md) |
| Cashier return-page visibility | 20 PHP cases / 235 assertions; 27 read-only MySQL access checks across four cashiers; 18 quarantine + 7 damaged MySQL checks; 74 browser scenarios; route/syntax checks | Own-branch lists/details, no inspection token/form, forged filter/ID/role rejection, retained administrator actions; all 27 local table hashes preserved; [review](../reviews/2026-10-23-cashier-return-pages.md) |
| Damaged Items | 15 PHP cases / 182 assertions; 7 read-only MySQL checks; 61 browser scenarios; syntax checks | Mixed refund/exchange/quarantine sources, exact event links, no duplicates/writes, all 27 local table hashes preserved; one original damaged return; [review](../reviews/2026-10-23-damaged-items-page.md) |
| Quarantine inspection | 15 focused PHP cases / 189 assertions; 18 isolated MySQL checks; 59 browser scenarios; syntax checks | Four simultaneous inspectors produce one release/log/outcome. Local additive migration preserves 25 existing data-table hashes. Broader 248-case regression: 237 pass, 9 fail, 2 error in remaining inventory safeguards; [review](../reviews/2026-10-23-quarantine-page.md) |
| Blank cashier import recovery | 49 isolated MySQL checks; 62 PHP cases / 680 assertions; 11 export checks | Completion-only registration, explicit-schema status, incomplete/wrong-definition refusal, repeat/partial imports; hosted 24/0 state confirmed by user, recovery prepared only; [review](../reviews/2026-10-21-cashier-import-recovery.md) |
| phpMyAdmin export 8 upgrade | 11 isolated MySQL checks | Full export import, required columns, 24 valid references, record/migration hashes, restrictive FK definitions and repeat/interchangeable patches; file-only preparation, no application/host change; [review](../reviews/2026-10-21-export8-database-upgrade.md) |
| Cashier foreign keys | 43 isolated MySQL checks; 62 PHP cases / 680 assertions | All 24 invalid-parent updates, restrictive deletes, repeat/refused migrations, real shifts/handover, backup rollback and reset compatibility. Local upgrade preserves 25 data-table hashes/prior migrations; no hosted change; [review](../reviews/2026-10-21-cashier-foreign-keys.md) |
| Cashier own login history | 37 PHP cases / 355 assertions; 17 browser scenarios | Self-only reads, account revocation, date validation/cap, preserved drafts and existing login/presence rules; no application data/hosting change; [review](../reviews/2026-10-21-cashier-login-history.md) |
| Online users / Last seen | 42 PHP cases / 339 assertions; 12 presence JS checks; 18 session and 19 clock checks; 18 browser scenarios; 9 MySQL groups | In-memory controllers, browser fixtures and isolated MySQL; additive local schema applied twice with 24 existing table hashes preserved; no hosted deployment; [review](../reviews/2026-10-21-user-presence.md) |
| Server-synchronized topbar clock | 2 endpoint tests / 19 assertions; 24 related PHP cases / 199 assertions; 19 JS checks; 6 real-server browser scenarios | Public local HTTP clock endpoint, in-memory auth/CSRF cases and browser dates 2001/2099; no authenticated requests or application-record changes; [review](../reviews/2026-10-21-server-clock.md) |
| Product stock form UI | 4 existing PHP tests / 26 assertions; 18 browser scenarios and responsive screenshots | Actual rendered view and existing SQLite stock cases; labels, expiry sorting, input limits and fixture POST; no application data/hosting change; [review](../reviews/2026-10-21-stock-adjustment-ui.md) |
| Administrator multiple-session policy | 34 PHP tests / 263 assertions; 18 JS activity/notice checks; two POS session-notice scenarios | In-memory SQLite and browser fixtures; independent admin sessions, legacy leases, cashier protection and account revocation; no MySQL rerun or application/hosting change; [current policy](../reviews/2026-10-21-admin-multiple-logins.md) |
| Sales History date filtering | 5 PHP tests / 34 assertions; 22 browser scenarios; mobile/desktop light/dark screenshots | Actual controller in SQLite and rendered browser fixtures; older-than-cap records, bounds, branch scoping and table controls; no application database/hosting change; [review](../reviews/2026-10-21-sales-history-filters.md) |
| Administrator single login and save reviews | 63 PHP cases / 21,007 assertions; 28 review scenarios; 32 existing overview, 55 management, 15 activity checks; 8 MySQL groups with 8 competing processes per role | In-memory controllers, browser fixtures and separate temporary MySQL server; application/hosting unchanged; [review](../reviews/2026-10-20-admin-login-submit-review.md) |
| Cashier single login | 42 focused tests / 492 assertions; 15 JS checks; 30 POS browser scenarios; 7 isolated MySQL groups including 8 simultaneous claims with one winner | Controller/lease/authorization/rollback checks in SQLite, actual MySQL concurrency, browser fixtures; local additive schema applied with 23 existing table hashes preserved; hosting unchanged; [review](../reviews/2026-10-20-cashier-single-login.md) |
| POS AJAX cart actions | 19 focused tests / 327 assertions; 28 browser action scenarios; 68 layout scenarios | In-memory SQLite and rendered browser fixtures; cart/benefit/payment retention, held-sale scoping, CSRF, ambiguous requests and intended checkout navigation; application data/hosting unchanged; [review](../reviews/2026-10-20-pos-ajax-actions.md) |
| Management form validation / required SKU | 18 focused tests / 391 assertions; 55 browser checks across 12 Add/Edit forms; 32 updated product overview checks | Actual controller save/rejection paths in SQLite plus static browser fixtures; no application data or hosting changes; [record](../reviews/2026-10-20-management-form-validation.md) |
| Business regression file, 20 October | 191 tests / 22,590 assertions: 179 passed, 9 failed, 3 errored | In-memory `BusinessLogicTest.php`; twelve unsuccessful delivery/expiry safeguards remain; separate from the older full App-suite count; [record](../reviews/2026-10-20-management-form-validation.md) |
| Product entry overview | 32 browser checks; 6 focused business tests / 61 assertions; syntax/parsing checks | Admin/cashier rendered fixtures, native POSTs only to fixture server, and in-memory SQLite catalog/medicine-type tests; application database and hosted installation unchanged; [record](../reviews/2026-10-20-product-create-overview.md) |
| Product unit selection | 14 focused tests / 408 assertions; 55 management browser checks and 32 product overview checks | In-memory SQLite and refreshed fixtures; required/listed values, legacy spelling and unchanged stock verified; no application database/hosting change; [review](../reviews/2026-10-20-product-unit-selection.md) |
| Reports overview reconciliation | 4 focused tests / 107 assertions; 18 browser scenarios; exact supplied-screenshot arithmetic reproduced from export plus prepared demo | In-memory SQLite and static browser fixtures, including empty/legacy differences and mobile/desktop themes; no application/hosted database access; [repair](../reviews/2026-10-20-report-reconciliation.md) |
| Administrator POS branch selection | 28 focused tests / 273 assertions; PHP syntax checks passed | In-memory SQLite controllers, rendered templates, selected-branch checkout and cashier restrictions; no live database/browser/hosting change; [repair](../reviews/2026-10-20-admin-pos-branch.md) |
| Month forecast demo import | Eleven isolated verification groups; 297 sales/items, 594 balanced movements, 19 positive reorder results across four windows | Supplied export imported into isolated MySQL 8.2.0; application databases/hosted phpMyAdmin unchanged; [review](../reviews/2026-10-19-month-forecast-demo.md) |
| Full suite after checkout repairs | 222 tests; 210 passed, 9 failed, 3 errored | Later recorded full baseline; twelve inventory/expiry safeguards remain unsuccessful |
| Checkout/report/promo fixes | [Repair report](../reviews/2026-10-16-checkout-fixes.md) | Focused controller/browser and local repair verification |
| Automatic category promos | 28 focused PHP tests / 992 assertions; shared PHP/JS and browser cases | Isolated controllers and stubbed browser submissions |
| PDF exports | 9 targeted tests / 94 assertions; 14 PDFs / 39 rendered pages | Verified sample output, not every future report size |
| Cashier Shifts tables | 13 browser scenarios | Filtering, labels, sort, forms, empty states and responsive layouts |
| My Day tables | 15 browser scenarios | Live table refresh, search, ordering and pagination |
| User-name data update | Seven local user rows; all other fields checked apart from modification time | No credentials/roles/branch/status changes |
| Shift reset files | Source/cleaned imports and repeated patch checks in isolated MySQL | Reset not applied to local application database |
| Current user manual | 37 pages, 34 chapters, nine document checks, eight responsive HTML checks; pages 1, 2, 20 and 37 rendered and reviewed after date removal | Requested section, revision labels and issue date removed; other operating text and black styling verified; no application tests or database operations |

The [recorded artifact inventory](../reference/Verification-Artifacts.md) lists available check/result files and their hashes without importing raw database records.

## Commands for a relevant future check

Use the local installed toolchain and inspect the intended test fixture/database setup before running a case. The application business test harness uses in-memory SQLite for its controller cases; standalone review scripts may target a local database and must be read first.

```powershell
& 'C:\UniServerZ\core\php83\php.exe' -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage
node tests/discount-math.test.cjs

# Existing focused browser harnesses use rendered fixtures and headless Chrome.
node writable/medicine-type-review/check.cjs --cashier-shift-tables-only
node writable/medicine-type-review/check.cjs --my-day-tables-only
```

Do not report the full suite as passed based on a selected filter or a subset of checks. Static/source checks, SQLite controller tests, fixture browser checks and actual concurrent MySQL/hosting validation are distinct evidence types.

## Closing a defect

Capture the original reproduction, changed behavior and a check that distinguishes the two. Record environment, date, command, result and remaining limitations in a [review](../templates/Review.md). Keep original audit notes and link the fix rather than deleting the finding.

Related: [Known issues](../project/Known-Issues.md) · [Review index](../reviews/README.md) · [Manual build guide](../manual/README.md).
