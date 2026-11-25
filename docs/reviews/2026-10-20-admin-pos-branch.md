---
title: Administrator POS branch selection
type: repair-report
status: implemented
updated: 2026-10-20
tags: [pos, accounts, cashier-shifts, regression]
---
# Administrator POS branch selection

An administrator with no assigned branch saw **No branch assigned to this user** when opening POS. The POS entry check redirected only cashiers to Start Shift. It let administrators reach the missing-branch check before they could choose a drawer. Start Shift also sent administrators to the shift details after opening, despite its button saying **Start shift and open POS**.

## Resulting workflow

With the cashier-workspace upgrade installed, opening POS without an active shift now sends both roles to Start Shift. An administrator chooses an available branch drawer and records the opening count; opening or resuming the active shift leads directly to POS. The existing shift service supplies the selling branch. The administrator's database and session branch assignment can remain empty.

Cashiers remain restricted to drawers in their assigned branch. Occupied drawers and pending handovers retain their existing availability checks. Closing a shift retains the last branch for reporting, but opening POS requires a new active shift. Retrying an already-closed opening request still leads to that saved shift's details instead of creating another shift.

A checkout request with no resolved branch also points to Start Shift when shift tracking is installed, including the AJAX redirect. It keeps the cart and creates no sale. Existing checkout recovery and active-shift locking remain in place. Legacy installations without the cashier-workspace schema retain their previous branch checks.

## Changed files

- `app/Controllers/Cashier/SalesController.php`: apply the POS entry gate to administrators and give a missing-branch checkout the Start Shift destination.
- `app/Controllers/Cashier/WorkspaceController.php`: send both roles to POS after opening or resuming their shift.
- `tests/unit/CashierWorkspaceCases.php`: reproduce the unassigned administrator issue, verify drawer-based branch selection and checkout, preserve cashier restrictions, and extend normal/AJAX missing-shift checkout cases to an administrator with no branch.

## Verification

The two new administrator tests failed before the fix: POS returned to the previous page, and opening a shift led to shift details. After the fix, the three targeted cases passed with **58 assertions**. The broader relevant selection passed **28 tests / 273 assertions** using PHP 8.3.0, PHPUnit 11.5.55 and in-memory SQLite:

```powershell
& 'C:\UniServerZ\core\php83\php.exe' -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage --filter '(AdminWithoutAssignedBranch|AdminPosUsesChosenDrawer|Cashier|AjaxCheckout|CheckoutWithoutShift|OldCheckout|Login|Session)' tests/unit/BusinessLogicTest.php
```

Checks covered preserved cart/held-cart state before opening, repeated open requests, account-session revalidation with an empty branch assignment, products from the selected branch, a completed administrator sale against that branch's stock and drawer totals, closing/reopening behavior, cashier branch restrictions, authentication/session cases, and checkout recovery. PHP syntax checks passed for both controllers and the updated test file.

The controller tests rendered the existing Start Shift and POS templates. No browser session, live sale, application database change or hosted deployment was performed. No SQL import is needed for this code repair on an installation that already has the cashier-workspace upgrade. The historical full-suite inventory/expiry failures were outside this focused verification.

Related: [Cashier shifts](../system/Cashier-Shifts.md) · [Roles and access](../system/Roles-and-Access.md) · [Testing and evidence](../development/Testing-and-Evidence.md).
