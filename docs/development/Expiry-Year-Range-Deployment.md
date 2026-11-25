---
title: Expiry year range deployment
type: deployment-guide
status: superseded
updated: 2026-10-28
tags: [deployment, expiry, inventory]
---
# Expiry year range deployment

**Superseded:** the owner removed administrator review. Use the [expiry date limit upload guide](Expiry-Date-Limit-Deployment.md) and its current package. The earlier instructions below are retained as historical documentation.

Extract `build/hostinger-expiry-year-range-2026-10-28.zip` locally. Upload its `app` and `public` contents into the matching application folders, preserving paths. The archive contains the expiry changes, current shared dependencies and the preceding [selected audit repairs](Selected-Audit-Repairs-Deployment.md), plus this guide and per-file SHA-256 hashes. It targets the matching application/schema already reviewed on 27 October, including the existing quarantine update 000018 and delivery/expiry snapshot fields.

**No new SQL, migration or Composer update is needed.** Keep the host's own `.env` and writable data. Refresh/reopen product and expiry forms after uploading both PHP and assets. Hosting has not been changed automatically.

The default normal range ends on **31 December 2036** during 2026, advancing with the Philippine calendar year. Configure `yearsAhead` in `app/Config/InventoryExpiry.php` or optionally set `inventoryexpiry.yearsAhead = 10` in the host's existing `.env`. Supported values are 1–100; invalid numeric values fall back to ten. This setting does not change a product's actual expiry.

Use approved demo records in a test installation to check:

1. Add Product and Add from Existing Products allow tomorrow through the normal maximum. Today and later-than-limit dates are rejected without review.
2. A cashier has no exception control. A legitimate longer date must be verified and saved by an administrator through product editing/creation or replacement.
3. An administrator confirms the packaging date and supplies a 3–200-character reason before saving a later date. Activity Logs shows **Expiry range review**, the accepted date, normal maximum, reason and staff member.
4. Leave an old expired or long-dated product expiry unchanged and edit another field. The original date and sale snapshots remain intact. Changing the date reapplies the range rules.
5. Replace stock with an ordinary future date. Replacement removes old stock and records the new delivery once. A later date still needs administrator review, including when it equals the old date.
6. Reopen the form after a validation error and check its retained date/review/reason. Without JavaScript, normal dates remain usable and the administrator exception remains conservatively unavailable in the browser.

The [implementation review](../reviews/2026-10-28-expiry-year-range.md) records local tests and limitations. The existing **A05 expiry-correction workflow** still has three failing regression cases. This package does not claim a clean whole-system or hosted-deployment sign-off.
