---
title: Online users and Last seen
type: implementation-review
status: completed
updated: 2026-10-21
tags: [users, presence, sessions, testing]
---
# Online users and Last seen

The user authorized Online/Offline and Last seen on the administrator Users page, with a two-minute recent-check-in window and support for multiple administrator sessions.

## Behavior

Users now shows an Online now count, a separate Presence column and server-formatted Last seen. Account status retains Active/Inactive semantics. Visible staff pages send a check-in every 30 seconds; the administrator list refreshes its cells in place. Different sessions for one administrator aggregate to one online user. Logging out ends only that session's capability; closing a tab/browser relies on the recent-check-in timeout. Newly installed tracking does not invent historical activity.

DataTables updates all loaded rows, including other pages, and invalidates cached search/sort values without replacing action forms. Existing search, sorting, length and page remain; a page is clamped if filtered results shrink below it. Presence and Last seen omit automatic column dropdowns. Failed requests mark current presence Unavailable and retain previously known last-seen timestamps. Currentness is an estimate, with the next 30-second poll adding display latency to the two-minute cutoff.

## Authorization and sessions

Migration 000016 adds `user_presence_sessions`: SHA-256 token hash, user ID, authentication fingerprint, optional cashier-lease hash, creation/last-seen/expiry/end timestamps. Raw random capabilities stay in the owning PHP session and authenticated page, never URLs or database rows. They authorize only check-in and, for current administrator accounts, the online-status list.

Successful sign-in and valid real account requests attach/renew the capability. Heartbeats update only Last seen; they do not extend its authorization deadline, cashier leases or PHP sessions. The deadline follows the configured application-session lifetime, refreshed only by verified real requests. Existing valid sessions acquire tracking on their next native request while retaining carts. Background requests do not create replacement capabilities. Missing schema/registration failures do not block ordinary authentication.

`presence/heartbeat` and `presence/users` use explicit methods and a scoped `X-Pharxmaco-Presence` secret header. Their browser requests use `credentials: omit`; the exact paths bypass session-backed CSRF initialization and have no cookie-based authentication. Normal forms, logout and End login retain CSRF protection. The read endpoint validates live administrator role and account fingerprint. Cashier capabilities additionally require current matching unexpired lease ownership. End login, password/role/branch/status/session-version changes therefore immediately remove online eligibility. Presence does not force an authentication logout when its own capability expires; normal account filters remain authoritative.

Summaries aggregate historical last-seen times through the user/time index and inspect only recent unended/unexpired records for online status. No IP, browser identifier or PHP session ID is stored. Usernames/passwords/fingerprints/capabilities are not returned by the status API.

## Verification

- **40 focused business cases / 320 assertions**, including eight new presence cases, passed in SQLite. Checks covered independent administrator sessions, logout isolation, the exact 120-second cutoff, retained Last seen/Never, unchanged cashier expiry, authorization deadlines, real versus background renewal, account revocation, anonymous/cashier read rejection, method guards, hashed-token storage, missing-schema rollout and rendered table/actions.
- **Two server-clock/filter tests / 19 assertions** passed after extending the exact stateless endpoint exceptions. Ordinary logout and End login CSRF checks remain verified.
- **12 presence JS checks**, **18 existing cashier/admin session-notice checks**, and **19 existing server-clock checks** passed. Presence requests omit login credentials/activity headers, skip hidden/offline pages, suppress overlapping requests, recover from errors/timeouts and stop after revocation without forcing login navigation.
- **18 browser scenarios** passed on rendered Users/cashier fixtures, including off-page updates, preserved search/order/page/action forms, filter-count shrinkage, failure/recovery, cashier heartbeat-only behavior and 320/390/768/1440 light/dark layouts. Desktop/mobile screenshots were inspected; no JavaScript exceptions or horizontal page overflow occurred.
- **Nine isolated MySQL groups** passed: repeatable phpMyAdmin SQL, native migration agreement, no invented check-ins, multi-admin aggregation, active-capability preservation on reimport, same-second updates, per-session logout, unchanged cashier leases, expiry and account revocation. The temporary database was removed.
- Unauthenticated HTTP checks of the actual local endpoints returned 401 JSON without setting a session cookie. No real accounts were signed in for testing. The full application suite was not rerun; tracked inventory/expiry issues remain open.

Evidence: `writable/user-presence-review/` contains `focused-junit.xml`, `clock-junit.xml`, `browser-results.json`, screenshots, `mysql-results.json`, `local-upgrade-results.json` and `package-verification.json`.

## Delivery and database status

Applied the additive SQL twice locally to `pharxmaco_drugstore`: hashes of all rows in 24 existing non-migration tables matched, prior migration entries were unchanged, registration 000016 exists once and no presence rows were fabricated. Existing users/passwords, stock, sales, shifts and login leases were preserved.

[Upload/import guide](../upgrades/2026-10-21-user-presence.md). `build/hostinger-user-presence-2026-10-21.zip` contains 15 runtime files, the SQL, README and verified hash manifest. Hosting was not changed automatically. Existing tabs must refresh after deployment to begin tracking. This is presence telemetry, not proof of active typing or a replacement for login authorization.
