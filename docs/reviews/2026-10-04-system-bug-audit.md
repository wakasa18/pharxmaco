---
title: "System bug audit — October 4, 2026"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/reviews/2026-10-04-system-bug-audit.md"
source_sha256: a0e05ec9438a9871bc53635aa0d19421b7003684168299093dafcaebec8c610b
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/reviews/2026-10-04-system-bug-audit.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# System bug audit — October 4, 2026

Reviewed the local application and fixed the confirmed issues below. This is a code and regression-test audit, not a guarantee that every possible bug has been eliminated. The hosted application and its live database were not changed.

## Confirmed issues fixed

| Issue | Before | After |
| --- | --- | --- |
| Passwords retained by failed forms | CodeIgniter `withInput()` copied all POST/GET input, including passwords and tokens, into session flash data on failed login, user creation and backup restoration. | Login retains only a scalar username. Account forms retain only editable account details. Restore failures retain no submitted form data. Malformed login arrays are rejected safely. |
| Sign-out accepted GET requests | Following a logout link ended the session without CSRF verification. | Both navigation controls submit POST forms with CSRF tokens. The GET route is removed and the controller also rejects non-POST requests. Sign-out clears account/cart data and rotates the session ID before storing its confirmation message. |
| Stock and sales committed before activity auditing | An activity-log failure could produce an error after inventory or a sale had already committed. | Manual stock changes and checkout include the activity record in their transaction. Checked writes, rollback and commit handling preserve inventory and sale consistency; failed checkout keeps the cart. |
| Checkout used details read before inventory locking | A price, cost, shared product, category, discount or branch change during checkout could be missed. | Checkout rereads and locks the relevant records, rejects changed/unavailable data, rolls back its writes and asks the cashier to review the cart. It does not silently accept a revised price. |
| Numeric input exceeded database capacity | Excessive or non-finite tender and oversized counted stock could reach database writes. | Tender and gross sale totals are limited to the existing DECIMAL(10,2) range; counted stock respects the existing signed INT limit. Existing add/remove unit limits remain. |
| Permanent hiding was not rechecked by stock adjustment | If the branch record's permanent-delete flag changed between the initial lookup and the lock, the adjustment could continue. | The locked branch record is checked for the permanent flag as well as its other deletion markers. |

## Verification

- Baseline: 131 unit tests / 21,518 assertions passed before the changes.
- Final: 145 unit tests / 21,619 assertions passed with PHP 8.3 and in-memory SQLite.
- New tests reproduced failed-form secret retention, GET logout, failed stock/checkout auditing, stale checkout prices and out-of-range tender before their fixes.
- Additional coverage verifies CSRF rejection/acceptance, valid logout, malformed login input, branch/discount changes, successful discounted checkout with recovery tokens, duplicate retry handling, failed sale-item/stock-log writes and stock limits.
- All 195 application PHP files passed syntax checks; edited PHP and tests were checked again after the final changes.
- All 10 standalone files in `public/assets/js` passed Node syntax checks. Inline scripts are not included in this count.
- 20 administrator/cashier navigation layouts passed local browser checks, including phone/desktop sizes, light/dark themes, focus, keyboard navigation, search, menus and large text. The updated mobile sign-out layout was also visually inspected.

The existing suite also covers refunds, exchanges, discounted quantity corrections, branch-specific inventory/trash, retained deletion, expiry actions, report calculations, forecasts, CSV/PDF helpers, readable dates and pagination. That coverage was rerun, not replaced by the new tests.

## Boundaries

- No live sales, refunds, stock adjustments, backup restoration or schema changes were performed.
- SQLite tests simulate changes at lock boundaries; they do not prove MySQL's concurrent lock scheduling. Hostinger smoke testing is still needed after deployment.
- The browser check used locally rendered fixtures, not a live hosted session. The first sandboxed browser attempt timed out; the permitted local run passed.
- Backup restoration was checked only for rejection/password handling and by reading the implementation. A complete destructive restore was not performed.
- Existing discounted-sale corrections still depend on retained discount settings because historical invoices do not contain immutable discount-rule snapshots. See the earlier discounted-correction deployment guide for that limitation.

Deploy using [the audit-fix guide](../upgrades/2026-10-04-system-audit-fixes.md). No new SQL migration is required.
