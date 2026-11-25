---
title: Recent project changes
type: change-log
status: current
updated: 2026-10-29
tags: [history, project-memory]
---
# Recent project changes

This is a navigational summary of recorded work. Historical guides retain the complete implementation and validation notes.

## 29 October 2026

- Added Products stock-status/manufacturer filters alongside Medicine type. They combine before the row cap, retain assigned/selected branch boundaries, and keep DataTables search/sort working. Clear filters preserves branch context. Seven PHP cases / 35 assertions and 72 browser checks pass; no SQL/data/hosting change. [Review](../reviews/2026-10-29-product-stock-manufacturer-filters.md).

- Improved branded POS search, selling-branch visibility, initial URL filtering/reset and unavailable-stock explanations. Removed reorder forecasts/suggestions from reports, notifications, exports and scheduled generation while retaining sales forecasts/threshold alerts and historical data. Added shared GCash/card reference validation to checkout/exchange/corrections and refreshed the manual. 27 focused cases / 216 assertions and 120 browser checks pass; full 425 / 25,425 retains the same three A05 issues. [Review and package](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md).

## 28 October 2026

- Fixed expiry calendar limits before the first selection: unchanged valid dates retain min/max; legacy values keep validation exemptions while the popup uses server bounds. Disabled years/months/days, paging, keyboard and commit checks agree; report calendars retain their own bounds. 289 calendar/browser checks and 25 PHP cases / 320 assertions pass. No SQL/data/hosting changes. [Review and upload](../reviews/2026-10-28-expiry-calendar-bounds.md).

- Removed the expiry administrator override at the owner's request. The same configurable date limit now applies to all accounts; unchanged stored dates remain intact. 16 focused cases / 98 assertions and 116 browser checks pass. No SQL, application-data or hosted change. [Current review and package](../reviews/2026-10-28-expiry-limit-only.md).

- Earlier implementation, superseded by the limit-only change above: added a configurable expiry-year range across product creation/catalog/edit and stock replacement. Default maximum is December 31 of the Philippine year plus ten; administrators can verify longer dates with a recorded reason, and unchanged legacy dates remain intact. Nineteen cases / 129 assertions and 63 browser checks pass; no SQL or application-data/hosting change. [Review](../reviews/2026-10-28-expiry-year-range.md).

- Repaired selected A02/A03/A04/A06/A08/A09/A10/A11/A13/A15: refund/source/receiving guards, server automatic promos and exchange savings, exact noncash settlement, quarantine cost recovery and CSV/PDF generation/history/names. Added 51 focused cases / 450 assertions, 20 browser checks, real MySQL refund/receiving races and four inspected PDF pages. Full suite 382 cases / 25,112 assertions retains only three A05 expiry-edit failures/errors. No application data/schema/hosting changes; 24-file upload package prepared. [Review](../reviews/2026-10-28-selected-audit-repairs.md).

## 27 October 2026

- Audited the current system against official Odoo, ERPNext and Square workflow documentation, reviewed security and ran 331 tests. Confirmed local private-file exposure and multiple transaction/inventory/pricing/reporting defects; matched component advisory ranges. Seven inventory bug characterizations / 22 assertions, ten operations observations and 47 focused security cases / 398 assertions provide evidence. Local database health was checked in a read-only transaction: no stock/amount/FK inconsistencies, but widespread historical delivery/expiry gaps. No runtime/data/schema/hosting fixes made. [Full report](../reviews/2026-10-27-whole-system-audit.md).

## 23 October 2026

- Applied the owner-selected start-of-expiry-date cutoff: today is Expired and blocked across POS, exchanges, held carts, resellable returns/quarantine release, alerts and expiry reports. New inventory requires a future expiry. Thirty-eight focused tests / 397 assertions and two PDF pages pass; the broad run retains the same 11 known inventory failures/errors. No SQL/application data/hosting change; hosting ZIP prepared. [Review](../reviews/2026-10-23-expiry-date-cutoff.md).

- Fixed full Stock Logs PDF memory exhaustion using row-wise canvas pagination and a complete long-note section. Existing 128 MB limit retained; 1,087 routed records use 28 MiB and 10,000 synthetic records use 44 MiB. All ordered IDs/long notes verified; 29 PHP cases / 283 assertions and 25 HTTP checks pass. No SQL or hosted change; upload package prepared. [Repair](../reviews/2026-10-23-stock-log-pdf-memory-fix.md).

- Audited routed exports through actual local HTTP downloads, guarded MySQL fixtures and PDF/browser print inspection. Found sales CSV HTTP 500 (also recorded in history), unfiltered stock PDF memory exhaustion at 128/256 MB, and Summary PDF filename ambiguity. 25 PHP cases / 270 assertions pass; 179 HTTP checks expose these findings; 21 PDFs / 71 pages checked. Runtime/schema/hosting unchanged; test wording refreshed. [Review](../reviews/2026-10-23-export-functionality-audit.md).

- Added refund/correction confirmation overviews with native payload preservation, before/after changes, payout/collection and stock effects; clarified the existing server exchange overview and added its submit lock. Refund preview now uses exact remaining net cents. Twenty PHP cases / 179 assertions and 40 browser scenarios pass. No schema/application data/hosting change; upload ZIP prepared. [Review](../reviews/2026-10-23-transaction-overviews.md).
- Enabled branch-scoped cashier reads for Quarantine and Damaged Items while retaining administrator-only inspection/release. Current stored assignment overrides requested branches; guessed IDs, copied drafts and forged roles cannot grant broader access. Twenty PHP cases / 235 assertions, 27 MySQL access checks, 18 quarantine + 7 damaged checks and 74 browser scenarios pass. No schema/data change; all 27 local table hashes preserved. Current combined upload prepared. [Review](../reviews/2026-10-23-cashier-return-pages.md).
- Added admin Damaged Items with a read-only damaged-return ledger, three sources, branch/source filters, DataTables, quantities and source-record details. Direct refund/exchange damage and later quarantine damage appear once each. No SQL/schema/data changes; all 27 local table hashes preserved. Fifteen PHP cases / 182 assertions, seven MySQL checks and 61 browser scenarios pass. Combined hosting package prepared. [Review](../reviews/2026-10-23-damaged-items-page.md).
- Built admin Quarantine with pending/resolved returns, DataTables, branch/outcome filters, inspected-by/time/note history and release stock logs. Whole-line outcomes are final and original refunds remain unchanged. New POS/exchange replacement delivery/expiry snapshots are saved; unknown historical delivery releases are blocked. Fifteen PHP cases / 189 assertions, 18 MySQL checks (four competing inspectors), 59 browser scenarios and syntax checks pass. Applied additive 000018 locally after backup with 25 existing data-table hashes preserved; three original returns remain pending. Hosted update prepared only. Broad regression retains nine failures/two errors in other inventory safeguards. [Review](../reviews/2026-10-23-quarantine-page.md).

## 21 October 2026

- Prepared completion-only recovery after hosted results confirmed 24 FKs but missing migration 000017. Added explicit-schema status SQL, cached full-import metadata, grouped ALTERs and bounded lock waits. Forty-nine MySQL, 62 PHP / 680 assertions and eleven export checks passed; host recovery remains a user import step. [Review](../reviews/2026-10-21-cashier-import-recovery.md)
- Checked the `(8).sql` phpMyAdmin export and prepared a source-specific latest existing-database patch for 000017. Eleven isolated MySQL checks passed with all 24 non-migration table hashes preserved; existing 000014–000016 need no reapplication. The source, local application database and host were not changed. [Review](../reviews/2026-10-21-export8-database-upgrade.md)
- Added migration 000017 enforcing 24 restrictive cashier/login/presence FKs, aligned event sale-ID type, made explicit reset handovers compatible and validated restored FKs before commit. Forty-three isolated MySQL checks and 62 PHP cases / 680 assertions passed. Applied locally twice after a full SQL backup; all 25 existing data tables and previous migration rows preserved. Hosting ZIP/SQL ready. [Review](../reviews/2026-10-21-cashier-foreign-keys.md)
- Added cashier-only My Login History with existing login results/sign-outs, Philippine time/IP, self-only access, server date range and DataTables. Preserves login ownership and drafts; no SQL or new history records. Thirty-seven PHP cases / 355 assertions and 17 browser scenarios passed; seven-file upload prepared. [Review](../reviews/2026-10-21-cashier-login-history.md)
- Added live Online now, Presence and Last seen to Users, separate from Active/Inactive account status. Supports multi-admin sessions, cashier expiry, logout/revocation and stateless polling with preserved DataTables state. Forty-two PHP cases, 12 presence JS checks, existing session/clock checks, 18 browser scenarios and nine isolated MySQL groups passed. Schema 000016 applied locally with existing records preserved; hosting files/SQL prepared. [Review](../reviews/2026-10-21-user-presence.md)
- Made the topbar clock server-synchronized, using monotonic elapsed time and Philippine formatting. Added a stateless no-cache GET endpoint, periodic/return/reconnect sync and last-sample fallback. Two endpoint tests, 24 related PHP cases, 19 JS checks and six actual-server browser scenarios passed. Six-file hosting ZIP prepared; no data/schema/hosted change. [Review](../reviews/2026-10-21-server-clock.md)
- Refined Change Password input/button alignment using one flex field border and consistently inset Show/Hide buttons. Twelve responsive, light/dark and large-control combinations passed, including containment and no text overlap. Updated one-view hosting ZIP prepared. [UI follow-up](../reviews/2026-10-21-password-visibility.md#ui-alignment-follow-up)
- Added keyboard-accessible Show/Hide buttons to New Password and Confirm Password. Both start masked and mask again on reset/history return; existing password validation/save behavior remains. Focused script/browser checks and desktop/mobile visual inspection passed. Single-view hosting package prepared; no SQL or real password changes. [Review](../reviews/2026-10-21-password-visibility.md)
- Improved Products stock adjustment labels, spacing, expiry grouping and browser validation. Actions require a positive whole quantity and trimmed reason; stored units and controller rules remain. Four existing stock tests / 26 assertions and 18 browser scenarios passed. Single-view upload package prepared; no data/schema/hosted change. [Review](../reviews/2026-10-21-stock-adjustment-ui.md)
- Removed the administrator one-login rule while keeping cashier lease/idle enforcement and cashier-only End login. Existing valid administrator sessions keep drafts; independent logins and logout isolation are verified, with account/password revocation preserved. Thirty-four PHP tests / 263 assertions, 18 JS checks and two POS scenarios passed. Seven-file hosting ZIP prepared with no SQL or application-data changes. [Policy update](../reviews/2026-10-21-admin-multiple-logins.md)
- Replaced duplicate Sales History date filters with one always-visible database range, Apply/Clear and a quiet load-limit/search note. Added invalid-range rejection while preserving branch restrictions and DataTables search/sort/pagination. Five focused PHP tests / 34 assertions and 22 browser scenarios passed. Six-file hosting update prepared; no database or hosted deployment change. [Implementation record](../reviews/2026-10-21-sales-history-filters.md)

## 20 October 2026

- Extended one-login protection to administrator accounts and added product-edit and discount-create/edit save reviews. Changed values compare against saved records; branch prices and unspecified tax are preserved correctly. Sixty-three focused PHP cases, 28 review scenarios and existing overview/validation/activity checks passed; isolated MySQL verified one owner per account for both roles. Existing schema 000015 is reused. [Implementation record](../reviews/2026-10-20-admin-login-submit-review.md)
- Added one active login per cashier account, idle expiry, real-activity renewal, explicit JSON rejection and administrator End login without closing shifts. Migration 000015 was applied locally without changing existing operational records. Forty-two focused tests / 492 assertions, 15 JS checks, 30 POS scenarios and seven isolated MySQL groups passed, including eight competing login processes. Hosting bundle/SQL prepared. [Implementation record](../reviews/2026-10-20-cashier-single-login.md)
- Converted POS cart forms to AJAX while preserving the payment form and product filters. Clear/Hold/Resume reset customer details; ambiguous requests use a read-only cart refresh and checkout keeps its recovery lookup. Nineteen focused tests / 327 assertions, 28 action scenarios and 68 layout scenarios passed. Six-file hosting update prepared; no database change. [Implementation record](../reviews/2026-10-20-pos-ajax-actions.md)
- Changed Unit to a required dropdown on Add/Edit Product. Standard pharmacy units and valid existing catalog units are selectable; blank/unlisted values are rejected server-side without rewriting stock or prices. Fourteen focused tests / 408 assertions, 55 management browser checks and 32 overview checks passed. [Implementation record](../reviews/2026-10-20-product-unit-selection.md)
- Made SKU required in product create/edit and added matching browser/server character policies to Products, Categories, Suppliers, Branches, Users and Discounts. Fixed supplier errors disappearing after redirects. Eighteen focused tests / 391 assertions, 55 management browser checks and 32 overview checks passed. A broader 191-case business run retains twelve tracked inventory/expiry failures/errors. Hosting ZIP prepared with no database changes. [Implementation record](../reviews/2026-10-20-management-form-validation.md)
- Updated Add Product: SKU uses an example placeholder, and Save Product/Add to My Branch opens a complete Product overview before Confirm & save. Review supports editing, keyboard access, mobile layout, current-value confirmation and duplicate-click protection. Thirty-two browser checks and six focused business tests / 61 assertions passed. [Implementation record](../reviews/2026-10-20-product-create-overview.md)
- Corrected the Reports overview's missing VAT exemption breakdown and misleading gross-sales label. The supplied screenshot reconciles exactly after including PHP 5.89 VAT exemption. Original sales, discounts, VAT exemption and refunds are shown separately with the net formula; recorded differences remain Other adjustments. Four focused tests / 107 assertions and 18 responsive browser scenarios passed. Hosting package prepared; no data or hosted deployment change. [Repair record](../reviews/2026-10-20-report-reconciliation.md)
- Fixed administrator POS entry with no branch assignment. Both roles start a shift before entering POS and continue directly to POS after choosing an available drawer/counting opening cash. A branchless checkout directs to Start Shift while keeping the cart. Cashier restrictions remain; 28 focused tests / 273 assertions and PHP syntax checks passed. Application databases and hosted deployment unchanged. [Repair record](../reviews/2026-10-20-admin-pos-branch.md)

## 19 October 2026

- Prepared `database/samples/2026-10-19-month-forecast-demo.sql` for the supplied `(7).sql` export. Adds 297 synthetic sales/items and 594 balanced movements to fill 30 days for 19 pairs, retaining current balances and all original rows. Eleven isolated verification groups passed with actual forecast/promo logic, phpMyAdmin parsing, reimport and rejection/rollback cases. Import file only; application databases unchanged. [Review](../reviews/2026-10-19-month-forecast-demo.md)
- Canceled the requested removal of restock suggestions/reorder forecasts and reverted that request's manual edits. No system feature was removed.

## 18 October 2026

- Removed the designated-supervisors wording from the manual's intended-users row; it now reads **Cashiers and administrators**. PDF/HTML rebuilt; nine document checks passed and page 2 was rendered and reviewed. [Update record](../reviews/2026-10-18-user-manual-monochrome-update.md#intended-users-simplified)
- Removed the manual's issue date from the cover, document-information table, HTML navigation and all PDF footers. Replaced the instruction to check the issue date with an instruction to use a current copy. The document identifier and existing download filename remain. Nine document checks and eight HTML checks passed; pages 1, 2, 20 and 37 were rendered and reviewed. [Update record](../reviews/2026-10-18-user-manual-monochrome-update.md#issue-date-removed)
- Removed numbered revision labels from the manual cover, document-information table, HTML navigation and PDF footers. The document identifier and issue date remain. Nine document checks and eight HTML checks passed; pages 1, 2, 20 and 37 were rendered and reviewed. [Update record](../reviews/2026-10-18-user-manual-monochrome-update.md#revision-labels-removed)
- Removed "Information to give the administrator," renamed the last chapter "Terms and definitions" and removed its obsolete troubleshooting cross-reference. Nine document checks and eight HTML checks passed; revised front matter, contents and ending pages were visually checked. Revision 1.2 was archived before replacing the current PDF. [Update record](../reviews/2026-10-18-user-manual-monochrome-update.md#requested-section-removal)
- Revised the manual to Revision 1.2 at the user's request: all fonts are black; tables use white cells, thin grid borders and bold headers. Removed blue accents and shaded callouts. Nine document checks and eight HTML checks passed; reviewed all 37 page overviews and selected full-size pages. The current 18 October PDF was replaced after archiving Revision 1.1. [Monochrome update](../reviews/2026-10-18-user-manual-monochrome-update.md)
- Published manual Revision 1.1 with 11-point Calibri body text, Calibri Light chapter headings, a new cover, six-part contents, navy/blue accents and aligned currency tables. Operating instructions, 34 chapters and 37 pages are retained. Eight document checks, seven responsive HTML checks and visual inspection of every PDF page passed. The original Revision 1.0 PDF is preserved. [Design update](../reviews/2026-10-18-user-manual-design-update.md)
- Reviewed the user manual's page format, writing and training usability. The existing PDF structure/navigation checks passed; exact control names, selected screenshots, tighter prose, heading punctuation, currency alignment and release details were recommended. The manual was not rewritten or re-exported. [Format review](../reviews/2026-10-18-user-manual-format-review.md)

## 17 October 2026

- Created the 34-chapter, 37-page [user manual](../manual/Pharxmaco_User_Manual.md), its HTML/PDF outputs and build verification.
- Corrected Cashier Shifts DataTables filters. Cash-pair columns retain sorting without an inappropriate combined-value dropdown. Stacked values use readable separators, including `Cashier — Branch`; desktop/mobile selections and phrase search were checked. Runtime files: `public/assets/js/datatables-init.js`, `app/Views/cashier/workspace/manager.php`, `app/Views/layouts/staff.php`.
- Updated seven local placeholder full names and the demo-account seed. Prepared `database/upgrades/2026-10-17-user-full-names.sql` for existing databases. Modification timestamps change; credentials, roles, branches, active/inactive status and session-version fields are preserved.
- Organized this documentation vault, its current-state notes, historical source snapshots, catalog, source maps and maintenance tools.

| Username | Sample full name |
| --- | --- |
| `admin` | Juan Miguel Santos |
| `cashier` | Maria Angela Reyes |
| `cashier2` | Carlo Mendoza |
| `cashierss` | Rhea Castillo |
| `demo_admin` | Patricia Garcia |
| `demo_cashier1` | Daniel Cruz |
| `demo_cashier2` | Sofia Ramos |

## 16 October 2026

- Refined automatic category promotion allocation alongside product promos and Senior/PWD benefits. [Guide](../reviews/2026-10-16-automatic-category-promos.md)
- Repaired checkout recovery, correction product events and the benefit/promo centavo comparison. Reconciled the authorized local stock and test-refund records. [Repair report](../reviews/2026-10-16-checkout-fixes.md)
- Redesigned shift print reports with interim/final distinction, reconciliation and full activity details. [Shift print update](../reviews/2026-10-16-shift-print-update.md)
- Improved report PDFs, repeat identity, reconciliation, inventory identifiers and full movement notes. [PDF update](../reviews/2026-10-16-pdf-export-update.md)
- Enabled DataTables in Cashier Shifts operational tables. Printable reports retain complete static tables.
- Prepared a shift-history clearing patch and a cleaned full export. The preparation did not clear the application database. [Reset guide](../upgrades/2026-10-16-clear-cashier-shifts.md)

## 14–15 October 2026

Invoice fields/numbering, medicine classifications, beneficiary details, eligibility and statutory discount fields were added. Product catalog and branded demo stock were prepared. Shared drawers, shift handover, My Day, held carts and review workflows followed. Browse [upgrade guides](../upgrades/README.md) and [reviews](../reviews/README.md).

Dates above summarize the recorded work periods; the source filename date does not prove when a particular host imported or deployed it.
