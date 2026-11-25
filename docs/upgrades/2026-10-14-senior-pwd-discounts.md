---
title: "Senior Citizen/PWD medicine discounts"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-14-senior-pwd-discounts.md"
source_sha256: 4e2dbd6a07c8088419824260cbc5ffcfe17635f6c2d43d8c3cd75010af567f98
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-14-senior-pwd-discounts.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Senior Citizen/PWD medicine discounts

Import `2026-10-14-senior-pwd-discounts.sql` into the existing application database after the receipt/invoice schema upgrade. The equivalent CodeIgniter migration is `2026_10_14_000013`. The SQL is repeatable and preserves past products and invoices.

1. Configure `taxStatus` (`vat` or `non_vat`) in `app/Config/ReceiptInvoice.php`. VAT-registered sellers use 12% VAT-inclusive selling prices.
2. In Products → Edit, confirm **Senior Citizen/PWD medicine benefit** eligibility. Both branded and generic medicines can qualify. Existing records start **Not confirmed**; a generic name alone does not confirm eligibility. Confirm required prescriptions for qualifying supplements.
3. At checkout choose **Senior Citizen** or **PWD**, enter the beneficiary name and valid ID/reference number, select the qualifying product lines, and confirm that the ID, required prescription/booklet or supporting documents, and exclusive-use conditions were checked.

The benefit applies to every unit on a selected line, for one beneficiary. Use separate sales for different beneficiaries or other buyers' units of the same product. There is no promotion stacking, minimum purchase or arbitrary cap on this statutory benefit. Promotional discounts retain their configured scope, dates, minimum and cap; a promotional name does not activate statutory treatment.

For a VAT-registered seller, a taxable ₱112 eligible line becomes ₱100 after removal of ₱12 VAT, then receives a ₱20 discount, giving ₱80 due. Already VAT-exempt and zero-rated lines receive 20% without a second VAT deduction. Non-VAT sellers apply 20% without VAT removal. The calculation rounds VAT-exclusive line bases to centavos, calculates 20% on their sum, and allocates the discount exactly among selected lines.

Receipts save beneficiary details, the 20% discount, VAT removed and the applicable tax breakdown. Product catalog tax classifications remain unchanged. Refunds use the saved amount actually paid, including both reductions, and retain any last centavo across partial refunds.

Completed statutory invoices allow notes/payment corrections; changing quantities requires a refund and new POS sale. Exchanges of statutory transactions follow the same refund-and-new-sale process, with beneficiary verification again. Ordinary promotional exchanges retain their existing flow.

Staff must still perform the required identity/document checks and maintain the supporting pharmacy records. This update implements the calculation and record fields; it does not validate IDs against an external registry or establish BIR POS registration.

Validation: 51 related PHP tests passed, shared browser/server money cases passed, and 59 browser checks passed across light/dark mode, mobile/desktop and 58/80 mm printing. The SQL was imported twice into the local database with all existing product and sale values preserved; the updated full backup was verified through isolated MySQL imports. Two older expiry/refund tests reproduce the same failures with the original controller (expired original delivery after replacement, and delivery changes during restocking). They are separate stock-return issues.

References: [BIR RR 5-2017 published by NCDA](https://ncda.gov.ph/disability-laws/implementing-rules-and-regulations-irr/revenue-regulations-no-5-2017-rules-and-regulations-implementing-republic-act-no-10754/), [FDA Advisory 2020-1547](https://www.fda.gov.ph/fda-advisory-no-2020-1547-reiteration-of-doh-administrative-order-no-2012-0007-as-amended/), [DOH AO 2010-0032](https://www.fda.gov.ph/wp-content/uploads/2021/04/Administrative-Order-No.-2010-0032.pdf).
