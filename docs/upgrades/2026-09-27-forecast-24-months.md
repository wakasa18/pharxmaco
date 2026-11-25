---
title: "Forecast history: 24 months"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-09-27-forecast-24-months.md"
source_sha256: 60c56cb77dfa8094d07b1b0ab3192b2b047fc254b92c41f929dde53958fd3f90
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-09-27-forecast-24-months.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Forecast history: 24 months

Package: `build/hostinger-forecast-24-months-2026-09-27.zip`.

Forecast filters now accept up to 24 months of historical sales, measured back from the selected end date. The server validation, browser date controls, and help text use the same limit. Saved manual forecasts and exports use the updated server validation. The future forecast horizon, normal report date filters, and dashboard trend controls are unchanged.

No database change or SQL import is required.

## Upload

Back up and replace:

- `app/Controllers/Admin/ReportsController.php`
- `app/Views/admin/reports/index.php`

This patch builds on the September 27 saved-forecast and restock-modal updates. Keep their service, route, CSS, and JavaScript files installed.

After uploading, reload Reports, select dates up to 24 months apart, and click Update Forecast. Changing fields alone still does not save.

Validation: both PHP files passed syntax checks. Server checks confirmed that 18- and 24-month ranges are accepted and a 25-month range is shortened with the correct message. Client date-script checks confirmed the 24-month minimum and unchanged normal report controls. No database records were changed during validation. This package has not been uploaded to Hostinger.
