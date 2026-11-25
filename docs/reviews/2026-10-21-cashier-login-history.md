---
title: Cashier own login history
type: implementation-review
status: completed
updated: 2026-10-21
tags: [cashier, accounts, authentication, testing]
---
# Cashier own login history

The user narrowed the earlier device/session-management request to cashiers viewing their own login history. Cashier single-login protection remains the chosen policy; this update adds no device management or remote session termination.

## Behavior and access

Cashiers open **My Login History** from their account menu or Preferences in the sidebar. The page lists recorded sign-ins, failed/denied attempts associated with their account, and explicit sign-outs, with Philippine date/time and IP address. DataTables provides loaded-record search, a result filter, sorting, row length and pagination. Server From/To dates are inclusive and applied before the latest 1,000-event cap; invalid, array-valued or reversed dates load no records and show an error. The date controls stay visible for empty results.

`GET cashier/login-history` is inside the cashier-filter group. The controller additionally revalidates the current account and rejects administrators. It always uses the authenticated session's account ID, ignores supplied account selectors, and queries only authentication-prefixed audit rows. `AuthenticationAudit::details()` recognizes the existing event labels and validates IP values; arbitrary activity text is not rendered as login history. Output is escaped and the response uses private/no-store caching. Other users' activity and unassigned authentication attempts are excluded.

This is a read-only view of existing `activity_logs`, without a schema change or fabricated history. It does not expose passwords, capability/lease tokens or other account identifiers. Opening the page preserves the cashier's cart and checkout state and does not require opening a shift. Existing authentication, cashier idle/ownership rules, administrator multiple logins and presence behavior remain.

Explicit sign-outs are distinct from browser closure, login expiry or administrator revocation; those do not become invented logout events. The history contains only retained events recorded by the authentication audit. Browser/device metadata was not collected in those audit records and is not inferred. Last active belongs to the separate presence feature, not this login-event list.

## Implementation

- `app/Controllers/Cashier/LoginHistoryController.php`: validated self-only database query and strict server date range.
- `app/Libraries/AuthenticationAudit.php`: shared existing labels and safe history decoder; existing audit message format is unchanged.
- `app/Views/cashier/login_history.php` and `public/assets/css/login-history.css`: existing staff design/DataTables with responsive controls and empty state.
- `app/Config/Routes.php`, `app/Views/layouts/sidebar.php`, `app/Views/layouts/topbar.php`: protected route and cashier-only links.
- `tests/unit/LoginHistoryCases.php`, included in `BusinessLogicTest.php`: access isolation, tampered selectors, account revocation, date bounds/invalid inputs, older records outside the cap, unchanged drafts/audit rows, decoder, route/navigation and empty state.

## Verification and delivery

**37 focused PHP cases / 355 assertions passed**, including eight new login-history cases and the existing authentication, single-login, administrator-session and presence checks. The initial CLI attempt omitted the SQLite extension; rerunning with `-d extension=sqlite3` used the documented test configuration. Seven changed/new PHP files also passed syntax checks.

**17 browser scenarios passed** with rendered in-memory test fixtures: table initialization, initial descending date order, single date range, search/clear, pagination/length, account/sidebar links, empty table and eight responsive light/dark combinations at 320, 390, 768 and 1440 pixels. No JavaScript exceptions or horizontal page overflow occurred. Desktop/mobile screenshots were visually inspected. The browser fixture server did not authenticate real accounts or change application data.

Evidence: `writable/login-history-review/focused-junit.xml`, `browser-results.json`, `check.cjs`, screenshots and `package-verification.json`. The full application suite was not rerun; existing inventory/expiry findings remain open.

[Upload guide](../upgrades/2026-10-21-cashier-login-history.md). `build/hostinger-cashier-login-history-2026-10-21.zip` contains seven runtime files, README and verified hash manifest. No SQL import, application data change or automatic hosted deployment.
