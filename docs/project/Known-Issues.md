---
title: Known issues and capability gaps
type: issue-register
status: open
updated: 2026-10-29
tags: [project-memory, defects]
---
# Known issues and capability gaps

The [27 October audit](../reviews/2026-10-27-whole-system-audit.md) is followed by the [28 October selected repairs](../reviews/2026-10-28-selected-audit-repairs.md). A02/A03/A04/A06/A08/A09/A10/A11/A13/A15 are repaired and verified locally. Hosting and historical application data are unchanged. Remaining items below were outside the owner's selected repair scope.

Reorder planning was removed on 29 October. Former BUG14/A12 is a retired capability rather than a repaired historical algorithm; restoring it would require expiry-aware stock calculations. [Current change](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md).

## Open defects from the recorded audit

| ID | Priority | Finding and next verification |
| --- | --- | --- |
| BUG02 | High | Ordinary product editing can bypass a dedicated expiry-correction workflow. `ExpiryStockResolution::ACTIONS` currently lists replace/remove/deactivate, without the expected correction action. The current 28 October limit rejects new/changed dates beyond its maximum for every account, but an ordinary within-range date change still bypasses a dedicated correction workflow. The A05 characterization retains an in-range 2030-01-01 → 2030-02-01 change, unchanged quantity and no stock movement. Three correction cases remain unsuccessful. [Current expiry limit](../reviews/2026-10-28-expiry-limit-only.md). |
| BUG08 | High | Actual local Apache exposes project-root .env, SQL and archives to unauthenticated requests. Host state unknown. Serve only public and remove private files from web reach. [Audit A01](../reviews/2026-10-27-whole-system-audit.md#a01--high-configuration-and-archives-are-exposed-by-local-apache). |
| BUG10 | High maintenance | Bundled CodeIgniter 4.7.0 and Dompdf 3.1.5 match published advisory ranges; app exploitability is conditional. Upgrade current tested patched releases and review PHP patch level. [Audit A07](../reviews/2026-10-27-whole-system-audit.md#a07--high-priority-maintenance-installed-components-match-published-security-advisories). |
| BUG15 | Medium | Password forms allow 255 characters while bcrypt uses 72 bytes; synthetic different suffixes verify against one accepted long password. [Audit A14](../reviews/2026-10-27-whole-system-audit.md#a14--medium-long-accepted-passwords-have-equivalent-suffixes). |

Evidence: [POS/inventory audit](../reviews/2026-10-15-pos-inventory-audit.md) and [latest update audit](../reviews/2026-10-16-latest-update-audit.md). The latter's first four new checkout/report/rounding findings were subsequently repaired, as recorded below.

The [20 October management-validation regression run](../reviews/2026-10-20-management-form-validation.md) executed 191 business cases and still reported nine failures and three errors in delivery/expiry, returns, exchanges and expiry corrections. That is historical evidence; the 28 October repair result below supersedes the selected return/receiving safeguards, while A05 expiry editing remains open.

## Selected repair verification, 28 October

Fifty-one new focused cases / 450 assertions pass, plus 20 browser checks, two real MySQL synthetic concurrency races and inspected Full/Summary PDFs. Full suite: 382 tests / 25,112 assertions; 379 pass, two failures and one error all belong to **BUG02 / A05 expiry editing**, which was not selected. No failures were hidden or marked skipped. [Details and deployment](../reviews/2026-10-28-selected-audit-repairs.md).

## Current data and verification limits

The 27 October local read-only check found no stock aggregate mismatches, negative balances/prices, duplicate SKUs/invoices/inventory pairs, over-refunds, sale-line-total mismatches or orphans across 50 FKs. However, 976/977 historical sale lines lack source snapshots and 104/117 inventory rows lack delivery identity. Do not invent historical provenance from current stock. The current database balancing does not make unsafe requests correct.

The historical 27 October suite had 10 failures and 2 errors. The 28 October suite result above supersedes that count; its test-response/filter isolation was corrected without changing the production clock. [Complete evidence and scope](../reviews/2026-10-27-whole-system-audit.md).

## Repaired findings

| Finding | Recorded status |
| --- | --- |
| BUG01 / A03-A04: unsafe provenance and source identity | Original delivery/expiry now guards refunds/exchanges/corrections; receiving/replacement rotates identity, including same expiry. Unknown history stays nonresellable. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG03 / A06: receiving replay | Legacy receive is rejected; guarded Add/Remove/Count do not mutate twice. Real MySQL two-worker Add race commits one increment. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG04 / A10: noncash overpayment/reference | Exact GCash/Card tender, reference and zero change enforced on server and browser. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG05 / A13: Sales CSV failure/history | CSV completes generation before history and returns attachment; failure/retry tested. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG07 / A15: Summary filename | Summary uses sales_summary_; Full uses sales_report_. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG09 / A02: duplicate refund IDs | Canonical unique IDs and locked current per-item/invoice balances cap payout. Real MySQL refund race commits once. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG11 / A08: client-controlled automatic pricing | Automatic product/category and favorable Senior/PWD pricing no longer depend on hidden flags/default selector. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG12 / A09: exchange promo mismatch | Replacement engine applies automatic offers, respects lineage allowances and invalidates stale reviewed pricing/source. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| BUG13 / A11: released quarantine cost | Effective Released outcome recovers original item cost once in sale-cohort totals/history/exports. [28 October repair](../reviews/2026-10-28-selected-audit-repairs.md) |
| Stock Logs PDF exhausted memory on complete history | Fixed with direct canvas pagination; complete 1,087-row route and 10,000-row stress output verified at 128 MB. [Repair](../reviews/2026-10-23-stock-log-pdf-memory-fix.md) |
| Old checkout replay erased a newer cart | Fixed; [repair report](../reviews/2026-10-16-checkout-fixes.md) |
| Ambiguous HTTP error discarded checkout recovery | Fixed; saved-sale status is checked and uncertain state retained |
| Same-total quantity corrections omitted product movements | Fixed; product changes append correction events |
| Senior/PWD rounding selected a one-cent worse combination | Fixed in PHP and JavaScript comparison |
| Nine local master-stock discrepancies | Locally reconciled from branch balances, with audit/backup |
| Test sale #41 refunds exceeded PHP 500 by PHP 125 | Owner confirmed test data; local recorded total corrected to PHP 500 |
| Cash table filter concatenated expected/counted values | Invalid cash-pair dropdown disabled; numeric sorting retained |
| Cashier/branch filter merged names | Shared extraction separates stacked text; filtering/search verified |

## Capability gaps and unverified acceptance work

- Multiple stock lots, manufacturer lot tracking and FEFO allocation. Quarantine now supports a whole-line, single final inspection outcome; partial inspection/reversal, physical disposal certificates and supplier credits remain outside scope. See [Quarantine](../system/Quarantine.md).
- Prescription/prescriber/patient records and pharmacist dispensing approval.
- Purchase orders, supplier receiving documents/credits, routed interbranch transfers and unit conversion.
- Payment terminal/provider confirmation and settlement reconciliation.
- Full accounting, payroll, overhead and accounting journals.
- Production registration configuration, product-specific tax/benefit eligibility, actual MySQL concurrent scheduling, hosted installation and a complete disaster-recovery exercise.

These are feature or acceptance boundaries, rather than evidence of an immediate error in every transaction. See [Research limitations](Research-Scope-and-Limitations.md).

The [23 October Quarantine update](../reviews/2026-10-23-quarantine-page.md) passed its focused checks and four-process MySQL release test. Its broader 248-case business run had 237 passes, nine failures and two errors. Those results are historical; the 28 October repair verification above supersedes the selected return/exchange/receiving safeguards.

The later [Damaged Items page](../reviews/2026-10-23-damaged-items-page.md) adds visibility across damaged refund/exchange/inspection records without stock/schema/data changes. Its focused checks pass. Physical disposal, supplier credits and damaged-inventory adjustments outside the return ledger remain separate capability gaps.

The September hosting review remains historical. The 27 October audit freshly matched advisory ranges and confirmed local static exposure; actual hosted configuration and reachability remain unverified. See [Deployment](../development/Deployment.md).

Related: [Current state](Current-State.md) · [Testing](../development/Testing-and-Evidence.md) · [Review template](../templates/Review.md).
