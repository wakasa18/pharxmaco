---
title: Administrator single-login restriction removed
type: implementation-review
status: completed
updated: 2026-10-21
tags: [authentication, administrators, cashiers, sessions]
---
# Administrator single-login restriction removed

The user requested removal of the administrator one-login rule added on 20 October. Administrators now use independent ordinary sessions. The cashier single-login policy remains.

## Implemented behavior

- Administrator sign-in does not claim or validate a cashier lease. Two browsers can sign in with the same administrator account; signing out in one does not invalidate the other.
- Same-browser repeated sign-in retains its cart. Valid legacy administrator sessions discard their local lease token without losing their draft, even when the old lease expired or the cashier session table is unavailable.
- Administrator access still reloads active account state and verifies the authentication fingerprint. Password, role, branch, status and session-version changes retain their existing invalidation behavior. Normal PHP/application session expiry still applies.
- Cashiers keep database ownership, duplicate-login rejection, 15-minute idle expiry, activity renewal and stale-token protection. The lease service rejects administrator claim attempts.
- Administrators retain POS session-ended notices for explicit 401/revocation responses, but their client script runs without cashier activity timers or heartbeats. The activity endpoint also skips cashier lease checks for administrators.
- Users → End login targets cashiers only, in both UI and controller. Existing POST/admin/CSRF checks and transactional audit/revocation behavior remain. No shifts are closed by this action.

The application changes are in the seven files listed in the [upload guide](../upgrades/2026-10-21-admin-multiple-logins.md). The single-login SQL/migration remain unchanged. No cleanup/import is necessary; unused older administrator rows do not govern administrator access.

## Verification

**34 focused PHP tests / 263 assertions passed** with in-memory SQLite. This includes updated administrator two-session, logout independence, same-browser draft, legacy expired-token, missing-schema and password-revocation cases, cashier ownership/release/rollback cases, and existing account, submit-review and Sales History cases. The Users view and staff script role attributes were checked against actual rendered templates.

**18 JavaScript checks passed**, covering existing cashier activity/notice behavior plus administrator notice-only operation with no heartbeat requests. **Two POS browser scenarios passed** at desktop/mobile widths with draft/recovery values retained and no fixture POSTs. PHP syntax and JavaScript parsing checks passed.

Evidence: `writable/admin-multiple-login-review/focused-junit.xml`, `tests/cashier-session.test.cjs`, `writable/pos-ajax-review/browser-session-results.json` and the package verification manifest. The isolated MySQL verifier was updated to reject administrator lease claims; MySQL concurrency was not rerun for this policy removal. Earlier MySQL evidence is historical. The full application suite was not rerun; tracked unrelated inventory/expiry failures remain open.

## Delivery

`build/hostinger-admin-multiple-logins-2026-10-21.zip` contains seven runtime files, its installation README and a verified SHA-256 manifest. No new schema or SQL import is needed. Existing application data and hosted files were not changed.

This policy supersedes the administrator single-login portion of the [20 October extension](2026-10-20-admin-login-submit-review.md). Its product/discount submit reviews remain implemented.
