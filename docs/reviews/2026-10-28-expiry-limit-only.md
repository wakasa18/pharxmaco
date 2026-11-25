---
title: Expiry date limit without administrator override
type: implementation-review
status: implemented-locally
updated: 2026-10-28
tags: [inventory, expiry, validation, testing]
---
# Expiry date limit without administrator override

A later [calendar bounds repair](2026-10-28-expiry-calendar-bounds.md) fixes browsing outside the range before selecting a date on existing products. The policy and historical verification below describe this earlier checkpoint. Use the newer calendar upload package for current deployment.

The owner requested removal of the administrator review added earlier on 28 October. New or changed expiry dates now have the **same limit for every account**. The upper bound is 31 December of the current Philippine year plus ten: **31 December 2036** during 2026. This is configurable entry validation; staff still copy the actual packaging date.

## Current behavior

| Entry | Result |
| --- | --- |
| Tomorrow through the maximum | Accepted |
| Today or earlier, for a new or changed date | Rejected |
| Later than the maximum, including an administrator submission | Rejected |
| Old review, reason or posted role fields sent to bypass the limit | Do not grant an override |
| Existing stored expiry left unchanged on Edit Product | Retained, including expired or legacy dates beyond the limit |
| Blank expiry | Optional for product/catalog entry; replacement stock requires a date |

Add Product, Edit Product, Add from Existing Products and Replace with new stock use the same browser and server bounds. The administrator checkbox, exception reason and special review audit path have been removed. Ordinary product and inventory activity logging continues.

The server compares an edited date with the locked stored inventory date. A posted original date cannot grandfather a changed value. Replacement always checks its incoming date as new stock, even if it equals the old delivery date. The Asia/Manila server calendar controls the bounds; the device clock does not. Malformed calendar dates, year zero and years beyond four digits remain invalid.

`app/Config/InventoryExpiry.php` retains `yearsAhead = 10`. Supported settings are 1–100; invalid numeric settings fall back to ten. The optional host setting remains `inventoryexpiry.yearsAhead = 10`. No `.env` file was modified or included in the upload.

## Verification

16 focused PHP cases / 98 assertions passed using isolated in-memory SQLite. They cover date/config/timezone boundaries, both-role rejection of old review payloads without writes, unchanged history, normal-boundary saves across all paths, replacement idempotency and whitespace normalization.

116 browser checks passed at 1440 and 390 pixels on eight actual form views rendered with an empty in-memory database. No exception controls or instructions remain. Bounds, reset, retained values, unchanged legacy dates and native limits without JavaScript were checked; no JavaScript errors or POSTs occurred. Desktop/mobile screenshots were visually inspected.

Full suite: 398 cases / 25,210 assertions; 395 pass, two failures and one error. The same three A05 expiry-correction cases remain unsuccessful; no new failure appeared.

Syntax checks passed for 32 packaged PHP files, the revised expiry test trait and the new JavaScript (33 PHP + one JavaScript).

Evidence is retained under `writable/expiry-limit-only-20261028/`, including isolated test results, rendered forms, browser checks/screenshots and package verification. The [testing index](../development/Testing-and-Evidence.md) records the final counts.

## Deployment and limits

No SQL import, migration or historical-data change is required. Application database records and hosting were not changed. The [current upload guide](../development/Expiry-Date-Limit-Deployment.md) covers `build/hostinger-expiry-date-limit-2026-10-28.zip`, which also retains the selected audit repairs. It supersedes the earlier administrator-review package.

The three existing **A05 expiry-correction test issues remain**: an ordinary within-range date change still lacks a dedicated expiry-correction workflow. The limit does not add that workflow or manufacturer-lot tracking. See [Known issues](../project/Known-Issues.md).

Upload: `build/hostinger-expiry-date-limit-2026-10-28.zip`, 147,242 bytes, 34 runtime files including eight changes for removing the override, plus the guide and SHA-256 manifest. All 36 entries were verified. SHA-256: `22acbf6296294bd5d759fb4d76a1624a0370527a4adbdd59cda70f4069da44ff`.

Related: [Inventory and expiry](../system/Inventory-and-Expiry.md) · [Earlier range/review implementation](2026-10-28-expiry-year-range.md) · [Selected audit repairs](2026-10-28-selected-audit-repairs.md).
