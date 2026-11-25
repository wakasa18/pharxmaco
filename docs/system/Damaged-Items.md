---
title: Damaged returned products
type: system-reference
status: current
updated: 2026-10-23
tags: [inventory, damage, returns]
---
# Damaged returned products

Administrators and cashiers open **Inventory → Damaged Items** to see products marked damaged during refunds, exchanges or later quarantine inspections. Cashiers see only their current assigned branch, including returns processed by colleagues; the branch field is read-only. Administrators can select any branch. Each returned product line appears once with its product/SKU, branch, quantity, source, staff member and classification date.

1. Choose a damage source and **Apply filters**. Administrators can also choose a branch; cashiers remain in their assigned branch.
2. Use DataTables quick search, sort or pagination to locate a product. Totals describe the server-filtered list before quick search.
3. Choose **View details** for the return/inspection reason and source records.
4. Follow **View refund slip**, **View exchange return slip** or **View quarantine inspection** as applicable. Exchange details also link the replacement receipt.

Direct damaged refunds/exchanges retain their original Damaged condition. A quarantine inspection classified as damaged retains its original Quarantined return condition and shows the later inspection separately. The ledger reads historical records even when current product/inventory availability changes.

Viewing the page does not deduct stock again or change a customer's payment/refund. It does not confirm physical disposal, create a supplier credit or release damaged units. Generic stock removals without an explicit damaged-return record are outside this list.

There is no new SQL update. Direct damaged returns work on the existing schema; existing Quarantine update 000018 is required to include later inspection outcomes. See [deployment](../development/Damaged-Items-Deployment.md), [implementation/evidence](../reviews/2026-10-23-damaged-items-page.md), [Quarantine](Quarantine.md) and [inventory rules](Inventory-and-Expiry.md).
