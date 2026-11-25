---
title: Reports forecasts and exports
type: system-reference
status: current
updated: 2026-10-29
tags: [reports, forecasts, pdf]
---
# Reports, forecasts and exports

Dashboard, Reports, My Day and shift summaries use different scopes. Read each date/branch label before comparing totals. A net sales amount, provider collection total and physical drawer balance answer different questions.

Reports provide sales summaries, product/invoice lists, sales forecasting. Product reorder forecasts and restock suggestions have been removed. Expiry Report and Stock Logs have separate filters and exports. PDF/CSV use the applied report filters; browser quick search and the current DataTables page do not necessarily define an export's scope.

## Current export verification

The [23 October export audit](../reviews/2026-10-23-export-functionality-audit.md) found that sales CSV fails with an undefined PDF-mode key, full stock-history PDF exhausts the tested 128/256 MB PHP limits, and Summary PDF uses the Full filename prefix. The Stock Logs PDF finding was subsequently [repaired and verified](../reviews/2026-10-23-stock-log-pdf-memory-fix.md) at 128 MB for 1,087 routed records and 10,000 synthetic records; the later [28 October repairs](../reviews/2026-10-28-selected-audit-repairs.md) resolve Sales CSV and Summary filenames. Exports finish generation before history is recorded; Full/Summary routed attachments and generation-failure retry were tested. Filtered stock PDF, stock CSV, expiry CSV/PDF, sales Full/Summary PDF contents and browser print reports pass their recorded checks.

## Current logic findings

The [28 October repairs](../reviews/2026-10-28-selected-audit-repairs.md) fix CSV generation/history, Summary filenames and recovered cost for released quarantine units. Cost/profit for an original sale-date cohort uses current return outcomes; this can restate derived earlier-period results and is not an inspection-date accounting journal. Original refunds are unchanged. Fully refunded damaged loss remains retained. The former A12 reorder-calculation issue is retired with the feature; its historical algorithm was not repaired. [Removal](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md).

## Financial meaning

Original sales, discounts, VAT relief, refunds and remaining sales are shown separately. Older unexplained differences are labeled Other adjustments rather than silently attributed to VAT relief. Sale-date revenue and later refund-date money movement can differ. Gross merchandise profit excludes full business overhead and accounting adjustments.

The Reports overview now shows Original sales before deductions, Discounts, VAT exemption and Refunds against sales, with the net-sales formula. Its previous Gross sales card had already deducted refunds and omitted the separate VAT-exemption amount. The [20 October repair](../reviews/2026-10-20-report-reconciliation.md) reproduces the supplied screenshot exactly and aligns the overview with the existing PDF reconciliation. CSV columns retain their existing format.

The management PDF labels Top 10 products and Latest 10 sales explicitly. Full PDF is a detailed report layout, rather than an unlimited invoice ledger. Summary PDF condenses the financial/payment tables. CSV export is a CSV file compatible with spreadsheet tools, not a formatted XLSX workbook.

## Forecasting

Reports uses Holt-style level/trend estimates for sales revenue. Update Forecast saves seven future sales periods through `SalesForecastSnapshot`; the saved-records modal shows sales periods only. Ordinary low-stock alerts compare physical recorded quantity with staff-maintained reorder levels. No suggested order quantity, days-left forecast or reorder recommendation is generated. Historical product forecast rows are retained.

The old `forecast:reorder` entry point is a retired no-write compatibility stub. Remove any old host cron entry; no host scheduler was changed automatically. The legacy `ReorderForecastService` is not called by active runtime workflows.

## Printable outputs

Sales/forecast, expiry and stock-log PDFs use shared A4 landscape report identity, repeat headings, timestamps, page numbers and stable identifiers. Stock-log deltas are new minus old; long remarks continue in an appendix. Internal receiving IDs are labeled separately from missing manufacturer lots.

Shift reports use their separate A4 portrait layout. Receipts use browser printing and their configured thermal width. [PDF update](../reviews/2026-10-16-pdf-export-update.md) and [shift print update](../reviews/2026-10-16-shift-print-update.md) retain the verified samples and limits.

Code: `app/Controllers/Admin/ReportsController.php`, `StockLogsController.php`, `app/Libraries/SalesForecastSnapshot.php`, `ReportPdf.php`, `CsvExport.php`.

Related: [Evidence](../development/Testing-and-Evidence.md) · [Research limitations](../project/Research-Scope-and-Limitations.md) · user manual Chapters 23, 27 and 28.
