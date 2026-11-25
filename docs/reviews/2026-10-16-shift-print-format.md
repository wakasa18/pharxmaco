# Printed shift report review - 16 October 2026

**Implemented:** the findings below have been addressed by the [shift print update](2026-10-16-shift-print-update.md). The tested summaries now fit one A4 page; the full 120-sale report includes every movement. The remaining text preserves the original review.

The current report is a reasonable A4 cash-reconciliation report. It contains the core operational figures, but its layout and completeness need improvement before using it as the main signed closing document. This is a review; production templates, financial records and the local application database were not changed.

## What works

- Shift ID, cashier, branch, drawer, status and opening/closing times are identified. Philippine time is labelled.
- An open shift is labelled as interim; a closed shift is labelled as final.
- Sales count, units, refunds, discounts and net collections are separated. The sales label explains that amounts are after discounts and VAT relief.
- Cash, GCash and card receipts/payouts are separated from the physical cash drawer.
- The ledger shows opening cash, cash payments, payouts, additions/removals, expected cash, physical count, over/short, cash retained and cash removed at closing.
- Expected cash uses opening cash + net cash receipts + cash added - cash removed. Noncash takings do not increase physical drawer cash.
- Closed shifts use their saved closing summary, protecting the closing figures from later activity. Review details and signature lines are supported.
- A4 margins, hidden print controls, readable currency symbols, wrapping product names and repeated table headers worked in the samples. No clipped or overlapping rows were seen across the eleven reviewed pages.

The comparison is consistent with the cash-session information described by [Shopify's cash register management guide](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/cash-register-management) and the identity, timing, cash movements and expected/actual amounts in [Square's CashDrawerShift documentation](https://developer.squareup.com/reference/square/objects/cashdrawershift). These are operational reference examples, not a legal certification or a universal mandatory format.

## Findings and recommendations

| Priority | Finding | Recommended behavior |
| --- | --- | --- |
| Medium | The final print includes only the latest 100 activity movements. A synthetic shift with 120 completed sales showed totals for 120 and detail for 100. The caption does disclose this limit. | Use a compact summary plus an optional complete transaction appendix/export. Identify any limited list clearly and provide a way to reconcile the complete detail against the totals. |
| Medium | The shared-drawer opening expected amount and opening difference are absent from the printed report, although they are visible on the shift screen. A closing count can balance while an earlier opening handover discrepancy remains relevant. | Show previous cash left, opening physical count, opening over/short and its explanation together. Retain the separate closing over/short. |
| Medium | The signature area is after every product and activity row. In the busy sample it appeared on page seven, detached from reconciliation on page one. | Put cashier/receiving cashier or manager sign-off beside the cash reconciliation on the summary page. Print detailed rows as an appendix. |
| Low | The header has no store/business display name or generated timestamp. Later pages have no repeated shift identity or page numbers when browser headers/footers are disabled. The print does not include the closing actor from the stored action history. | Add store/branch identity, shift/report reference, generated date/time, closed-by name, and repeated page identity with Page X of Y. Include a reviewer name/status even when review is still pending. |
| Low | The layout uses substantial vertical spacing. The small open and closed samples each occupied two A4 pages; the busy report occupied seven, with a mostly empty final signature page. | Use denser print-specific spacing and a one-page closing summary where practical. Keep details readable in separate pages. |

These page counts describe the tested Chrome A4 output with the application's 12 mm CSS margins and browser headers/footers disabled. Printer drivers, scaling, fonts and browser settings can alter pagination. The current template explicitly targets A4; an 80 mm thermal roll needs its own layout.

Optional operational additions are a denomination count sheet and separate promotional/Senior/PWD discount totals. They would support cash counting and pharmacy review, but they are not necessary to establish the basic drawer formula and are not asserted here as statutory requirements.

## Suggested print order

1. Store/branch, shift reference, cashier, opening/closing times, generated timestamp and interim/final status.
2. Sales and payment summary, separating cash and digital collections.
3. Opening handover reconciliation, closing expected/count/over-short, retained cash and cash removed.
4. Notes, actual closing actor, handover/review status and signatures.
5. Optional detailed products and complete movement appendix, with repeated shift identification and numbered pages.

## Checks and evidence

- Reviewed `app/Views/cashier/workspace/print.php`, `_ledger.php`, `_stats.php`, `_tables.php`, the print CSS, the report controller and cashier summary/closing logic.
- Rendered open/closed controller views in isolated SQLite. A second isolated fixture created 120 synthetic sale events and 24 distinct products, then closed the drawer at its expected count. No live sale, shift or stock record was created.
- Printed all three samples through Chrome's actual A4 PDF pipeline. Windows PDF rendering produced eleven page images, all visually inspected: open 2 pages, closed 2 pages, busy 7 pages.
- Evidence is in `writable/shift-print-review/browser-results.json`, `busy-counts.json` and `page-counts.json`. QA PDFs and page images are under `tmp/pdfs/shift-print-review/`; they are internal samples, not production reports.
- The controller rendering/access test passed (one test, fourteen assertions). Drawer handover, close/freezing, refund attribution and exchange cash settlement checks were also run for this review; see `writable/shift-print-review/logic-checks.txt`.

No physical printer, thermal roll, hosted browser or live bank/GCash settlement was tested. The render used synthetic zero-discount sales for layout; this review does not replace the separate POS/inventory audit.
