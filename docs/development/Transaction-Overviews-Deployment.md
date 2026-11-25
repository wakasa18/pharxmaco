---
title: Transaction overviews deployment
type: deployment-guide
status: prepared
updated: 2026-10-23
tags: [deployment, refunds, exchange, corrections]
---
# Transaction overviews deployment

Upload the `app` and `public` files from `build/hostinger-transaction-overviews-2026-10-23.zip` over the current application, preserving the existing project-folder mapping. Include the new shared partial, JavaScript and stylesheet. The archive includes their current modal/pricing dependencies and a SHA-256 manifest; it is an incremental update rather than a complete fresh installation.

**No SQL import or migration is required.** No existing sale, refund, stock, shift or user record is changed by uploading the files. Hosting has not been updated automatically.

## Check after upload

1. Open a sale's refund form. Select quantities, conditions, refund method and reason. **Review refund** should open **Refund overview**, without processing anything. Back to edit should retain entries.
2. As an administrator, edit a completed eligible sale. **Review correction** should show changed quantities/fields, saved/new totals and the payment difference. No-change corrections cannot confirm. Required settlement/reference checks still apply.
3. Open Exchange Product, choose returns/replacements and **Review exchange**. Its server-calculated **Exchange overview** should show the original branch, items/conditions, planned stock effects and exact collect/payout difference. Back to edit should retain the selection.
4. Confirm only a real intended transaction after checking the details. Refund/correction dialogs require the acknowledgement checkbox. The exchange overview has its own required acknowledgement. Browser refresh/back restores ordinary form controls; server validation remains authoritative.

Refund and correction confirmation dialogs need JavaScript; existing validated native endpoints are retained. Cashiers keep their assigned-branch access. Sale correction stays administrator-only, and all existing shift/payment/stock checks remain. This upload does not repair the unrelated inventory safeguards listed in [Known issues](../project/Known-Issues.md).

Validation: 20 PHP cases / 179 assertions, 40 browser checks, three fixture-only confirmation POSTs and syntax checks. Actual application database/hosting unchanged. See the [implementation review](../reviews/2026-10-23-transaction-overviews.md).
