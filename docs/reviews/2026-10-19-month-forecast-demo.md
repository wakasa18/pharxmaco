---
title: Month of recent forecast demo data
type: review
status: prepared-for-import
updated: 2026-10-19
tags: [demo-data, forecasting, inventory, sql]
---
# Month of recent forecast demo data

Prepared an additive phpMyAdmin import for `u315645644_pharxmacotest (7).sql`. The user requested **import file only**. No local application database or hosted database was modified, and no application features or manual sections were removed.

The patch `database/samples/2026-10-19-month-forecast-demo.sql` adds **297 synthetic sales, 297 items and 594 matching stock movements**, filling the missing observations needed for **30 days from 19 September to 18 October 2026** across 19 active, unexpired product/branch pairs. The seven Main Branch and twelve Patag Branch pairs retain their existing low closing stock. Original records, prices, expiry dates, quantities, timestamps, users, shifts and forecast snapshots are preserved.

Four sample sales apply the existing product promotion using the actual application pricing rules. Synthetic invoice/note markers identify the training data, with no cashier attribution, registered invoice numbers or actual payments invented. The file checks source records before first import, rejects partial/edited fixtures and adds nothing on a complete reimport.

Eleven verification groups passed in an isolated MySQL 8.2.0 instance: original-row preservation; balanced sales and stock; real promotion snapshots; every-day coverage in UTC and UTC+8; actual branch-scoped reorder and revenue forecasts; phpMyAdmin statement parsing; repeat imports; changed-source and partial/edited-fixture rejection; and rollback on simulated insert failure. The forecast service returned positive suggestions for all 19 selected pairs in recommended/default/month/90-day windows. No full application test-suite claim is made. All temporary verification schemas were removed and the isolated server stopped afterward.

Read the [import and forecast-display guide](../data-guides/samples/2026-10-19-month-forecast-demo.md) for the steps and exact validation boundary. Detailed evidence remains in `writable/forecast-month-review/verification.json` and the preparation manifest in that folder. SQL SHA-256: `e9dd9455ca2ac4ca758c97e37a08fc7926c56115ab8e6b6729a7031cdd883da8`.

Related: [Current state](../project/Current-State.md) · [Reports and forecasts](../system/Reports-and-Forecasts.md) · [Testing and evidence](../development/Testing-and-Evidence.md).
