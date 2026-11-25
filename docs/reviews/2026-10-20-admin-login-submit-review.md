---
title: Administrator login protection and save reviews
type: implementation-review
status: completed
updated: 2026-10-20
tags: [authentication, administrator, products, discounts, forms]
---
# Administrator login protection and save reviews

The user requested the cashier single-login rule for administrator accounts, a changed-values review for Edit Product, and save reviews for New/Edit Discount.

## Account protection

The existing per-user lease now covers both allowed roles. Administrator login rejects another independent session, keeps same-session sign-in and PHP session-ID rotation working, releases on sign-out and expires after 15 minutes without activity. The shared activity script loads for both roles; background requests do not renew leases by themselves. Protected account checks still validate current password/role/branch/status fingerprints.

Administrators can end another administrator's or cashier's login through Users. The action rejects self-release with an instruction to use Sign out. Existing administrator authorization, POST/CSRF protection and transactional audit behavior remain. Any open shift is retained.

The historical `CashierLoginSession`, `cashier_login_sessions`, token key and script name are retained for compatibility. No new schema migration is required. Unlike the previous cashier-only rollout, both roles now fail closed if update 000015 is missing.

## Save reviews

A shared review partial/script adds confirmation dialogs to product edit and discount create/edit. Product edits show only effective changes with saved/current and proposed values. The selected branch supplies prices, stock, reorder level, expiry and status; the product supplies shared catalog fields. Corrected the old branch-price defaults and retained an existing unspecified tax classification instead of defaulting it to VATable during editing.

Discount creation shows the offer's details and manual/automatic application behavior. Discount edits compare against the saved record and show removed category/product targets or caps as Not applicable. Numeric display formatting alone is not a change. Inactive availability is stated explicitly. Product stock changes require a reason before review.

Snapshots use JSON hex escaping and a field whitelist; rendered values use textContent. A redisplayed rejected form compares with the saved database snapshot, rather than treating the submitted form as the original. No-change edits cannot confirm. Close, Back to edit, Escape and backdrop retain entries. A confirmed native POST keeps CSRF, stock-concurrency fields and existing server rules. A changed payload reopens the review; repeated confirmation cannot post twice.

## Verification

- **63 focused PHP cases / 21,007 assertions passed**, including existing authentication, management validation, catalog and discount arithmetic, plus administrator competing sessions, same-session preservation, another-admin release, self-release rejection, saved branch defaults, unspecified tax, original snapshots on rejection and safe snapshot encoding.
- **28 new browser review scenarios passed** with three confirmed POSTs to a fixture server. Covered product changes, required stock reasons, no-op edits, discount scope/type transitions, stale confirmation, duplicate clicks, safe description text, redisplay, mobile/desktop light/dark layouts and keyboard controls. Output was visually reviewed.
- **32 existing Add Product checks, 55 management validation checks and 15 activity-script checks passed.**
- **Eight isolated MySQL groups passed.** Eight simultaneous PHP processes for each role produced exactly one cashier owner and one administrator owner. Reimport preservation, same-second checks, stale tokens and stale credential snapshots were also checked.
- Runtime PHP syntax and archive/source hashes were verified. This was targeted verification; existing inventory/expiry defects remain open and no full-suite pass is claimed.

The configured local MySQL service refused connections, so MySQL verification used a separate temporary server/data directory on a private loopback port. The temporary processes and data directories were cleaned up. No application database was changed. The prior local schema update 000015 remains the prerequisite; this extension did not need another import. Hosted files/database were not changed automatically.

## Delivery

`build/hostinger-admin-login-submit-review-2026-10-20.zip` contains current authentication, POS, product/discount form files and their review/validation dependencies, along with the existing idempotent SQL and installation notes. [Deployment guide](../upgrades/2026-10-20-admin-login-submit-review.md).

Related: [Earlier cashier login update](2026-10-20-cashier-single-login.md), [Access](../system/Roles-and-Access.md), [Inventory](../system/Inventory-and-Expiry.md), [POS/discounts](../system/POS-and-Discounts.md).
