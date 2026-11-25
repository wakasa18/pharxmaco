---
title: Human sample user full names
type: data-update
status: locally-applied
updated: 2026-10-17
tags: [users, database, demo]
---
# Human sample user full names

The owner requested realistic human full names for existing placeholder users. The local `pharxmaco_drugstore` update changed seven accounts. The [change log](../project/Change-Log.md) lists their usernames and new names.

## Existing hosted database

In phpMyAdmin, select the intended existing application database and import **`database/upgrades/2026-10-17-user-full-names.sql`**. This changes only the known placeholder/test-name matches and their modification timestamps. It leaves custom names alone and makes no further changes on a repeat import.

Usernames, passwords, roles, branch assignments, active/inactive status and session versions are retained. The existing demo-account seed and the combined October 15 seed now use the same human names for newly created demo accounts; those seeds continue to skip existing usernames.

## Recorded local verification

The apply script saved the previous names/timestamps under `writable/backups`, performed the update transactionally, compared every user field and added an activity record. Seven rows changed; all fields other than names/modification timestamps remained unchanged. A subsequent read confirmed all seven new names.

Artifacts: `writable/user-name-update/apply.php`, `inspect.php` and `result.json`. The backup is a targeted rollback record, not a full database restore. The hosted database was not modified by the local operation.

Refresh an authenticated page to display the current name. `AccountSession::check()` refreshes the session's full-name value without changing name-only authentication fingerprints.

Related: [Access](../system/Roles-and-Access.md) · [Database and recovery](../system/Database-and-Recovery.md) · [Home](../Home.md).
