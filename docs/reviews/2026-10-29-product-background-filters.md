---
title: Product table background filtering
type: implementation-review
status: implemented-locally
updated: 2026-10-29
tags: [products, datatables, filters, testing]
---
# Product table background filtering

Manufacturer, Stock and Medicine type now refresh the DataTables rows through a same-origin GET request. Apply filters and Clear filters use the same path. The document, topbar and table controls stay mounted; search, sorting, rows-per-page and existing browser column filters survive row replacement. Clear filters explicitly resets browser filters and restores the selected/assigned branch catalog. The URL reflects successfully applied server filters without navigation.

The Products controller returns a no-store JSON response for AJAX requests, using the existing branch-scoped queries before the 3,000-row cap. Regular requests still render the complete page. Both paths share the extracted row template. Manufacturer options remain independent of the selected filters, so short and empty results can be cleared. The shared DataTables initializer now supports an opt-in refreshable table, rebuilding row search/date/sort metadata and column choices after replacement. Other table modes retain their behavior.

Requests are abortable and numbered; an older response cannot replace newer results. Failures, expired sessions and unexpected HTML retain the previous rows, show a message and offer Retry. A 20-second timeout prevents indefinite busy state. No automatic document reload occurs. Manufacturer/Stock mobile copies remain synchronized without duplicate submitted fields.

Stock-entry quantities, reasons and actions are retained in page memory even when a product is temporarily filtered out. If a returned balance differs from the draft's original balance, submission stays blocked until the quantity is reviewed and re-entered. Read-only filtering reuses an unspent one-use stock token; successful stock submission still consumes it through the existing server logic. This avoids overlapping GETs invalidating an unfinished stock action. Existing server balance, permission and audit checks remain.

## Verification

- **10 PHP cases / 68 assertions pass** with isolated SQLite, covering the original filter safeguards plus AJAX payloads, branch isolation, empty-result recovery, escaping, no-store responses and unspent/consumed stock tokens. Inventory and stock-log records remain unchanged by filter reads.
- **270 browser checks pass** at 1440, 768 and 390 pixels for both roles. Actual controller-rendered fixtures cover desktop/mobile controls, no document navigation during filtering, unchanged workspace identity, combination/reset, existing column predicates and search across replacement, row labels, empty recovery, punctuation, HTTP 503/retry, HTTP 401 and unexpected HTML, delayed-response races and stock drafts including a changed balance. The run made 93 synthetic background GETs and no POSTs; no JavaScript exceptions occurred. Reduced motion enabled for stable screenshots; desktop/mobile layouts visually inspected.
- Full suite: **435 tests / 25,493 assertions; 432 pass, two failures and one error**. The three unsuccessful cases are the same documented A05 expiry-correction workflow issues. No new suite failures.
- Runtime package files and the modified test trait pass syntax checks. Evidence: `writable/product-async-filters-20261029/`.

No SQL, schema, application data or hosting changes. Runtime changes: Products controller/view/row partial, page-specific table script, shared DataTables initializer and staff asset version. [Upload guide](../development/Product-Filters-Deployment.md) · [Previous column placement](2026-10-29-product-datatable-column-filters.md).

Upload: `build/hostinger-product-async-filters-2026-10-29.zip`, 207,773 bytes, 50 runtime files plus guide and SHA-256 manifest. All 52 ZIP entries verified. SHA-256: `2eb1fc1ca212c9842dceafef542c48c6200ff85114e2e95dbcdce027403a6d8d`.
