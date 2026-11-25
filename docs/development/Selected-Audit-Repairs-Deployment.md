---
title: Selected audit repairs deployment
type: deployment-guide
status: prepared
updated: 2026-10-28
tags: [deployment, pos, inventory, reporting]
---
# Selected audit repairs deployment

The ten selected audit findings are repaired locally. Refresh the POS or reopen an existing refund/exchange form to load the current controls and review data.

For hosting, extract `build/hostinger-selected-audit-repairs-2026-10-28.zip` locally and upload its `app` contents over the corresponding application folder, preserving paths. The archive includes changed runtime files, their current shared helper dependencies, this guide and a SHA-256 manifest. Keep the target host's own configuration and writable data.

**No new SQL import, migration, Composer update or historical-data rewrite is required.** This package targets the matching application/schema reviewed on 27 October, including existing delivery/expiry snapshots and update 000018 for Quarantine. Missing original history is not backfilled. The hosted installation has not been changed automatically.

## Checks after upload

Use an approved demo environment/records for transactions. Do not create real payments or refunds just to test a deployment.

1. Default No Discount still applies matching product/category promos. Senior/PWD uses the favorable treatment per eligible item. All-product offers remain manual without stacking.
2. GCash/Card require a reference and exact final amount, with no cash change. Change quantities/benefits and confirm the exact amount updates. Cash tender/change works normally.
3. Refund a known current delivery only once. Missing, expired or different original delivery must not be offered as Resellable; Quarantine/Damaged/Expired stay available.
4. Receive stock with Add stock and retry the same action. There should be one increment/log. Old receive payloads ask the user to reopen the current form.
5. Replace stock, including the same-expiry case. Old returns must not match that new delivery. Quantity corrections require their recorded, matching, unexpired delivery.
6. Review an exchange with matching product/category offers; check replacement savings, original return credit and settlement. A stale promo/expiry/delivery review must be reopened before confirmation.
7. Download Sales CSV, Full PDF and Summary PDF with normal, filtered and empty ranges. Summary uses sales_summary_; Full uses sales_report_. Only generated exports should appear in history.
8. Check a released quarantine return's recovered cost in its original sale-period report. Pending/Damaged/Expired units retain their cost loss. The original refund remains unchanged.

Historical returns with missing delivery/expiry data can be refunded under a nonresellable condition. Available stock is protected; the update does not fabricate old batch information or change existing balances. An internal delivery ID is not a manufacturer lot number.

Local validation and remaining unselected findings are recorded in the [repair review](../reviews/2026-10-28-selected-audit-repairs.md). In particular, A05 expiry editing still has three failing regression cases. This upload does not include dependency or server-layout fixes from other audit findings.
