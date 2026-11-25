---
title: Product entry overview before saving
type: implementation-record
status: implemented-locally
updated: 2026-10-20
tags: [inventory, products, forms, accessibility]
---
# Product entry overview before saving

Later on 20 October, the owner made SKU required and requested character validation across all management forms. The [follow-up update](2026-10-20-management-form-validation.md) supersedes this record's earlier blank-SKU behavior. The overview's current browser checks reject blank SKU and markup before opening.

Removed the SKU placeholder **Optional, but recommended** from Add Product and replaced it with **e.g. PAR500-001**. This is a wording change; it does not introduce a new required-SKU rule or alter existing catalog matching.

## User flow

On `products/create`, an administrator's **Save Product** or cashier's **Add to My Branch** now opens **Product overview** after native form validation. The dialog shows the product name and destination branch, SKU, category, medicine type, generic name, strength, unit, tax classification, Senior/PWD eligibility, prices, starting stock, reorder level, expiry, manufacturer and supplier.

**Back to edit**, the close button, Escape and backdrop dismissal retain all entries. Only **Confirm & save** submits the normal product form. Changing values after an overview was opened refreshes the overview and requires another confirmation. Repeated final clicks produce one submission. The existing server validation, cashier branch restrictions, SKU reuse, CSRF and database save paths remain in place. The existing-catalog assignment page was outside this view change.

The dialog reuses the shared modal helper for focus trapping, background isolation, scroll locking and focus restoration. Its body scrolls while the header/actions remain visible, including on mobile. User-entered values use text content rather than HTML. With JavaScript unavailable, the existing native Save Product/Add to My Branch submission remains available.

## Files

- `app/Views/products/create.php`: new SKU example, review markup and page assets.
- `public/assets/js/product-create-review.js`: populate the overview, handle dismissal and allow one confirmed native POST.
- `public/assets/css/product-create-review.css`: responsive dialog layout using existing workspace tokens.

The frontend-design skill informed the review layout. The existing typefaces, colors and shared dialog behavior were retained; no global product, topbar or editor styles were changed.

## Validation

- **32 browser checks passed**, including administrator and cashier flows, invalid required fields, full overview values, Enter submission, focus trapping/restoration, editing, Escape/backdrop dismissal, stale overview protection, safe display of entered HTML, blank-SKU behavior, correct branch, CSRF preservation and one final POST per flow.
- Layout checks covered 390, 768 and 1440 pixels in both themes. Desktop/mobile screenshots were visually inspected. A shared mobile dialog minimum-height conflict found during verification was corrected in the page-specific stylesheet.
- **6 focused business tests / 61 assertions passed** for catalog assignment, validation and medicine-type creation/editing using in-memory SQLite. PHP syntax and JavaScript parsing checks passed.

Evidence: `writable/product-create-review/render.php`, `check.cjs`, `browser-checks.json`, `overview-390.png` and `overview-1440.png`. The browser submitted twice only to an isolated static fixture server; it did not create application products or stock. No application database change, hosted deployment or manual PDF rebuild was performed. No new migration is required. The full application test suite was not rerun.

Related: [Inventory and expiry](../system/Inventory-and-Expiry.md) · [Roles and access](../system/Roles-and-Access.md) · [Testing and evidence](../development/Testing-and-Evidence.md).
