---
title: Management form character validation and required SKU
type: implementation-record
status: implemented-locally
updated: 2026-10-20
tags: [forms, validation, products, accounts, regression]
---
# Management form character validation and required SKU

The owner requested symbol validation for **all management forms** and made SKU required. The update covers Add/Edit Products, Categories, Suppliers, Branches, Users and Discounts. It also applies quantity/money checks to the existing-product branch assignment form.

## Input policy

| Field | Accepted input |
| --- | --- |
| SKU / branch code | ASCII letters/numbers, hyphens, underscores, dots and slashes; at least one letter or number; no spaces |
| Username | ASCII letters/numbers, dots, underscores and hyphens; at least one letter or number |
| Full name / contact person | Unicode letters, spaces, apostrophes, commas, dots and hyphens; at least one letter |
| Product, generic, category, business and promo names; manufacturer; unit; strength | Unicode letters/numbers and useful punctuation, including `+`, `&`, `%`, `/`, parentheses, apostrophes, commas, dots and hyphens; symbol-only text, markup and unsupported characters are rejected |
| Contact number | 7–15 digits, with a possible leading `+`, spaces, parentheses, dots and hyphens |
| Address | Letters/numbers and address punctuation, including `#`, commas, slashes and line breaks; no symbol-only text or markup |
| Money | Nonnegative decimal input up to 99,999,999.99 with at most two decimal places; no currency symbols or exponent notation |
| Product quantity / reorder level | Whole numbers from 0 to 1,000,000; no decimals or exponent notation |
| Email | Existing email validation; `+` tags remain valid |
| Password / free-text discount description | Symbols remain allowed; existing password and length checks remain in place |

Examples verified include `Vitamin C + Zinc (10%)`, `500 mg / 5 mL`, `Ma. José O’Connor-Santos`, `O'Connor & Sons, Inc.`, `RX-500_01/A.2`, `orders+demo@example.com` and a password containing punctuation. Invalid examples include symbol-only names, markup, a SKU containing spaces, `ana@cruz` as a username, misplaced/repeated `+` in contact numbers, `1e2` as money/quantity and excess decimal precision.

## Required SKU and user feedback

SKU is required in both product create/edit HTML and the shared server-side product rules. It is limited to 50 characters; the edit form's previous 100-character maximum now matches the server. Edit-unit length also matches the existing 30-character rule. Existing duplicate-SKU and catalog-reuse checks remain. No existing SKU was generated or rewritten, and legacy records are unchanged until an authorized edit. Existing-product branch assignment continues to identify its already-existing catalog product by ID.

Browser checks use the same character definitions as the PHP rules, with inline messages, `aria-invalid` and linked descriptions. Correcting an input clears its error. Product overview opens only after validation; confirming it still sends the original native form/CSRF payload once. Server validation also rejects invalid string submissions without browser assistance.

Supplier create/edit now display flashed validation errors after a rejected save redirects back to the form. Previously these views checked only the current request's validation service, which could omit the redirected errors.

## Implementation

- `app/Validation/ManagementRules.php`: shared character definitions and the CodeIgniter `management_text[type]` rule.
- `app/Config/Validation.php`: registers the rule class.
- Products, Categories and administrative Users/Branches/Suppliers/Discounts controllers: apply rules before saving.
- Their create/edit views, shared discount form and product catalog assignment form: opt into management validation; product forms mark SKU required.
- `app/Views/partials/management_validation.php`, `app/Views/layouts/staff.php`, `public/assets/js/management-validation.js`: deliver the shared browser checks.
- `tests/unit/ManagementFormCases.php`: actual create/update controller regressions and character-rule coverage.

No route, role, database schema, permission, saved product balance, credential or invoice amount was changed. The user manual PDF was not rebuilt.

## Verification

- **18 focused tests / 391 assertions passed**, covering all six management controllers' create/update paths, supported punctuation, rejected symbol-only names, required SKU for create/edit and cashier creation, invalid SKU/username/phone/address/amount inputs, preserved password punctuation, supplier error visibility, catalog/medicine-type behavior and login/session regressions.
- **55 browser checks passed across 12 management Add/Edit pages**, including accepted/rejected characters, clearing inline errors, required SKU, valid email `+` tags and password punctuation. Desktop/mobile required-SKU errors were visually inspected.
- **32 product overview browser checks passed** with the updated policy: blank SKU and markup now block review; keyboard/modal behavior, current-value confirmation, branch preservation, CSRF and duplicate-click protection remain valid. The two successful submissions went only to a fixture server.
- PHP syntax checks passed for 24 affected PHP files; JavaScript parsing passed.
- A broader in-memory **BusinessLogicTest run executed 191 tests / 22,590 assertions: 179 passed, 9 failed and 3 errored**. The twelve unsuccessful cases concern the already tracked delivery/expiry, returns, exchange and expiry-correction safeguards. This is a business-test-file result, not a full App-suite pass or a repair of those inventory issues.

Evidence remains in `writable/management-form-review/business-logic-junit.xml`, `verification-summary.json`, `browser-checks.json`, `sku-error-390.png`, `sku-error-1440.png` and the product overview review folder. Tests used isolated SQLite and static browser fixtures. Application databases and the hosted installation were not accessed or modified.

## Hosting package

`build/hostinger-management-validation-2026-10-20.zip` contains **26 runtime files**, instructions and SHA-256 hashes. It includes product overview assets and the shared modal dependency. Its archive contents were checked against current source hashes. Upload the `app/` and `public/` contents together into matching directories and refresh the forms. **No SQL import or dependency installation is required.** The hosted application was not deployed by this task.

Related: [Inventory and expiry](../system/Inventory-and-Expiry.md) · [Access](../system/Roles-and-Access.md) · [Known issues](../project/Known-Issues.md) · [Testing and evidence](../development/Testing-and-Evidence.md).
