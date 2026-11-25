# Inventory / POS audit, 27 October 2026

Read-only runtime review. Seven audit characterizations used the existing BusinessLogicTest setup copied as an audit-only base: in-memory SQLite, real controllers/services, no application database or hosted writes. These 7 tests / 22 assertions PASS because they assert the observed defects; this is reproduction evidence, not a clean regression or repaired behavior. All files are under this directory. The sandbox prevented PHP reading its bootstrap; a bounded approved PHP invocation ran the isolated tests.

## Confirmed findings

### INV01 HIGH: aliased refund item IDs over-refund and duplicate stock restoration (new)

At app/Controllers/Cashier/SalesController.php:1727-1728 every supplied key is cast to int, then appended at :1771 without a canonical-ID/duplicate check. Both preliminary validation and locked validation (:1803) run for all entries before any refund is inserted (:1845/:1895). Distinct request keys resolving to the same item can therefore both use the original refundable balance.

Reproduction: sold quantity 5, line net PHP50, branch stock10. A valid-token, valid-condition refund with two noncanonical keys resolving to item1, quantity5 each, commits 2 refund rows / 10 units / PHP100 and stock20. A unique token, CSRF and ordinary row locks do not prevent this same-request duplication. Reachable to an authorized staff account; refund payout and stock invariants are violated. Evidence: refund-alias.json; testAuditAliasedRefundItemIdsOverpayAndDoubleRestock. Fix direction: require canonical positive integer keys, deduplicate/aggregate per normalized ID and validate cumulative quantities/amounts under the sale lock.

ProductExchange::quantities (:37-50) normalizes into a keyed result, so this same alias does not append duplicate exchange lines. Checkout similarly keys cart by productID (:SalesController 758). SaleCorrection indexes plans against actual itemIDs and does not append the alias twice.

### INV02 HIGH: ordinary resellable returns and sale corrections bypass original delivery/expiry

SalesController.php:1862-1864 calls ReturnCondition with the CURRENT branch expiry. ProductExchange.php:149 does the same. They do not call StockExpiryProvenance::restockQuantity, which validates the original recorded expiry and same delivery. Expired or unknown original merchandise can consequently return to a newly dated/current delivery, despite the UI/service having a proper provenance helper.

SaleCorrection.php:224-230 validates availability/expiry only for increases; :243-246 blindly adds stock for decreases. Direct reproduction reduces an expired sale line5->3 and restores branch stock10->12; correction-expired.json. Delivery checks are absent in both directions, which explains existing failed safeguards in BusinessLogicTest.

The root suite independently reruns the corresponding pre-existing cases. Fix direction: use original sale snapshots + current delivery, locked inventory checks and explicit nonresellable return handling; reject historical quantity correction if it mixes deliveries.

### INV03 HIGH: replacement/receiving fails to rotate delivery identity, undermining Quarantine too

ExpiryStockResolution.php:103 changes stock/expiration_date but omits a new stock_delivery_id; :108 persists that unchanged identity. Products.php:792 stock updates likewise do not rotate identity for receiving/adds. Two physically different deliveries sharing the same date can appear identical to StockExpiryProvenance::assertSameDelivery.

Fresh reproduction: quarantine2 units from delivery A, replace available stock with20 new units dated2099-01-01, same as prior date. Delivery ID stays A; Quarantine::releaseProblem accepts and resolves the OLD return, producingstock22. Evidence replacement-quarantine.json; testAuditReplacementWithSameExpiryAllowsOldQuarantineRelease. Quarantine's own guard does check source/expiry; this is an upstream identity-invalidation defect, not a missing Quarantine access check.

### INV04 HIGH: ordinary Edit Product can re-date expired stock without the expiry audit workflow

Products.php:555 accepts submitted expiration_date during ordinary edit. At unchanged quantity it creates no stock movement; :593 writes only generic Updated product activity. ExpiryStockResolution::ACTIONS (:10) has no correction action. Reproduction edits2000-01-01->2099-01-01, preserves10 units, produces0 stock_logs and generic activity only. The date-based sale guard would now consider those same units unexpired. Evidence edit-expiry.json. This confirms pre-existing BUG02. Require privileged correction/reason, before/after audit, stale revision and preserve historical snapshots.

### INV05 HIGH: legacy receiving retries duplicate stock

Products.php:717 still permits stock_mode=receive. Token/stale protections at :743 and :770 cover add/remove, while :780 adds receive directly to the current balance. Repeating the identical5-unit request with old_stock10 twice changes10->15->20 and creates2 movements. Evidence receive-replay.json. This confirms BUG03; old payload remains reachable even though the UI now uses add/remove. Remove the legacy mode or add durable idempotency and a locked revision.

### INV06 MEDIUM: noncash checkout accepts overpayment/change with no payment reference

SalesController.php:801-810 validates amount/range, only length-checks reference. :933 requires sufficient tender for every method, :937 calculates change for every method. Reproduction GCash transaction netPHP10 accepts amount_paid100 / change90 / referenceNULL and commits. Evidence digital-overpay.json. This confirms BUG04. Exchange and correction payment differences already require a noncash reference; checkout does not. Define/validate exact noncash settlement and record actual externally confirmed payment before labeling it settled. This application currently records a method; it does not verify provider capture.

### INV07 MEDIUM: exchange replacement does not receive the POS automatic promotion

ProductExchange.php:167-174 initializes zero discount and only evaluates an explicitly selected discount_id. There is no ProductPromotion::all call in that service. Same active20% product offer on a PHP30 replacement yieldsPHP6 through POS helper butPHP0 in exchange quote/total30 when the default No discount is retained. Evidence exchange-promo.json. Existing explicit selection can apply the promotion manually, so this is a cross-workflow policy inconsistency, not evidence that no exchange discounts ever work. Confirm intended policy and reuse the server promotion engine to align automatic product/category offers.

## Protection confirmed in source / existing tests

- Checkout has one-time token hashes, completed-sale recovery and locked inventory stock/expiry/Trash rechecks; source snapshot capture occurs with locked inventory.
- Cart lines and exchange quantities normalize to keyed IDs, unlike the refund duplicate append.
- Refunds recalculate remaining quantities and exact net amounts under the sale lock for distinct canonical lines; partial returns preserve remaining centavos.
- Exchange uses deterministic inventory locking, server quote and idempotent linked invoice identity; statutory exchanges deliberately require refund + a new POS sale.
- New inventory rejects expiry today/past; POS/exchange sellability uses expiry>today, consistent with the owner's selected policy.
- Quarantine independently enforces admin inspection, original source/expiry/current availability and one final outcome. Damaged/Expired outcomes do not deduct stock twice. Source rotation flaw above still affects matching.
- Branch catalog assignment does not transfer physical stock; units do not perform automatic conversion.

## Boundaries / further verification

The initial source/characterization phase did not read the application DB. A later authorized local read-only health pass is recorded below; it does not verify hosted data. No real sale/refund/import or hosting request was sent. SQLite lock hooks cannot prove MySQL scheduling. Source syncMasterStock helpers in Products.php:970, SalesController.php:2053 and SaleCorrection.php:336 use a plain SUM before updating products, unlike Quarantine.php:127 current locking reads; cross-branch REPEATABLE READ snapshot drift/deadlock needs an isolated real-MySQL concurrency test before being presented as a confirmed finding. Multiple concurrent lots, FEFO, supplier purchase orders/credits, interbranch transfer records, unit conversions and disposal certificates are functional scope gaps rather than all being runtime bugs.

## Authorized follow-up: local database health

A bounded direct mysqli pass connected to the already-running loopback pharxmaco_drugstore database. It began START TRANSACTION READ ONLY, accepted only SELECT/SHOW through a statement whitelist, then ROLLBACK. Ninety-eight read statements ran; Com_insert/update/delete/create_table/drop_table/alter_table counters stayed zero. No service start, controller request, authentication event or data write. Evidence: db-health.php and db-health.json. Record references are hashed, not personal data.

- 27 tables; 67 products, 117 branch inventory records, 479 sales, 977 sale items, 24 refund lines, 1,031 stock logs, 11 cashier shifts, one quarantine resolution and 19 migration rows. Versions through 000018 appear once each.
- Zero negative stock/prices, master-versus-branch-SUM differences, duplicate nonempty SKU/invoice or product-branch pairs, over-refunded item quantities/amounts, over-refunded sale amounts, negative refunds or saved sale-line-total mismatches.
- All 50 installed FK constraints checked: zero orphan references. This proves these installed relationships, not absence of all possible logical data inconsistencies.
- 976 of 977 historical sale items lack original delivery and expiry snapshots; 104 of 117 branch inventory records lack delivery identity. These are provenance gaps, not fabricated retrospectively. Most historical resellable returns cannot establish original provenance.
- 41 branch inventory records retain positive stock with expiry on/before 27 October. This count includes inactive/trash records. It records physical/recorded quantities awaiting expiry resolution and is not evidence they can currently be sold.

The local source state differs from earlier supplied hosting exports; do not transfer these counts to hosting. Database date was 27 October 2026.
