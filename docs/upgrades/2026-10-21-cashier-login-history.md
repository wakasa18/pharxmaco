---
title: "Cashier login history"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-21-cashier-login-history.md"
source_sha256: 77443d79a57090b3e7ad033a6747959666f0fc7838b4cbdcf19e8feb6013eb16
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-21-cashier-login-history.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Cashier login history

This application update adds **My Login History** for cashiers. No SQL import is required.

## Install

1. Back up the application files being replaced.
2. Extract `build/hostinger-cashier-login-history-2026-10-21.zip`.
3. Upload its `app` and `public` files to the matching application directories. Keep the directory structure. The package assumes the current cashier login protection and staff layout updates are installed.
4. Refresh the cashier's page. Open **My Login History** from the account menu or the sidebar's Preferences group.

No application database records, credentials, sales, stock or shifts are modified by this update. Existing cashier single-login protection and administrator multiple-login behavior remain in place. Hosting is not changed automatically.

## What cashiers see

- Recorded successful sign-ins, failed/denied attempts linked to their account, and explicit sign-outs.
- Date/time in Philippine time, the result, and the recorded IP address.
- DataTables search, result filter, sorting, rows per page and pagination.
- A From/To date range applied in the database before the latest 1,000-record limit. Narrow the dates to find older records. Quick search filters loaded records only.

Only the authenticated cashier's account ID is used. There is no user selector, arbitrary account endpoint, deletion or session termination action. An administrator cannot open this cashier-only page; their existing Activity Logs page remains available.

The page reads existing `activity_logs` authentication records. Earlier unrecorded logins are not reconstructed. Attempts without a known account ID are not attributed to a cashier. Closing the browser or letting a login expire does not create an explicit sign-out record. Browser/device descriptions and last-active tracking are outside this history-only change. An IP address identifies the recorded network connection, not a particular device or person.

## Files

Six PHP files and one CSS file are included, together with this guide and a SHA-256 manifest. Shared DataTables assets remain existing dependencies. No `.env`, database export, credentials or test fixtures are included.

## Validation

37 focused PHP cases / 355 assertions passed, including eight new history cases and existing authentication, lease, administrator-session and presence cases. Seventeen browser scenarios passed using rendered test fixtures, including search/paging, empty history and 320/390/768/1440 light/dark layouts. No application records were changed for verification. The full application suite and hosted deployment were not tested by this update.
