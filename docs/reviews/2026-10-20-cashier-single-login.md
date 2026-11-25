---
title: One active login per cashier account
type: implementation-review
status: completed
updated: 2026-10-20
tags: [cashier, authentication, sessions, testing]
---
# One active login per cashier account

The user confirmed that cashiers have separate accounts and authorized blocking simultaneous use of one cashier account from independent sessions. This rule supplements the existing one-owner shared-drawer workflow.

## Implemented behavior

A verified cashier login claims one database lease keyed by user ID. Another independent session receives an account-in-use message and cannot replace the active login or cart. Different cashier accounts are independent; tabs sharing one browser session share one login. Administrator sessions retain their existing behavior.

The lease expires after 15 minutes without activity, or releases immediately on sign-out. A lightweight activity request renews access after actual trusted interaction on a visible page. Background polling, including My Day's JSON requests, does not renew it by itself. Expired and replaced tokens cannot revive themselves or release a newer login. PHP session-ID regeneration retains ownership.

Administrators have **Users → End login**, protected by administrator authorization, POST and the existing CSRF filter. It revokes the fingerprint, releases the lease and records an audit entry in one transaction. It leaves shifts, drawer counts, held sales and financial records intact. An audit failure rolls back the revocation.

Account filters validate the lease on protected requests. AJAX/JSON refusals return HTTP 401 with a sign-in destination. POS displays a Sign in notice and blocks further cart/checkout actions while retaining the visible draft and local pending-checkout record. It does not treat logout/expiry as proof that a pending sale failed.

On rollout, the first valid older cashier session without a lease claims ownership and retains its cart. Competing older sessions fail validation on their next request. If the new table is missing, cashier access fails closed and administrator access remains available.

## Implementation

- `CashierLoginSession.php`: account-row transaction lock, unique per-user lease, conditional acquisition, expiry, owned renewal/release and SHA-256 token storage. Raw random tokens remain in PHP sessions.
- `AccountSession.php`, `Auth.php` and the three role filters: acquisition, legacy adoption, protected-request validation, logout release, activity and explicit unauthorized responses. Existing password checks, account revocation and throttle rules remain.
- `AuthenticationAudit.php`: records blocked duplicate logins and unavailable setup without passwords, usernames or session tokens.
- `Admin/Users.php`, user list and routes: administrator End login action and audit transaction.
- `cashier-session.js`, staff layout and POS view: activity detection, sign-in notice and POS interaction lock; GET activity avoids CSRF regeneration races.
- Migration `2026_10_20_000015` and `database/upgrades/2026-10-20-cashier-single-login.sql`: add `cashier_login_sessions` without modifying existing operational records.

## Verification

**42 focused PHP tests / 492 assertions passed** using in-memory SQLite. New cases cover competing sessions, separate accounts, same-session sign-in, session regeneration, logout, expiry, stale tokens, real-activity renewal, background polling, JSON rejection, legacy adoption, changed accounts, admin release, audit rollback, repeatable migration, missing schema and the POST/CSRF form. The selection also reran relevant existing authentication, POS, held-sale, checkout and statutory/promotion checks.

**Seven isolated MySQL verification groups passed.** Eight separate PHP processes attempted to claim the same account at the same time; exactly one succeeded. Other checks covered native/imported schema agreement, InnoDB, same-second renewal, active-lease preservation on reimport, stale-token protection and credential snapshots changing during login. The temporary test database was removed afterward.

**15 JavaScript activity checks and 30 desktop/mobile POS scenarios passed.** Idle/hidden/offline behavior, synthetic-event rejection, activity renewal, expired notices, unchanged draft/recovery values and disabled POS actions were checked. The session-ended view was also rechecked and visually inspected at desktop/mobile sizes. Browser requests went only to fixtures.

The broader application suite was not rerun; previously tracked inventory/expiry failures remain open. See [Known issues](../project/Known-Issues.md). This work does not bind browser sessions to hardware or change drawer ownership.

## Local and hosted status

Applied the SQL twice to the configured local `pharxmaco_drugstore` database. Before/after hashes of all records in its 23 existing non-migration tables matched. Old migration rows were preserved and update 000015 is recorded once. No live cashier accounts were signed in for testing, and no existing shifts were closed.

`build/hostinger-cashier-single-login-2026-10-20.zip` contains 19 runtime files, the additive SQL, installation notes and a SHA-256 manifest. Runtime PHP syntax and all archived source hashes were checked. The included POS files supersede the earlier POS package versions.

For hosting, import the SQL in the selected existing phpMyAdmin database **before** uploading the matching code. Hosted files/database were not changed automatically. [Import and operating guide](../upgrades/2026-10-20-cashier-single-login.md).

Related: [Access](../system/Roles-and-Access.md), [Cashier shifts](../system/Cashier-Shifts.md), [POS updates](2026-10-20-pos-ajax-actions.md).
