# Quarantine page update

The administrator workspace now has **Inventory → Quarantine**. The page reads existing refund lines whose original condition is `quarantined`, including exchange returns. It starts with pending inspections and offers branch/status filters, quick search, sorting and pagination.

Cashiers can also view their assigned branch's records and completed outcomes. They do not approve inspections or release stock. Use the current `build/hostinger-cashier-return-pages-2026-10-23.zip` and [cashier visibility upload guide](../../docs/development/Cashier-Return-Pages-Deployment.md) for the latest application files. No additional SQL update is needed for cashier visibility.

## Install on the hosted system

1. Export a backup of the application database in phpMyAdmin.
2. Select the existing application database, then import the entire `database/upgrades/2026-10-23-quarantine.sql` file. Do not import a full old database export over current records.
3. Check for **Quarantine upgrade complete**. Migration `2026_10_23_000018` should have one record.
4. Upload the matching `app` and `public` files from `build/hostinger-quarantine-2026-10-23.zip`, preserving their folders. Upload through the same mapping used by earlier application updates; the archive is rooted at the project folder.
5. Sign in as an administrator, open Quarantine and verify pending returns. Uploading files alone displays a database-update notice until the SQL is imported.

This is an additive update for the current application schema. Updates 000010–000017 should already be installed. The previously reported hosted **24 cashier foreign keys / migration 000017 missing** state has a separate completion file; use the [cashier import recovery guide](2026-10-21-finish-cashier-foreign-keys.md) to finish that registration if still pending.

The import creates one empty `quarantine_resolutions` table and records migration 000018. It reads parent key types so signed and unsigned installations match their existing keys. It preserves existing sales, refunds, stock, users and shifts; reimporting also preserves completed inspections. No old refund is automatically resolved or moved into stock.

## Inspection

- Choose **Inspect return**. Verify the product, original sale, branch, held quantity and recorded expiry.
- Choose **Release to stock**, **Mark damaged**, or **Mark expired**.
- Enter an inspection note and confirm physical inspection of all units. Choose **Save inspection**.
- Find completed inspections with the status filter and **View inspection**.

Release is available only for an active product/branch/inventory record, with known original delivery and expiry matching the current inventory, and an expiry accepted by the existing stock rules. Unknown or changed delivery records must not be reconstructed from current stock. The form refreshes after intervening stock/return changes. New POS and exchange replacement lines now retain delivery/expiry snapshots; historical lines are not backfilled.

A release adds the returned quantity once to its original branch, recalculates master stock, and saves a `returned` stock log linked to the inspection. Damaged/expired outcomes do not deduct available stock again. Each return line has one final outcome, inspecting administrator, time and note. The original refund remains Quarantined and retains its original amount/condition.

The workflow resolves whole refund lines. It does not record partial inspections, supplier credits, physical disposal certificates, manufacturer lot numbers or multiple simultaneous stock lots. Choosing Damaged/Expired records the inspection classification; it does not claim physical disposal occurred.

## Local status and evidence

Applied locally after a full SQL backup; all 25 pre-existing non-migration data-table hashes and earlier migration rows were preserved. Three existing quarantined returns are pending; no inspection or demo refund was added to the application database. Hosting has not been changed.

Validation: 15 focused PHP cases / 189 assertions, 18 isolated MySQL checks (including four simultaneous inspectors), 59 browser scenarios, and PHP syntax checks. A broader run has 248 cases: 237 pass, nine fail and two error in existing inventory/refund/expiry safeguards. See the [implementation review](../../docs/reviews/2026-10-23-quarantine-page.md).
