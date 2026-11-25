# Reports overview reconciliation update

20 October 2026

The report now shows Original sales, Discounts, VAT exemption and Refunds against sales separately, followed by the net-sales formula. The supplied screenshot's PHP 5.89 difference is recorded VAT exemption. Saved sales and balances are unchanged.

## Install on the existing application

1. Keep a copy of the four existing files listed below before replacing them.
2. Copy the archive's `app/` and `public/` contents into the matching directories of your existing application, preserving the paths. Upload all four files together.
3. Open Reports and refresh with Ctrl+F5.

Runtime files:

- app/Views/admin/reports/index.php
- app/Views/admin/reports/_sales_summary.php
- app/Libraries/ReportPdf.php
- public/assets/css/reports.css

No SQL import is needed. The existing receipt/invoice VAT fields must already be installed. This archive contains no database configuration, passwords or database export.

For 21 September–20 October 2026, the supplied export plus prepared month-demo history reconciles as:

PHP 238,862.00 original sales - PHP 779.02 discounts - PHP 5.89 VAT exemption - PHP 440.00 refunds = PHP 237,637.09 net sales.

The previous Gross sales amount, PHP 238,422.00, already deducted those refunds. Verify the four deductions and net total using the same branch/date filters after uploading. Later transactions can change these example totals.

Four focused tests / 107 assertions and 18 desktop/mobile browser scenarios passed locally. The hosted system has not been changed automatically. CSV columns and saved financial records retain their existing format.
