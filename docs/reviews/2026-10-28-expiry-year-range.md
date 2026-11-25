---
title: Expiry year range and administrator review
type: implementation-review
status: superseded
updated: 2026-10-28
tags: [inventory, expiry, validation, testing]
---
# Expiry year range and administrator review

**Historical implementation.** The owner subsequently removed the administrator override. Use the [current limit-only behavior and package](2026-10-28-expiry-limit-only.md). The text and evidence below describe the earlier version.

New or changed stock expiry dates now use one configurable range in the browser and server. The default ends on **31 December of the current Philippine year plus ten**: **31 December 2036** for entries made in 2026. This is a data-entry guard chosen for this application, not a medicine shelf-life calculation or regulatory limit. Staff must still copy the actual packaging date.

## Behavior

| Entry | Result |
| --- | --- |
| Tomorrow through the normal maximum | Accepted without an exception |
| Today or earlier, for a new or changed date | Rejected; the existing start-of-expiry-day sales cutoff remains |
| Date later than the normal maximum | Administrator must check the packaging-confirmation box and provide a 3–200-character reason |
| Cashier submits a later date, even with forged review/role fields | Rejected with an instruction to contact an administrator |
| Existing stored date left unchanged during product editing | Retained, including an expired date or a legacy date beyond the range |
| Blank date | Remains optional for product/catalog entry; replacement stock requires a date |

The controls appear on Add Product, Edit Product, Add from Existing Products and Replace with new stock. Administrators can enable the longer range and record a reason on their existing product/replacement forms. This is not an approval queue or permission to sell an expired item. Cashier stock replacement still enforces the ordinary date range.

The server compares an edited date against the **locked stored inventory date**, never a posted original value. Replacement always represents new stock, so it requires review for a long date even when that date matches the old delivery. Malformed calendar dates, year zero and years beyond four digits are rejected. The minimum and maximum use Asia/Manila rather than the device clock.

Exceptional dates create an **Expiry range review** entry in Activity Logs, identifying the product/branch, accepted date, normal maximum, reason and staff member. The review is saved within the inventory transaction; a failed audit rolls back product changes, stock movements and delivery identity. Review reasons stay out of the existing 255-character stock-log remarks. Historical sale/refund snapshots are not rewritten.

`app/Config/InventoryExpiry.php` sets `yearsAhead = 10`. The supported configuration range is 1–100 years; other numeric values fall back to ten. An optional host `.env` setting is `inventoryexpiry.yearsAhead = 10`. No `.env` file was modified or included in the upload.

## Verification

- **19 new PHP cases / 129 assertions pass**, using in-memory SQLite. Coverage includes exact bounds/leap days, configured ranges, Philippine time, malformed/forged requests, Unicode reason length, unchanged history, create/edit/branch assignment/replacement and rollback on failed review audits.
- **63 browser checks pass** on actual rendered form views at 1440 and 390 pixels. They cover min/max, required reasons, review toggling/reset, retained and escaped rejected input, cashier restrictions, unchanged dates, replacement requirements and conservative no-JavaScript behavior. No JavaScript exceptions or form POSTs occurred.
- Desktop/mobile screenshots were visually inspected for readable labels, wrapping and control spacing. The new JavaScript and runtime/test PHP files pass syntax checks.
- Full suite: **401 cases / 25,241 assertions; 398 pass, two failures and one error**. All three unsuccessful cases are the existing A05 expiry-correction workflow, as in the preceding selected-repairs checkpoint. Syntax checks pass for 32 packaged PHP files, four changed test files and the new JavaScript.
- New-stock success fixtures were moved inside the normal range. Existing legacy dates and dedicated out-of-range/review cases remain. The A05 characterization uses an in-range change so this separate unresolved issue remains visible.

Evidence: `writable/expiry-year-range-20261028/range-tests.xml`, `full-tests.xml`, `browser-results.json`, `syntax.json`, rendered fixtures/screenshots and `package-verification.json`. The complete-suite result is recorded in [Testing and evidence](../development/Testing-and-Evidence.md).

## Deployment and limits

No SQL import, migration or historical-data update is required. Application database records and hosting were not changed. [Upload instructions](../development/Expiry-Year-Range-Deployment.md) describe the cumulative application package, which also retains the preceding selected audit repairs.

**A05 remains open:** ordinary date-only editing still lacks a dedicated expiry-correction workflow inside the allowed range. The full suite retains its three existing failures/errors. This update does not add manufacturer-lot tracking or repair the other unselected issues in [Known issues](../project/Known-Issues.md).

Related: [Inventory and expiry](../system/Inventory-and-Expiry.md) · [Selected audit repairs](2026-10-28-selected-audit-repairs.md).

Upload: `build/hostinger-expiry-year-range-2026-10-28.zip`, **149,143 bytes**, containing 34 runtime files (11 expiry-related changes), this guide's deployment companion and a manifest. All 36 entries were verified. SHA-256: `2034c3b36ea06963d521ad129e4b55f6639f7944213c5604f4712f3dba661c4d`.
