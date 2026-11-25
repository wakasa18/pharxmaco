---
title: "Sales invoice receipt fields"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-14-receipt-invoice-fields.md"
source_sha256: 073e40ffbbf3625f8f013228e1915ed045a5b778e0a3b53ecfcaebe447bb9b26
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-14-receipt-invoice-fields.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Sales invoice receipt fields

Adds the SALES INVOICE heading, configurable seller TIN and VAT/Non-VAT status, registered-series numbering, VAT/Non-VAT breakdowns, recorded discount names and amounts (including Senior Citizen/PWD promotions), and generic medicine names and strengths.

Sales invoices show the selling branch's business address below its branch name. Configure this address in **Branches → Edit → Address**. Existing store branding and telephone remain. No BIR-registered business-name field or POS authorization details are added. Refund slips retain their existing header.

## Registration values

The seller has not supplied a TIN, tax status or registered invoice series yet. Defaults in `app/Config/ReceiptInvoice.php` leave these values empty. New invoices show **Not configured**; older invoices without the new snapshots show **Not recorded**. Existing transaction references remain available. No registration identity or authorized serial is invented.

Set actual values in `app/Config/ReceiptInvoice.php`, or use the corresponding environment keys:

```ini
receiptinvoice.tin = ""
receiptinvoice.taxStatus = ""
# Set "vat" or "non_vat" after confirming the seller's registration.
receiptinvoice.vatRate = 12
receiptinvoice.serialPrefix = ""
receiptinvoice.serialStart = 1
receiptinvoice.serialEnd = 0
receiptinvoice.serialDigits = 8
```

Replace the empty registration values and number range with the actual registration. `serialEnd = 0` disables registered-series allocation. Configuring a positive end enables a transactionally locked counter shared by checkout and exchanges. Serial numbers are saved on each sale and retained on reprint; rolled-back transactions do not consume numbers, retries do not issue duplicates, and exhausted ranges cannot issue another number. The counter for a prefix persists across configuration changes.

Saved TIN, status, VAT rate, invoice serial, discount name and medicine details remain unchanged when configuration or products are edited later. Registration changes apply to future sales.

If Complete Sale returns to the POS because the registered range is exhausted, the page now displays the specific invoice error and preserves the cart. `serialStart = 1` with `serialEnd = 1` allows only one invoice. Until a registered range is available, keep `serialEnd = 0` to use transaction references. Existing serial counters and saved invoices are retained.

## Tax and medicine details

Product Create/Edit accepts generic name, strength and tax classification. The Tax classification dropdown defaults to **VATable** for new products and existing products with a blank classification. Other classifications can still be selected explicitly. Leave unknown medicine details empty; they are never guessed from a brand. VAT calculations require a recorded classification for every line: VATable, VAT-exempt or zero-rated. Unknown classifications show an unavailable breakdown. Non-VAT invoices show net Non-VAT sales and zero VAT charged.

VAT breakdowns treat recorded selling prices as VAT-inclusive and split the actual net amounts after recorded discounts. The breakdown reconciles to the recorded sale total and does not add a charge. Older/inconsistent records are not assigned invented tax history.

Senior Citizen/PWD discounts print the selected discount's recorded name and actual amount. This receipt change does not add beneficiary verification, statutory VAT relief or new discount calculations. It displays the existing checkout results.

This format follows the requested exclusions; it is not a certification of BIR compliance. The BIR's [sample invoice requirements](https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%2077-2024%20Annex%20A1-B6.pdf) include seller registration information beyond the retained fields.

## Deployment

The additive migration has been applied to the local application database. Existing product and sale row counts were preserved. For another installation, select its existing database and import **all** of `2026-10-14-receipt-invoice-fields.sql` before uploading the matching PHP files. The upgrade is repeatable and adds seven columns plus `receipt_invoice_sequences`; it preserves sales, payments, stock and transaction references.

The runtime package is `build/hostinger-receipt-invoice-fields-2026-10-14.zip`. Upload files to their matching application folders, preserving chosen receipt configuration. If public assets are served directly from `public_html/assets`, place the stylesheet under `assets/css/receipts.css`. The SQL and this guide should stay outside the public web root.

## Validation

36 receipt, checkout, exchange and product tests passed (296 assertions). This includes snapshot persistence, retry behavior, tax reconciliation, output escaping, serial rollback/range exhaustion and a repeatable migration. Four stock-expiry checks failed identically against saved original runtime files; they predate this change.

The checkout follow-up passed eight invoice/recovery tests (74 assertions), including exhausted-range error reporting, retaining the cart and stock on failure, and successful checkout with numbering disabled while preserving the existing serial counter. The effective local configuration was checked without creating a sale.

42 browser checks passed across all seven invoice variants at 320, 390, 768 and 1440 pixels, plus 58 mm and 80 mm print media. The thermal screenshots were visually inspected. Screenshots are saved under `writable/receipt-invoice-review` for review. Physical printers have not been tested. The older refund regression script reaches its refund-preview check and fails on a transparent header-background expectation; the invoice changes do not modify that header background rule.
