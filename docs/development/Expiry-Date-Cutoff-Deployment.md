---
title: Expiry date cutoff deployment
type: deployment-guide
status: prepared
updated: 2026-10-23
tags: [deployment, expiry, pos]
---
# Expiry date cutoff deployment

The local application now treats an item as Expired at the start of its recorded expiration date, using the PHP server's Philippine date. Refresh Products to see today's dated items as Expired.

For hosting, upload the `app` folder from `build/hostinger-expiry-date-cutoff-2026-10-23.zip` over the corresponding application folder. The ZIP contains all 18 changed runtime files, this guide and a SHA-256 manifest. Preserve the directory mapping.

**No SQL import, migration or dependency installation is required.** Existing quantities and historical records are unchanged. Hosting has not been updated automatically. This package assumes the current matching application files and existing schema, including the previously installed return/quarantine features.

## Check after upload

1. Refresh Products. Yesterday and today should show Expired; tomorrow should show Near expiry. An item 31 days ahead should have no expiry warning badge and appear as Good in Expiry Report. Existing no-expiry handling should remain.
2. Today's expired stock must not be offered in POS search or accepted from an older cart/held sale. Exchange replacements must also exclude it.
3. Compare Expiry Report filters with its CSV/PDF, Dashboard and stock alerts. Today belongs only to Expired, never Near expiry.
4. A resellable refund or quarantine release must not return today's expired units to available stock. Quarantine/Damaged/Expired recording remains available.
5. New inventory with an expiry must use a date after today.

Do not process real transactions just to test this update; use an approved demo record/environment. Existing physical quantities are not automatically disposed, reduced or rewritten at midnight. Views recalculate status on the next request/refresh; an already open page may retain its earlier label, while server actions validate the current date.

Local verification: 38 focused tests / 397 assertions pass, plus two visually reviewed PDF pages and syntax checks. The broader suite retains 11 previously recorded inventory/provenance failures/errors. [Review and limits](../reviews/2026-10-23-expiry-date-cutoff.md).
