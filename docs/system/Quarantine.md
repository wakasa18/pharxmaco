---
title: Quarantined returns and inspection
type: system-reference
status: current
updated: 2026-10-28
tags: [quarantine, inventory, returns]
---
# Quarantined returns and inspection

Administrators open **Inventory → Quarantine**. This lists quarantined refund and exchange return lines. Pending inspections are shown first; select a branch or outcome and Apply filters. Quick search, sort and pagination operate on the filtered list.

Cashiers can open the same page to **view their assigned branch only**. Their branch is fixed; View details shows pending returns and completed inspection notes without an approval form. Inspection/release remains an administrator action. See [access rules](../reviews/2026-10-23-cashier-return-pages.md).

1. Choose **Inspect return** and verify the product, branch, held quantity, source invoice and expiry.
2. Inspect every unit. Choose **Release to stock**, **Mark damaged**, or **Mark expired**.
3. Record the inspection note and tick the physical inspection confirmation.
4. Choose **Save inspection**. The outcome becomes read-only. Use the status filter and **View inspection** to find it later.

Release adds the held quantity once to the original branch and records the stock log, inspector and time. It requires the original delivery/expiry to be recorded and match the current branch stock, with active records and a valid expiry. Unknown historical deliveries are not guessed. Damaged/Expired classify the return without another stock deduction or a claim of physical disposal.

The [28 October repairs](../reviews/2026-10-28-selected-audit-repairs.md) rotate current delivery identity when inventory is received/replaced, so matching expiry alone cannot identify the old delivery. Reports now recover cost once for Released outcomes against the original sale-period cohort, preserving the original refund and condition.

An inspection resolves the whole refund line once. If the stock or return changes while the form is open, review the refreshed details. If the result cannot be confirmed, reopen the return to inspect the saved outcome before retrying. The original customer refund/payment and original Quarantined condition remain unchanged; the later inspection lives in a separate audit record.

Requires database update 000018. New POS/exchange replacement lines retain delivery and expiry snapshots; historical sales are not backfilled. See [upgrade instructions](../upgrades/2026-10-23-quarantine.md), [implementation and evidence](../reviews/2026-10-23-quarantine-page.md), and [inventory rules](Inventory-and-Expiry.md).

Returns marked damaged after inspection also appear on [Damaged Items](Damaged-Items.md), together with products marked damaged during the original refund/exchange. Use its source filter and View details to trace either classification.
