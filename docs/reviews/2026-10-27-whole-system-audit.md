---
title: Whole system logic and security audit
type: audit
status: partially-repaired
updated: 2026-10-28
tags: [audit, security, pos, inventory, reporting]
---
# Whole system logic and security audit

**28 October follow-up:** the owner-selected A02/A03/A04/A06/A08/A09/A10/A11/A13/A15 are now [repaired and verified locally](2026-10-28-selected-audit-repairs.md). The findings below retain the original 27 October evidence. A01/A05/A07/A12/A14 and hosted verification remain open.

Pharxmaco has the main structure of a retail POS: branch inventory, recorded sales, tender/change, receipts, partial returns, cash shifts, adjustments and reports. However, this review confirmed financial/inventory defects and an actual local server exposure. The current source and deployment should not receive a production-readiness sign-off based on the passing tests alone.

This was an audit, not a repair. Runtime files, accounts, application records, schema and hosting were not changed. Characterizations below intentionally reproduce bad behavior; passing such a characterization does not mean the bug is fixed.

## Comparison with established systems

Official documentation was consulted on 27 October 2026. These references are workflow comparisons, not evidence that Pharxmaco must implement every ERP feature or match another system's expiry-day policy.

| Reference | Relevant baseline | Pharxmaco assessment |
| --- | --- | --- |
| [Odoo POS](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale.html), [POS workflow](https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/use.html) | Opening/closing cash, traceable orders, returns, tender methods and employee permissions | The corresponding workflows exist. Retry, return, noncash settlement and reporting defects below need correction. |
| [Odoo expiration dates](https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/product_management/product_tracking/expiration_dates.html) | Lot-linked expiry and traceability; FEFO stock selection | Pharxmaco has one current expiry per product/branch. Internal delivery IDs are not manufacturer lots, and receiving does not consistently invalidate them. |
| [ERPNext stock reconciliation](https://docs.frappe.io/erpnext/stock-reconciliation), [batch inventory](https://docs.frappe.io/erpnext/batch) | Physical/book count reconciliation, auditable movements and batch identity | Delta adjustments and retained history exist. Duplicate receiving and unsafe restocking undermine the balances. Multiple concurrent batches remain outside current capability. |
| [Square returns/exchanges](https://squareup.com/help/us/en/article/6350-process-a-return-or-exchange-with-square-for-retail) | Original transaction linkage, refund permissions and explicit restocking choices | Pharxmaco records return conditions and original sales, but ordinary restocking can disregard original delivery/expiry. |

Security review considered [OWASP's web application risk categories](https://owasp.org/projects/top-ten), source/configuration and current vendor advisories. No security certification or exhaustive absence-of-vulnerabilities claim is made.

## Confirmed findings, in recommended repair order

### A01 — High: configuration and archives are exposed by local Apache

Unauthenticated HEAD requests to the actual local Apache returned HTTP 200 for the project-root `.env`, `composer.json`, a database seed SQL, `backuppp.zip` and `docs/`. The protected `app/Config/Database.php` and `writable/` returned 403. Response bodies containing credentials or backup contents were not requested.

Apache serves the parent `www` directory; the project root has no `.htaccess`. Denials inside `app/`, `writable/` and `public/` do not cover sibling files. Using a URL ending in `/public` does not prevent direct requests to its parent tree.

Evidence: `writable/system-audit-20261027/security/static-exposure.json`; `C:/UniServerZ/core/apache2/conf/httpd.conf:301`, `:316`, `:328`, `:343`; `public/.htaccess:2`, `app/.htaccess:2`, `writable/.htaccess:2`, `app/Config/Paths.php:89`.

Serve only `public/`, or use the private-project/public_html layout. Keep SQL/ZIP/env/development files outside the served tree. If untrusted users could reach this server, rotate exposed credentials after access is removed. Internet/LAN reachability and the hosted server's layout were not tested; the confirmed exposure is local.

### A02 — High: one refund request can refund the same item twice

Refund array keys are cast to integers and appended without detecting duplicate normalized IDs. All entries are checked before any entry is inserted, so two keys representing the same item can both consume its original remaining quantity and refund balance.

Isolated reproduction: a line sold 5 units for PHP 50 accepted two entries targeting that line, each for 5 units. It committed 10 returned units, PHP 100 refunded, and stock 10 to 20. The request still requires an authorized account and valid tokens; CSRF and sale locks do not prevent duplication within the request.

Source: `app/Controllers/Cashier/SalesController.php:1727`, `:1771`, `:1803`, `:1845`. Evidence: `inventory/refund-alias.json` beneath the audit directory. Require canonical item IDs, reject duplicates and validate cumulative amounts/quantities under the sale lock.

### A03 — High: ordinary returns and corrections can restore the wrong or expired stock

Resellable refunds and exchange returns validate the current branch expiry, rather than validating the original sale's expiry and delivery against current stock. Unknown or old returned merchandise can enter a newer delivery. Sale corrections also increase stock when quantity is reduced without a return-condition/provenance check.

An isolated correction of an expired sale line from 5 to 3 restored stock from 10 to 12. Existing original-expiry/delivery refund, exchange and correction safeguard tests remain unsuccessful.

Source: `app/Controllers/Cashier/SalesController.php:1862`, `app/Libraries/ProductExchange.php:149`, `app/Controllers/Admin/SaleCorrection.php:224`, `:243`. Evidence: `inventory/correction-expired.json` and the full suite. Apply original snapshots, delivery validation and locked inventory checks consistently; route nonresellable returns to their recorded condition.

### A04 — High: receiving/replacement retains old delivery identity

Replacing stock or manually replenishing it does not consistently assign a new `stock_delivery_id`. Physically different deliveries with the same expiry can therefore pass a source-identity comparison.

Reproduction: quarantine 2 old units, replace available stock with 20 units having the same future expiry, then release the old quarantine return. The delivery ID remains unchanged and the service accepts the release, producing 22 units. Quarantine has a source check; the upstream receiving identity is wrong.

Source: `app/Libraries/ExpiryStockResolution.php:103`, `:108`, `app/Controllers/Products.php:792`. Evidence: `inventory/replacement-quarantine.json`. Rotate identity for physically new deliveries and define how additions to existing stock are represented. This is part of existing BUG01, now reproduced through Quarantine too.

### A05 — High: ordinary product editing can re-date expired stock

Edit Product accepts a changed expiry at unchanged quantity without the dedicated expiry-correction safeguards or an expiry-specific before/after movement audit. Reproduction changed 2000-01-01 to 2099-01-01, retained 10 units and produced no stock movement; only generic product activity was recorded. Those same units then satisfy the date-based sale check.

Source: `app/Controllers/Products.php:555`, `:593`, `app/Libraries/ExpiryStockResolution.php:10`. Evidence: `inventory/edit-expiry.json`. This confirms BUG02. Use an administrator correction with reason, stale revision and before/after audit; retain sale snapshots.

### A06 — High: legacy receiving doubles stock on replay

The still-reachable `stock_mode=receive` payload is outside the token/stale-count protections used by Add/Remove. Repeating the same 5-unit request with the original count of 10 changes stock 10 to 15 to 20 and creates two movements.

Source: `app/Controllers/Products.php:717`, `:743`, `:770`, `:780`. Evidence: `inventory/receive-replay.json`. This confirms BUG03. Remove the unused legacy mode or give it durable idempotency and locked revision validation.

### A07 — High priority maintenance: installed components match published security advisories

The bundled framework identifies as CodeIgniter 4.7.0 (`system/CodeIgniter.php:58`), and the lock contains Dompdf 3.1.5. Packagist advisory ranges were fetched with verified Windows TLS and matched with Composer's version-constraint implementation. The saved match lists contain five framework and six Dompdf advisories. Official framework advisories also list later fixes requiring 4.7.5.

Examples: [CodeIgniter forwarded HTTPS trust](https://github.com/codeigniter4/CodeIgniter4/security/advisories/GHSA-7wmf-pw8j-mc78), [framework upload validation](https://github.com/codeigniter4/CodeIgniter4/security/advisories/GHSA-4hwf-v4mp-hf6c) and [Dompdf chroot validation](https://github.com/dompdf/dompdf/security/advisories/GHSA-wvh6-f5jh-8gw4). The framework's `IncomingRequest::isSecure()` still trusts arbitrary forwarded HTTPS headers at `system/HTTP/IncomingRequest.php:267`. Application uses of the advisory-specific `deleteBatch`, Parser/renderString and public image-upload paths were not found in this review. A vulnerable version is not proof of a reachable SQL-injection or code-execution route.

Upgrade the bundled framework to a current tested patched release (at least 4.7.5 for those cited fixes) and Dompdf to a current tested patched release (at least 3.1.6 for those cited fixes), then rerun the workflows. The framework is the root project rather than a locked dependency, so a dependency-only Composer check can miss it. PHP CLI is 8.3.0; use a current supported patch release rather than assume the original 8.3 release has later security fixes. [PHP support policy](https://www.php.net/supported-versions.php).

Evidence: `dependency-matches.json`, `packagist-advisories.json`. Composer audit itself failed because its certificate store could not validate the issuer; TLS verification was not disabled. This is not recorded as a successful Composer audit.

### A08 — Medium: automatic promos can be disabled by checkout fields

The server auto-promo path depends on `discount_id=auto`; benefit/promo combination also depends on `include_product_promos=1`. The normal UI supplies these fields, but server pricing policy can be bypassed by omitting/changing them.

Reproductions: a PHP 10 item with an active 50% product promo completes at PHP 10 with a blank selection; an eligible PHP 112 PWD item with the same favorable promo completes at PHP 80 instead of PHP 56 when the hidden combination flag is disabled.

Source: `app/Controllers/Cashier/SalesController.php:777`, `:839`. Evidence: `operations/results.json`. Evaluate automatic product/category offers server-side for every applicable checkout, independently of the display selector and hidden flags.

### A09 — Medium: exchange replacements do not share automatic promo behavior

A PHP 30 replacement with an active 20% product offer quoted PHP 0 discount and PHP 30 payable, while the POS promotion engine produced PHP 6 discount. The exchange service only calculates an explicitly selected offer. This is a cross-workflow policy inconsistency; manual offer selection can still work.

Source: `app/Libraries/ProductExchange.php:167`. Evidence: `inventory/exchange-promo.json`. Reuse the automatic engine for replacement sales, or explicitly define and display an approved different exchange policy.

### A10 — Medium: noncash tender behaves like cash

Both GCash and Card checkout accepted PHP 100 tender for a PHP 10 sale, recorded PHP 90 change, and allowed a missing reference. The method label does not confirm an actual provider payment.

Source: `app/Controllers/Cashier/SalesController.php:801`, `:933`, `:937`. Evidence: `operations/results.json` and `inventory/digital-overpay.json`. This confirms BUG04. Validate exact noncash settlement/reference rules and keep provider confirmation distinct from manual recording.

### A11 — Medium: released quarantine stock does not recover report cost

Reports recover item cost only from the immutable original condition `resellable`; they ignore a later Quarantine outcome of Released. Releasing 2 units costing PHP 4 each restored inventory 10 to 12, but COGS/gross profit stayed PHP 20/PHP 10 rather than recovering PHP 8 to PHP 12/PHP 18. This affects the shared report calculation and its outputs.

Source: `app/Controllers/Admin/ReportsController.php:67`, `:1055`, `app/Libraries/Quarantine.php:113`. Evidence: `operations/results.json`. Include the effective released outcome without changing the original refund condition or double-counting restored cost.

The separate concern that fully refunded damaged invoices lose all cost was tested and **refuted**: actual report COGS was PHP 20 and gross profit negative PHP 20, with receipt/refund money movements PHP 50/PHP 50. Do not classify that case as a bug.

### A12 — Medium: restock suggestions treat expired units as usable

Reorder candidate selection and days-left calculations use physical stock without expiry. Ten expired units with forecast demand 5/day show 2 days left and suggested quantity 140, rather than zero usable stock and quantity 150 under the service's existing 30-day assumption. With 150 expired units and reorder level 100, the product is omitted entirely.

Source: `app/Libraries/ReorderForecastService.php:104`, `:119`, `:192`, `:213`. Evidence: `operations/results.json`. Separate physical and saleable stock for candidate selection, projected availability and reorder amounts.

### A13 — High: Sales CSV fails and leaves misleading export history

The CSV filename reads undefined `$data['pdfMode']`; the successful-looking history record is inserted first. The isolated route/controller reproduction raised the undefined-key exception and left one history row. This confirms BUG05; the earlier real HTTP audit returned 500.

Source: `app/Controllers/Admin/ReportsController.php:498`, `:500`. Evidence: `operations/results.json`. Use an explicit CSV name and record completion only after successful output generation, or record failed status distinctly.

### A14 — Medium: long accepted passwords have equivalent suffixes

User forms allow up to 255 characters but use PHP 8.3's bcrypt default, which considers only the first 72 bytes. A synthetic 73-byte password, a different final byte and the 72-byte prefix all verified against one hash. No real credential was involved. This does not demonstrate bypass of a short password.

Source: `app/Controllers/Admin/Users.php:111`, `:136`, `:306`, `:313`, `app/Controllers/Auth.php:70`. Evidence: `security/password-boundary.json`; [PHP bcrypt limit](https://www.php.net/manual/en/function.password-hash.php). Validate bytes to match the chosen algorithm or migrate deliberately to a suitable full-input scheme.

### A15 — Low: Summary PDF uses the Full filename prefix

The summary content is selected correctly but its filename is still `sales_report_`. Source: `app/Controllers/Admin/ReportsController.php:689`, `:704`. This confirms BUG07. Use a distinct summary prefix to avoid confusing saved downloads.

## What was checked and works within the tested scope

| Area | Verified protection or behavior | Important boundary |
| --- | --- | --- |
| Checkout | Idempotent saved-sale lookup, recovery state, cart ownership, locked quantity/expiry/Trash checks and captured sale snapshots | No current MySQL scheduler/load proof; noncash and automatic-pricing findings remain |
| Discounts/tax | Cent allocation, favorable benefit comparison when invoked, product eligibility and beneficiary validation | This is a code audit, not product-by-product tax classification or regulatory certification |
| Returns | Distinct canonical lines recalculate remaining units/net cents; linked exchange quote/idempotency | Aliased refund IDs and original-delivery safeguards fail as above |
| Inventory | Branch-specific catalog assignment, audit movements, today-expired sale blocking, retained historical records | Multiple lots/FEFO absent; receiving identity and expiry editing fail |
| Quarantine/damaged | Administrator inspection, cashier own-branch reads, one final outcome, no second deduction for Damaged/Expired | Upstream delivery identity can fool source matching; report cost recovery is incomplete |
| Cashier operations | Shift ownership, opening/closing checks, closed snapshots, movement signs and insufficient-cash guards | No actual cash count, printer hardware or hosted concurrency verification |
| Reports | Sale/refund-date distinction, fully refunded damaged cost, branch-specific demand and recorded forecast basis | Expired-stock reorder, quarantine cost, CSV and filename defects remain |
| Access/security | Disabled auto-routing; explicit role groups; account-change revocation; CSRF on stateful actions; cashier lease; scoped presence/login history/return records | Local static files bypass the application entirely; host settings unknown |
| Files/backups | CSV formula neutralization; remote PDF assets disabled; data-only restore has schema/FK/password checks in source | No real restore/import or new large-export/print exercise performed in this audit |
| Management forms | Required SKU, field-specific character rules, numeric limits and submit reviews covered by regression tests | Bcrypt length mismatch remains |

No direct application SQL-injection or stored-XSS exploit was confirmed. That is a bounded review result, not a guarantee of absence. Local development/HTTP/non-Secure-cookie/CSP settings must be checked against actual production configuration.

## Evidence and limitations

- Existing full suite: **331 tests / 24,628 assertions; 10 failures and 2 errors**. The 11 previously recorded unsuccessful business cases remain. The additional clock test fails only in the combined run, receiving stale shared HTTP response status 204; its two cases pass alone with 19 assertions. Track it as test isolation, not a demonstrated clock endpoint failure.
- Syntax checks: all **258 application PHP files and 29 browser JavaScript files passed**. Syntax checks do not prove runtime correctness.
- Security subset: **47 cases / 398 assertions passed**. These overlap the full suite and must not be added to its count.
- Inventory bug characterizations: **7 cases / 22 assertions reproduce the stated defects**. They pass because they assert the observed bad behavior, not because the system is repaired.
- Operations: **10 isolated observations**, including exact result JSON, CSV failure and one refuted suspected issue.
- Public static exposure used unauthenticated HEAD requests only. No secret response bodies, copied active sessions or hosted penetration requests were used.
- Current vendor advisory ranges were checked; no dependencies were installed or updated.
- Local database health: 27 tables inspected within START TRANSACTION READ ONLY, with a SELECT/SHOW-only query wrapper and rollback. All six monitored write-command counters remained zero; no service was started.

### Current local database health

The local application database was already running. This check read counts and hashed record references only; it did not write application data or copy personal records.

| Check | Result |
| --- | --- |
| Scope | 27 tables, 67 products, 117 branch inventory records, 479 sales, 977 sale items, 24 refund lines |
| Negative balances/prices | None found |
| Master stock versus sum of branch balances | No mismatches |
| Duplicate nonempty SKUs/invoices or product-branch pairs | None found |
| Over-refunded quantities/item amounts/sale amounts | None found |
| Sale-line total reconciliation | No mismatches |
| Foreign keys | 50 constraints checked, no orphans |
| Missing original sale delivery snapshots | 976 of 977 lines |
| Missing original sale expiry snapshots | 976 of 977 lines; some products may legitimately have no expiry, but unknown history must not be assumed resellable |
| Missing current branch delivery identity | 104 of 117 inventory records |
| Positive stock dated on/before 27 October | 41 inventory records, including inactive/Trash records; an operational-review count, not proof they are sellable |

These clean balance checks do not negate the reproduced request bugs. Historical delivery/expiry gaps cannot safely be repaired by inventing snapshots from today's catalog. A safe fallback is to retain unknown returned units outside available stock until a supported inspection outcome is recorded.

Evidence: `writable/system-audit-20261027/inventory/db-health.json` and its guarded script. It issued 98 read queries; the transaction was rolled back and application write counters stayed zero. Hosted data was not read.

Evidence directory: `writable/system-audit-20261027/`. Full XML/output, reproduction scripts, exact synthetic amounts, redacted HTTP status metadata and vendor advisory responses are retained there. The Windows sandbox reported the existing PHP bootstrap unreadable; bounded approved PHP test invocations resolved that tool limitation. No protective setting or TLS verification was disabled.

Functional limits remain: multiple manufacturer batches/FEFO, full purchase orders and supplier credits, documented interbranch transfer workflow, unit conversions, pharmacist/prescription workflow, provider reconciliation, complete accounting and disposal certificates. These are capability limits, separate from the reproduced bugs. Forecasts use a fixed 30-day coverage assumption and do not prove future accuracy without an independent evaluation.

Repair order: close private-file exposure and update vulnerable components; fix refund duplication, delivery identity/provenance, expiry corrections and receiving replay; enforce automatic/noncash pricing rules; repair report/reorder calculations and exports; resolve password length and filename/test-isolation issues. Recheck the actual host after controlled deployment.

Related: [Known issues](../project/Known-Issues.md), [current state](../project/Current-State.md), [testing evidence](../development/Testing-and-Evidence.md).
