---
title: "Server-synchronized workspace clock"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-21-server-clock.md"
source_sha256: c675849cf956893b9bdd844e4d82253e4a0a38e8baa38d86a7eb194a29bad7d4
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-21-server-clock.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Server-synchronized workspace clock

The topbar date/time now comes from the application server and displays Philippine time (`Asia/Manila`). Changing the user's device date, time or timezone does not change the clock.

The page includes an initial server timestamp. JavaScript advances it using a monotonic timer, synchronizes immediately and every five minutes while the page is visible, and resynchronizes on focus, tab return or reconnection. A failed request keeps the clock advancing from the last server sample. Network round-trip time is estimated when applying a successful sample.

## Install on the host

Use this follow-up with the current Pharxmaco application files.

1. Back up the six matching application files.
2. Extract `build/hostinger-server-clock-2026-10-21.zip` locally.
3. Upload all included `app` and `public` files together into the existing application directory, preserving their paths.
4. Hard-refresh an authenticated workspace page. Hover over the clock to see its synchronization status.

No SQL import, migration or third-party time API is needed. The archive includes:

- `app/Config/Routes.php`
- `app/Config/Filters.php`
- `app/Controllers/ServerTime.php`
- `app/Views/layouts/staff.php`
- `app/Views/layouts/topbar.php`
- `public/assets/js/server-clock.js`

The public, read-only `GET server-time` route returns server milliseconds and timezone metadata with caching disabled. It uses no authentication/database logic. Its narrow CSRF exception prevents session creation for clock requests; all other routes retain their CSRF protection. Browser clock requests omit credentials and do not renew cashier leases or login sessions.

The clock follows the hosting server's own clock accuracy. This update does not configure operating-system time synchronization or change saved sale/shift timestamps. With JavaScript disabled, the page shows its server-rendered time rather than a live clock.

## Verification

Two endpoint/filter tests, 24 related authentication/logout/history cases, 19 JavaScript clock checks and six browser scenarios passed. Browser verification used the actual local HTTP endpoint with the device clock deliberately set to 2001 and 2099, plus failure/reconnection checks. The HTTP response returned no session cookie. No authenticated requests or financial-record changes were made. Hosted files were not deployed automatically.
