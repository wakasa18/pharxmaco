---
title: "Discount fixes: files-only Hostinger upload"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-09-20-discount-deployment.md"
source_sha256: c0e3ba703e1ab04d8b3d45c03970824d69e8c1cf8a2927ee4b690a5412a695af
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-09-20-discount-deployment.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Discount fixes: files-only Hostinger upload

No SQL import or migration is needed for these discount fixes. Use the existing database with the previously completed September 17 update.

1. Upload these application files, preserving their folders:
   - `app/Libraries/DiscountPolicy.php`
   - `app/Controllers/Cashier/SalesController.php`
   - `app/Controllers/Admin/Discounts.php`
   - `app/Controllers/Admin/SaleCorrection.php`
   - `app/Views/cashier/sales/index.php`
   - `app/Views/admin/sale_correction/edit.php`
   - `public/assets/js/discount-math.js`
2. Reload open POS/correction tabs. Verify the discount preview and correction preview before reopening checkout.

If the earlier snapshot-based version was already uploaded, also replace `app/Models/SalesModel.php` and `app/Libraries/SaleRevision.php` with the current versions. The application no longer reads or writes `discount_snapshot`; an already-added column can remain unused. Do not run the superseded September 20 SQL file or migration. No existing columns or migration-history records need to be deleted.

Checkout rounds in centavos before subtracting discounts or comparing minimums. Discount amounts accept at most two decimal places, and positive amounts must be at least 0.01.

Discounted sale quantities are read-only, with the same restriction enforced on the server. Use the existing refund workflow and a new sale to change these items. Notes and payment details remain editable without rewriting original totals, profits or item discounts. Quantity corrections for sales without discounts continue to work.

The JavaScript asset is required by both edited views. No test scripts or files under `writable/receipt-review` need to be uploaded.
