---
title: "Expiry handling safeguards"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-04-stock-expiry-safeguards.md"
source_sha256: ea71fd223bad9053d77fbe3438e97f9627adb8cd8c628f704929b866df59f7a5
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-04-stock-expiry-safeguards.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Expiry handling safeguards

This package addresses findings 2 and 3 of the [panelist review](../reviews/2026-10-04-panelist-system-review.md). It contains the latest local versions of its included files and assumes the preceding October 4 system fixes are installed. Historical discount snapshots and the remaining Product Edit audit defect are separate, unresolved work.

## Behaviour

- Checkout and exchange replacement items store the locked branch inventory's delivery identifier and expiry at sale. Older sale items stay unknown; current stock dates are never copied into historical sales.
- Resellable refunds/exchange returns require a recorded, valid, unexpired original expiry, a matching delivery identifier and the same current expiry. Expired, unidentified or superseded deliveries must use Quarantine, Damaged or Expired and do not increase available stock. The forms show expiry at sale and disable Resellable where these checks fail; the server independently rejects forged selections.
- Replacement always creates a new identifier, even if both deliveries have the same expiry date. Manual stock increases also start a new inventory identity, conservatively preventing old returns from merging into a replenished stock pool. Stock removals, checkout deductions and accepted same-delivery returns retain the identity.
- Product Edit keeps existing expiry read-only and rejects direct expiry changes on the server before saving any product changes. Its links open the dedicated replacement or expiry correction form.
- **Correct expiry** is administrator-only. It requires checking the printed package date, a reason, confirmation and a current revision. It retains quantities and records old/new dates, actor and reason in stock/activity history in one transaction. It does not rewrite previous sale snapshots. Correcting an expiry changes the inventory identity, so earlier returns stay outside available stock. This is for a data-entry mistake; receiving another delivery still uses Replace with new stock.
- Quantity corrections cannot return unidentified/expired units to available stock or mix a different delivery into an existing sale item. Old invoices without provenance retain notes/payment correction and refund/exchange options, but cannot have quantities changed. Use a refund with a non-resellable condition and a separate new sale when delivery identity is unavailable or changed.

The current database still supports **one aggregate delivery/expiry per product per branch**. The identifier is an application tracking marker, not a supplier lot number or proof of the physical package. Staff must inspect actual returns. Different expiry dates must not be combined using Add stock; use the controlled replacement workflow after separating the old stock. Multiple simultaneous lots and FEFO need a separate inventory design. There is no new mechanism to automatically approve quarantined returns into stock.

## Hostinger, phpMyAdmin or SQLyog

1. Export a database backup and pause sales, refunds, exchanges and inventory changes.
2. Ensure the preceding database upgrades through `2026_10_03_000009` are installed.
3. Select the existing application database and import 2026-10-04-stock-expiry-safeguards.sql (repository file: `database/upgrades/2026-10-04-stock-expiry-safeguards.sql`). Run all statements, stopping on errors. Expect one branch column, two sale-item columns and one migration record. Existing quantities, dates and sale totals remain unchanged; the added columns start as NULL.
4. Extract `build/hostinger-stock-expiry-safeguards-2026-10-04.zip` locally. Upload the enclosed `app/` files to the matching Hostinger application paths, preserving folders. The SQL and this guide belong in project documentation, not the public web root. No files need removal.
5. Reload the forms and verify the acceptance cases below before resuming operations. If PHP changes appear cached, clear the application/opcode cache using the hosting controls.

For local deployment, run `php spark migrate` using this project's configured application database, or import the same SQL before using the new code. The package does not run a database upgrade automatically.

## Files

- `app/Database/Migrations/2026_10_04_000010_add_stock_expiry_provenance.php`
- `app/Libraries/StockExpiryProvenance.php`
- `app/Libraries/ExpiryStockResolution.php`
- `app/Libraries/ProductExchange.php`
- `app/Models/BranchProductModel.php`
- `app/Models/SaleItemModel.php`
- `app/Controllers/Products.php`
- `app/Controllers/Admin/SaleCorrection.php`
- `app/Controllers/Cashier/SalesController.php`
- `app/Controllers/Cashier/ExchangeController.php`
- `app/Views/products/edit.php`
- `app/Views/admin/reports/expiry_action.php`
- `app/Views/cashier/sales/refund_partial.php`
- `app/Views/cashier/sales/exchange.php`
- `app/Views/admin/sale_correction/edit.php`

## Acceptance checks

Use a test database and test accounts.

1. Sell one unexpired product. Confirm its new sale item has a delivery identifier and the expiry seen at checkout. Return it as Resellable without replacing/increasing stock: the same stock increases correctly.
2. Replace the remaining inventory, including a test where the replacement has the same expiry. Return from the original invoice: Resellable is unavailable and a forged submission is rejected. Quarantine completes the refund/exchange without adding stock.
3. Try an older invoice whose provenance is NULL: quarantine remains available; Resellable is blocked. Never fill historical snapshots from current inventory merely to enable a return.
4. Verify Product Edit cannot change existing expiry, including a forged POST. Other normal edits with unchanged expiry still work.
5. As an administrator, correct a verified typing mistake. Check unchanged stock, original and corrected date in history, unchanged earlier sale snapshots, and no duplicate log on a repeated confirmation. A cashier must receive 403 for this action, even with a forged POST.
6. Change inventory while an expiry form is open: confirmation must reject stale values. Simulate a failed activity insert in an isolated database: the expiry correction and stock log must roll back together.

Automated verification results are recorded in the completion note added to the panelist review. The SQL is additive and repeatable; the application database and live Hostinger installation are not modified by building this package.

Validation: **158 unit tests / 21,690 assertions passed**, including 13 added expiry regression cases, and all 15 packaged PHP files passed syntax checks. The migration was tested in SQLite for preservation/idempotence; native MySQL import and live deployment were not executed. The ZIP includes `SHA256SUMS.txt`; packaging verifies every entry against the local source.
