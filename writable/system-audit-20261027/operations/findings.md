# Operations audit — 27 October 2026

Read-only runtime review and a rerunnable in-memory SQLite reproduction using the existing BusinessLogicTest fixture and mock session. No application database, live browser session or hosting changes. The bounded CLI needed an approved sandbox exception for PHP file-read metadata; evidence remains inside the workspace. Runtime files were not edited.

## New confirmed findings

1. **Medium: released quarantine stock is still treated as unrecovered cost in reports.** ReportsController.php:67-72 only recovers inventory cost when the original refund condition is resellable. Quarantine.php:113-129 correctly increases available stock and writes a released resolution, preserving the original quarantined condition. These workflows are not joined. Reproduction: sale of 5 units at PHP10 with unit cost PHP4, return2/refund20 into quarantine, then release2. Available stock10->12; report COGS20 and grossprofit10 remain unchanged, whereas recovering PHP8 cost gives COGS12/grossprofit18 for remaining net30. The same join feeds both report totals (1055-1060) and grouped charts (1330-1334); CSV/PDF inherit it. Preserve the original return condition and account for released resolutions in cost recovery; ensure no double recovery.

2. **Medium: restock calculations count expired stock as available cover.** ReorderForecastService.php:104-119 filters physical stock <= reorder threshold with no expiry selection;192 uses that stock in days-left213 and suggested quantity214-215. A product with demand5/day,10 expired units, reorder100 still receives days_left2/suggest140 just like future-dated inventory. All10 are unsellable, so sellable cover is0 and 30-day order is150. With150 expired units/reorder100, it disappears entirely. Expiry alerts remain separate, so this is a misleading availability/replenishment calculation rather than a failure to label expiry. Use sellable availability for forecasts and retain physical balances for audit/disposal.

3. **Medium: automatic product/category promo rules can be skipped by changing client fields.** SalesController.php:777-789 runs automatic offers only for discount_id=auto. A blank selection skips them;839 combines benefits only if include_product_promos=1. These hidden/client controls therefore determine whether the owner-mandated automatic policy executes. Actual checkout with active50% product promo and blank discount_id saves total10/discount0/final10 instead5. An eligible VAT-inclusive112 PWD sale with50% promo and include_product_promos=0 completes at80 instead56. This requires an authenticated crafted POST/client modification; the normal UI currently supplies the expected fields. Derive automatic matching on the server, using client preview only to confirm freshness.

## Prior findings revalidated

4. **High: Sales CSV export still fails and records a successful-looking history row first.** ReportsController.php:498 writes export history;500 reads the absent pdfMode field. Reproduction catches Undefined array key pdfMode at500 after report_exports count becomes1. PDF mode is only initialized in the PDF path689. Previous routed audit already established HTTP500; this run revalidated actual controller exception with isolated data.

5. **Medium: digital checkout accepts cash-style excess payment/change and no reference.** SalesController.php:798-811 validates method, amount and reference length but not required digital reference/exact settlement;933-937 accepts amount >= final and computes change for every method. Both GCash and Card save a completed final10 sale with amount_paid100/change90/referenceNULL. There is no payment-provider integration, so do not describe this as bypassing an actual payment terminal. It is an inconsistent manual settlement record and cashier workflow.

6. **Low: Summary PDF filename remains indistinguishable from full report.** ReportsController.php:689 selects correct summary content;704 always prefixes sales_report_. Source-confirmed; not rerendered during this subaudit.

## Reviewed protections and limits

Reviewed cent-based DiscountPolicy/DiscountAllocator and BenefitProductPromotion comparison, beneficiary/eligibility validation, stock/discount revalidation on checkout, drawer ownership and lock order, immutable closed shift snapshots, negative-cash payout guard, refund-date cash movements vs sale-date revenue, exchange-credit cancellation, branch-specific zero-filled Holt demand series, and explicit forecast snapshot writes. Existing unit tests exercise these paths; the parent audit runs/reports the suite counts.

Forecasts are a simple Holt model with fixed30-day coverage, net returned units removed from historic demand, and no supplier lead-time, stockout-censoring, seasonality model or out-of-sample accuracy evidence. These are capability/research limitations, distinct from the expired-stock calculation bug. Sales reports deliberately use sale-date cohorts/current corrected values; cash movement reports use payout dates, so differences across those views are not automatically reconciliation errors.

Artifacts: reproduce.php and results.json in this directory. Ten observations across quarantine release, both digital methods, blank automatic selection, three stock-expiry variants, PWD hidden flag, CSV exception/history, and fully refunded damaged stock. No hosted verification or real concurrent MySQL scheduling was attempted here.

## Checked and not reported as a bug

A fully damaged refunded invoice retains its inventory loss. Actual buildReportData returns sales net0, cost20 and gross profit-20, both in totals and grouped history; cash movement separately shows receipts50/payout50/net0. Revenue cohorts deliberately exclude fully refunded invoices, but cost/profit queries include them (ReportsController1055-1062 and1310-1337). PDF text explicitly states this convention (reports_pdf.php34). This is distinct from the unrecovered cost after quarantine release.
