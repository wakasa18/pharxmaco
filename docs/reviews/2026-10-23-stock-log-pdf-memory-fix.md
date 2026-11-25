---
title: Stock log PDF memory fix
type: review
status: implemented-locally
updated: 2026-10-23
tags: [review, stock-logs, exports, pdf, performance]
---
# Stock log PDF memory fix

Fixed BUG06 from the [export audit](2026-10-23-export-functionality-audit.md). The exact previously failing URL, `stock-logs/export-pdf?search=&branch_id=&action_type=&date_from=&date_to=`, now returns a complete PDF under the existing 128 MB PHP memory limit.

## Change

The former export built a complete PHP result array and a large HTML/CSS table. Dompdf exhausted 128 MB and 256 MB while constructing/layouting that frame tree.

The controller now consumes its selected database result one row at a time. `StockLogPdf` draws a table directly onto the existing Dompdf CPDF canvas, paginates/wraps each row, repeats column headers and uses the shared report identity/page-number stamping. Long notes are spooled to an automatically removed temporary file, then drawn in full in the notes section. Oversized notes/legacy fields can continue across pages. A count mismatch raises an error instead of returning an incomplete document.

This is one PDF containing every matched record, with no new row cap. Search, branch, action type, inclusive dates, descending log-ID order and cashier branch restrictions remain in the existing query path. Product IDs/SKUs, units, signed after-minus-before movements, recorded references, Unicode and complete notes are retained. The response adds no-store and nosniff headers. No financial/inventory mutation, schema change, dependency or memory-limit increase is involved.

Files changed: `app/Controllers/StockLogsController.php` and new `app/Libraries/StockLogPdf.php`. The previous HTML template remains available to historical view fixtures; the live stock PDF route now uses the canvas renderer.

## Verification

| Check | Result |
| --- | --- |
| Actual HTTP downloads, normal authentication/CSRF routing, 128 MB PHP limit | 25/25 checks pass: complete history, branch/date/type selection, search, empty selection, each cashier's own branch despite forged filters, and anonymous denial |
| Previously failing full selection | All 1,087 IDs in order, once each; 113 pages, 287,846 bytes; PHP peak 29,360,128 bytes (28 MiB) |
| 10,000-record synthetic stress | All 10,000 ordered IDs and 72 complete multi-line notes; 1029 pages, 1,942,930 bytes; PHP peak 46,137,344 bytes (44 MiB), limit 128 MB; about 5.153 seconds locally |
| Focused PHP regression | 29 cases / 283 assertions pass with coverage disabled; includes PDF fonts, iterator completeness, empty selections, long unbroken fields/notes and existing invoice/refund/shift/export checks |
| Export-link JavaScript | Existing checks pass |
| PDF inspection | Seven PDFs: all ID sequences, page counts, repeated column/report headings and footers; Unicode names/peso and literal markup; representative first/continuation/middle/final pages rendered and inspected |

Content checks use a scoped CPDF stream inspector plus PDF structure/selected-page character bounds and Poppler rendering. They are specific to the bundled Unicode CPDF output, rather than a general PDF parser. Visual inspection confirms readable columns/metadata/notes without page-edge clipping. Tests do not claim unlimited document size: the PDF itself and database-driver result still require memory. Filtering remains useful for practical print length.

Evidence: `writable/stock-pdf-memory-fix/http-results.json`, `memory.jsonl`, `expected-ids.json`, `pdf-inspection.json`, `unit.xml` and `preservation.json`; PDF samples and stress metrics are under `tmp/pdfs/stock-pdf-memory-fix/`. The audit fixture scripts now accept an isolated review-directory argument via `PHX_EXPORT_REVIEW_DIR` so prior results are retained. Synthetic renderer/content-inspection scripts are under `tests/integration/stock-log-pdf*`.

The application database is used only as a read source for a disposable local test copy. Test logins/stock fixtures remain in that copy, which was removed after verification. 26 of 27 source-table row hashes match the starting snapshot; concurrent source changes were observed in `user_presence_sessions` and retained. Tests made no application-data writes. No hosted deployment or physical-printer test was performed. Sales CSV failure (BUG05) and Summary filename ambiguity (BUG07) remain separate open findings.

## Delivery

Updated locally; hosting package: `build/hostinger-stock-log-pdf-fix-2026-10-23.zip`. Four runtime files plus README and manifest. [Upload instructions](../development/Stock-Log-PDF-Deployment.md). No SQL required.

Related: [Inventory](../system/Inventory-and-Expiry.md) ? [Reports](../system/Reports-and-Forecasts.md) ? [Known issues](../project/Known-Issues.md) ? [Testing](../development/Testing-and-Evidence.md).
