---
title: Expiry calendar bounds deployment
type: deployment-guide
status: prepared
updated: 2026-10-28
tags: [deployment, expiry, calendar]
---
# Expiry calendar bounds deployment

Extract `build/hostinger-expiry-calendar-2026-10-28.zip` and upload its `app` and `public` contents into the matching application folders, preserving paths. It includes the current expiry limit, calendar fix and preceding [selected audit repairs](Selected-Audit-Repairs-Deployment.md), plus this guide and a SHA-256 manifest. The target remains the matching application/schema reviewed on 27 October, including quarantine update 000018 and delivery/expiry snapshots.

**No SQL import or migration is needed.** Keep the host's `.env` and writable data. Hosting was not changed automatically.

After uploading, close old product forms and use **Ctrl+F5** to load the updated calendar and field scripts. Check an existing product dated within the range, open its calendar, and open the year grid before choosing anything. Years outside the allowed range must already be disabled, and the navigation arrows must stop at the range boundary. Check the first and last allowed months/days, keyboard navigation and Escape as well.

An unchanged legacy expiry may stay in the product record, while its calendar opens inside the allowed range. Opening or cancelling the calendar must not rewrite that old value. Report date filters keep their own date behavior. Touch devices retain their OS date picker and input limits.

The expiry policy remains tomorrow through **31 December 2036** during 2026, advancing with the Philippine calendar year. There is no administrator review/override. Configuration remains `InventoryExpiry::yearsAhead`, default ten; see the [limit guide](Expiry-Date-Limit-Deployment.md).

[Verification and limits](../reviews/2026-10-28-expiry-calendar-bounds.md). The previously documented A05 dedicated expiry-correction workflow remains outside this fix.
