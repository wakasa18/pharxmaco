---
title: Product DataTables column filters
type: implementation-review
status: implemented-locally
updated: 2026-10-29
tags: [products, datatables, filters, testing]
---
# Product DataTables column filters

Follow-up: form navigation was subsequently replaced with table-only background requests. See [current behavior and verification](2026-10-29-product-background-filters.md). This checkpoint records the original column placement.

Manufacturer and Stock now sit in the DataTables second header row beneath their matching headings. Phones/tablets expose synchronized copies in **Column filters**. Medicine type and the Apply/Clear actions remain above the table. Changing a column's server filter submits the existing GET form immediately.

The original server queries still apply before the 3,000-row limit, retain branch permissions and offer manufacturer choices independent of the result set. No client-only replacement limits these controls to loaded rows. Short and empty results retain both controls so a user can recover. Clear quick filters keeps server selections; Clear filters restores the branch catalog.

The shared initializer supports opt-in `data-dt-server-filter` headings. It moves the original select and removes its fallback field wrapper after initialization. The original retains explicit form ownership; mobile copies have unique IDs and no name/form attributes, preventing duplicate submitted parameters. With JavaScript unavailable, the original form remains usable. Existing automatic column filters, search, sorting and paging retain their behavior. The layout's script version changed for cache refresh.

## Verification

- **7 PHP cases / 39 assertions pass**, using isolated SQLite: existing quantity/manufacturer/branch safeguards plus matching header targets and form ownership.
- **150 browser checks pass** at 1440, 768 and 390 pixels for administrators and cashiers, using actual controller-rendered SQLite fixtures. Checks cover placement, desktop/mobile sync, exactly one submitted value, automatic GET submission, preserved branch/type, existing status filters/search/sort, independent quick reset, short/empty results, recovery, punctuation and horizontal overflow. No JavaScript exceptions or POSTs occurred. Reduced motion enabled for stable navigation. Desktop/mobile screenshots visually inspected.
- Full suite: **432 tests / 25,464 assertions; 429 pass, two failures and one error**. The same three previously recorded A05 expiry-correction cases remain unsuccessful; this placement change introduces no new failures.
- Runtime package sources and the modified PHP test trait pass syntax checks. Evidence: `writable/product-column-filters-20261029/`.

Runtime changes are the Products view, shared DataTables initializer and staff script cache version. Controller filtering is unchanged from the [original filter update](2026-10-29-product-stock-manufacturer-filters.md). No SQL, schema, application data or hosting changes. [Upload guide](../development/Product-Filters-Deployment.md).

Upload: `build/hostinger-product-column-filters-2026-10-29.zip`, 203,290 bytes, 48 runtime files plus guide and SHA-256 manifest. All 50 ZIP entries verified. SHA-256: `354fb589720481757f856dd3f4e7698088643a8e12ed6e1e1170c7d3006922e6`.
