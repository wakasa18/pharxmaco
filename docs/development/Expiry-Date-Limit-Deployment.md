---
title: Expiry date limit deployment
type: deployment-guide
status: prepared
updated: 2026-10-28
tags: [deployment, expiry, inventory]
---
# Expiry date limit deployment

Use the newer [expiry calendar package](Expiry-Calendar-Deployment.md) for the pre-selection year-grid fix. It includes this date-limit behavior. The earlier package instructions are retained below.

Extract `build/hostinger-expiry-date-limit-2026-10-28.zip` locally. Upload its `app` and `public` contents into the matching application folders, preserving paths. This cumulative package contains the limit-only expiry update, shared dependencies and the preceding [selected audit repairs](Selected-Audit-Repairs-Deployment.md), plus this guide and per-file SHA-256 hashes. It supersedes the earlier expiry package with administrator review.

**No new SQL, migration or Composer update is needed.** Keep the host's own `.env` and writable data. Refresh or reopen product and expiry forms after uploading both PHP and assets. Hosting has not been changed automatically.

The limit applies to administrators and cashiers equally. New or changed expiry dates must be after today and on or before **31 December 2036** during 2026. The maximum advances with the Philippine calendar year. Configure `yearsAhead` in `app/Config/InventoryExpiry.php` or optionally set `inventoryexpiry.yearsAhead = 10` in the host's existing `.env`. Supported settings are 1–100; invalid numeric values fall back to ten.

Use demo records in a test installation to check:

1. Add Product and Add from Existing Products accept tomorrow through the maximum. Today and later-than-limit dates are rejected.
2. Administrator and cashier forms have no expiry-review checkbox or exception-reason field. An administrator cannot submit a later date.
3. Leave an existing expired or long-dated expiry unchanged and edit another field. The original date and sale snapshots remain intact. Changing that date reapplies the bounds.
4. Replace stock with an ordinary future date. Replacement records the old stock out and new stock in once. It rejects an out-of-range date even if that date matches the old delivery.
5. Reopen the form after a validation error and check that the entered date is retained. Browser bounds still protect normal entry without JavaScript; the server validates every submission.

Historical review activity entries are preserved as records of earlier operations. The removed override does not create new special expiry-review entries.

The [implementation review](../reviews/2026-10-28-expiry-limit-only.md) records verification and limitations. The existing three A05 expiry-correction test issues remain. This package is not a whole-system or hosted-deployment sign-off.
