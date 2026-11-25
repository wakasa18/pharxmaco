# POS and inventory logic audit — 15 October 2026

The system has a useful retail POS foundation, with tested discounts, checkout safeguards and shared-drawer shifts. It still has important inventory traceability defects and missing pharmacy workflows. I would use the present setup for demonstration and controlled testing while these issues are addressed.

This review did not change application business logic or database records. The additional reproduction scripts write only to the existing in-memory SQLite test fixture. The local application database inspection ran inside an explicitly read-only transaction.

## Verification performed

- Reviewed checkout/cart, branch inventory, pricing/cost snapshots, discounts, invoice numbering, refunds, exchanges, administrator sale corrections, stock adjustments, expiry actions, cashier shifts, held sales, My Day, reports, roles and retained historical records.
- Ran the existing PHP unit suite: **196 tests, 22,048 assertions; 184 passed, 9 failed and 3 errored**. The twelve unsuccessful checks concern delivery/expiry provenance, returns, quantity corrections and the expected expiry-correction action. Test errors are not twelve separate production crashes.
- Ran `node tests/discount-math.test.cjs`: shared centavo cases, actual POS eligibility/rounding checks, and Senior Citizen/PWD VAT, Non-VAT and mixed-tax cases passed.
- Ran three additional isolated controller reproductions: duplicate legacy stock receiving, GCash overpayment without a reference, and Card overpayment without a reference. All three reproduced the weaknesses described below. These are observation tests that pass when the current weakness is reproduced.
- Inspected local `pharxmaco_drugstore`: **67 products, 117 branch inventory rows, 477 sales and 975 sale items**. These counts describe this local database, not the hosted database.

## What works in the tested scenarios

| Area | Checks performed and result |
| --- | --- |
| Checkout | Invalid quantities, insufficient stock, expired current stock, inactive/deleted products and branch restrictions are rejected. Checkout rechecks relevant inventory, price, cost and product state under locks. |
| Duplicate checkout and failure recovery | Checkout tokens/recovery prevent the tested repeated submissions from charging twice. Failed sale-item, stock-log or required checkout audit writes roll back the tested transaction. |
| Promotions | Product/category/all-product scope, eligible subtotal minimums, fixed discount applied once, percentage caps and exact centavo allocation passed. Nonmatching products retain their normal amounts. |
| Senior Citizen/PWD | Selected eligible lines, beneficiary name/ID and document confirmation, VAT relief before the 20% discount, Non-VAT treatment, rounding and saved beneficiary metadata passed. A PHP 112 eligible VAT-inclusive line becomes PHP 100 before a PHP 20 discount, for PHP 80 payable. This validates the implemented calculation, not every product's legal eligibility. |
| Refund amounts | Remaining quantities and recorded net amounts are checked in current tested refund flows. Partial refunds, payout date/method, non-resellable conditions and repeated submission protections have coverage. Stock provenance remains unsafe as described below. |
| Exchanges and corrections | Linked exchange records, actual settlement differences, promotion allowances, administrator correction permissions, stale review rejection for tested price/quantity changes and rollback have coverage. Delivery/expiry changes are the important exception. |
| Cashier work | Start Shift routing, one shared drawer owner, counted opening cash, handover reservation, expected cash, cash additions/removals, insufficient cash payout rejection, closing differences, frozen closing summaries and manager review passed. |
| Held sales and My Day | Held carts are scoped to the cashier/branch and resume against current prices and availability. Day totals separate sales, refunds, exchanges and payment methods; historical unknown refund methods remain explicitly unknown. |
| Product/history handling | Branded/Generic/Non-medicine classification, branch assignments, retained snapshots, trash/restore scope and role/session revocation have coverage. Historical transaction references are retained. |
| Inventory monitoring | Low-stock, near-expiry, expired stock, branch availability and reorder forecasting exist. Forecast persistence/rollback and cross-branch handling have tests; reorder planning still uses a fixed coverage assumption. |

Passing checks describe their tested scenarios. They do not mean every route, permission combination or production race has been exhaustively tested.

## Confirmed defects and data issues

### 1. High: sold stock is not tied reliably to its original delivery and expiry

Checkout does not populate `sale_items.stock_delivery_id` or `expiration_date_at_sale`. `SaleItemModel` also omits both fields from its allowed fields. The existing `StockExpiryProvenance` helper is not connected to the main checkout/refund/exchange paths.

Refunds and exchanges decide whether to restock using the **current inventory expiry**, rather than the returned item's original delivery and expiry. Replacing inventory or replenishing it does not reliably create a new delivery identity. Quantity corrections can also adjust current stock without proving it represents the original delivery.

Example: an item sold from an older expired delivery can be marked resellable after a newer delivery replaces stock, because the newer expiry is checked. The existing failing tests reproduce this class of problem. The exchange review also fails to invalidate a quote when only the replacement expiry changes.

Evidence: `app/Controllers/Cashier/SalesController.php:886` and `:1652`; `app/Models/SaleItemModel.php:15`; `app/Libraries/ProductExchange.php:149`; `app/Controllers/Admin/SaleCorrection.php:213`; `app/Libraries/ExpiryStockResolution.php:98`; tests beginning at `tests/unit/BusinessLogicTest.php:846`.

All **975 existing local sale items** lack a complete delivery/expiry snapshot. Historical fields were added later, so this count alone does not establish how each older transaction was processed. Unknown historical batches must remain unknown; they should not be backfilled with today's stock identity.

### 2. High: normal product editing bypasses expiry correction safeguards

The administrator product-update path writes the posted expiry directly, including when quantity is unchanged. It does not route the date change through a specific audited expiry-correction action. The dedicated expiry library currently supports replace/remove/deactivate, while the tests also expect an administrator correction action that is not implemented.

Evidence: `app/Controllers/Products.php:552`; `app/Libraries/ExpiryStockResolution.php:11`; `tests/unit/BusinessLogicTest.php:1026` and `:1041`.

### 3. High: legacy receiving can double-count the same submission

`stock_mode=add/remove` has a one-time session token and stale-count checks. The accepted legacy `stock_mode=receive` path lacks those duplicate-submission protections. Calling it twice with the identical five-unit request changes stock **10 → 15 → 20**, creating two logs.

This was reproduced against the real controller in SQLite. It is a remaining server-side path even if the current interface normally uses Add.

Evidence: `app/Controllers/Products.php:739` and `:777`; `writable/pos-inventory-audit/probe-results.json`.

### 4. High: existing local stock totals and one historical refund need reconciliation

- **Nine product master stock counts differ from the sum of their branch stock rows.** For example, product #7 has master stock 80 and branch total 113. Checkout checks branch stock, so a mismatch does not by itself prove overselling. It does mean the duplicated master total cannot be relied on until reconciled. The cause of these existing differences was not established by this audit.
- **Sale #41 has PHP 500 net merchandise value but PHP 625 in recorded refunds**, across three fully returned lines. The excess recorded amount is **PHP 125**. Quantities do not exceed quantities sold. This identifies a historical accounting discrepancy, not proof of money actually paid out. Current refund calculation tests pass; the older record needs separate review rather than rewriting history automatically.

Evidence: `writable/pos-inventory-audit/local-data-results.json`, including per-product stock differences and refund line totals.

### 5. Medium: digital checkout permits cash-style change without a reference

Both GCash and Card checkout accepted a **PHP 10 sale with PHP 100 paid, PHP 90 change and no reference number** in isolated controller tests. Checkout only requires tender to be at least the total. Other settlement paths have stricter reference rules.

There is no payment gateway/terminal confirmation integration in the reviewed application paths. Digital payments are manually recorded and must be verified operationally. The local data contains **59 noncash sales without references** and **10 noncash sales with positive change**; those records need review, not an assumption that every one is invalid.

Recommended behavior: require a reference/authorization identifier and exact digital settlement, or model any intentional overpayment/change explicitly by payment method. A reference alone is not proof that a provider settled the payment.

Evidence: `app/Controllers/Cashier/SalesController.php:643`, `:763`; reproduction results JSON.

## Missing or incomplete real pharmacy capabilities

### Batch stock and FEFO

Each product has one branch inventory row with one stock quantity and expiry. The expiry replacement implementation explicitly removes the entire old stock before adding a new expiry. It cannot retain two deliveries of the same medicine with different expiry dates, costs and lot numbers at the same time.

A real batch model should record delivery/lot, expiry, received quantity, remaining quantity, supplier document and cost; allocate the earliest usable expiry first; and preserve the allocation on each sale/return. It also enables lot recalls and separate quarantined stock. WHO describes batch segregation and first-expiry-first-out stock rotation in its [storage guidance](https://www.who.int/docs/default-source/medicines/norms-and-standards/guidelines/quality-control/trs986-annex3-who-model-quality-assurance-system-for-procurement-agencies.pdf).

### Prescription dispensing

Branded/Generic is not an OTC/prescription classification. The reviewed application has no dedicated Rx flag, prescription/patient/prescriber record, dispensing pharmacist approval or partial-fill balance workflow. The benefit document checkbox does not provide those functions for ordinary medicine sales.

These activities may currently happen manually outside the software; the system itself does not record or enforce them. [RA 10918, Section 33](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/70354) addresses valid prescriptions, pharmacist dispensing and partial filling, so this is a material pharmacy workflow gap.

### Receiving, transfers, units and purchasing

- Supplier records and stock logs exist, but there is no complete purchase order / supplier invoice / receiving document workflow in the reviewed routes/controllers.
- Stock-transfer view files exist, but no corresponding active transfer route/controller workflow was found. Branch availability lookup is not an inventory transfer.
- `unit` is a label; there is no box → strip → tablet conversion with one base stock quantity.
- Reorder suggestions use a fixed **30-day coverage** assumption, rather than supplier lead time, safety stock, outstanding purchase orders and available batch shelf life (`ReorderForecastService.php:30`).
- Gross profit is a merchandise margin report. It is not a full accounting net-profit calculation with expenses, input VAT and accounting journals.

These are additional capabilities, not all immediate checkout defects. Their priority depends on the actual operating process.

## Configuration and operating policy needing confirmation

- Effective local invoice settings have **no seller TIN and no enabled registered serial range**. The locked serial allocator and range-exhaustion behavior have tests, but current receipts fall back to an internal transaction reference. Actual registration details remain necessary before treating invoices as registered production documents.
- All **67 products are VATable**, as previously requested for this setup. That is a catalog setting, not a verified real-world tax assessment. Specific product registrations must be checked against applicable FDA/BIR drug lists; BIR publishes an updated list in [RMC 87-2026](https://bir-cdn.bir.gov.ph/BIR/pdf/RMC%20No.%2087-2026_redacted.pdf). This audit did not recalculate the catalog's legal classifications.
- **14 products have unconfirmed Senior Citizen/PWD eligibility.** The implementation intentionally blocks benefits for unconfirmed products. One combined eligibility flag and a cashier document confirmation do not independently establish each transaction's entitlement.
- Cashiers currently can create products, assign catalog products to their own branch, adjust stock, and process allowed refunds/exchanges. The branch restrictions are tested, but decide which actions should require manager approval or limits for your business.
- Demo stock, prices and expiry dates remain sample values. They need a physical stock count and actual receiving documents before production use.

## Other local data checks

| Check | Local result |
| --- | ---: |
| Negative branch stock | 0 |
| Duplicate product/branch inventory pairs | 0 |
| Duplicate invoice references | 0 |
| Sale items missing their parent sale | 0 |
| Invoice header totals disagreeing with item totals/discount/VAT relief | 0 |
| Tender minus change disagreeing with recorded final total | 0 |
| Active stocked Branded/Generic medicine rows without expiry | 0 |
| Stocked inventory rows without delivery ID | 97 |

These checks establish internal record consistency only. Physical stock, provider settlement and legal registration were not verified by these SQL checks.

## Recommended order of work

1. Connect delivery/expiry provenance to checkout, refunds, exchanges, corrections and replenishment; make all twelve unsuccessful safeguards pass. Reject unknown-origin returns from sellable stock and prevent ordinary edits from silently changing expiry.
2. Close the duplicate receiving path and standardize payment reference/settlement validation.
3. Reconcile the nine master stock differences and sale #41's recorded refund discrepancy with the original records. Preserve an audit trail rather than silently overwriting history.
4. Introduce batches/FEFO, receiving documents and unit conversion; then add transfers if used in daily operations.
5. Add or document the Rx/pharmacist workflow, verify product tax/benefit classifications, configure actual invoice registration and agree manager approval policies.

The automated tests use SQLite and simulated competing writes; they do not verify actual simultaneous MySQL lock scheduling. This review also did not exercise real payment hardware, provider settlement, production hosting, backup disaster recovery or clinical dispensing practice. Those remain separate acceptance checks.

Reproduction artifacts: `writable/pos-inventory-audit/AuditProbeTest.php`, `probe-results.json`, `inspect-local.php` and `local-data-results.json`.
