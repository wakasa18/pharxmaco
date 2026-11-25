# Shift report printing update - 16 October 2026

The shift report now opens with a compact A4 closing summary. Its cash reconciliation, opening handover difference, review status and signatures stay together. A second print action adds complete product and activity appendices.

## Using the report

Open a shift from My Day or Cashier Shifts, then select **Print shift report**.

- **Print summary** prints the closing summary. Ctrl+P also defaults to this mode. The tested open/closed summaries, including a 120-sale shift, fit one A4 page.
- **Print with details** adds the product movements and all original shift movements in chronological order. The old 100-row limit remains on the live My Day screen, but does not limit the print appendix.
- Long or multiline notes have a short summary and an automatic notes continuation. The full text prints in both modes.

The report shows the store/branch, shift reference, generated timestamp in Philippine time, actual closing staff name when recorded, interim/final status, opening expected/count/difference, closing expected/count/difference, payment totals, cash retained/removed, handover and manager review. Signatures appear on the summary page. Missing historical values are labelled without inventing an opening amount, closing actor or review.

Running page identity and Page X of Y use CSS page-margin boxes. Use current Chrome or Edge, A4 portrait, 100% scale and disable browser-provided headers/footers to avoid duplicate headers. Other browsers or printer drivers may paginate differently. This update targets A4 office printing.

## Installation

Extract `build/hostinger-shift-print-2026-10-16.zip` into the existing project root and replace its six application files. Keep the `app/` and `public/` paths. Refresh the report tab afterwards. There is **no SQL import or new migration** for this update.

The small archive contains:

- `app/Controllers/Cashier/WorkspaceController.php`
- `app/Libraries/CashierDayReport.php`
- `app/Libraries/CashierShift.php`
- `app/Views/cashier/workspace/print.php`
- `public/assets/css/shift-print.css`
- `public/assets/js/shift-print.js`

It requires the previously installed cashier workspace. The full cashier workspace archive and the combined checkout-fixes archive have also been refreshed. Seller configuration, `.env`, uploads and database records are excluded from this application-only print update.

## Financial behavior and validation

Closed totals still come from the saved closing snapshot. The complete appendix reads the immutable original shift event ledger; no historical closing totals or event records are rewritten. An open report calculates its totals and appendix from the same fetched events. Existing cashier/branch access checks run before loading the full print ledger.

- Seven targeted print/shift tests passed before final validation, covering complete detail, chronological ordering, saved closing totals, closing actor, handover differences, note escaping, foreign-branch access and existing shift operations.
- The final full suite ran 224 tests / 23,247 assertions: 212 passed, with the same 9 failures and 3 errors in the previously documented inventory/expiry safeguards. There were no new failures from this update.
- Five browser/PDF samples were checked: open summary, closed summary, 120-sale full report, the same shift's summary, and long notes. Print controls, both button actions, default mode after printing, and previews from 320 to 1440 pixels were checked. All 120 movements and 24 products appear in the busy report.
- The small and busy summaries print on one page. The busy full report uses five A4 pages, including all 120 movements; the earlier format used seven pages while listing only 100. The long-notes case uses a separate continuation page.
- Page images were reviewed for readable totals, signatures with reconciliation, currency symbols, table headings, row continuity and page numbers. QA samples are in `tmp/pdfs/shift-print-review/`; the source fixtures use isolated SQLite and do not create live shifts or sales.

Physical printers, hosted browsers, non-Chromium margin-box support and thermal roll layouts were not tested. Very large names or unusual driver settings may require more space; full note content is retained rather than clipped to force a page count.
