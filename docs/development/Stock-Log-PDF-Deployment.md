---
title: Stock log PDF deployment
type: deployment-guide
status: prepared
updated: 2026-10-23
tags: [deployment, stock-logs, pdf]
---
# Stock log PDF deployment

The local Stock Logs PDF export has been updated. Return to Stock Logs and choose PDF again, or reload the previous export URL.

For hosting, upload the app folder from `build/hostinger-stock-log-pdf-fix-2026-10-23.zip` over the current application, preserving its folder mapping. Include the new `app/Libraries/StockLogPdf.php`. The archive also contains the current controller and shared PDF dependencies, plus a SHA-256 manifest.

**No SQL import, migration, dependency installation or PHP memory-limit increase is required.** This package changes the Stock Logs PDF export. Existing data and access permissions are preserved. Hosting has not been changed automatically.

## Check after upload

1. Open Stock Logs and export PDF with all filters empty. It should download a complete PDF rather than display a memory error.
2. Check Total records, first/last log IDs, page numbers and complete movement notes against the selected history.
3. Try a branch/date/search/type filter and an empty selection. Cashiers must continue to receive only their own branch's records.

The export remains one A4 landscape PDF with all matching records, in descending log-ID order. It uses direct canvas pagination rather than one large HTML table. Long notes continue in a complete-notes section, and columns/page identity repeat on continuation pages.

Local verification: six actual route downloads and anonymous denial at a 128 MB limit; 1,087 records use a 28 MiB PHP peak. A separate 10,000-record synthetic test uses 44 MiB, preserving every ordered ID and all 72 long notes. Twenty-nine PHP cases / 283 assertions pass. [Review](../reviews/2026-10-23-stock-log-pdf-memory-fix.md).
