---
title: Inventory and expiry
type: system-reference
status: current
updated: 2026-10-29
tags: [inventory, expiry, products]
---
# Inventory and expiry

The catalog separates shared product identity from branch stock. Shared fields include product/generic name, strength, classification and category; branch inventory holds its quantity, prices/cost, reorder level, expiry and availability. Adding an existing catalog product to a branch does not transfer physical stock from another branch.

Administrators also have [Quarantine](Quarantine.md) for returned units held outside available stock. Inspect a whole returned line, record its outcome and note, and either release a matching known delivery to stock or classify it as Damaged/Expired without a second deduction. The original refund remains unchanged. New POS/exchange replacement lines retain original delivery/expiry; unknown historical lines are not backfilled. See the [23 October review](../reviews/2026-10-23-quarantine-page.md).

Add Product opens **Product overview** before saving when JavaScript is available. Administrators and cashiers can review entered product details, classifications, branch, prices and initial stock, then choose Back to edit or Confirm & save. Closing the overview keeps all entries. See the [product entry update](../reviews/2026-10-20-product-create-overview.md).

SKU is now **required on product create/edit**, with a 50-character limit and ASCII letters/numbers, hyphens, underscores, dots or slashes. Matching browser/server checks allow useful punctuation in medicine and business names while rejecting symbol-only text and unsupported characters. Prices use at most two decimals; product quantities use whole numbers up to 1,000,000. Existing records and SKU matching are preserved; no legacy SKU was fabricated. See the [management validation update](../reviews/2026-10-20-management-form-validation.md).

Products has Medicine type, Stock status and Manufacturer filters applied before the table row cap. Manufacturer and Stock appear beneath their DataTables headings, or in **Column filters** on small screens. These filters, Medicine type and Apply/Clear refresh only the table rows in the background, preserving workspace/search/sort and stock drafts. Stock options are All, In stock, Low / out of stock, Out of stock and Above reorder level, based on each branch's recorded balance/threshold. Manufacturer choices are scoped to retained inventory in the selected/assigned branch. Clear filters retains that branch; DataTables searches and sorts the resulting rows. Clear quick filters keeps applied server selections. Expiry remains separate from physical stock. [Background updates](../reviews/2026-10-29-product-background-filters.md) · [Filter logic](../reviews/2026-10-29-product-stock-manufacturer-filters.md).

## Independent classifications

Administrators can find directly damaged refund/exchange returns and later damaged quarantine outcomes in [Damaged Items](Damaged-Items.md). The read-only ledger includes quantities, branch/source filters and links to each original sale, event slip and inspection. It does not infer damage from generic stock-removal reasons or deduct stock a second time.

Edit Product opens **Review product changes** before saving. It compares against saved product/branch values, displays only effective changes, and requires a reason for a changed stock count. Branch prices initialize from the selected inventory record; an existing unspecified tax treatment stays unspecified. Closing the review keeps edits; Confirm & save uses the existing server save rules. [Review update](../reviews/2026-10-20-admin-login-submit-review.md).

Unit is a required dropdown on product create/edit, offering common pharmacy units and valid units already used in the catalog. The server rejects blank or unlisted choices. Existing unit spellings are preserved; selecting a unit does not convert quantities or prices. See the [unit selection update](../reviews/2026-10-20-product-unit-selection.md).

| Field | Meaning |
| --- | --- |
| Medicine type | Branded, Generic, Non-medicine or Not specified |
| Generic name and strength | Ingredient/formulation identity independent of branding |
| Tax treatment | VATable, VAT-exempt, zero-rated or unspecified |
| Senior/PWD eligibility | Confirmed eligibility is independent of brand or generic classification |
| Branch status/deletion markers | Whether this branch's stock is operationally available |

Supplies were intentionally retained as Non-medicine. Catalog cleanup and branded seed guides document identifiable formulations and sample values: [catalog](../upgrades/2026-10-15-product-catalog.md), [branded products](../upgrades/2026-10-15-branded-products.md).

## Quantity and expiry actions

The themed expiry calendar enforces its range before the first selection, including unchanged valid product dates. Years, months, days and paging beyond the range are unavailable. For an unchanged legacy date, opening the popup shows an allowed calendar view without changing the stored field value. The popup uses the server range separately from the native legacy validation exemption. [Calendar bounds repair](../reviews/2026-10-28-expiry-calendar-bounds.md).

New/changed dates run from tomorrow through December 31 of the Philippine year plus ten (2036 during 2026), with the same limit for administrators and cashiers. There is no administrator override or expiry-review reason field. Existing dates left unchanged during editing remain valid, including expired or legacy long dates. Replacement always checks its incoming date, even when it matches the old delivery. `InventoryExpiry::yearsAhead` controls the horizon; no stock or historical snapshots are rewritten. [Current limit and configuration](../reviews/2026-10-28-expiry-limit-only.md).

The owner-selected rule expires an item at the **start of its recorded expiry date**, using the server Philippine date. Today or earlier is Expired and blocked in POS, exchange replacements, held-cart resumption and increased sale quantities. Resellable returns and quarantine release cannot add today-expired units to available stock; recording Quarantined/Damaged/Expired returns remains possible. Tomorrow through 30 days ahead is Near expiry. New branch inventory with an expiry requires a future date. Labels, expiry filters/exports and alerts follow the same rule. No physical stock is automatically removed, and an already open page needs a refresh to update its label. [Implementation and testing](../reviews/2026-10-23-expiry-date-cutoff.md).

Add/Remove stock use deltas: stock 20 plus 5 becomes 25; remove 5 becomes 15. Administrative absolute count entry of 5 means a final balance of 5. Reasons and stale-count checks support auditability. A unit is a selling-unit label; no automatic box/strip/tablet conversion exists.

The expiry workflow offers replacement, disposal/supplier return, and administrative branch deactivation. Replacement removes the old quantity before adding the new quantity; old 12 replaced by 30 yields 30. Remaining stock after partial disposal keeps its expiry. Supplier credits and physical disposal remain external operating processes.

There is one current quantity/expiry per product per branch. The model cannot retain independent concurrent manufacturer lots or guarantee FEFO. Internal delivery/reference IDs must not be represented as manufacturer lot numbers.

## Retention and known safeguards

Branch-specific Trash preserves other branches and historical transaction references. Permanent hiding retains underlying records for history rather than erasing the audit trail. Restore follows retained status and does not automatically make every item active.

The [28 October repairs](../reviews/2026-10-28-selected-audit-repairs.md) enforce original delivery/expiry for returns and corrections, rotate identity on newly received/replaced/increased inventory, and reject legacy receive requests. Retried modern stock actions cannot add twice. Removal retains identity. Unknown historical provenance is not backfilled; Quarantine/Damaged/Expired stay available without restocking. This single-current-delivery model is not full lot tracking. Ordinary date-only product editing (A05) remains open in [Known issues](../project/Known-Issues.md).

Code: `app/Controllers/Products.php`, `app/Libraries/ExpiryStockResolution.php`, `StockExpiryProvenance.php`, `BranchProductTrash.php`, `RetainedRecordDeletion.php`, `app/Models/SaleItemModel.php`.

Related: [stock/expiry review](../reviews/2026-10-15-pos-inventory-audit.md) · [Reports](Reports-and-Forecasts.md) · user manual Chapters 19–24.
