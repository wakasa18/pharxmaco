---
title: Invoices and receipt configuration
type: system-reference
status: current
updated: 2026-10-17
tags: [invoices, receipts, configuration]
---
# Invoices and receipt configuration

Seller registration and serial settings are configured in **`app/Config/ReceiptInvoice.php`**. Branch address/contact information comes from the branch record. Browser Interface Settings controls appearance/preferences; it is not the seller-registration editor.

| Setting | Current source value | Purpose |
| --- | --- | --- |
| `tin` | Empty | Seller's actual registration value |
| `taxStatus` | `vat` | Seller VAT/Non-VAT setting |
| `vatRate` | `12.0` | Configured VAT calculation rate |
| `serialPrefix` | Empty | Registered invoice series prefix |
| `serialStart` | `1` | First configured number |
| `serialEnd` | `0` | Zero disables registered-range allocation |
| `serialDigits` | `8` | Numeric padding |

Values above are a source checkpoint, not a hosted configuration inspection. Fill registration values from the actual business records; the software does not supply them.

## Numbering behavior

An enabled configured series uses a transactional counter with range checking. Failed allocation rolls back with the failed transaction; range exhaustion blocks completion. Reprinting a saved invoice keeps its original number. Disabling/resetting invoice history must not be used to recycle issued numbers.

Without a registered range, the application uses the internal `INV` transaction-reference path. An internal reference must not be described as proof of an approved registered serial series.

## Saved information

Invoices retain saved sale/item values, ingredient/strength/tax snapshots, applied discounts/VAT relief and beneficiary metadata. Sale Details includes beneficiary name/ID for statutory discounts. Branch address/contact are rendered from branch information; changing a branch record is not the same as rewriting a saved item-price snapshot.

The receipt includes the configured heading, tax presentation, applicable deductions and medicine identification. These features support business records; they do not establish BIR accreditation or certify every tax treatment.

Code: `app/Libraries/ReceiptInvoice.php`, `app/Config/ReceiptInvoice.php`, `app/Views/cashier/sales/receipt.php`. See [invoice upgrade guide](../upgrades/2026-10-14-receipt-invoice-fields.md) and user manual Chapters 9 and 29.
