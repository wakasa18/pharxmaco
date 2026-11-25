---
title: Cashier visibility for Quarantine and Damaged Items
type: access-review
status: implemented-locally
updated: 2026-10-23
tags: [cashier, permissions, quarantine, damaged-items]
---
# Cashier visibility for Quarantine and Damaged Items

Cashiers now see **Inventory → Quarantine** and **Inventory → Damaged Items**. They can read their assigned branch's returns, details, original transaction/slip links and recorded inspection outcomes. Administrators retain all-branch filters and quarantine inspection actions. The request was visibility for cashiers; it does not grant stock-release or inspection approval rights.

## Access enforcement

`app/Libraries/ReturnRecordAccess.php` reads the current database account and permits active administrators/cashiers. Cashiers need an existing assigned branch. Their list/detail scope uses that stored assignment, overriding requested branch IDs; controllers show a read-only branch name and do not send other branch names in the branch filter. Lists include the assigned branch's records, including returns processed by colleagues, rather than only the signed-in cashier's returns.

Both ledger services apply this scope. Details are checked against the original sale's branch before returning a record. Out-of-branch damaged detail requests return 404; quarantine details redirect with the same generic not-found message used for missing returns. A forged filter, stale session branch or guessed record ID cannot expand the cashier's record access. Inactive users and cashiers with missing/nonexistent assignments fail closed.

The four list/detail GET routes now use the shared Admin/Cashier filter. Existing `admin/...` URLs remain to preserve links/bookmarks, as with the shared Expiry Report routes. The quarantine POST stays in the administrator route group with global CSRF protection; its controller and service also retain administrator checks. Even a cashier request with a copied draft and forged session role is rejected by the database role check.

Cashier quarantine details create no inspection draft/token and render no outcome inputs, confirmation or save form. Pending items explain that an administrator must inspect them; completed outcomes remain readable. Administrator inspection behavior is unchanged. Both sidebar links use staff visibility.

## Database and deployment

No new migration, SQL or business-data change. Existing update 000018 still enables quarantine inspection history. Direct damaged returns remain readable if that table is absent; the cashier sees an administrator-setup message instead of SQL upload instructions.

Prepared `build/hostinger-cashier-return-pages-2026-10-23.zip`, containing current Quarantine/Damaged Items files and the new access helper. The existing Quarantine SQL/guide is included for installations still needing 000018; no new import is required for this access change. Deployment is prepared, not uploaded to hosting. See [current upload guide](../development/Cashier-Return-Pages-Deployment.md).

## Verification

| Check | Result and boundary |
| --- | --- |
| PHP regression | 20 cases / 235 assertions pass. Includes existing Quarantine/Damaged Items cases and five new access cases covering stored branch enforcement, forged filters/session branches, out-of-branch IDs, inactive/unassigned users, read-only forms/no draft, copied-token/forged-role POST rejection, and preserved administrator controls. |
| Read-only MySQL access | 27 checks pass across four current cashier accounts; two real other-branch detail attempts are rejected. All 27 local table hashes and migration rows are preserved. No account/data/schema writes. |
| Existing MySQL checks | Seven damaged-ledger read checks and 18 isolated quarantine checks pass, including four simultaneous inspectors, exactly one release, atomic rollback and repeat migration imports. Scratch schema writes only. |
| Route filters | CLI `filter:check` confirms GET Quarantine/Damaged Items use `cashier`, while POST `admin/quarantine/1` uses `admin`. CSRF remains enabled. |
| Browser | 74 scenarios pass with real rendered fixtures/assets: cashier navigation, assigned-branch tables, read-only branch, quick search/clear, no foreign text/inspection form/token, pending/resolved views, source links, administrator branch/inspection controls and 320/390/768/1440 widths in both themes. No horizontal page overflow or JavaScript exceptions. Desktop/mobile screenshots reviewed. |
| Syntax | Twelve changed/new PHP runtime/access-test files parse. |

Commands:

```powershell
$env:PHX_RETURN_ACCESS_FIXTURES='1'
& 'C:\UniServerZ\core\php83\php.exe' -d extension=sqlite3 vendor\bin\phpunit tests\unit\BusinessLogicTest.php --filter '::testDamaged|::testQuarantine|::testCashierReturn|::testCashierQuarantine' --no-coverage
& 'C:\UniServerZ\core\php83\php.exe' tests\integration\return-access.php
& 'C:\UniServerZ\core\php83\php.exe' tests\integration\damaged-items.php
& 'C:\UniServerZ\core\php83\php.exe' tests\integration\quarantine.php
node writable\return-access-review\check.cjs
```

Artifacts: `writable/return-access-review/focused-junit.xml`, `mysql-results.json`, `browser-results.json`, screenshots and package verification; existing MySQL reports remain in their earlier review directories. The broader business suite was not repeated; its previously recorded inventory failures remain open. See [known issues](../project/Known-Issues.md).
