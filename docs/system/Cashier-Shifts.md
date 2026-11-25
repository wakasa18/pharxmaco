---
title: Cashier shifts and My Day
type: system-reference
status: current
updated: 2026-10-20
tags: [cashier, shifts, reconciliation]
---
# Cashier shifts and My Day

One shared drawer per branch is the accepted default. A drawer has one active cashier, and a user has one active shift. Opening records the physical count; a difference from expected handover cash requires a note. A named handover reserves the drawer for the intended next cashier until accepted or administratively released.

With shift tracking installed, POS entry requires the signed-in operator's active shift for both administrators and cashiers. Administrators choose a branch through its available drawer; their account branch can remain unassigned. Opening or resuming a shift continues to POS. After closing, the last branch remains available for reporting, but selling requires a new shift. [Administrator POS repair](../reviews/2026-10-20-admin-pos-branch.md).

## Calendar totals and shift totals

Cashier login ownership is separate from shift ownership. The account accepts one active session; logout, idle expiry and administrator End login do not close the shift or hand over its drawer. The cashier signs in again to resume that shift or uses the existing count/close/handover procedure. [Single-login rules](../reviews/2026-10-20-cashier-single-login.md).

My Day reports the signed-in operator's branch/date activity. A shift can cross midnight and stays open until explicitly counted and closed. These are different reporting scopes. My Day separates sales, refunds, exchanges, payment methods and product movements; it does not represent every cashier's combined branch total.

Held sales persist separately from the normal session cart. Resume rechecks current availability and prices. Resolve held carts before closing; administrator close-on-behalf has its own reason/discard requirements.

## Reconciliation

```text
Expected drawer cash = opening cash
                     + cash payments after change
                     - cash payouts
                     + cash added
                     - cash removed

Over / short = counted cash - expected drawer cash
```

GCash and Card do not add physical cash to the drawer. A closing count records cash left for the next shift and the remainder removed/banked. Do not also record that same closing removal as a separate cash-out. A changed money ledger requires a fresh closing review/count.

Closed shifts retain their captured summary. Later refunds or financial corrections are attributed to the processing operator's current shift. Equal-total quantity corrections still add product events. Administrator review notes do not rewrite the counted totals.

## Tables and print reports

My Day and Cashier Shifts operational tables use DataTables search, sorting, page length and pagination. Cash-pair columns do not create concatenated filter dropdowns. Stacked label text uses a readable separator. Quick search operates on loaded records; it is not an unrestricted database search.

Printable reports remain complete static output. An open shift prints as interim; a closed shift prints the captured final summary. The full report includes the activity appendix. See [shift print update](../reviews/2026-10-16-shift-print-update.md).

Code: `Cashier/WorkspaceController.php`, `CashierShift.php`, `CashierDayReport.php`, `app/Views/cashier/workspace`, `public/assets/js/cashier-workspace.js`, `public/assets/js/datatables-init.js`.

Related: [workspace guide](../upgrades/2026-10-15-cashier-workspace.md) · [recorded tests](../development/Testing-and-Evidence.md) · user manual Chapters 4 and 15–18.
