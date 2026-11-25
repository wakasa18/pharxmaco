---
title: Sales History date-filter update
type: implementation-review
status: completed
updated: 2026-10-21
tags: [sales, datatables, filters, ui]
---
# Sales History date-filter update

The previous Transaction Records card showed one server date range in a yellow limit banner and a second browser-only range in the DataTables toolbar. The two ranges affected different records and made older transactions difficult to find reliably.

## Completed behavior

- One always-visible Sale date from/to form, with Apply and Clear. Date changes wait for Apply; its native GET request works without JavaScript.
- Date filtering runs against the database before the existing 1,000-row cap. End dates include the entire day; either bound is optional.
- Invalid calendar dates and reversed ranges return an explicit error and no loaded transactions. Browser validation catches reversed ranges before navigation.
- A quiet note replaces the yellow banner and explains the load limit, selected range and loaded-only quick search.
- DataTables keeps quick search, column filters, ordering and pagination. The page opts out of its duplicate date range and suppresses redundant link notices for date parameters only. Other tables retain their defaults, and non-date linked filters remain disclosed.
- Clear returns to the base history URL, removing URL filters and rebuilding the table. Applying dates preserves existing linked keyword/branch/payment/status filters. Cashier branch scoping is unchanged.
- Empty tables still initialize DataTables and retain the range and search controls.

## Verification

`tests/unit/SalesHistoryCases.php`, included by `BusinessLogicTest.php`, passed **5 tests / 34 assertions** using the real history controller and templates against in-memory SQLite:

1. More than 1,000 newer records hide an older sale initially; its date range finds it.
2. Midnight and 23:59:59 on the end date are included; the next day's midnight is excluded; open bounds work.
3. Reversed, malformed and impossible calendar dates load no unrelated sales.
4. A cashier cannot change branches through the date form/query; an administrator's branch filter remains effective.
5. The single range is always present, linked filters survive Apply, Clear points to the base route and empty results retain controls.

`writable/sales-history-review/check.cjs` passed **22 browser scenarios**, including native GET Apply/Clear, no date auto-submit, corrected invalid ranges, loaded-row search, numeric sorting, page length, column filters, empty states, non-date filter disclosure and unchanged default date controls on other tables. Widths 320, 390, 768 and 1440 were checked in light/dark themes without horizontal page overflow. Navigation animations were disabled through the test browser's reduced-motion preference. Screenshots at 390/1440 were inspected. No JavaScript exceptions remained.

PHP syntax and JavaScript parsing checks passed. The full business suite was not rerun; earlier inventory/expiry failures are not resolved by this UI update.

Evidence: `writable/sales-history-review/browser-results.json`, `focused-junit.xml`, the four rendered fixtures, screenshots and `package-verification.json`.

## Delivery and limits

Six runtime files are packaged in `build/hostinger-sales-history-filters-2026-10-21.zip`, with a README and verified SHA-256 manifest. See the [installation guide](../upgrades/2026-10-21-sales-history-filters.md).

No application data, schema or hosted installation was changed. The existing 1,000-row loading limit remains; quick search covers loaded rows. Very busy single-day ranges remain subject to that cap.
