---
title: POS and discount behavior
type: system-reference
status: current
updated: 2026-10-29
tags: [pos, promotions, senior-pwd]
---
# POS and discount behavior

The cashier builds a branch-scoped cart, reviews price/stock/expiry, chooses any manual or beneficiary discount, records payment and completes the sale. Checkout revalidates relevant records under locks and commits the sale, items, stock movements and required audit/event records together. Draft and held carts do not reserve stock.

## Pricing choices

POS cart actions update in place through AJAX. Adding/removing items keeps payment and beneficiary entries, checked remaining eligible lines and product filters. Clear, Hold, Resume and an empty cart reset customer details. Held-sale lists and totals refresh without replacing the page; a confirmed completed sale still opens its confirmation page. Unconfirmed cart actions offer a read-only **Refresh cart** before continuing. See the [implementation and recovery checks](../reviews/2026-10-20-pos-ajax-actions.md).

| Selection or offer | Behavior |
| --- | --- |
| No Discount | Default; no additional manual/beneficiary choice. Matching product/category promos still apply. |
| Product promo | Automatic on matching products; individual rules are omitted from the dropdown. |
| Category promo | Automatic on matching category lines; a fixed amount/cap is allocated once, not repeated per line. |
| Senior Citizen / PWD | Requires beneficiary details, confirmation and selected confirmed-eligible lines. Compare statutory treatment with matching automatic promos. |
| All-products ordinary offer | Manually selectable; uses its pricing path rather than stacking ordinary offers. |

Product/category offer minimums use the eligible scope. Category amounts are allocated proportionally with exact centavos. If another treatment replaces a category share, that share is not redistributed. Ordinary offer ties use the lower discount ID; equal benefit savings retain statutory treatment.

For a configured 12% VAT seller, an eligible PHP 112 VAT-inclusive line has a PHP 100 base, PHP 12 VAT relief and PHP 20 statutory discount, giving PHP 80 payable. A 25% ordinary promo gives PHP 84, so statutory treatment wins; a 30% promo gives PHP 78.40 and wins instead. The winning ordinary-promo line retains ordinary tax treatment. This describes implemented arithmetic, not a legal eligibility determination.

Sources: [automatic category rules](../reviews/2026-10-16-automatic-category-promos.md), [combined benefit rules](../reviews/2026-10-15-combined-benefit-promos.md), `ProductPromotion.php`, `BenefitProductPromotion.php`, `StatutoryDiscount.php` and `public/assets/js/discount-math.js`.

The POS displays the current selling branch. Branded and generic products use the same eligibility checks: active catalog/branch inventory, positive stock, future or inapplicable expiry and no deletion markers. The initial catalog stays complete for client search/clear/reset; URL queries are visible, and name, generic ingredient and SKU matching includes branded medicines. A branded empty state points to branch/stock/expiry review. [Catalog diagnosis](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md).

## Beneficiary and payment records

Administrator New/Edit Discount forms now open a save review. Creation shows offer details, availability and automatic/manual application behavior; editing lists changed values and removed targets/caps. Rejected submissions retain the saved baseline for comparison. [Save review update](../reviews/2026-10-20-admin-login-submit-review.md).

The form captures beneficiary name and valid ID/reference, document confirmation and selected eligible products. All units on a selected line receive its chosen treatment. Saved beneficiary information is visible in Sale Details and the receipt.

Cash retains ordinary tender/change. GCash/Card require the exact final amount and a validated reference, with zero cash change. GCash uses 13 ASCII digits for the supported customer payment-confirmation workflow; card approval/transaction references accept provider-varying lengths up to 100 ASCII letters/digits and common separators. Shared browser/server rules also cover exchange settlements and changed correction references; unchanged historical references remain available for nonfinancial edits. [Format evidence and rules](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md#payment-reference-formats). The browser recalculates a read-only exact tender and requires the reference; the server validates independently. This remains manual payment recording, without provider capture/settlement integration. [28 October repairs](../reviews/2026-10-28-selected-audit-repairs.md).

Automatic product/category pricing is enforced on the server for blank/default selection and Senior/PWD, independent of hidden fields. Supplied previews and current locked rules are rechecked. Exchange replacements use the same engine and show offer details; original return credit preserves paid value. Explicit all-product offers remain manual without stacking; statutory exchanges still use refund/new POS.

## Recovery and after-sale work

**Review refund** opens Refund overview before processing: selected items/conditions, exact remaining-value payout, repayment method, reason and stock effect. Administrator **Review correction** shows saved/new totals, changed quantities/payment/reference/notes and the exact settlement difference. Both dialogs require acknowledgement; Back to edit preserves the form and sends nothing. The existing server-reviewed exchange step is now labeled **Exchange overview**, with return/replacement stock effects and Confirm & process exchange. These are confirmation steps over existing workflow permissions and validations. [Overview update](../reviews/2026-10-23-transaction-overviews.md).

Sales History provides one Sale date from/to range with Apply/Clear. The database filters dates before loading up to 1,000 records; quick search, column filters and pagination cover those loaded records. End dates include the whole day. Invalid ranges show an error, and Clear returns to the latest list. See the [date-filter update](../reviews/2026-10-21-sales-history-filters.md).

Checkout tokens deduplicate a completed request. Recovering an old checkout keeps a newer cart intact. Ambiguous responses trigger saved-sale lookup; uncertainty retains pending recovery rather than inviting another payment. See the [repair report](../reviews/2026-10-16-checkout-fixes.md).

Refunds reject aliased/duplicate IDs and use locked current line/invoice balances for exact payout ceilings. Resellable returns and quantity corrections require their original, matching, unexpired delivery; unknown history remains refundable under a nonresellable condition. Receiving/replacement rotates identity. [Repair](../reviews/2026-10-28-selected-audit-repairs.md). Exchanges link returned items and replacements, and record only the actual settlement difference as new money. Ordinary POS cart and exchange review are separate. Administrator corrections check saved revisions; automatic-promo invoice quantity changes require a refund/new POS sale.

Procedures: user manual Chapters 5–14. Related: [Inventory](Inventory-and-Expiry.md) · [Cashier shifts](Cashier-Shifts.md) · [Invoices](Invoices-and-Receipts.md).
