---
title: "Panelist review: business logic and system readiness"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/reviews/2026-10-04-panelist-system-review.md"
source_sha256: f360721794a7549da0fa02de85db875e22ddad8f6c5bdd2307077bb815fbd66a
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/reviews/2026-10-04-panelist-system-review.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Panelist review: business logic and system readiness

Reviewed: October 4, 2026.

**Verdict: the core system works, but I would require revisions before recommending it for dependable pharmacy operations or accepting unqualified forecasting-accuracy claims.** The important remaining problems concern payment history, returned-stock expiry, inventory auditing and historical business rules. Passing the existing tests does not establish that those rules are complete.

This review assesses the latest local files, including the earlier October 4 fixes. It does not repeat the previously fixed GET logout, failed-form password retention or checkout/manual-adjustment audit defects as unresolved findings. Application code and the application database were not changed for this review.

## Findings, in priority order

| # | Priority | Finding | Evidence |
| --- | --- | --- | --- |
| 1 | High | Sale corrections do not produce accurate dated payment movements and cannot represent mixed methods. | Reproduced in two isolated scenarios. |
| 2 | High | A returned package is checked against the current delivery's expiry, not its original expiry. | Reproduced after replacing expired stock. |
| 3 | High | Product Edit can bypass the controlled expiry replacement/disposal workflow. | Reproduced with unchanged stock. |
| 4 | High | Product Edit still commits before its activity audit, despite the earlier adjustment/checkout fixes. | Reproduced with a failed audit insert. |
| 5 | High | Historical discount terms are not immutable; some changed rules pass the compatibility check. | Reproduced with fixed/percentage rules yielding the same original amount. |
| 6 | Medium | Restock planning uses physical stock rather than stock that can actually be sold. | Reproduced with expired stock above its reorder threshold. |
| 7 | High | The last-administrator rule is not protected against concurrent changes. | Source-level interleaving analysis; actual MySQL concurrency not tested. |
| 8 | Medium | Login throttling stops working if the configured dummy cache fallback is used. | Reproduced using that fallback. |
| 9 | High for research claims | Forecast accuracy remains unestablished by the application. | Source/test search and review of the current forecast paths. |
| 10 | Medium for historical evaluation | A forecast for an old origin includes refunds that occurred after that origin. | Reproduced with a later refund. |
| 11 | Medium | The 24-month history policy is not enforced in the shared service/CLI path. | Source review. |

### 1. Record correction settlements separately from the original payment

**Example reproduced:** an invoice received PHP 50 cash on September 14. On October 4, correcting its quantities required a PHP 20 cash payout. The September 14 cash report changed to PHP 30, while the October 4 report showed **no payout at all**. The payout exists only in the activity-log description.

A second scenario originally received PHP 50 cash and collected an extra PHP 10 through GCash. The correction replaced the invoice's single `payment_method` with GCash. The original-date report then classified **all PHP 60 as GCash**. It lost the distinction between the original cash receipt and the later GCash collection.

The cash report deliberately uses current corrected invoice values, and its UI discloses that limitation. That can be useful for a restated invoice report, but it is insufficient for reconciling actual receipts and payouts by date and method. A free-text audit record does not feed the cash movement totals.

**Change needed:** preserve the original payment event and record each additional collection/payout as a separate dated settlement linked to the invoice, including amount, method, reference, actor and correction ID. Generate cash reconciliation from those events. Keep restated sales totals as a separate, clearly labelled report. Preserve original and amended invoice versions if the system needs to reproduce both receipts.

Panel question: **"If yesterday's cash sale is corrected today, how does today's cashier reconcile the money actually paid back?"**

Evidence: CashMovementReport.php (repository file: `app/Libraries/CashMovementReport.php#L18`), SaleCorrection.php (repository file: `app/Controllers/Admin/SaleCorrection.php#L180`), sale update (repository file: `app/Controllers/Admin/SaleCorrection.php#L264`).

### 2. Validate the returned package's expiry and provenance

**Example reproduced:** the sale was made while its delivery expired on September 15. After that stock was disposed of and replaced by 10 units expiring in 2099, a return from the original sale was marked resellable. It was accepted into the new stock: **10 became 11**. The refund path checked the new delivery's expiry and could not identify the expired original delivery.

This requires the operator to select an incorrect resellable condition; it is not a claim that every return is automatically restocked. The normal quarantine choice is a useful safeguard. However, the system cannot validate the condition against the package originally sold because sale items contain no batch/expiry allocation.

**Change needed:** capture lot/expiry provenance at sale and require verification of the actual returned package before permitting resellable restocking. When provenance or expiry cannot be established, keep it outside available stock. Do not merge an unidentified old return into a new lot. A full design should track product/branch lots and stock movements per lot.

Panel question: **"How do you know the returned medicine belongs to the unexpired delivery currently displayed?"**

Evidence: refund expiry check (repository file: `app/Controllers/Cashier/SalesController.php#L1559`), ReturnCondition.php (repository file: `app/Libraries/ReturnCondition.php`), BranchProductModel.php (repository file: `app/Models/BranchProductModel.php`). The one-row-per-product/branch design also remains insufficient for batch-level FEFO.

### 3. Prevent ordinary Product Edit from silently relabelling existing stock

**Example reproduced:** Product Edit changed the expiry from January 1, 2000 to January 1, 2099 while retaining all 10 units. No disposition, replacement confirmation or stock reason was required. There were zero stock logs, and the activity entry only said the product was updated.

The dedicated expiry replacement action correctly removes the old stock and logs the new stock. Product Edit offers a second path that bypasses those protections. A legitimate typo correction and replacement of a delivery are different business events.

**Change needed:** handle expiry changes explicitly. Require an administrator's documented correction reason and old/new expiry audit for genuine data-entry mistakes; use the existing replacement workflow for a new delivery. Where quantities belong to different lots, edit the relevant lot rather than overwriting one aggregate date.

Panel question: **"What stops an expired product becoming available simply by editing its expiry date?"**

Evidence: editable expiry payload (repository file: `app/Controllers/Products.php#L528`), quantity-based stock logging (repository file: `app/Controllers/Products.php#L543`), ExpiryStockResolution.php (repository file: `app/Libraries/ExpiryStockResolution.php`).

### 4. Make the remaining inventory CRUD audit atomic

**Example reproduced:** an administrator edited counted stock from 10 to 12 while a test trigger rejected activity-log inserts. The page raised `audit unavailable`, but the stock was already **12** and one stock movement had committed. The activity log contained no entry.

The earlier fix protects `Products::updateStock` and checkout. It does not protect `Products::update`, which commits before calling `logActivity`. Product creation has the same commit-before-activity structure. Several other administrative CRUD paths also write the entity before logging it; those were identified by source review, not all individually fault-injected.

**Change needed:** apply a consistent transaction policy to business changes and required audit records, check each write and commit result, and return a controlled failure. Include the relevant before/after fields in the audit rather than only "Updated product". The user should not receive an error while an unlogged change has already taken effect.

Panel question: **"Can a stock change succeed when its required audit record fails?"**

Evidence: product edit commit (repository file: `app/Controllers/Products.php#L560`), subsequent audit (repository file: `app/Controllers/Products.php#L566`), creation commit (repository file: `app/Controllers/Products.php#L405`), user update audit (repository file: `app/Controllers/Admin/Users.php#L227`).

### 5. Store the discount terms used by each invoice

**Example reproduced:** the original PHP 50 sale used a fixed PHP 10 discount. The same discount record was later changed to 20 percent. Both rules give PHP 10 on the original sale, so the compatibility check accepted it. Increasing the gross amount to PHP 60 then applied **PHP 12**, although the original fixed rule would still give **PHP 10**.

The existing compatibility check correctly blocks many changed or missing rules. It cannot identify this case because equal historical amounts do not prove equal rules. Keeping the discount database row after deletion preserves a reference, not an immutable version of its terms. Category changes can similarly affect historical eligibility.

**Change needed:** save a versioned discount snapshot at checkout, including type/value, cap, minimum, scope and the sale-time eligibility of each item. Corrections should use that snapshot. For exchanges, explicitly distinguish honouring original terms from applying a new current promotion. Existing invoices without snapshots need a controlled policy; historical terms cannot safely be invented.

Panel question: **"Does editing a promotion change the discount terms used when correcting an old invoice?"**

Evidence: live rule lookup (repository file: `app/Libraries/SaleCorrectionPricing.php#L15`), amount compatibility check (repository file: `app/Libraries/SaleCorrectionPricing.php#L45`), editable discounts (repository file: `app/Controllers/Admin/Discounts.php#L140`).

### 6. Separate physical stock from sellable stock in restock planning

**Example reproduced:** 10 units were expired, with a reorder level of 5. The product had zero sellable units, but the restock service returned **no suggestion** because physical stock 10 was above the threshold.

The POS correctly rejects expired stock. Dashboard and reorder logic use the aggregate physical quantity, so their availability picture can disagree with what the cashier can sell. Expiry alerts exist separately, but they do not automatically correct the reorder calculation.

**Change needed:** retain physical stock for accountability while calculating sellable stock separately. Use sellable stock for availability and demand coverage, and show the unavailable quantity and reason. Expired stock should prompt an expiry action and, where demand warrants it, replenishment planning.

Evidence: low-stock selection (repository file: `app/Libraries/ReorderForecastService.php#L119`), suggested quantity calculation (repository file: `app/Libraries/ReorderForecastService.php#L215`), dashboard counts (repository file: `app/Controllers/Dashboard.php#L126`).

### 7. Protect the last-administrator invariant transactionally

The checks protect sequential changes. They count active administrators and then perform a separate update without locking the invariant.

With two active administrators, A and B can concurrently deactivate each other: both requests pass the self-account check, both see a count of two, and both updates can leave no active administrator. Session invalidation after the updates does not prevent requests that already passed authentication.

**Change needed:** serialize administrator role/status changes using a consistent transactional guard, recheck the count under that guard, and commit the account change with its audit. Apply a similarly consistent guard to active-branch/cashier-assignment rules.

This is a concrete source-level interleaving, not a claim that concurrent MySQL execution was reproduced in this review.

Evidence: update guard (repository file: `app/Controllers/Admin/Users.php#L211`), deactivation guard (repository file: `app/Controllers/Admin/Users.php#L245`), unlocked count (repository file: `app/Controllers/Admin/Users.php#L371`).

### 8. Do not silently lose login throttling when cache storage fails

The configured fallback is the dummy cache, which stores nothing. Using that fallback in the isolated probe, six failed logins were accepted for processing; the next request was not blocked and still reported four attempts remaining.

**Change needed:** detect whether a durable/atomic throttle store is available. Use a reliable fallback for login counters or reject login processing with a controlled service-unavailable response when protection cannot operate. Cache cleanup and concurrent increments also need to preserve the intended limit.

This is conditional on cache failure/fallback. It does not establish that the current Hostinger cache is failing. Normal working-cache lockout is covered by the passing tests.

Evidence: dummy fallback (repository file: `app/Config/Cache.php#L36`), counter writes (repository file: `app/Controllers/Auth.php#L71`), LoginThrottleFilter.php (repository file: `app/Filters/LoginThrottleFilter.php`).

### 9. Establish forecast accuracy before making an accuracy claim

Holt calculations, saved revenue points and low-stock product estimates are implemented. The current app/test search did not find a chronological holdout evaluator, MAPE/MAE results or a baseline comparison that demonstrates predictive performance.

This is an evaluation gap, not a claim that the forecast formula is broken. Saved forecasts are now working; the earlier mismatch between saving only product estimates and displaying revenue forecasts has been addressed.

**Change needed:** define the forecast target, origin and horizon, then evaluate on observations unavailable at training time. Report sample coverage, error metrics and a simple baseline. Specify handling of zero actual values rather than reporting undefined percentage error as zero. If the paper claims research-data preparation, retain its dataset/version and cleaning evidence separately from synthetic POS demonstration data.

Panel question: **"What measured evidence shows this forecast is better than a simple baseline?"**

Evidence: Holt implementation (repository file: `app/Controllers/Admin/ReportsController.php#L1428`), saved forecast log (repository file: `app/Controllers/Admin/ReportsController.php#L1243`), [existing research review](2026-09-27-research-alignment-review.md), [synthetic-data notice](../data-guides/samples/README.md).

### 10. Define an as-of data view for historical forecast evaluation

**Example reproduced:** a September 14 forecast estimated five units per day. Adding a refund dated October 4 changed the estimate to four, although the forecast origin remained September 14. The refund aggregation has no cutoff at the historical origin and uses the sale's current status.

Restating old net-sales reports using later refunds can be intentional. Reconstructing what was known at a historical forecast origin needs different semantics. Reusing the restated data for holdout evaluation can include information unavailable at training time. Sale corrections also overwrite the underlying history.

**Change needed:** use event/revision dates or immutable input snapshots to reconstruct the training data known at each origin. Keep this distinct from current restated reports. Save the input basis/version with the forecast run if reproducibility is required.

Evidence: unbounded refund aggregation (repository file: `app/Libraries/ReorderForecastService.php#L51`), net quantity query (repository file: `app/Libraries/ReorderForecastService.php#L159`), revenue refund join (repository file: `app/Controllers/Admin/ReportsController.php#L1288`).

### 11. Enforce the 24-month policy across all forecast entry points

The Reports UI limits history to 24 months. The CLI applies only a minimum number of days, and the common service does not independently enforce the maximum. The command can also change smoothing constants.

**Change needed:** enforce the history policy in a shared validation path. If adjustable constants are intended for experiments, label and document those runs as experimental rather than treating them as the fixed research configuration.

Evidence: CLI minimum only (repository file: `app/Commands/GenerateReorderForecast.php#L45`), service entry point (repository file: `app/Libraries/ReorderForecastService.php#L98`).

## Business decisions to clarify

These are policy/design questions, not confirmed runtime failures:

- **Cashier authority:** branch restrictions are useful and mostly well enforced. Document whether cashiers may create shared catalog products, dispose of stock, issue refunds and complete exchanges without administrator approval; define any quantity/value limits. Keep the approved branch-only deactivation rule.
- **Held sales:** they are session-only carts, not durable saved orders. Signing out or losing/revoking the session removes them. Either state that clearly or persist held orders if staff must resume them after a shift/session change. Holding a cart does not reserve inventory; checkout must still revalidate availability, as it currently does.
- **Demand versus observed sales:** a zero-sales day may mean no demand, no sellable stock, a closed branch or missing data. The current model fills absent days with zero. Record availability/closure/data-quality information and show data sufficiency before presenting order quantities as reliable estimates.
- **Restock policy:** the 30-day coverage is an explicit assumption, not a supplier lead-time or optimal purchasing model. Clarify that it is a planning aid. Forecast unit estimates, revenue forecasts and suggested purchase quantities are different outputs.
- **Retained deletion:** hiding records while retaining database history matches the requested design. Do not replace it with physical deletion. Clarify that "permanent" means removed from the application/Trash, while database recovery remains possible through the flags.

## What is already strong

- Checkout has stock/expiry checks, retry-token handling, discount allocation and transactional stock/audit writes.
- Partial refunds track remaining quantities and paid values; quarantine/damaged/expired conditions do not add available stock.
- Exchanges preserve a link to the original transaction and separate credit from cash movements; the original-product exclusion is enforced on the server.
- Account changes revoke sessions, authenticated login-page visits redirect appropriately, and sign-out uses POST/CSRF.
- Branch trash and retained permanent hiding preserve historical references and isolate branch inventory.
- Update Forecast explicitly saves revenue points and product estimates, and normal filter changes do not submit the save.
- Existing tests cover important reporting, pagination, date formatting and export helpers.

## Scope and verification

The review traced authentication/roles, POS/cart/checkout, discounts/corrections, refunds/exchanges, product/catalog/stock workflows, expiry actions, branch/user administration, trash/retention, dashboard/report calculations, forecasting/snapshots, exports and backup/restore safeguards. It also inspected the existing upgrade schema and previous review/deployment notes. This was not an exhaustive click-through of every browser screen.

- Reran the full unit suite: **145 tests / 21,619 assertions passed**.
- Ran **nine isolated scenario probes** against the real controllers/libraries using the existing in-memory SQLite fixture. They reproduced the behaviours described above without using the application database.
- Reproduction code: panel-review-checks.php (repository file: `writable/receipt-review/panel-review-checks.php`).
- Captured results: panel-review-observations.json (repository file: `writable/receipt-review/panel-review-observations.json`).
- No application PHP, JavaScript, SQL migration or production records were changed. Only this report and local review artifacts were added.
- No live Hostinger session, real external payment, production restore, physical printer or concurrent MySQL scheduling was tested. SQLite strips `FOR UPDATE` in the test fixture, so its lock-boundary probes are not proof of native MySQL locking behaviour.
- A passing suite proves the cases it covers; it does not invalidate the additional scenarios uncovered here.

## Recommended next work

1. Fix dated settlement accounting and preserve payment methods/events.
2. Close the returned-expiry and Product Edit bypasses; decide the scope of lot inventory.
3. Make all required inventory/admin audit writes atomic.
4. Preserve historical discount terms and transaction revisions.
5. Protect administrator/throttle invariants and calculate sellable-stock availability consistently.
6. Add reproducible forecast evaluation, an as-of training basis and shared history validation.

No deployment package was created: this request was for a panelist review and findings, not implementation.

## Subsequent expiry handling fix — October 4, 2026

After the review, the user authorized **expiry handling only**. The returned-stock and Product Edit bypasses in findings 2 and 3 are now guarded: new sale items retain delivery/expiry snapshots, resellable returns and stock-changing quantity corrections require matching original inventory, replacement/replenishment changes the identity, Product Edit rejects expiry changes, and an administrator-only correction action records a reason and before/after history without changing quantity or old sale snapshots.

This remains a single-delivery/expiry inventory design, not full multi-lot tracking or FEFO. Legacy sale items retain unknown provenance and cannot be automatically restocked. The other findings, including the general Product Edit audit defect, historical discount terms and expired-stock reorder calculation, were not fixed by this package.

Validation: **158 tests / 21,690 assertions passed**, including 13 added regression cases. All 15 packaged PHP files passed syntax checks. The nullable schema migration was tested for preservation and repeated execution in SQLite; the MySQL import and live Hostinger deployment were not executed. The deployment ZIP is built with a SHA256 manifest and verifies every packaged file against the local source.

Deployment guide: [2026-10-04-stock-expiry-safeguards.md](../upgrades/2026-10-04-stock-expiry-safeguards.md). Import its SQL before uploading the matching application files. The original review and captured observations above describe the state before this subsequent implementation.
