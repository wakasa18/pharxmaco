---
title: "Optional demo login accounts"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "build/cashier-workspace-2026-10-15/database/upgrades/2026-10-15-demo-users.md"
source_sha256: 3c75c03fa0af3f470279168ed65c962ea501acea72759af2dc287b1bf14e2f03
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `build/cashier-workspace-2026-10-15/database/upgrades/2026-10-15-demo-users.md`. Findings and test counts refer to the original work. Read [Current state](../../../../../project/Current-State.md) and [Known issues](../../../../../project/Known-Issues.md) for subsequent changes.

# Optional demo login accounts

Import `2026-10-15-demo-users.sql` into the existing database in phpMyAdmin. Keep this optional seed separate from the normal schema upgrade. It is also applied to the current local database when prepared by this task.

| Username | Role | Branch |
|---|---|---|
| `demo_admin` | Administrator | All branches |
| `demo_cashier1` | Cashier | Selected active branch |
| `demo_cashier2` | Cashier | Same branch |

**Password for all three newly created accounts:** `[demo password omitted from vault]`

The SQL prefers an active **Main Branch**, then the first active branch. You can edit the branch assignment at the top before importing. If there is no active branch, it creates no accounts and reports that result. The administrator has normal full administrator access; these are normal accounts, not an isolated demo mode. Transactions made with them affect the selected database's sales and stock.

Existing usernames are skipped. Reimport does not reset a password, reactivate an account, change a branch or grant a new role. The listed password applies only to accounts this seed creates.

To demonstrate the cashier workflow:

1. Sign in as `demo_cashier1`. On **Start Shift**, select the shared drawer, enter counted opening cash and choose **Start shift and open POS**.
2. Use **Open POS** to make a demo sale. My Day shows that cashier's sales, transaction count and products sold.
3. Finish the cart, resolve held sales, count and close the shift, then choose **Demo Cashier Two** for handover.
4. Sign in as `demo_cashier2`, count the handover cash and start the next shift.
5. Sign in as `demo_admin` and open **Cashier Shifts** to review and print the closing report.

One shared drawer can have only one active cashier. Existing shifts, handovers and held carts are preserved. This file creates accounts only; it does not fabricate transaction history or open shifts. Deactivate the demo accounts under **Users** when finished, and change their passwords before giving them ongoing access.
