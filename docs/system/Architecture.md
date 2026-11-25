---
title: Architecture and code map
type: system-reference
status: current
updated: 2026-10-21
tags: [architecture, code-map]
---
# Architecture and code map

Pharxmaco is a server-rendered PHP application built on the bundled CodeIgniter 4 framework. MySQL holds application data; browser JavaScript enhances the POS, live cashier workspace and DataTables. PHP/Dompdf generates report PDFs; browser printing handles receipts and shift reports.

| Layer | Repository path | Responsibility |
| --- | --- | --- |
| Web entry | `public/index.php`, `public/.htaccess` | Request entry and routing |
| Routes/configuration | `app/Config` | Endpoints, filters, database and invoice settings |
| Controllers | `app/Controllers` | Role/branch checks, validation and HTTP responses |
| Business services | `app/Libraries` | Pricing, shifts, stock actions, forecasts, exports and recovery |
| Persistence | `app/Models`, `app/Database/Migrations` | Field access and additive schema changes |
| Views | `app/Views` | Staff layout, POS, inventory, reports and printable output |
| Browser assets | `public/assets/js`, `public/assets/css` | UI behavior, table enhancements and pricing previews |
| Verification | `tests`, `writable/*-review`, `writable/*-audit` | Automated cases, fixtures and recorded results |
| Operations | `database/upgrades`, `build`, `writable/backups` | Import patches, deployment archives and backups |

## Main code entry points

| Feature | Controllers and libraries |
| --- | --- |
| Authentication/access | `Auth.php`, `AccountSession.php`, `AuthenticationAudit.php`, role filters |
| Cashier referential integrity | `CashierForeignKeys.php`, migration 000017; shared definitions generate phpMyAdmin SQL |
| Cashier login history | `Cashier/LoginHistoryController.php`, existing authentication audit records; validated session account only |
| POS and saved sales | `Cashier/SalesController.php`, `ReceiptInvoice.php`, `SaleRevision.php` |
| Discounts | `ProductPromotion.php`, `BenefitProductPromotion.php`, `StatutoryDiscount.php`, `DiscountPolicy.php`, `DiscountAllocator.php` |
| Exchanges/corrections | `Cashier/ExchangeController.php`, `ProductExchange.php`, `Admin/SaleCorrection.php`, `SaleCorrectionPricing.php` |
| Cash drawers | `Cashier/WorkspaceController.php`, `CashierShift.php`, `CashierDayReport.php` |
| Inventory/expiry | `Products.php`, `ExpiryStockController.php`, `ExpiryStockResolution.php`, `StockExpiryProvenance.php` |
| Retained deletion | `BranchProductTrash.php`, `RetainedRecordDeletion.php` |
| Reports | `Admin/ReportsController.php`, `StockLogsController.php`, `ReportPdf.php`, `CashMovementReport.php` |
| Sales forecasts | `Admin/ReportsController.php` Holt revenue calculations and `SalesForecastSnapshot.php`; old reorder command is retired |
| Backup/restore | `Admin/BackupRestore.php`, `DatabaseBackupService.php` |

Controller paths are relative to `app/Controllers`; service paths to `app/Libraries`, except where explicitly qualified. Browser quote calculations live in `public/assets/js/discount-math.js`; server checkout remains authoritative.

## Data boundaries

The topbar clock uses a PHP-rendered server anchor and stateless public `GET server-time` samples, with monotonic browser elapsed time between syncs. It displays Asia/Manila time independently of device clock settings. See the [implementation and verification](../reviews/2026-10-21-server-clock.md).

Clock and scoped presence routes avoid session-backed CSRF initialization and omit login cookies; ordinary forms retain CSRF protection. Presence uses hashed, per-login capabilities bound to current account credentials and cashier lease ownership. Only valid administrator capabilities read online status; heartbeat and status polls cannot renew authentication. `user_presence_sessions` retains recent/historical check-in timestamps for the [live Users view](../reviews/2026-10-21-user-presence.md).

`products` is the shared catalog and `branch_products` holds branch inventory. `sales` and `sale_items` preserve completed-sale values and descriptive snapshots. `refund_items` records returns. Cashier shift/event tables provide drawer and personal-day reporting. The presence of stock provenance fields does not mean all workflows populate them; see [Known issues](../project/Known-Issues.md).

The source tree contains some older views/controllers that are not active routes. Use the [route inventory](../reference/Routes.md) when deciding whether a feature is currently available.

Related: [Schema and SQL](../reference/Schema-and-SQL.md) · [Current state](../project/Current-State.md).
