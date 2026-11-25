---
title: Product unit dropdown
type: implementation-review
status: completed
updated: 2026-10-20
tags: [products, forms, validation]
---
# Product unit dropdown

Unit is now a required selection on Add and Edit Product, as requested. The initial choice is **Select a unit**. Common options include Tablet, Capsule, Bottle, Box, Sachet, Tube, Pack and Piece. Valid units already used by catalog products are included with their existing spelling and case, so editing a product preserves its current unit.

The dropdown uses the existing form styling. Product overview displays the selected unit. The server rejects blank and unlisted values, including requests that bypass the browser. Existing units must still satisfy the management character rules and 30-character limit. Selecting a unit does not convert stock or prices, and no database migration or record rewrite is included.

## Implementation

- `app/Libraries/ProductUnit.php` defines standard options, incorporates catalog units and verifies allowed selections.
- `app/Views/partials/product_unit_field.php` renders the shared required field in product create/edit.
- `app/Controllers/Products.php` supplies options and applies the `product_unit` rule in `app/Validation/ManagementRules.php`.
- `tests/unit/ManagementFormCases.php` covers rejected create/edit requests, legacy units, exact case, saved selections and unchanged stock.

## Verification

Fourteen focused PHP tests / 408 assertions passed using in-memory SQLite. The tests cover required SKU, management rules, catalog behavior and product-unit selections. Refreshed browser fixtures passed 32 product overview checks and 55 management validation checks across 12 Add/Edit pages. The edit form and required unit selection were also visually checked. Browser POSTs went only to the fixture server.

The broader business suite was not rerun for this change. Its previously recorded nine failures and three errors in inventory/expiry safeguards remain open; see [Known issues](../project/Known-Issues.md).

## Hosting package

`build/hostinger-product-unit-selection-2026-10-20.zip` contains 28 runtime files, installation notes and a SHA-256 manifest. All packaged runtime files were checked against the workspace. It includes the earlier management validation and product overview dependencies. The previous management-validation ZIP was refreshed to the same contents.

Upload the archive's `app/` and `public/` files to the corresponding application directories together, after retaining the current files. Refresh the forms after uploading. No SQL import is required. No application database or hosted installation was changed automatically.

Related: [Management validation](2026-10-20-management-form-validation.md), [Product overview](2026-10-20-product-create-overview.md), [Inventory](../system/Inventory-and-Expiry.md).
