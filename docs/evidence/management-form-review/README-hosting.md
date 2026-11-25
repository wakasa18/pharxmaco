---
title: "Product unit selection and management form validation"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "writable/management-form-review/README-hosting.md"
source_sha256: 6c0a1352b4b384b9462556ff3ebf65210c2db554a51aa36f9e6dcab5eeb0a360
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `writable/management-form-review/README-hosting.md`. Findings and test counts refer to the original work. Read [Current state](../../project/Current-State.md) and [Known issues](../../project/Known-Issues.md) for subsequent changes.

# Product unit selection and management form validation

20 October 2026

This update adds matching browser/server character rules for Products, Categories, Suppliers, Branches, Users and Discounts. SKU is required when creating or editing a product, with a maximum of 50 characters. Product overview before saving is included.

Unit is now a required dropdown on Add and Edit Product. It offers common pharmacy units and retains valid units already present in the catalog, including their original spelling. Blank or unlisted values are rejected by the server. Selecting a unit does not convert stock quantities or prices.

Upload all `app/` and `public/` files from this archive together into the matching directories of the existing application. Keep a copy of the corresponding old files first. Refresh the forms with Ctrl+F5 after uploading. The validation configuration, custom PHP rule class, controllers and browser script must be updated together.

No SQL import, existing-record rewrite, generated SKU, password reset or dependency installation is included. Older blank SKUs are not silently filled; an administrator must enter a valid SKU when editing such a product. Existing catalog assignment identifies its already-existing product by ID and remains available.

## Accepted input

- SKU and branch codes: ASCII letters/numbers, hyphens, underscores, dots and slashes; at least one letter or number. Spaces are not allowed.
- Usernames: ASCII letters/numbers, dots, underscores and hyphens; at least one letter or number.
- People's names: Unicode letters, spaces, apostrophes, commas, dots and hyphens; at least one letter.
- Product/category/business/promotion names and medicine details: Unicode letters/numbers and useful punctuation, including +, &, %, /, parentheses, apostrophes, commas, dots and hyphens. Symbol-only text, markup and unsupported characters are rejected.
- Contact numbers: 7–15 digits with a possible leading +, spaces, parentheses, dots or hyphens.
- Addresses: text and address punctuation, including #, commas, slashes and line breaks. Symbol-only text and markup are rejected.
- Money: nonnegative ordinary decimal amounts with at most two decimal places, up to 99,999,999.99. Product quantity/reorder fields use whole numbers from 0 to 1,000,000.
- Email retains normal email validation, including + tags. Password symbols and free-text discount descriptions remain allowed.

Supplier validation errors also remain visible after a rejected save redirects back to the form. Existing authorization, duplicate checks, CSRF, password handling and business checks still run.

## Verification boundary

For the unit dropdown, 14 focused PHP tests / 408 assertions passed. Refreshed browser verification passed 55 validation checks across 12 management Add/Edit pages and 32 product overview checks, including the required dropdown and selected unit in the overview. Browser submissions went only to a fixture server; application databases were untouched. The earlier management-validation run separately passed 18 focused tests / 391 assertions.

A broader 191-case business regression run had 179 passes, 9 failures and 3 errors in the previously tracked inventory/expiry area. This archive does not repair those inventory/expiry issues. The hosted application has not been changed automatically.
