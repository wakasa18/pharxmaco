---
title: "Thermal receipt styling"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-01-thermal-receipts-deployment.md"
source_sha256: 3f81518d12daf675cba6f5fcd7551a44c5a3bee94733327b12ef6e12bbbf5f52
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-01-thermal-receipts-deployment.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Thermal receipt styling

Package: `build/hostinger-thermal-receipts-2026-10-01.zip`.

Sales receipts, reprints, refund slips and the refund preview now share a traditional thermal receipt appearance: Courier-style monospace text, centered business details, compact item lines, aligned amounts, dashed separators and a double rule above the total.

The page supports 58 mm and 80 mm paper widths. Print output uses black text on white paper, with 8 pt text for 58 mm and 9 pt text for 80 mm. Long names and readable dates wrap within the receipt. Screen previews retain the existing theme support.

## Upload

Back up and replace these files in the corresponding project folders:

1. `public/assets/css/receipts.css`
2. `app/Views/partials/receipt_header.php`
3. `app/Views/cashier/sales/receipt.php`
4. `app/Views/cashier/sales/refund_slip.php`
5. `app/Views/cashier/sales/refund_partial.php`

If the public folder contents are deployed directly into `public_html`, upload the CSS to `public_html/assets/css/receipts.css`. Follow the application's existing folder mapping for the PHP views.

The receipt views request CSS version `20260930-1`. Refresh the receipt page after uploading. This package assumes the existing receipt scripts, shared partials and preceding readable-dates update are installed. No database changes are needed. Calculations, payment records and refund behavior are unchanged.

## Print settings

Select 58 mm or 80 mm on the receipt page and choose the matching paper size in the printer settings. Print at Actual size / 100% and disable browser headers and footers. The preview on screen is enlarged for readability; the print layout uses millimeters.

## Verification

All four PHP files passed syntax checks. Seven receipt fixtures rendered successfully and the shared CSS parsed successfully. The browser checks passed 66 screen/print cases, including mobile widths, themes, both paper widths, print actions and peso rendering. The desktop sale and 58 mm print layouts were visually reviewed. No production database was modified, and this package has not been deployed to Hostinger. Physical printer output has not been tested.
