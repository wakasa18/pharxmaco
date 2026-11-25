# PDF export format review - 16 October 2026

**Updated:** the format and presentation fixes are described in the [PDF export update](2026-10-16-pdf-export-update.md). Missing manufacturer lots and current catalog identity are now labelled explicitly; their underlying data-model limits are retained. The original review follows below.

The three direct PDF exports have a usable management-report layout. Their headings, filters, typography, currency symbols, table alignment and page numbering work in the reviewed samples. They need improvements to financial presentation, one table page break, document identification on continuation pages and inventory traceability. No production application files or application database records were changed for this review.

## Coverage and method

All direct PDF routes found in the application were checked:

| Export | Route | Template |
| --- | --- | --- |
| Sales and Forecast | `admin/reports/pdf` | `app/Views/admin/reports/reports_pdf.php` |
| Expiry | `admin/expiry-report/export-pdf` | `app/Views/admin/reports/expiry_report_pdf.php` |
| Stock Logs | `stock-logs/export-pdf` | `app/Views/stock_logs/pdf.php` |

The actual view templates, shared styles, PdfFactory/Dompdf renderer and controller page-number methods generated 11 synthetic PDFs. All 38 pages were rendered and visually reviewed, with closer inspection of normal reports and problematic page boundaries. A narrowly scoped CPDF text inspection confirmed page numbers, the missing forecast table heading and complete record IDs in the longer inventory samples.

A separate probe executed the real checkout, partial-refund and report-building code against isolated in-memory SQLite. It did not connect to the application's data. The existing export-link checks also passed: double-click handling, fresh request IDs, filter retention, middle-click and fallback behavior.

| Sample | Pages |
| --- | ---: |
| Sales - daily, one partially refunded PWD sale | 3 |
| Sales - weekly layout | 3 |
| Sales - monthly layout | 3 |
| Sales - empty sections and zero totals | 3 |
| Sales - 14 periods, 10 recent sales, 10 products, 10 restock rows | 8 |
| Expiry - four statuses | 1 |
| Expiry - empty | 1 |
| Expiry - 36 product records | 4 |
| Stock Logs - normal | 1 |
| Stock Logs - empty | 1 |
| Stock Logs - 35 records, including five approximately 500-character remarks | 10 |

These are controlled layout examples, not performance tests or an exhaustive financial audit. The weekly and monthly samples test period labels and layout. Financial evidence below comes from the actual daily report query. Browser receipt/refund/exchange Save as PDF flows are separate from these direct exports. The shift print report was reviewed and improved in the [preceding shift report work](2026-10-16-shift-print-update.md).

## What works

- A4 landscape dimensions, physical margins, readable 9-point table text, visible peso symbols and right-aligned amounts were verified.
- First pages identify the business, report title, branch, relevant filters, generator and generation time. Sales also labels currency and Asia/Manila time.
- Page X of Y appears on all 38 pages. Table column headers repeat on most continuation pages; the specific exception is documented below.
- Long product, branch and staff names wrap within their columns. No overlapping or clipped body rows were identified in the reviewed samples.
- Empty states use explicit messages. Expiry's expired, near-expiry, good and missing-date states have readable text as well as color.
- Cash receipts, refund payouts and net movement are separated, with totals and an explanation of their different date bases. Change is excluded from receipts; unknown historical payout methods have a warning.
- Forecasts are explicitly described as planning estimates. History range, grouping and future periods are identified. Restock coverage is explained.
- All 35 synthetic stock-log IDs and all 36 expiry SKUs were present exactly once. Stock Logs exports the complete filtered query, rather than only the on-screen page.

## Findings

### 1. Medium - Sales totals lack a complete reconciliation

`ReportsController.php:1035` and `:1277` calculate the displayed "Gross Sales" after subtracting recorded refund payouts. The PDF summary and period table show discounts and net sales, but no VAT-relief amount or complete original-sales-to-net bridge.

The real-controller probe produced this example:

| Component | PHP |
| --- | ---: |
| Two units at recorded VAT-inclusive price 112 | 224.00 |
| PWD discount | -40.00 |
| VAT relief | -24.00 |
| Original amount payable | 160.00 |
| Partial refund of one unit | -80.00 |
| Remaining sale value | 80.00 |

The PDF prints **Gross Sales 144.00, Total Discount 40.00, Net Sales 80.00**. Subtracting its displayed discount from its displayed gross gives 104.00. The missing 24.00 is VAT relief. The net amount in this example is correct; the report does not provide enough information to explain it, and its gross label conceals a refund adjustment.

Recommended: print a clearly labelled bridge from original recorded sales through discounts, VAT relief and returns/refunds to remaining value. State the VAT basis of the figures, distinguish sales-value adjustments from refund-date payouts, and define the transaction statuses included. Fully refunded sales are currently excluded from the revenue transaction count. This should be explicit if that count is retained.

As an operational comparison, [Shopify's analytics field definitions](https://help.shopify.com/en/manual/reports-and-analytics/shopify-reports/report-types/analytics-fields) distinguish gross sales before discounts/reversals, net sales after adjustments and total sales including taxes. The recommendation is to make this application's own basis equally clear, rather than silently treating different measures as interchangeable.

### 2. Medium - A sales table can continue without its column headings

In the longer Sales and Forecast sample, "Past Sales and Estimated Values" and its three-column heading print at the bottom of page 5, with no data row. Page 6 begins with October 1 and the two monetary values, without repeating Period / Actual Net Sales / Estimated Sales.

This was confirmed both visually and in the generated PDF content, not inferred solely from the CSS. The shared `table-header-group` rule works elsewhere but does not protect this boundary.

Recommended: keep the section title, column heading and first data row together, then repeat column headings on every continued page. Verify the actual Dompdf output after changing pagination rules.

### 3. Medium - Expiry is useful for screening, but incomplete for tracing stock

The expiry report includes product, SKU, manufacturer, supplier, branch, quantity, expiration date and status. It does **not** print the unit represented by the quantity or a batch/lot reference. The note says quantities use each product's recorded unit, but a reader cannot see that unit on the page.

Recommended: add units and, when genuinely recorded, batch/lot and receiving references. Days to expiry and an action/review column would help staff prioritize checks. Batch reporting requires the underlying data to support multiple lots; an internal stock-delivery identifier must not be presented as a manufacturer's lot number.

This recommendation is consistent with the traceability and batch/expiry records discussed in [WHO's good storage and distribution guidance, section 18.35](https://cdn.who.int/media/docs/default-source/medicines/norms-and-standards/guidelines/distribution/trs1025-annex7-%281%29.pdf?download=true&sfvrsn=1f96888d_4). The current report can still serve as a product/branch screening list.

### 4. Medium - Stock Logs needs stronger product and movement identification

The report identifies log ID, product name, branch, actor, action, quantity, previous/new stock, remarks and date/time. It omits SKU/product ID, quantity unit and dedicated receiving/sale/adjustment references. The printed ID is the log ID.

`StockLogsController.php:166` joins the current product name from the catalog. Renaming a product can therefore change the name shown when an older log is exported. Historical product-name snapshots are not part of this query.

Recommended: print SKU or a stable product ID and unit; clearly label quantity as a movement/change and retain before/after counts. Add actual source-document references when available. Historical identity preservation is a data-model improvement, not just a PDF style change.

### 5. Low - Continuation pages lose report identity

Only page 1 contains the report branch, filters/range and generation time. Later pages have a generic management/stock-movement footer and page number. A detached continuation page cannot reliably be matched to a branch and reporting period.

Recommended: repeat a compact report title, branch, date range/as-of date and report reference on every page. Add an explicit timezone to Expiry and Stock Logs timestamps, as Sales already does.

### 6. Low - Limited sales lists should identify their scope

The main PDF's Best-Selling Products and Recent Sales queries each use `limit(10)` (`ReportsController.php:1089`, `:1110`). The headings do not state Top 10 / Latest 10 or explain that these lists are not the complete transaction detail supporting the summary.

Recommended: label the limits. Keep a separate complete transaction export/appendix if reconciliation against every invoice is required.

### 7. Low - Some reports use excessive paper

The empty sales sample occupies three pages of zero totals and empty tables. The 35-row stock sample occupies ten pages when five long remarks force large unsplittable rows and unused space before them. The text remains present, but the layout is cumbersome for routine review.

Recommended: use a compact empty state, separate summary from optional forecast/detail sections, and put lengthy remarks in a numbered notes appendix while preserving their complete text. Avoid reducing font size simply to force more rows onto a page.

## Suggested order of improvement

1. Correct the sales labels and print a complete financial reconciliation.
2. Fix the orphaned table heading and repeat report identity on continuation pages.
3. Add units and stable product/source identifiers to inventory reports; plan batch capture separately where needed.
4. Label the Top/Latest 10 limits and simplify empty/long reports.

This review added only documentation and synthetic QA helpers/artifacts. No SQL import, application deployment or data correction was performed.
