---
title: Export functionality audit
type: review
status: findings-open
updated: 2026-10-23
tags: [review, exports, pdf, csv, testing]
---
# Export functionality audit

Not all exports work in the current source. Actual HTTP downloads reproduced a sales CSV failure and a large stock-log PDF failure. The Summary PDF downloads correctly but uses the Full PDF filename prefix. No application runtime fixes or SQL migrations were applied by this audit.

The subsequent [Stock Logs PDF repair](2026-10-23-stock-log-pdf-memory-fix.md) resolves BUG06. The results below preserve the original audit evidence; BUG05 and BUG07 remain open.

## Confirmed findings

| ID | Priority | Reproduction and effect | Code location |
| --- | --- | --- | --- |
| BUG05 | High | Reports ? Excel CSV returns HTTP 500 for daily, weekly, monthly, empty and default-date exports. The controller reads an undefined `pdfMode` key. Export history is inserted before the failure, so a failed download also appears in history. | `app/Controllers/Admin/ReportsController.php:498` and `:500` |
| BUG06 | High | Unfiltered Stock Logs ? PDF returns HTTP 500 from Dompdf memory exhaustion for the 1,087-row scratch dataset (1,031 original logs plus 56 controlled test logs). Reproduced with both 128 MB and 256 MB PHP memory limits. The 55-row branch/date PDF works; all 1,087 rows download successfully as CSV. | `app/Controllers/StockLogsController.php:129` through its full-array query and `render()` |
| BUG07 | Low | Full and Summary PDFs both use `sales_report_...pdf`. Timestamps may distinguish separate downloads, but the filename does not identify Summary. Contents and export deduplication do distinguish the modes. | `app/Controllers/Admin/ReportsController.php:709` |

Suggested repairs: remove the PDF-only filename dependency from CSV; generate its content successfully before recording an export; use `sales_summary_` for Summary PDF. Bound or split large stock PDF generation with an explicit scope/count and provide the full CSV alternative. Do not silently truncate the exported history.

## Results by export surface

| Export | Result in this audit |
| --- | --- |
| Reports Excel CSV | Fails before output; five filter/grouping variants reproduced |
| Reports Full PDF | Downloads; daily/weekly/monthly, empty-sales and current all-branch datasets checked |
| Reports Summary PDF | Downloads and reconciles; filename issue above |
| Expiry Excel CSV | Works for All, Expired, Near expiry, Good, Missing and an empty selection |
| Expiry PDF | Works for all five statuses, cashier scope and the current all-branch inventory; largest checked output is 18 pages |
| Stock Logs Excel CSV | Works with full history, branch/date/search/type filters, Unicode, complete long notes and formula protection |
| Stock Logs PDF | Works for filtered 55-row history and an empty selection; unfiltered history fails at the tested memory limits |
| Sale invoice and reprint | HTTP render and browser print buttons work; reprint identity retained |
| Refund slip | HTTP render and browser print work; correct test payout and Quarantined condition retained |
| Shift report | Both Summary and Full print controls generate PDFs; checked 1-page Summary and 3-page Full |
| Database backup download | Creates/downloads a valid .pmbak file; declared row count and all 27 table definitions match. Invalid filename rejected. No restore performed |

The historical-sales-import controller contains CSV response code but has no route or reachable export control in the current route configuration. It is not counted as an available export. Cash movements, forecast periods and restock suggestions are sections of the Reports exports, rather than separate routed downloads. Excel exports are CSV files, as their interface labels indicate.

## Verification evidence

- Actual PHP HTTP server with the current public entry point and normal route, authentication and CSRF filters; local disposable MySQL schema copied from the application database, with controlled branches/accounts/sales/refund/stock/shift fixtures. Sessions and cache isolated before the final audit run.
- Final HTTP matrix: **179 checks, 152 pass and 27 failed assertions**, covering **37 file/render requests** plus backup operations. Those 27 failures are repeated assertions for the three findings above, not 27 separate defects. Five sales CSV requests and one large stock PDF request fail.
- **25 focused PHP cases / 270 assertions pass**. The runner reports that no coverage driver is installed. One existing exchange receipt test expected the former heading, ?Review your exchange?; its assertion now checks the implemented ?Exchange overview? so both replacement and refund receipt checks execute. This is a test-only update.
- Export-link JavaScript checks pass: request identifiers, retained filters, repeated clicks, middle-click and fallback behavior.
- **16 successful report PDFs / 64 pages**, plus **5 browser-generated print PDFs / 7 pages**: **21 PDFs / 71 pages** rendered with Poppler and reviewed. A4 landscape, repeated report identity/page numbering, page bounds, all 55 stock-log rows, full note ending and reconciliation values checked. Receipts use 80 mm width; shift reports use A4 portrait.
- Sales Summary controlled example: PHP 224 original minus PHP 40 discounts, PHP 24 VAT relief and PHP 80 refunds equals PHP 80 remaining sales. The printed payout is PHP 80.
- Cashiers cannot download administrator reports/backups. Cashier expiry/stock exports ignore a forged other-branch selection. Other-branch receipt, refund and another cashier's shift are denied. Anonymous requests are denied.
- Backup bytes are parsed and checked; the temporary backup is removed. No restore, hosted upload, phpMyAdmin import or physical printer test performed.

Artifacts: `writable/export-audit-20261023/http-results.json`, `http-large-256M.json`, `pdf-results.json`, `print-results.json`, `backup-summary.json`, `history-effects.json`, `preservation.json` and `unit.xml`. Generated pages are under `tmp/pdfs/export-audit/` and are verification samples, not replacement application files.

## Data preservation and limits

The scratch schema was removed. Existing sales, sale/refund lines, inventory, users, shifts and migration row hashes match the starting snapshot. Two source tables changed during concurrent use of the real local application: report export history and user presence. These changes were retained. The six failed-login audit messages from the initial test setup were removed with an exact original-row-hash check; existing authentication history was restored. No sales, stock, refund or shift records were edited in the application database.

This is a local export audit, not a hosted deployment check, a clean whole-system test result or a guarantee for every future report size. The backup test verifies creation/download/format, not a complete disaster-recovery exercise. Remaining inventory defects are tracked in [Known issues](../project/Known-Issues.md).

## Repeat the audit

From the project root, prepare the fixtures with `php -d extension=sqlite3 tests/integration/exports.php`, then run `node tests/integration/exports-http.mjs`, `node tests/integration/exports-print.mjs` and the bundled Python with `tests/integration/exports-pdf.py`. The HTTP runner intentionally fails while the findings remain reproducible. The PDF inspector requires pdfplumber, Pillow and the configured Poppler path. Finally run `php -d extension=sqlite3 tests/integration/exports.php cleanup`. Cleanup removes only the guarded scratch schema and checks source row hashes; any concurrent source changes are reported rather than reverted.

Related: [Reports and forecasts](../system/Reports-and-Forecasts.md) ? [Testing](../development/Testing-and-Evidence.md) ? [Current state](../project/Current-State.md).
