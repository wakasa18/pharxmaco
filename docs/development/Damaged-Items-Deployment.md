---
title: Damaged Items page deployment
type: deployment-guide
status: prepared
updated: 2026-10-23
tags: [deployment, damaged-items, returns]
---
# Damaged Items page deployment

Administrators and cashiers have **Inventory → Damaged Items** at `admin/damaged-items`. Cashiers view only their assigned branch. The page combines products marked damaged during refunds/exchanges with quarantined returns later inspected as damaged. Existing records appear without a seed or data conversion.

## Upload

Use the current `build/hostinger-cashier-return-pages-2026-10-23.zip` and [cashier return pages upload guide](Cashier-Return-Pages-Deployment.md). It includes both pages and their current access helper; it supersedes the earlier administrator-only Damaged Items bundle.

1. Upload the archive's `app` and `public` files using the existing project-folder mapping. Preserve their directories.
2. Sign in as an administrator and open **Inventory → Damaged Items**.
3. Verify the branch/source filters, a returned product's **View details**, and its original sale/return-slip links.

There is **no new migration or SQL update** for Damaged Items. Direct damaged refund/exchange records work without the Quarantine table. To also include later quarantine inspection outcomes, existing update 000018 must be installed. Its unchanged SQL and guide are included under `database/upgrades`; import `2026-10-23-quarantine.sql` only if that update is still needed. The Quarantine import is repeatable and preserves existing records.

The bundle contains no credentials, backups, reset scripts or demo records. It has a SHA-256 manifest and was checked against current source. It is not a complete fresh application installation. Hosting has not been changed.

## Using the page

Select a branch and source (**All sources**, **Refund**, **Exchange**, **Quarantine inspection**) and choose **Apply filters**. Record/unit totals describe that filtered list; DataTables quick search, sorting and pagination operate on its loaded rows. Each returned product line appears once, without grouping separate events into an ambiguous product balance.

**View details** shows the original return condition/reason, damage classification, date, staff member, quantity and source invoice. Direct exchange returns have an exchange receipt/return-slip link. Quarantine outcomes retain their inspection link and note; the original return continues to say Quarantined.

The page is read-only. It does not add or deduct stock, alter a refund, release damaged products, record physical disposal or create a supplier credit. Inventory removals described only by free-text reasons are not inferred to be damaged returns.

## Verification

Fifteen focused PHP cases / 182 assertions, seven read-only MySQL checks and 61 browser scenarios pass. All 27 local table hashes, including migration rows, were unchanged by the MySQL checks. The local database contains one original damaged return / one unit; no sample damaged records were added. See the [implementation review](../reviews/2026-10-23-damaged-items-page.md).
