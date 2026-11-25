# PDF export update - 16 October 2026

The Sales/Forecast, Expiry and Stock Logs PDF exports now use a shared A4 report layout with repeated identity, report references, Philippine timestamps and page numbering. Sales reconciliation includes every displayed adjustment, while inventory reports include units and stable identifiers.

## Using the updated exports

- On **Reports**, apply the branch, sales dates and forecast filters, then select **Full PDF** or **Summary PDF**. Both exports use those applied filters. Summary PDF groups receipts/payouts by payment method and omits detailed product, invoice and forecast tables.
- Expiry Report and Stock Logs retain their existing PDF buttons and access rules.
- Summary PDFs and empty sales reports fit one page in the tested examples. Full reports preserve their detail and may span several pages.

## What changed

### Sales and Forecast

- Replaced the misleading PDF gross-sales label with **Original sales**, using the recorded prices before discounts, VAT relief and refunds. The underlying legacy gross-sales field remains available to existing report consumers.
- Summary and period tables show discounts, recorded VAT relief, refunds against the selected sales and remaining sales. Historical differences not recorded as VAT relief appear as **Other adjustments**, including in period detail when applicable.
- The tested PWD sale reconciles as **224.00 - 40.00 - 24.00 - 80.00 = 80.00 PHP**. Checkout and refund controllers supplied these amounts in isolated SQLite.
- Notes define the VAT basis, sale-date versus refund-date treatment and revenue-transaction statuses. Fully refunded/void invoices remain excluded from the existing revenue transaction count; return-related cost losses remain in recorded profit.
- Product/invoice summaries are explicitly labelled **Top 10 Best-Selling Products** and **Latest 10 Sales**. Their limits are stated. These lists are management summaries, rather than a complete sales ledger.
- Summary and Full PDF exports have distinct filenames and duplicate-request identities. An actual retry remains deduplicated.
- Empty sections no longer generate several pages of empty tables. Forecast estimates and current-stock reorder suggestions remain clearly identified.

### Expiry

- Added stable product IDs, stock units and days to expiry. Dates in the detail table use unambiguous YYYY-MM-DD formatting.
- Retained SKU, manufacturer, supplier, branch, quantity, expiry and status in separate columns. The report describes actions for expired, near-expiry and missing-date stock.
- Added source labels and a compact receiving-reference lookup. This prints the current branch-inventory ID and its recorded internal stock-provenance reference; repeated references are listed once.
- Unknown units/references are explicitly marked as not recorded. Manufacturer lots are identified as **Not recorded**; an internal receiving reference is never represented as a manufacturer's batch number.

### Stock Logs

- Added SKU and stable product ID, quantity units and recorded invoice references for POS sales, sale corrections and partial refunds. Other movements retain the stock-log ID as their source reference.
- Quantity now states the actual signed movement: **new stock - previous stock**. Positive values add stock; negative values remove it. Before/after values remain visible.
- Lengthy remarks use a log-number reference in the main table. Their complete text appears in a notes appendix, without truncation.
- Names, SKUs and units are explicitly identified as current catalog values. Product IDs remain stable when names change.

### Pagination and document identity

- The first column-heading row stays with the first data row. Later rows paginate normally and repeat the column headers. This fixes the orphaned forecast heading from the review.
- Every page contains the report type, branch, reporting scope, report reference, generation time and page number. PHT is explicitly defined as Asia/Manila.
- The report reference also appears in PDF metadata. Currency symbols, text escaping and long names are preserved.
- Shared styles retain readable 9-point table text; full movement notes flow outside narrow table cells.

## Verification

- **9 targeted PHP tests, 94 assertions passed**, including daily/weekly/monthly reconciliation, real partial refunds, inventory queries, escaping and distinct Summary/Full export retries.
- Existing export-link JavaScript checks passed.
- **14 synthetic PDFs, 39 pages**, generated through the actual views, Dompdf and page-stamping code; rendered and visually inspected. Tests use in-memory SQLite, rather than the application database.
- Document checks verified A4 landscape dimensions, running identity, scope/reference/page numbers, table continuation labels, all **35 stock-log IDs**, all **36 expiry SKUs**, and the complete text of the five long stock-log notes.
- The sales summary and longer payment-method summary each fit **one page**. The empty sales sample changed from **3 to 1 page**, the longer full sales sample from **8 to 7 pages**, and the longer stock-log sample from **10 to 7 pages**. Expiry includes additional traceability information and its lookup; the four-record sample spans two pages and the 36-record sample spans five.

These counts apply to the tested A4 output. The work does not certify other inventory logic or replace a complete financial audit.

## Installation

Use **build/hostinger-pdf-exports-2026-10-16.zip**. Upload its `app/` folder into the existing application's root, preserving all directories and replacing the corresponding files. Upload all files together: the controllers depend on the new shared library and views.

**No SQL import is needed.** This update uses existing invoice, VAT-relief, product-unit and stock-provenance fields. It does not change seller configuration, passwords, stock balances or saved sale/refund amounts. It requires the previously installed invoice and inventory upgrades.

The patch contains eleven application files plus this guide. The package is verified against the current source hashes and excludes configuration, uploads, writable data, synthetic fixtures and test credentials.

## Data limits retained explicitly

The existing inventory model records a current branch-product balance and an internal delivery/provenance identifier. It does not capture manufacturer lot numbers or multiple independently counted lots. Historical stock-log names/units are not snapshotted. This PDF update exposes stable IDs and accurately labels current or missing information; it does not invent past data or introduce a new lot-tracking workflow.
