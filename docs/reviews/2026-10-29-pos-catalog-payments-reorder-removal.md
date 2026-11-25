---
title: POS catalog visibility, payment references and reorder removal
type: implementation-review
status: implemented-locally
updated: 2026-10-29
tags: [pos, inventory, reporting, payments, testing]
---
# POS catalog visibility, payment references and reorder removal

The owner reported missing branded products, requested removal of reorder forecasts/suggestions, and asked which payment-reference formats the forms should accept.

## Branded products

A read-only local check found **12 sellable branded products in Main Branch**. Patag had four branded inventory records with expired dates and no assignment for the twelve newer branded SKUs. Those records were not changed: the POS must continue to exclude expired, inactive, trashed, unassigned and empty stock. Hosting may have different data; `database/diagnostics/2026-10-28-pos-branded-availability.sql` provides read-only checks for the selected application database.

The POS now shows its selling branch. The initial page retains the entire eligible branch catalog so clearing a URL search can reveal every eligible product again. The URL keyword is visible, applied through the existing browser filter, and removed when cleared. Name, SKU and generic ingredient searches work with branded medicines; the legacy search endpoint also returns ingredient and classification data. Empty branded results explain branch assignment, stock and expiry instead of suggesting that the product is missing from the shared catalog.

To make unavailable Patag stock sellable, staff must record an actual valid delivery/branch assignment through normal product or expiry-replacement workflows. No stock was fabricated and no expired date was advanced automatically.

## Removed reorder planning

Removed product reorder forecasts, restock-suggestion tables/dialogs and links, suggested quantities/days-left, report CSV/PDF sections, notification calculations and automatic product-snapshot writes. The old `forecast:reorder` command is a retired no-write compatibility stub, so an existing host cron cannot keep generating suggestions after upload.

Sales trends, revenue forecasts, seven-period saved sales forecasts, staff-maintained reorder levels and ordinary low-stock counts/alerts remain. The saved forecast dialog lists sales periods only. Historical product forecast rows remain in `forecasting_data`; they are not deleted or shown as an active planning feature. The unused legacy calculation library is retained for historical tests, without runtime callers.

## Payment-reference formats

| Method | Current form rule | Basis |
| --- | --- | --- |
| Cash | No digital reference is stored | Existing cash workflow |
| GCash | Exactly 13 ASCII digits; leading zeros preserved | Supported customer merchant-QR payment-confirmation workflow |
| Card | 1–100 ASCII letters/digits with spaces, dots, underscores, slashes and hyphens; at least one letter/digit | Application allowlist for varying provider approval/transaction references |

GCash identifies a customer's **13-digit Transaction Reference ID** in its [official Digital QR guidance](https://help.gcash.com/hc/en-us/articles/59390640942745-What-is-Digital-QR). This is the confirmation ID copied by this form, rather than every identifier used by GCash/QRPH integrations. The screenshot's blanket 10–13-digit claim was not adopted without official support.

Card references have different meanings and provider formats. [Maya's terminal workflow](https://developers.maya.ph/docs/accept-in-person-payments-using-maya-terminals) tells cashiers to copy the approval code or transaction reference from the receipt. Its [official samples](https://developers.maya.ph/page/webhook-sample-json-data) include eight-character approval codes and longer transaction identifiers. A universal six-digit card limit would reject supported examples. The form captures the receipt reference, with no new card-number, PIN or CVV field.

Shared PHP rules and browser constraints cover checkout, exchange payments/payouts and changed sale-correction references. Existing references can stay unchanged for a nonfinancial correction; a changed method/reference or payment difference must satisfy current rules. Even exchanges may leave the reference blank, but supplied values still validate. Cash clears the stored digital reference. Reference text is trimmed while case and leading zeros are retained. The app records a confirmed payment; these format checks do not contact the payment provider.

## Verification

27 focused cases / 216 assertions pass. Full suite: 425 cases / 25,425 assertions; 422 pass, two failures and one error. The three unsuccessful cases are the already documented A05 dedicated expiry-correction workflow.

120 desktop/mobile browser checks pass: 50 POS filters/reference checks, 34 exchange/correction checks and 36 report-dialog checks. No JavaScript exceptions or synthetic/application POSTs occurred. Retained URL searches clear correctly; leading zeros, valid provider references, rejected malformed values and unchanged historical corrections were checked. Screenshots were inspected.

Full report (three pages) and Summary (one page) retain their financial/sales forecast sections and contain no restock suggestions; all four pages were rendered. The updated manual remains 37 pages with 68 contents links, black embedded typography and no misplaced chapters or footer collisions. All pages were rendered and contact sheets inspected, with the affected instructions checked. The existing PDF download filename is retained.

Syntax checks pass for all 40 packaged PHP files, seven revised/new test files, the manual builder, four packaged JavaScript files and the manual verifier (48 PHP + five JavaScript checks).

Evidence: `writable/pos-catalog-reorder-payments-20261028/`, `writable/pos-branded-20261028/`, `writable/reorder-removal-20261028/` and `writable/system-manual/`. Existing success fixtures now use valid customer GCash references; removed-product-snapshot expectations were replaced with sales-only persistence/rollback/history checks.

No application records, schema or hosting were changed. No SQL import is required. The full suite's existing A05 expiry-correction issues remain separate from this work. Reorder-related A12 is retired with the feature, rather than a claim that the historical algorithm was repaired.

Upload: `build/hostinger-pos-catalog-payment-update-2026-10-29.zip`, 189,691 bytes, 46 runtime files including 18 changes, the guide and a SHA-256 manifest. All 48 ZIP entries were verified. SHA-256: `5b2ffaab8a0ac4da0887017602043e5a04bc2d3f6e782f64bb7169c09cbfba18`.

[Upload guide](../development/POS-Catalog-Payment-Update.md) · [POS](../system/POS-and-Discounts.md) · [Reports](../system/Reports-and-Forecasts.md) · [Known issues](../project/Known-Issues.md).
