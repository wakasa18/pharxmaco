---
title: Server-synchronized workspace clock
type: implementation-review
status: completed
updated: 2026-10-21
tags: [clock, server-time, sessions, testing]
---
# Server-synchronized workspace clock

The previous topbar used the browser's `new Date()` as its current-time source. It formatted Philippine time correctly but followed an incorrect device clock. The user requested server synchronization.

## Implementation

The topbar now renders a PHP server timestamp and initial date/time in Asia/Manila. A separate `server-clock.js` advances that timestamp with `performance.now()`, so changes to the device wall clock do not affect it. The old inline device-clock updater was removed to avoid competing writers.

Synchronization runs on initialization, every five minutes while visible, and on focus, tab return or reconnection. The browser adds half the measured round trip to a valid server timestamp. Requests cannot overlap, time out after eight seconds and reject errors, redirects, non-JSON or invalid timestamps. Failure retains the last server anchor and continues ticking; the clock tooltip reports the estimated state until a later success. The displayed clock is updated once per second, with the existing minute-only format.

`GET server-time` is public and read-only, implemented by `ServerTime` without account/session/database access. Responses disable caching and include integer epoch milliseconds plus Asia/Manila metadata. Requests use `credentials: omit` and do not carry the cashier-activity header. The exact `server-time` path is excluded from the session-backed CSRF filter because that filter otherwise starts an anonymous session even for GET requests. Resolved filters were checked: the clock excludes CSRF/auth filters, while logout retains CSRF protection. Other routes remain protected. Explicit route/method guards restrict the controller to GET.

The local HTTP response was verified as JSON with no-store/no-cache headers and no Set-Cookie. Clock polling does not renew login leases or keep administrator/cashier PHP sessions active.

## Verification

- `ServerTimeTest.php`: **2 tests / 19 assertions passed**, covering actual server-epoch bounds, response metadata/cache headers, GET-only routing and resolved filter scope.
- Related cashier/admin login, logout and Sales History selection: **24 tests / 199 assertions passed** with in-memory SQLite. Existing CSRF assertions were updated for the scoped configuration structure; logout and End login protection remain checked.
- `tests/server-clock.test.cjs`: **19 checks passed**, including deliberately wrong device time, Manila midnight rollover, transit estimation, malformed/non-JSON/error responses, timeout recovery, offline ticking, return/focus/reconnection, concurrent-request suppression and omitted credentials/activity headers.
- **Six actual-browser scenarios passed** against the local Apache `server-time` endpoint, through a fixture proxy. The browser was set to 2001 and then 2099; the displayed clock stayed within three seconds of server time. Failure continued ticking and reconnect restored synchronization. The endpoint set no session cookie; no authenticated requests were made. No JavaScript exceptions occurred. The topbar screenshot was visually inspected.
- PHP syntax and JavaScript parsing passed. The full application suite and MySQL concurrency checks were not rerun; unrelated tracked inventory/expiry issues remain open.

Evidence: `writable/server-clock-review/browser-results.json`, `server-clock-1440.png`, `focused-junit.xml`, `endpoint-junit.xml` and `package-verification.json`.

## Delivery and boundaries

The [upload guide](../upgrades/2026-10-21-server-clock.md) lists six runtime files in `build/hostinger-server-clock-2026-10-21.zip`. The archive includes verified hashes and requires no SQL import.

This is synchronization to the PHP hosting server, not an external time authority. Its accuracy follows that server's clock. Saved transaction timestamps, operating-system clock configuration, application records and hosted installation were not changed. With JavaScript disabled, the initial PHP time remains static.
