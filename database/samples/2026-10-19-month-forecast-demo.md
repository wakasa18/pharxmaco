# One month of recent forecast demo history

Prepared on **19 October 2026** against `u315645644_pharxmacotest (7).sql`. Import [`2026-10-19-month-forecast-demo.sql`](2026-10-19-month-forecast-demo.sql) into the **existing test database** in phpMyAdmin. It is an additive sample-data import, not a full database backup or schema upgrade.

The user selected **import file only**. The local application database and the supplied export have not been changed.

## Added history

The selected products have demand observations on all **30 complete days from 19 September through 18 October 2026**. Existing observations, including the earlier 14-day fixture, are retained. The import fills the missing product/day observations rather than adding another copy of those earlier demo days.

| Records | Added |
| --- | ---: |
| Completed synthetic sales | 297 |
| Matching sale items | 297 |
| Synthetic resupply movements | 297 |
| Matching sales deductions | 297 |
| Units resupplied and sold | 1,245 each |
| Net demo sales revenue | PHP 110,905.00 |
| Product/branch combinations | 19: 7 Main Branch, 12 Patag Branch |

Four added sales apply the existing Baby Wipes PHP 10 product promotion. Their amounts and saved promotion metadata were checked against the application's actual promotion logic. No Senior/PWD beneficiaries or discounts are fabricated.

Each new sale has a matching synthetic resupply and stock deduction. Its stock cycle starts and ends at the original historical balance, including when inserted between existing movements. **Current branch and product balances remain unchanged**, so the existing low-stock products remain available to reorder suggestions. Prices, reorder levels, expiry dates, product classifications and original modification timestamps are preserved. Only active products with future expiry dates are selected; expired low-stock products are excluded from the sample.

| Branch | Products receiving additional history |
| --- | --- |
| Main Branch | Dextromethorphan, Ambroxol, Fexofenadine, Diphenhydramine, sterile gauze pads, saline nasal drops, mosquito repellent lotion |
| Patag Branch | Diclofenac, paracetamol syrup, dextromethorphan, bisacodyl, loratadine, fexofenadine, isopropyl alcohol, povidone iodine, sterile gauze pads, diaper rash cream, baby wipes, saline nasal drops |

Invoices start with `DEMO-M30-20261019-`. Notes and stock remarks start with `PHX-DEMO-MONTH-20261019` and identify the records as synthetic. These are training records, not actual customer payments. No cashier, shift, cash-drawer movement, seller TIN or registered invoice serial is invented. The import preserves existing users, shifts, refunds, invoice counters and forecast snapshots. This fixture supports sales/forecast demonstrations; it does not create cashier shift history.

## Import in phpMyAdmin

1. Select the **existing test database matching the supplied export** in the left sidebar.
2. Open **Import**, choose `2026-10-19-month-forecast-demo.sql`, set character set **utf-8** and format **SQL**, then import the **entire file**. Keep the normal semicolon delimiter and stop on SQL errors.
3. The final result should say **30-day forecast demo import complete**, with **297 added sales** and **594 added stock movements**.
4. A complete repeat says **Already complete: no changes** and adds nothing.

The first import checks the existing products, branch inventory, branches, discounts, sales, sale items and stock logs against the supplied export. If those records have changed, or some demo records already exist but are missing/edited, the script rolls back with **no new records**. Re-export the intended database and prepare an updated fixture if that happens; do not remove its guards. The script uses a transaction and temporary staging tables, requires InnoDB for the affected tables, and does not create or replace permanent tables or disable foreign keys.

## Show the forecasts

1. Open **Reports**, choose **All Branches** or either individual branch, and set forecast grouping to **Daily**.
2. Under **Sales forecast settings**, set **Use sales from** to **2026-09-19** and **Use sales to** to **2026-10-18**.
3. Select **Update Forecast**, then open **Restock suggestions**. The application calculates the suggestions from the added sales history; no precomputed forecast results are imported.
4. Open the low-stock popup to view its reorder forecasts. It uses the application's separate 90-day history window.

The actual service returned positive suggestions for all 19 selected pairs using the recommended range, the Reports default range, the current-month range and the topbar's 90-day range as of 19 October. Other pre-existing low-stock products can also appear, including products with no usable demand history. This sample demonstrates the calculations; it does not measure forecast accuracy. As the records age, select the fixed range above to reproduce the demonstration.

## Verification

Imported the supplied export and the sample SQL into an isolated **MySQL 8.2.0** instance with foreign keys enabled. The original export was generated on MariaDB 11.8.9; its hosted server and phpMyAdmin browser were not accessed. The installed phpMyAdmin SQL parser extracted and executed the file statement by statement, including interleaved client diagnostic queries.

Checks covered all original-row preservation, sale totals/profits/snapshots/promotions, chronological stock continuity without negative balances, 30-day coverage in UTC and UTC+8, branch-scoped reorder results, seven positive sales-revenue forecasts, repeated imports, changed-source rejection, partial/edited-fixture rejection and rollback after a simulated insert error even when the client continues. No application code or forecasting weights were changed. The isolated schemas were dropped and the temporary server stopped after verification.

- Source export SHA-256: `3436403d03724597a9e03539b16e83aafc92428114cb430888233f714169579e`
- Import SQL SHA-256: `e9dd9455ca2ac4ca758c97e37a08fc7926c56115ab8e6b6729a7031cdd883da8`
- Preparation and detailed results: `writable/forecast-month-review/manifest.json` and `verification.json`.

