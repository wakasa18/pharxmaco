# Product medicine types

Adds an explicit **Medicine type** selector to Product Add/Edit with **Branded**, **Generic** and **Not specified**. The generic medicine name continues to describe ingredients independently of this classification.

Products and Add from Existing Products show a type label and can filter by it. Catalog search and pagination retain the selected type. POS product cards show the type and generic ingredient, and its type filter works together with category and text search. Searching the generic ingredient also finds branded products whose recorded ingredient matches.

Existing products keep **Not specified** until staff confirm their classification from the packaging and choose it in **Products → Edit → Medicine type**. Product names and generic ingredients do not automatically assign a type. Non-medicinal items may remain Not specified. Cashiers reusing an existing catalog item retain its shared classification.

## Local status

The additive SQL upgrade was applied to the local database and imported a second time to verify repeatability. Existing product values, including prices, stock and VAT classifications, were preserved. The upgrade records migration `2026_10_14_000012` and adds nullable `products.medicine_type`.

## Deployment

1. Select the existing application database and import all of [2026-10-14-product-medicine-types.sql](2026-10-14-product-medicine-types.sql).
2. Upload `build/hostinger-product-medicine-types-2026-10-14.zip` to matching application folders. It includes the current receipt/invoice runtime files as well as the medicine-type additions. Preserve your receipt configuration during deployment.
3. Classify existing products in Product Edit. Check a Branded, Generic and Not specified product in Products and POS.

The full database export `database/exports/pharxmaco-latest-invoice-data-2026-10-14.sql` now includes this schema update for an empty database import. For an existing database, use the small upgrade above.

## Validation

- Eight focused tests passed (55 assertions), covering Add/Edit persistence, invalid-input rejection, retaining classifications in older submissions, grouped unknown filters, cashier branch boundaries, repeatable migration, existing catalog behavior and invoice persistence.
- 49 browser checks passed across Products, catalog, Add/Edit and POS at 320, 390, 768 and 1440 pixels in light and dark themes. Type filtering, combined ingredient search, no-match state and reset were checked. No JavaScript errors were recorded.
- PHP syntax checks cover the changed runtime files. Local SQL was imported twice without changing preexisting product fields.
