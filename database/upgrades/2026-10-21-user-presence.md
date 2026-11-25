# Online users and Last seen

Administrators can now see **Online now**, **Presence** and **Last seen** on Users. Account status still means Active/Inactive; it is independent of online presence.

A user is online when at least one valid session checked in within the last two minutes. Visible workspace pages check in every 30 seconds. The list updates in place while preserving search, sorting, pagination and account-action forms. Closing a browser takes about two minutes to appear offline, plus the next list refresh. Last seen uses server time displayed in Philippine time. Never means no check-in has been recorded since this update.

Administrators may use multiple independent logins: signing out one leaves other sessions online. Cashier presence cannot renew their single-login lease or bypass the 15-minute idle limit. Presence requests omit login cookies and cannot sign in or access sales/financial data. The user-list presence endpoint is restricted to a valid administrator capability. Logout and account/password/permission changes revoke the relevant online eligibility.

## Local status

Applied the additive SQL twice to the configured local `pharxmaco_drugstore` database. All records in its 24 existing non-migration tables and the old migration entries were preserved. Migration 000016 is recorded once. No users were marked online and no historical last-seen dates were fabricated by the import. Refresh existing workspace pages once to start checking in.

## Install on Hostinger / phpMyAdmin

1. Back up the existing database and matching application files.
2. Select the existing application database in phpMyAdmin and import **`2026-10-21-user-presence.sql`**. It requires the existing single-login schema 000015. Stop if an SQL error occurs.
3. Extract **`build/hostinger-user-presence-2026-10-21.zip`**. Upload its `app` and `public` directories into the matching application paths, replacing the included files together. The bundle also includes current server-clock and session-notice dependencies.
4. Refresh authenticated workspace pages. Open **Users** as an administrator to view online status. Already open pages must reload once to begin tracking.

The SQL is repeatable and creates only `user_presence_sessions` plus its migration registration. Do not drop existing tables or reimport a complete backup into the live database. A missing/unavailable presence table shows Unavailable and does not block ordinary sign-in.

The ZIP includes 15 runtime files, the SQL, this README and a SHA-256 manifest. Hosted files/database were not updated automatically.

## Verification

42 focused PHP cases / 339 assertions, 12 presence JS checks, 18 existing session-notice checks, 19 clock checks and 18 Users browser scenarios passed. Nine isolated MySQL groups checked native/imported schema agreement, repeat imports, multi-session aggregation, logout isolation, unchanged cashier expiry and account revocation. The temporary database was removed. No real accounts were logged in for verification.

Presence is a recent-check-in estimate; it does not prove a person is currently typing or viewing a particular page. A failed status refresh shows Unavailable rather than reporting stale Online/Offline values. Normal application/session permissions and expiry remain authoritative.
