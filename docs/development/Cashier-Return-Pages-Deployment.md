---
title: Cashier return pages deployment
type: deployment-guide
status: prepared
updated: 2026-10-23
tags: [deployment, cashier, quarantine, damaged-items]
---
# Cashier return pages deployment

Use `build/hostinger-cashier-return-pages-2026-10-23.zip` for current Quarantine/Damaged Items pages with cashier visibility. This supersedes the earlier admin-only page bundles.

1. Upload the archive's `app` and `public` files using the existing project-folder mapping. Preserve directories and include the new `app/Libraries/ReturnRecordAccess.php` helper.
2. Sign in as a cashier and open **Inventory → Quarantine** and **Inventory → Damaged Items**. The branch is fixed to the account's assigned branch; each table and detail page shows that branch only.
3. Verify **View details**, completed inspection notes and links to the original sale/return slip. Cashiers have no quarantine inspection/save controls.
4. Sign in as an administrator and verify all-branch filtering and normal quarantine inspection controls.

**No new SQL import is required.** Existing Quarantine update 000018 remains necessary for quarantine history/outcomes. If not yet installed, its unchanged `database/upgrades/2026-10-23-quarantine.sql` and guide are included; select the existing application database in phpMyAdmin and follow that guide. Direct damaged return records work without that table.

Cashiers can view their assigned branch's records, including returns processed by other staff there. A cashier without a valid assigned branch cannot open the records. Administrators retain inspection/release authority. Changing a URL's branch or item ID does not expand cashier access.

The ZIP includes the current versions of both pages, their runtime dependencies, the existing optional Quarantine upgrade files, this README and a SHA-256 manifest. It contains no credentials, demo data, backups or reset scripts. It is an incremental update over the current application. Hosting has not been changed.

Verification: 20 PHP regression cases / 235 assertions, 27 read-only MySQL access checks across four cashiers, 18 existing isolated MySQL quarantine checks, seven damaged-ledger checks, three route-filter checks and 74 browser scenarios pass. All 27 local table hashes remain unchanged by the read-only access checks. See the [access review](../reviews/2026-10-23-cashier-return-pages.md).
