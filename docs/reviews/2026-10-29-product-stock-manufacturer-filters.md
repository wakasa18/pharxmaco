---
title: Product stock and manufacturer filters
type: implementation-review
status: implemented-locally
updated: 2026-10-29
tags: [products, inventory, filters, testing]
---
# Product stock and manufacturer filters

Follow-up: the controls were subsequently moved into their matching DataTables columns. See [current placement and verification](2026-10-29-product-datatable-column-filters.md). The results below describe the original top-form implementation.

Products now has **Stock status** and **Manufacturer** controls beside Medicine type. Apply filters combines all three before the existing 3,000-row table limit. DataTables continues to search, sort and paginate the resulting rows; its duplicate Manufacturer/Stock column-filter widgets are disabled.

| Stock option | Recorded branch quantity |
| --- | --- |
| All stock | No quantity restriction |
| In stock | Greater than zero |
| Low / out of stock | At or below that branch's reorder level, including zero |
| Out of stock | Zero or below |
| Above reorder level | Positive and above that branch's reorder level |

These are physical recorded-stock filters. Expiry and active/inactive status remain separate; positive expired inventory is still visible in Products and remains blocked in POS.

Manufacturer options are distinct trimmed names from retained products/inventory in the branch scope, independent of the selected stock/medicine filters and row cap. Matching is an exact parameterized comparison rather than a keyword/wildcard search. Original stored names are not rewritten. A requested name with no matching rows stays selected so it can be cleared; its value is safely escaped.

Administrators can filter across branches or within a selected branch. Cashiers' rows and manufacturer choices use their assigned branch even if a different branch ID is supplied. Apply preserves existing keyword/category/status/branch context. Clear filters removes filter selections while preserving the selected or assigned branch.

## Verification

- **7 PHP cases / 35 assertions pass** using isolated SQLite: quantity/reorder boundaries, master-versus-branch stock, combined filters, exact names/punctuation, retained records, manufacturer options independent of results, invalid input, administrator/cashier scope and reset behavior. No inventory or stock-log records changed.
- **72 browser checks pass** at 1440, 768 and 390 pixels for both roles: real controller-rendered forms, combined GET submission, punctuation, reset, touch-size labels, DataTables search/sorting and overflow. Reduced motion was enabled for stable navigation/screenshot checks; no JavaScript errors or POSTs occurred in that run. Screenshots were visually inspected.
- Full suite: **432 tests / 25,460 assertions; 429 pass, two failures and one error**. The three unsuccessful cases remain the previously documented A05 expiry-correction workflow, outside this change.
- Packaged runtime files and changed tests pass syntax checks. Evidence: `writable/product-filters-20261029/`.

Only the Products controller/view changed at runtime. No SQL, schema, application records or hosting were changed. [Upload guide](../development/Product-Filters-Deployment.md) · [Inventory reference](../system/Inventory-and-Expiry.md).

Upload: `build/hostinger-product-filters-2026-10-29.zip`, 194,461 bytes, 47 runtime files including the two changed files, the guide and a SHA-256 manifest. All 49 ZIP entries were verified. SHA-256: `76f40e27217ee573deba0e20812df4fe3e5d78ed7c3d3b74c6521963040006db`.
