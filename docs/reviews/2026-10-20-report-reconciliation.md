---
title: Report overview reconciliation and VAT exemption
type: repair-report
status: implemented-locally
updated: 2026-10-20
tags: [reports, vat, refunds, reconciliation]
---
# Report overview reconciliation and VAT exemption

The supplied screenshot showed gross sales of **PHP 238,422.00**, discounts of **PHP 779.02**, and net sales of **PHP 237,637.09**. Subtracting only the displayed discounts leaves an unexplained **PHP 5.89**. The missing deduction is recorded VAT exemption, which the report query already includes but the overview did not display.

## Reproduced amounts

Read the previously supplied `u315645644_pharxmacotest (7).sql` as data and combined its selected sales with the already-prepared month-demo manifest. For **21 September–20 October 2026**, this reproduces all three screenshot amounts and **633 revenue transactions** exactly. No application database or hosted database was queried or modified.

| Component | PHP |
| --- | ---: |
| Original sales before deductions | 238,862.00 |
| Less discounts | 779.02 |
| Less recorded VAT exemption | 5.89 |
| Less refunds against the selected sales | 440.00 |
| Net sales | 237,637.09 |

The old **Gross sales** value already subtracted the PHP 440.00 refunds: `238,862.00 - 440.00 = 238,422.00`. Therefore the screenshot reconciles as `238,422.00 - 779.02 - 5.89 = 237,637.09`. This is a missing/misleading presentation issue; the reproduced net total is correct.

## Application change

The overview now shows **Original sales**, **Discounts**, **VAT exemption** and **Refunds against sales** separately, with the net-sales formula and resulting amount. Transactions and average sale remain visible. The headline description now mentions VAT exemption. The desktop summary uses three columns; smaller screens use two.

The screen reuses `ReportPdf::reconciliation()`, which already presents these components in PDF reports. Unexplained recorded invoice differences appear as **Other adjustments**, including negative differences, and are not invented as VAT exemption. Existing revenue-status selection, invoice values, refunds, forecasts and the legacy `gross_sales` data field remain unchanged. This repair changes the overview; it does not change CSV export columns or rewrite prior export-history records.

Changed runtime files: `app/Views/admin/reports/index.php`, new `app/Views/admin/reports/_sales_summary.php`, and `public/assets/css/reports.css`. The report stylesheet URL was updated to refresh browser caches.

## Validation

- The new overview assertions failed before the view repair. Afterward, the focused report/PDF selection passed **4 tests / 107 assertions** in PHP 8.3.0 and in-memory SQLite. This includes actual statutory checkout and partial-refund data across daily, weekly and monthly grouping; the screenshot amounts; empty data; and positive/negative unexplained differences.
- **18 browser scenarios passed**: screenshot, legacy-difference and empty fixtures at 390, 768 and 1440 pixels in both themes. Checked every displayed deduction, final animated headline, footer net total, six summary cells, responsive columns and absence of clipped metrics/page overflow. Desktop light and mobile dark screenshots were visually inspected.
- PHP syntax checks passed for both views and the updated test file. The standalone source audit confirms the export hash and exact screenshot reconciliation.

Evidence: `writable/reports-reconciliation-review/source-audit.json`, `browser-checks.json`, `audit.cjs`, `render.php`, and `summary-*.png`. Browser command: `node writable/receipt-review/check-reports.cjs --reconciliation-only`. No forms, sales or exports were submitted to an application server. This is focused verification; the historical inventory/expiry full-suite failures were not reassessed.

## Hosted installation

Use `build/hostinger-reports-reconciliation-2026-10-20.zip`. It contains the three updated runtime files plus their existing `app/Libraries/ReportPdf.php` dependency, deployment instructions and hashes. Copy its `app/` and `public/` contents into the matching directories of the existing application, then refresh Reports with Ctrl+F5. Upload all four runtime files together. No SQL import is needed; existing invoice/VAT fields are required. The hosted application was not deployed or otherwise changed by this task.

Related: [Reports and forecasts](../system/Reports-and-Forecasts.md) · [PDF reconciliation update](2026-10-16-pdf-export-update.md) · [Testing and evidence](../development/Testing-and-Evidence.md).
