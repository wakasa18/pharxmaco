---
title: "Route inventory"
type: reference
status: generated
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
tags: [documentation, index]
---
# Route inventory

Generated from `app/Config/Routes.php`. Filters and controller/service checks together define effective access. Public login POST also uses login throttling. This is a static inventory, not a fresh access-control test.

| Method | Route | Controller | Route group |
| --- | --- | --- | --- |
| GET | `/` | `Auth::login` | Public |
| GET | `/login` | `Auth::login` | Public |
| POST | `/login` | `Auth::attemptLogin` | Public |
| POST | `/logout` | `Auth::logout` | Public |
| GET | `session/activity` | `Auth::activity` | Public |
| GET | `server-time` | `ServerTime::index` | Public |
| POST | `presence/heartbeat` | `Presence::heartbeat` | Public |
| GET | `presence/users` | `Presence::users` | Public |
| GET | `admin/damaged-items` | `Admin\DamagedItemsController::index` | cashier |
| GET | `admin/damaged-items/(:num)` | `Admin\DamagedItemsController::show/$1` | cashier |
| GET | `admin/quarantine` | `Admin\QuarantineController::index` | cashier |
| GET | `admin/quarantine/(:num)` | `Admin\QuarantineController::show/$1` | cashier |
| GET | `cashier/login-history` | `Cashier\LoginHistoryController::index` | cashier |
| GET | `cashier/start-shift` | `Cashier\WorkspaceController::start` | cashier |
| GET | `cashier/my-day` | `Cashier\WorkspaceController::index` | cashier |
| GET | `cashier/my-day/summary` | `Cashier\WorkspaceController::summary` | cashier |
| POST | `cashier/shifts/open` | `Cashier\WorkspaceController::open` | cashier |
| POST | `cashier/shifts/movement` | `Cashier\WorkspaceController::movement` | cashier |
| GET | `cashier/shifts/(:num)` | `Cashier\WorkspaceController::shift/$1` | cashier |
| GET | `cashier/shifts/(:num)/print` | `Cashier\WorkspaceController::printShift/$1` | cashier |
| POST | `cashier/shifts/(:num)/close` | `Cashier\WorkspaceController::close/$1` | cashier |
| GET | `cashier/sales` | `Cashier\SalesController::index` | cashier |
| POST | `cashier/sales/add-to-cart` | `Cashier\SalesController::addToCart` | cashier |
| POST | `cashier/sales/update-cart` | `Cashier\SalesController::updateCart` | cashier |
| POST | `cashier/sales/remove-cart-item` | `Cashier\SalesController::removeCartItem` | cashier |
| POST | `cashier/sales/clear-cart` | `Cashier\SalesController::clearCart` | cashier |
| POST | `cashier/sales/checkout` | `Cashier\SalesController::checkout` | cashier |
| GET | `cashier/sales/checkout-status` | `Cashier\SalesController::checkoutStatus` | cashier |
| POST | `cashier/sales/sync-cart` | `Cashier\SalesController::syncCart` | cashier |
| POST | `cashier/sales/hold-sale` | `Cashier\SalesController::holdSale` | cashier |
| POST | `cashier/sales/resume-held-sale/(:num)` | `Cashier\SalesController::resumeHeldSale/$1` | cashier |
| POST | `cashier/sales/discard-held-sale/(:num)` | `Cashier\SalesController::discardHeldSale/$1` | cashier |
| GET | `cashier/sales/completed/(:num)` | `Cashier\SalesController::completed/$1` | cashier |
| GET | `cashier/sales/view/(:num)` | `Cashier\SalesController::details/$1` | cashier |
| GET | `cashier/sales/receipt/(:num)` | `Cashier\SalesController::receipt/$1` | cashier |
| GET | `cashier/sales/search-products` | `Cashier\SalesController::searchProducts` | cashier |
| GET | `cashier/sales/branch-availability` | `Cashier\SalesController::branchAvailability` | cashier |
| GET | `cashier/sales/history` | `Cashier\SalesController::history` | cashier |
| GET | `cashier/sales/exchange/(:num)` | `Cashier\ExchangeController::form/$1` | cashier |
| POST | `cashier/sales/exchange/(:num)/review` | `Cashier\ExchangeController::review/$1` | cashier |
| POST | `cashier/sales/exchange/(:num)/complete` | `Cashier\ExchangeController::complete/$1` | cashier |
| GET | `cashier/sales/reprint/(:num)` | `Cashier\SalesController::reprint/$1` | cashier |
| POST | `cashier/sales/refund/(:num)` | `Cashier\SalesController::refund/$1` | cashier |
| GET | `cashier/sales/refund-form/(:num)` | `Cashier\SalesController::refundForm/$1` | cashier |
| POST | `cashier/sales/refund-partial/(:num)` | `Cashier\SalesController::refundPartial/$1` | cashier |
| GET | `cashier/sales/refund-slip/(:num)` | `Cashier\SalesController::refundSlip/$1` | cashier |
| GET | `/products` | `Products::index` | cashier |
| GET | `/products/create` | `Products::create` | cashier |
| GET | `/products/catalog` | `Products::catalog` | cashier |
| POST | `/products/add-existing` | `Products::addExisting` | cashier |
| POST | `/products/store` | `Products::store` | cashier |
| POST | `/products/update-stock` | `Products::updateStock` | cashier |
| GET | `stock-logs` | `StockLogsController::index` | cashier |
| GET | `stock-logs/export-excel` | `StockLogsController::exportExcel` | cashier |
| GET | `stock-logs/export-pdf` | `StockLogsController::exportPdf` | cashier |
| GET | `settings` | `Admin\Settings::index` | cashier |
| GET | `admin/expiry-report` | `Admin\ReportsController::expiryReport` | cashier |
| GET | `admin/expiry-report/item/(:num)` | `ExpiryStockController::show/$1` | cashier |
| POST | `admin/expiry-report/item/(:num)` | `ExpiryStockController::save/$1` | cashier |
| GET | `admin/expiry-report/export-excel` | `Admin\ReportsController::exportExpiryExcel` | cashier |
| GET | `admin/expiry-report/export-pdf` | `Admin\ReportsController::exportExpiryPdf` | cashier |
| POST | `admin/quarantine/(:num)` | `Admin\QuarantineController::save/$1` | admin |
| GET | `admin/cashier-shifts` | `Cashier\WorkspaceController::manager` | admin |
| POST | `admin/cashier-shifts/drawer` | `Cashier\WorkspaceController::configureDrawer` | admin |
| POST | `admin/cashier-shifts/review/(shift\|event)/(:num)` | `Cashier\WorkspaceController::review/$1/$2` | admin |
| POST | `admin/cashier-shifts/(:num)/release-handover` | `Cashier\WorkspaceController::release/$1` | admin |
| GET | `/dashboard` | `Dashboard::index` | admin |
| GET | `/products/edit/(:num)` | `Products::edit/$1` | admin |
| POST | `/products/update/(:num)` | `Products::update/$1` | admin |
| POST | `/products/delete/(:num)` | `Products::delete/$1` | admin |
| GET | `/categories` | `Categories::index` | admin |
| GET | `/categories/create` | `Categories::create` | admin |
| POST | `/categories/store` | `Categories::store` | admin |
| GET | `/categories/edit/(:num)` | `Categories::edit/$1` | admin |
| POST | `/categories/update/(:num)` | `Categories::update/$1` | admin |
| POST | `/categories/delete/(:num)` | `Categories::delete/$1` | admin |
| GET | `admin/reports` | `Admin\ReportsController::index` | admin |
| POST | `admin/reports/update-forecast` | `Admin\ReportsController::updateForecast` | admin |
| GET | `admin/reports/export-excel` | `Admin\ReportsController::exportExcel` | admin |
| GET | `admin/reports/pdf` | `Admin\ReportsController::exportPdf` | admin |
| GET | `admin/users` | `Admin\Users::index` | admin |
| GET | `admin/users/create` | `Admin\Users::create` | admin |
| POST | `admin/users/store` | `Admin\Users::store` | admin |
| GET | `admin/users/edit/(:num)` | `Admin\Users::edit/$1` | admin |
| POST | `admin/users/update/(:num)` | `Admin\Users::update/$1` | admin |
| POST | `admin/users/deactivate/(:num)` | `Admin\Users::deactivate/$1` | admin |
| POST | `admin/users/activate/(:num)` | `Admin\Users::activate/$1` | admin |
| GET | `admin/users/password/(:num)` | `Admin\Users::password/$1` | admin |
| POST | `admin/users/password-update/(:num)` | `Admin\Users::passwordUpdate/$1` | admin |
| POST | `admin/users/end-login/(:num)` | `Admin\Users::endLogin/$1` | admin |
| GET | `admin/activity-logs` | `Admin\Users::activityLogs` | admin |
| GET | `admin/settings` | `Admin\Settings::index` | admin |
| GET | `admin/backup-restore` | `Admin\BackupRestore::index` | admin |
| POST | `admin/backup-restore/create` | `Admin\BackupRestore::create` | admin |
| GET | `admin/backup-restore/download/(:segment)` | `Admin\BackupRestore::download/$1` | admin |
| POST | `admin/backup-restore/delete/(:segment)` | `Admin\BackupRestore::delete/$1` | admin |
| POST | `admin/backup-restore/restore` | `Admin\BackupRestore::restore` | admin |
| GET | `admin/branches` | `Admin\Branches::index` | admin |
| GET | `admin/branches/create` | `Admin\Branches::create` | admin |
| POST | `admin/branches/store` | `Admin\Branches::store` | admin |
| GET | `admin/branches/edit/(:num)` | `Admin\Branches::edit/$1` | admin |
| POST | `admin/branches/update/(:num)` | `Admin\Branches::update/$1` | admin |
| GET | `admin/sale-correction/(:num)` | `Admin\SaleCorrection::edit/$1` | admin |
| POST | `admin/sale-correction/update/(:num)` | `Admin\SaleCorrection::update/$1` | admin |
| GET | `admin/discounts` | `Admin\\Discounts::index` | admin |
| GET | `admin/discounts/create` | `Admin\\Discounts::create` | admin |
| POST | `admin/discounts/store` | `Admin\\Discounts::store` | admin |
| GET | `admin/discounts/edit/(:num)` | `Admin\\Discounts::edit/$1` | admin |
| POST | `admin/discounts/update/(:num)` | `Admin\\Discounts::update/$1` | admin |
| POST | `admin/discounts/toggle/(:num)` | `Admin\\Discounts::toggleStatus/$1` | admin |
| POST | `admin/discounts/delete/(:num)` | `Admin\\Discounts::delete/$1` | admin |
| GET | `admin/suppliers` | `Admin\Suppliers::index` | admin |
| GET | `admin/suppliers/create` | `Admin\Suppliers::create` | admin |
| POST | `admin/suppliers/store` | `Admin\Suppliers::store` | admin |
| GET | `admin/suppliers/edit/(:num)` | `Admin\Suppliers::edit/$1` | admin |
| POST | `admin/suppliers/update/(:num)` | `Admin\Suppliers::update/$1` | admin |
| POST | `admin/suppliers/delete/(:num)` | `Admin\Suppliers::delete/$1` | admin |
| GET | `admin/suppliers/trash` | `Admin\Suppliers::trash` | admin |
| POST | `admin/suppliers/restore/(:num)` | `Admin\Suppliers::restore/$1` | admin |
| POST | `admin/suppliers/force-delete/(:num)` | `Admin\Suppliers::forceDelete/$1` | admin |
| GET | `/products/trash` | `Products::trash` | admin |
| POST | `/products/restore/(:num)` | `Products::restore/$1` | admin |
| POST | `/products/force-delete/(:num)` | `Products::forceDelete/$1` | admin |
| GET | `/categories/trash` | `Categories::trash` | admin |
| POST | `/categories/restore/(:num)` | `Categories::restore/$1` | admin |
| POST | `/categories/force-delete/(:num)` | `Categories::forceDelete/$1` | admin |
| GET | `admin/discounts/trash` | `Admin\\Discounts::trash` | admin |
| POST | `admin/discounts/restore/(:num)` | `Admin\\Discounts::restore/$1` | admin |
| POST | `admin/discounts/force-delete/(:num)` | `Admin\\Discounts::forceDelete/$1` | admin |

[Architecture](../system/Architecture.md) · [Roles and access](../system/Roles-and-Access.md) · [Home](../Home.md)
