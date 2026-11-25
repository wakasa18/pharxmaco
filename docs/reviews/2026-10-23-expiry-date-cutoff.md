---
title: Expiry date cutoff update
type: implementation-review
status: verified
updated: 2026-10-23
tags: [expiry, inventory, pos, testing]
---
# Expiry date cutoff update

The owner explicitly selected **Expired and blocked from sale starting on the recorded expiration date**. An item dated 23 October is therefore expired from the start of 23 October, using the PHP server's Philippine calendar date. This records a system policy chosen by the owner.

## Behavior

| Recorded expiry | Classification | Sale availability from expiry rule |
| --- | --- | --- |
| Yesterday or earlier | Expired | Blocked |
| Today | Expired | Blocked |
| Tomorrow through 30 days ahead | Near expiry | Allowed subject to other stock checks |
| More than 30 days ahead | Good | Allowed subject to other stock checks |
| No applicable expiry | No expiry date | Existing handling retained |

Expired comparisons now use expiry <= today; available/near comparisons require expiry > today. Products, POS, My Day stock alerts, Dashboard, topbar notifications and expiry report filters/CSV/PDF share the cutoff. The dashboard shows Today for a newly expired item.

POS enforces the cutoff when listing/searching inventory, adding/updating a cart, resuming a held cart, and validating checkout, including its inventory-lock check. An expired held cart stays saved for review. Exchange replacement selection and server processing, increasing a corrected sale quantity, resellable refunds and quarantine release use the same boundary. Quarantine, damaged and expired return recording remain available without returning units to available stock.

Creating new branch inventory rejects an expiry on or before today; date inputs start at tomorrow. Existing records, sale snapshots and physical quantities are preserved. No migration or SQL import is needed. The expiry-replacement workflow already required a future date.

## Implementation and evidence

Eighteen runtime PHP files changed across controllers, libraries and views. See the archive manifest for exact file hashes. Fifteen boundary cases were added in `tests/unit/ExpiryCutoffCases.php`, required by `BusinessLogicTest.php`.

- Focused regression: **38 tests / 397 assertions passed**, including 15 new expiry cases / 99 assertions.
- Cases cover yesterday, today, tomorrow, +30, +31 and missing dates; actual view labels, query filters, POS actions and locked checkout, exchange quote/confirmation, held-cart preservation, refunds, sale correction, quarantine release, new inventory and notification counts.
- The actual expiry PDF view was rendered through the current PDF factory and stamping code. Both pages were inspected visually; statuses, summaries, page numbers and page-edge text bounds passed.
- All 18 changed runtime files pass PHP syntax checks.
- Full BusinessLogic run: **280 cases / 23,505 assertions; 269 pass, 9 fail, 2 error**. The 11 unsuccessful names match the previously recorded inventory/provenance and expiry-edit cases. These are still open in [Known issues](../project/Known-Issues.md); this update is not a clean whole-system audit.

Tests used in-memory SQLite and synthetic rendered fixtures. No application database access/write, SQL import or hosted deployment was performed. Hosted browser behavior remains a post-upload check.

Local evidence: `writable/expiry-date-cutoff/focused.xml`, `business-summary.json`, `pdf-results.json` and `package-verification.json`. The two-page sample is `tmp/pdfs/expiry-date-cutoff/expiry-boundaries.pdf`.

[Deployment instructions](../development/Expiry-Date-Cutoff-Deployment.md) ? [Inventory reference](../system/Inventory-and-Expiry.md).
