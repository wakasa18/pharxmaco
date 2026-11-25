---
title: Product filters deployment
type: deployment-guide
status: prepared
updated: 2026-10-29
tags: [deployment, products, inventory]
---
# Product filters deployment

Extract `build/hostinger-product-async-filters-2026-10-29.zip` and upload its `app` and `public` contents into the corresponding application folders, preserving paths. It includes the current product-filter changes and preceding catalog/payment, reporting and expiry updates for the matching application/schema.

**No SQL import or migration is required.** Keep the host's own `.env` and writable data. Hosting was not changed automatically. Reopen Products after uploading; use Ctrl+F5 if an older page remains open.

1. Find **Manufacturer** and **Stock** dropdowns directly beneath their DataTables column headings. Changing either updates only the table rows through a background request. The workspace remains open. On phones/tablets, open **Column filters** above the product cards.
2. Medicine type, **Apply filters** and **Clear filters** also update the table without navigating. Search, sorting and rows-per-page remain; Clear filters resets browser filters too. Low / out includes zero; Above reorder level uses each branch's own threshold.
3. Check that manufacturer choices follow the selected branch, while cashier accounts stay within their assigned branch. Both controls remain available with short or empty results.
4. Use DataTables Quick search/sorting on the matching records. **Clear quick filters** resets browser filters while keeping applied server filters. **Clear filters** restores the branch catalog.
5. On a failed request, the previous results remain visible and **Retry** appears. Stock-entry drafts survive hiding/restoring a product. A changed balance requires checking and re-entering the quantity before saving.

The controls use recorded quantities. Check the separate Expiry/Status columns when determining sale availability. [Background updates and verification](../reviews/2026-10-29-product-background-filters.md) · [Column placement](../reviews/2026-10-29-product-datatable-column-filters.md) · [Original filter logic](../reviews/2026-10-29-product-stock-manufacturer-filters.md).
