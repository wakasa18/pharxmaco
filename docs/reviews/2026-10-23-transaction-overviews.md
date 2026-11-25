---
title: Refund, exchange and sale correction overviews
type: implementation-review
status: implemented-locally
updated: 2026-10-23
tags: [refunds, exchange, corrections, confirmation]
---
# Refund, exchange and sale correction overviews

## Result

Refund and sale-correction forms now open a confirmation overview before their existing native POST. Exchange retains its existing server-calculated quote/revision step, presented as **Exchange overview** with clearer source-branch and planned stock effects. There is no redundant second exchange dialog.

| Workflow | Before processing |
| --- | --- |
| Refund | Choose **Review refund**. The dialog shows invoice, branch, repayment method, total payout, each selected item/quantity/condition/amount, planned stock effect and reason. Acknowledge the review and choose **Confirm & process refund**. |
| Exchange | Choose **Review exchange**. Its server overview shows returned value, replacement total, exact collect/payout/even difference, method/reference, discount/reason, returned conditions and stock effects for returns/replacements. Choose Back to edit or acknowledge and **Confirm & process exchange**. |
| Correct sale | Choose **Review correction**. The dialog shows saved/corrected totals, exact collect/payout difference, changed quantities and stock effects, changed payment/reference/notes/totals, and correction reason. Acknowledge and **Confirm & apply correction**. Existing payment-settlement confirmation remains required where applicable. |

## Behavior and safeguards

The shared dialog uses the workspace modal API for focus trapping, inert background, Escape, focus/scroll restoration and closing. Back to edit, close and Escape keep the form entries and do not send a request. A payload fingerprint includes the actual native form fields, so a change after review refreshes the overview and requires acknowledgement again. Repeated confirmation clicks are locked; exchange also has an immediate submit guard in addition to its existing server-side idempotency.

Correction comparisons use a safely encoded whitelist of the current saved sale, including when rejected submissions are redisplayed. Notes/product/reason text is rendered through textContent, not interpolated HTML. No-change corrections cannot confirm. Current CSRF/refund/exchange/revision/settlement fields are preserved on native submission. Original change given remains visible and is not treated as money available to cover a new payment difference.

Refund previews now receive the exact remaining net refundable amount from the controller, rather than deriving the entire payout by multiplying a displayed four-decimal unit price. Partial amounts use positive half-up cent rounding of the remaining proportion; a full remaining return uses its exact remaining amount. The live preview and overview share this helper. Seven browser examples were compared to actual payouts recorded by the real refund controller in isolated SQLite, including PHP 1 / three units and half-cent cases. This corrects preview rounding only; the existing server refund calculation is unchanged.

These refund/correction dialogs are browser confirmation layers over the existing validated POST endpoints. They are not new server draft/approval permissions, and require JavaScript. Existing native behavior/server validation remains the fallback without the script. Exchange continues requiring its server-reviewed draft/revision before completion. Permissions, branch restrictions, shift requirements, financial validations, transaction rollback and saved-sale conflict checks are retained. Known delivery/expiry safeguards outside this change remain open.

## Files

- New `app/Views/partials/transaction_overview.php`, `public/assets/js/transaction-overview.js` and `public/assets/css/transaction-overview.css`.
- Updated refund, exchange and administrator sale-correction views.
- `Cashier/SalesController`: supplies remaining refundable amount to its view; no financial-write changes.
- `Cashier/ExchangeController`: supplies the original selling branch name to entry/overview views; no completion-write changes.
- New `tests/unit/TransactionOverviewCases.php`; existing refund view fixture receives the new view data field.

No new SQL, migration or application data edit. No real refund, exchange or correction was submitted in the application database, and hosting has not been changed. [Upload guide](../development/Transaction-Overviews-Deployment.md) and archive: `build/hostinger-transaction-overviews-2026-10-23.zip`.

## Verification

| Check | Result and boundary |
| --- | --- |
| PHP | 20 cases / 179 assertions pass, including five new overview cases and existing refund, statutory net amounts, correction repricing/settlement, same-total product changes, exchange persistence and cashier shift/event cases. Uses actual controllers in isolated SQLite. |
| Browser | 40 scenarios pass in headless Chrome with real rendered fixtures/assets. Exactly three fixture-server POSTs cover refund, correction and exchange. Amounts/changed fields, safe text, Back/Escape, focus isolation/trapping, stale confirmation, duplicate clicks, preserved tokens/revisions/settlement, native digital-reference validation, no-change correction, discounted server/client agreement and exact refund rounding were checked. |
| Layout | Included in browser checks: opened refund/correction dialogs and exchange overview at 320/390/768/1440 widths in light/dark. Mobile inherited dialog min-height was corrected; scrollable body and visible footer stay inside the viewport. Desktop/mobile screenshots reviewed. |
| Syntax | Changed/new PHP templates/controllers and new JavaScript parse. |

Commands:

```powershell
$env:PHX_TRANSACTION_OVERVIEW_FIXTURES='1'
& 'C:\UniServerZ\core\php83\php.exe' -d extension=sqlite3 vendor\bin\phpunit tests\unit\BusinessLogicTest.php --filter '::testTransactionOverview|testUpdatedRefundAndReportViewsRender|testRefundConditionsControlStock|testStatutoryRefundsPayOnly|testValidCorrectionAdjusts|testDiscountedCorrection|testCorrectionRequiresExact|testCorrectionUsesLocked|testEqualTotalCorrection|testExchangeCommitsLinked|testExchangeCheaperReplacement|testCashierRefundBelongs|testCashierExchange|testCashierLegacyCorrection' --no-coverage
node writable\transaction-overview-review\check.cjs
```

Evidence: `writable/transaction-overview-review/focused-junit.xml`, `browser-results.json`, actual-controller `refund-expectations.json`, server-pricing `correction-expectations.json`, screenshots and package verification. Browser POSTs go only to a disposable local fixture server; no application records are changed. No fresh MySQL or whole-system pass is claimed. The previous [inventory failures](../project/Known-Issues.md) remain recorded separately.
