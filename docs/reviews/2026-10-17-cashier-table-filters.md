---
title: Cashier table filtering and readable labels
type: review
status: fixed
updated: 2026-10-17
tags: [cashier, datatables, ui]
---
# Cashier table filtering and readable labels

Operational tables in My Day and Cashier Shifts use the existing shared DataTables initializer. Shift print reports stay static so printed output contains the full report.

## Confirmed display problems and fixes

| Problem | Resulting behavior |
| --- | --- |
| Expected/count values became a single filter option such as `₱8.57₱8.57` | Cash-pair headers opt out of the automatic dropdown via `data-dt-filter="off"`. Their numeric `data-order` sorting and two-line display remain available. |
| Stacked cashier/branch names became `Main CashierMain Branch` | Shared plain-text extraction preserves group boundaries, producing `Main Cashier — Main Branch`. Nested inline emphasis stays intact; controls are excluded from extracted labels. |
| Search could lose the boundary between stacked values | Static table search uses the same readable text. Smart phrase search finds the combined cashier and branch. |

The dropdown controls for desktop and mobile share the same options and filter state. Other categorical filters, numeric/date ordering, review forms and CSRF fields retain their behavior. The staff layout's asset version was changed to load the new initializer.

## Files and evidence

Runtime: `public/assets/js/datatables-init.js`, `app/Views/cashier/workspace/manager.php`, `app/Views/layouts/staff.php`. Earlier table enablement also touched `app/Views/cashier/workspace/shift.php`.

The final recorded browser checks passed **13 Cashier Shifts scenarios** and **15 My Day scenarios**. These cover readable options, selecting an exact cashier/branch, mobile control synchronization, phrase search, sorting, review-form state, pagination, empty tables, live My Day replacement and responsive light/dark layouts. Screenshots were reviewed. Earlier focused rendering verification passed one PHP test with 14 assertions.

Artifacts: `writable/cashier-day-review/shift-table-checks.json`, `datatable-checks.json`, `shift-tables.cjs`, `prepare-shift-table-fixtures.cjs` and the corresponding PNGs. Fixtures are synthetic; these checks did not submit live sales or alter application data. No schema import is needed for this UI change. Older deployment archives require a freshness check before upload.

Related: [Cashier shifts](../system/Cashier-Shifts.md) · [Testing](../development/Testing-and-Evidence.md) · [Deployment](../development/Deployment.md).
