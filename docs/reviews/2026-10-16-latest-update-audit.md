# Latest update bug review — 16 October 2026

**Follow-up:** the four additional code findings below are now fixed, and the nine local master-stock discrepancies have been reconciled. The owner confirmed sale #41's refunds were test records; these now total PHP 500.00 with zero excess. See [the repair report](2026-10-16-checkout-fixes.md) for verification, backups and installation files. The rest of this document preserves the original audit findings and counts.

Confirmed four additional findings in checkout recovery, cashier reporting and promo rounding. Previously reported inventory, expiry, receiving and digital-payment defects remain reproducible. Application business logic and live database records were not changed by this review. Reproduction writes use in-memory SQLite; browser checkout requests are stubbed. Local database queries ran inside a read-only transaction.

## Additional findings confirmed in this review

### Medium: replaying a completed checkout can erase a newer customer's cart

Complete sale A with token A, start a new cart B under a new token, then replay the old sale-A checkout request. The completed-token lookup finds A and unconditionally removes the current session cart. Cart B disappears and the response redirects to A's completed-sale page. No duplicate invoice was created in this reproduction, but the new customer's draft was lost.

Evidence: `app/Controllers/Cashier/SalesController.php:584` and the parallel recovery branch at `:895`. Reproduced with the actual controller in `writable/latest-update-audit/AuditProbeTest.php`; results are in `probe-results.json` beside it. Preserve the current cart/token when recovering an older completed request unless that cart still belongs to the recovered checkout.

### Medium: HTTP 502 clears recovery state without checking the saved sale

A simulated non-JSON HTTP 502 response makes the browser delete `pharxmaco-pending-checkout`, re-enable Complete Sale, and show a failure message. It makes no checkout-status request. An error response alone does not establish whether the backend transaction committed. The potential duplicate-payment risk is an inference from the missing status check; this test did not simulate a real provider payment or prove an actual duplicate invoice.

Evidence: `app/Views/cashier/sales/index.php:2423`. The browser reproduction and recorded request list are in `writable/latest-update-audit/browser.cjs` and `browser-observations.json`. Preserve the token and check completion for ambiguous server/gateway failures before inviting a fresh submission.

### Medium: equal-total quantity corrections do not reach My Day product movements

An invoice with two PHP 10 products, quantities 5 and 1, was corrected to quantities 4 and 2. The PHP 60 total stayed unchanged and the corrected sale items were saved. The cashier ledger still contained only its original sale event: My Day/shift product movements showed 5 and 1, with zero quantity adjustments.

`CashierShift::recordCorrection()` returns early when the amount and payment method are unchanged, before examining changed product quantities. Evidence: `app/Libraries/CashierShift.php:183`; controller reproduction in the new audit probe. Cash totals remain correct in this case; product reporting and correction activity do not. Record product changes even when their settlement difference is zero.

### Low: Senior/PWD selection can miss the better promo combination by one cent

For a VAT seller, use two eligible items priced PHP 112.02 each, each with a fixed PHP 32.01 product promo. Ordinary automatic promos produce PHP 160.02 payable. Selecting PWD produces PHP 160.03: the comparison rejects each promo as an individual tie against the recalculated aggregate statutory amount, although accepting both gives a lower total. An equivalent Non-VAT example also reproduces the defect.

This was confirmed in both PHP and browser JavaScript. Evidence: `app/Libraries/BenefitProductPromotion.php:34`, corresponding `combinedStatutory()` in `public/assets/js/discount-math.js`, and the audit probe/browser JSON results. The ordinary promo-only quote should provide a lower-payable bound when selecting Senior/PWD. Shared PHP/JS agreement tests do not independently prove optimal treatment for every combination.

## Earlier defects still present

| Priority | Defect and current evidence |
| --- | --- |
| High | **Original delivery/expiry is not reliably preserved.** Checkout does not write the delivery/expiry snapshot fields and `SaleItemModel` omits them from allowed fields. Return restocking uses current branch expiry, so older expired/unknown-origin stock can enter a newer delivery's sellable balance. Delivery replacement, replenishment, exchanges and quantity corrections still fail the existing provenance safeguards. See `app/Models/SaleItemModel.php:13`, checkout item insertion, and return restocking at `app/Controllers/Cashier/SalesController.php:1742`. |
| High | **Ordinary product editing can change expiry directly.** `app/Controllers/Products.php:552` writes the posted expiry without the dedicated correction safeguards. The expected `correct_expiry` action is absent from `ExpiryStockResolution`. Existing tests reproduce both paths. |
| High | **Legacy receiving is not protected against duplicate submission.** Replaying the same `stock_mode=receive` five-unit request changes stock 10 → 15 → 20 and creates two stock logs. The token/stale-count checks cover `add/remove`, while `receive` is still accepted separately. Evidence: `app/Controllers/Products.php:739` and `:777`; the original audit probe was rerun successfully as an observation of this defect. |
| Medium | **Digital checkout accepts cash-style overpayment/change without a reference.** Both GCash and Card accepted PHP 100 paid on a PHP 10 sale, recording PHP 90 change and an empty reference. Evidence: checkout payment validation and `writable/pos-inventory-audit/probe-results.json`. Actual provider settlement was not tested. Exact digital settlement, reference handling, and any intended cash payout need explicit validation/accounting. |

The twelve unsuccessful existing unit checks describe missing safeguards across these inventory/expiry paths. Three are test errors, not twelve distinct demonstrated production crashes.

## Current local database findings

Read-only inspection of `pharxmaco_drugstore` confirmed **67 products, 477 sales and 975 sale items**. These findings describe the local database; the hosted database was not inspected.

- **Nine product master stock counts disagree with summed branch stock.** The cause was not established and no reconciliation was applied.
- **Three refund lines on sale #41 exceed their saved net merchandise values.** Items #57/#58/#59 have net values PHP 180/160/160 and refunds PHP 225/200/200: PHP 625 refunded against PHP 500 net, a PHP 125 discrepancy. This is a record inconsistency, not proof of actual cash paid out or a newly introduced refund-calculation defect.
- **All 975 sale items lack a complete original-delivery/expiry snapshot.** Historical fields were added later; unknown historical deliveries must remain unknown rather than being inferred from today's stock.
- **59 noncash sales lack references; 10 have positive change.** These records need operational review; their counts alone do not establish provider settlement or invalidity.
- All seven cashier workspace tables are present. No SQL import or migration was run during the review.

Evidence: `writable/latest-update-audit/local-data-results.json` and its guarded read-only inspection script.

## Checks completed

- Reviewed automatic product/category promos, manual all-product offers, No Discount default, Senior/PWD comparison and beneficiary snapshots, invoice totals, checkout recovery, refunds/exchanges, sale corrections, inventory/expiry handling, shared drawers/handover, held carts, My Day reporting, role boundaries, database schema presence and packaged update context.
- Full PHP unit run: **215 tests / 22,941 assertions; 203 passed, 9 failed, 3 errored**. The same twelve inventory/expiry checks remain unsuccessful. Output: `writable/pos-inventory-audit/latest-suite.txt`.
- JavaScript discount checks passed, including nine shared category cases and 142 combined PHP/JS cases. Report-export interaction checks passed.
- **263 browser regression scenarios passed:** 99 product/medicine-type/POS/receipt checks, 96 cashier workspace checks, 15 My Day DataTables checks, 42 combined promo/receipt/Sale Details checks, and 11 checkout feedback/retry checks. They cover the tested layouts, filters, paging, live refresh, benefit forms and saved displays; they do not negate the targeted fault reproductions above.
- Three new isolated observation tests reproduced stale-checkout cart loss, missing equal-total correction events and VAT/Non-VAT rounding cases. Three earlier observation tests reconfirmed duplicate receiving and both digital-payment cases. These tests assert the observed weakness; their passing result is not a clean bill of health.
- Two browser fault observations confirmed the HTTP 502 recovery gap and the VAT rounding comparison. No real checkout request or payment was sent.

Remaining limits: actual MySQL concurrency scheduling, real payment hardware/provider settlement, hosted phpMyAdmin reimports, physical inventory and medicine-specific legal eligibility were not verified. No newly found layout regression was observed in the tested pages. All-product offers remain manual under the current design; category shares are calculated once without redistributing shares replaced by a better treatment.

Priorities: address original-delivery return safeguards and duplicate receiving first, then protect checkout recovery/cart ownership and ambiguous HTTP failures. Correct cashier product events and the promo comparison edge case, and reconcile historical records against their source documents with an audit trail.
