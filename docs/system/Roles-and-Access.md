---
title: Roles and user access
type: system-reference
status: current
updated: 2026-10-23
tags: [accounts, permissions]
---
# Roles and user access

The system implements **administrator** and **cashier** roles. There is no separate supervisor role. Cashier access is restricted to the assigned branch; administrators manage broader configuration, records and reports.

| Workflow | Cashier | Administrator |
| --- | --- | --- |
| Start/close own shift and sell | Assigned branch | Own active shift in the selling branch for financial operations |
| Sales/receipt/refund/exchange access | Assigned branch | Broader access, with workflow checks |
| Quarantine and Damaged Items records | View assigned branch only | View all branches |
| Quarantine inspection/release/classification | Restricted | Administrator approval |
| Create catalog products/add existing products to branch | Assigned branch | Available through permitted product workflows |
| Stock adjustment and expiry removal/replacement | Assigned branch | Broader scope |
| Shared product editing, branch deactivation, Trash | Restricted | Administrative workflows |
| Discount, category, supplier, branch and user setup | Restricted | Administrative workflows |
| Correct saved sale, review closings/movements | Restricted | Administrative workflows |
| Main reports and database backup/restore | Restricted | Administrative workflows |

Authoritative checks are in the route filters, controllers and services; hiding a navigation link is not an authorization mechanism. The [routes](../reference/Routes.md) show their entry-point groups.

Both return-record pages are visible in cashier navigation. Read scope follows the current stored branch assignment, including colleagues' returns in that branch. Branch filters/record IDs cannot broaden that scope; quarantine inspection POSTs remain admin-only. See [cashier visibility](../reviews/2026-10-23-cashier-return-pages.md).

## Session behavior

Each cashier account permits one active authenticated session. Separate cashier accounts remain independent, and tabs in the same browser session share ownership. A second cashier session is rejected without replacing the first cart. Sign-out releases cashier ownership immediately; the lease expires after 15 minutes without activity. Background polling does not renew it by itself. Administrators can use **Users → End login** for cashier accounts; any shift remains open. Cashiers require schema update 000015.

Administrators can sign in from multiple independent browsers/devices. They do not use the cashier lease or its 15-minute idle restriction; normal application session expiry and account/password/permission checks still apply. Each session keeps its own cart, and signing out in one does not end another. Valid older administrator sessions ignore their old lease without losing drafts. See the [21 October policy update](../reviews/2026-10-21-admin-multiple-logins.md) and [current upload guide](../upgrades/2026-10-21-admin-multiple-logins.md). This supersedes the administrator single-login portion of the 20 October extension.

`AccountSession::check()` reloads the account, checks active status and verifies a fingerprint of authentication/permission fields. Password, role, status and branch changes can invalidate sessions. Name-only updates refresh the displayed session name on a subsequent authenticated request.

The [server-synchronized topbar clock](../reviews/2026-10-21-server-clock.md) fetches a public read-only time sample without credentials. Its exact route does not initialize the session-backed CSRF service. Clock polling does not count as account activity or extend login ownership.

Cashier login leads to Start Shift when no shift is open. An existing open shift resumes POS. Administrator login leads to Dashboard. Sign-out clears ordinary session cart data; held carts persist. Signing out does not close a drawer shift.

Opening POS without an active shift sends both roles to Start Shift when the cashier-workspace upgrade is installed. Administrators can leave their account branch unassigned: they select an available branch drawer and count opening cash, then POS uses that shift's branch. Opening or resuming a shift leads directly to POS for both roles. See the [administrator POS repair](../reviews/2026-10-20-admin-pos-branch.md).

## Personal login history

Cashiers open **My Login History** from the account menu or Preferences. They can see their own retained sign-ins, account-linked failed/denied attempts and explicit sign-outs, with Philippine time and IP addresses. The database query always uses the validated session account, without a user selector. Date filters precede the 1,000-event cap; DataTables searches loaded records. Administrators continue to use Activity Logs. No schema, device management, new logout inference or single-login policy change was introduced. [Implementation and checks](../reviews/2026-10-21-cashier-login-history.md).

## Online presence

Administrator Users shows Online now, Presence and Last seen, independently of Active/Inactive account status. Visible staff pages check in every 30 seconds. A user is online when at least one valid session checked in within two minutes; browser closure takes approximately that window plus list refresh latency to appear offline. Last seen is server-recorded and displayed in Philippine time, starting with this update.

Multiple administrator sessions count as one user, and signing out one does not end another. Cashier presence requires an unexpired matching login lease and cannot extend it. Scoped cookie-free capabilities authorize only check-ins and, for administrators, the online-status list. Missing schema/network failure shows Unavailable. [Implementation, import and checks](../reviews/2026-10-21-user-presence.md).

## Sample accounts

User Add/Edit now validates human-name punctuation separately from username syntax. Usernames accept ASCII letters/numbers, dots, underscores and hyphens; passwords retain punctuation and are not subject to the name rules. Existing credentials and account roles were not rewritten. [Management validation](../reviews/2026-10-20-management-form-validation.md).

Seven local placeholder names were updated to human sample names on 17 October. Existing active/inactive status and credentials were preserved. [Change log](../project/Change-Log.md) lists the mappings. Demo-account passwords are intentionally omitted from the vault; manage credentials through the application and the original operational setup.

Code: `app/Libraries/AccountSession.php`, `app/Models/UserModel.php`, `app/Controllers/Admin/Users.php`, `app/Config/Routes.php`.

Related: [Cashier shifts](Cashier-Shifts.md) · [User manual](../manual/Pharxmaco_User_Manual.md).
