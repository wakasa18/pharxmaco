---
title: Expiry calendar limits before selecting a date
type: implementation-review
status: implemented-locally
updated: 2026-10-28
tags: [inventory, expiry, calendar, testing]
---
# Expiry calendar limits before selecting a date

The year grid could offer dates beyond 2036 before the user selected a date. The screenshot showed the application's themed calendar. Its year/month/day disabling logic depended on the date input's limits, but Edit Product removed those limits whenever the input matched its saved date, including an ordinary valid date such as 12 June 2028. The calendar therefore opened with no limits and cached that state until a selection.

## Change

Ordinary saved dates now retain their minimum and maximum before any interaction, in both the server-rendered form and JavaScript. Only an unchanged stored date outside the allowed range keeps its native validation exemption, so unrelated edits can preserve old records.

The themed calendar reads the expiry field's server-supplied range independently of that exemption. Its initial view is clamped inside the range without changing the field's existing value. Invalid years, months and days are disabled before selection; navigation, keyboard actions, Today and final selection also respect the bounds. The range still runs from tomorrow through 31 December of the Philippine year plus ten (2036 during 2026), with no administrator override. Generic report/filter calendars retain their own ranges or unrestricted behavior.

The shared layout and expiry field asset versions changed so refreshed pages load the current scripts. Coarse-pointer/mobile devices retain their existing OS date picker and native min/max behavior; the application's themed year grid is used on supported desktop browsers.

## Verification

289 actual calendar/browser checks passed at 1440 and 390 pixels, plus coarse-pointer native fallback. Before-first-selection year disabling, initial 2028-06-12, disabled years/months/days and paging, legacy view clamping without rewriting, typing while open, keyboard/Escape/reset, optional versus required Clear, emitted input/change events and ordinary report December–January/unbounded navigation were verified. No JavaScript errors or POSTs occurred. Screenshots were visually inspected.

25 focused PHP cases / 320 assertions passed for expiry rules, management/product forms, units and submit-review behavior using isolated SQLite fixtures. The server policy and database write behavior were not changed by this UI repair.

Syntax checks passed for all 33 packaged PHP files and both modified JavaScript files. Independent read-only review found no remaining actionable bounds issue.

Evidence is under `writable/expiry-calendar-bounds-20261028/`. Earlier input-validity checks did not exercise the year grid before the first selection; this verification includes that interaction and the unchanged 2028 date from the screenshot.

No SQL, schema, application records or hosting were changed. The existing three A05 expiry-correction test issues are separate from this calendar fix and remain documented in [Known issues](../project/Known-Issues.md).

Upload: `build/hostinger-expiry-calendar-2026-10-28.zip`, 172,338 bytes, 37 runtime files including four changed files, this guide's deployment companion and a SHA-256 manifest. All 39 ZIP entries were verified. SHA-256: `64c9f9ca80206273f88a16d66335503515bf7529843b56c298cfecfdf0ec5876`.

[Upload instructions](../development/Expiry-Calendar-Deployment.md) · [Expiry limit policy](2026-10-28-expiry-limit-only.md) · [Inventory reference](../system/Inventory-and-Expiry.md).
