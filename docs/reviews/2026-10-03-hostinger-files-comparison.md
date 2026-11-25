---
title: "Hostinger app and assets comparison"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/reviews/2026-10-03-hostinger-files-comparison.md"
source_sha256: e68604a9f4833070988ccfd0ca09fe15c46354db4267c97c57b182ef8833092d
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/reviews/2026-10-03-hostinger-files-comparison.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Hostinger app and assets comparison

**October 4 update:** after the exchange filter, discounted quantity corrections and system audit fixes, the supplied `app (1).zip` matches 183 of 197 current files. Fourteen archived app files differ. One new functional file, `app/Libraries/SaleCorrectionPricing.php`, is absent from the archive. The supplied assets match 24 of 26 files; `sidebar.css` and `topbar.css` now differ. Deploy the [different-product exchange patch](../upgrades/2026-10-04-exchange-different-products.md), [discounted quantity correction patch](../upgrades/2026-10-04-discounted-sale-corrections.md) and [system audit patch](../upgrades/2026-10-04-system-audit-fixes.md) for these latest changes. No functional files have been removed. The machine-readable comparison has been refreshed with these results.

The remainder of this document records the October 3 comparison, before that exchange change.

Checked the supplied `app (1).zip` and `assets.zip` against the current workspace on October 3, 2026. Entries were read directly from the archives without extracting them, executing archived PHP, changing application files or contacting Hostinger.

**October 3 result: all 223 archived files exactly matched the local project at that time, byte for byte. No outdated or modified files were found.**

| Archive | Corresponding local folder | Archived files | Exact matches | Different files | Missing functional files |
| --- | --- | ---: | ---: | ---: | ---: |
| `app (1).zip` | `app/` | 197 | 197 | 0 | 0 |
| `assets.zip` | `public/assets/` | 26 | 26 | 0 | 0 |

Neither archive contains files absent from its corresponding local folder. No files differed solely because of line endings.

A separate code-content recheck confirmed **193 PHP files, 11 JavaScript files and 15 CSS files** match exactly. This compares the complete file contents, not just names, sizes or modification dates. No older code versions were found in the supplied archives.

## Latest updates included

The comparison also verified the 33 PHP files in the current permanent-delete-flags deployment manifest, plus the cashier catalog routes and views and selected POS/report assets. All 39 files are present and identical. This includes:

- Branch-specific product Trash and restore behavior.
- `is_deleted` and retained permanent deletion handling.
- The recoverable `is_permanently_deleted` flag and migration `2026_10_03_000009`.
- Product, branch-product, category, supplier and discount models and their shared retained-record behavior.
- Updated filtering and availability checks in POS, exchanges, expiry actions, forecasts, notifications, dashboards and reports.
- Cashier shared-product catalog, its routes, setup view and corrected layout.
- The current CSS and JavaScript assets in `public/assets/`.

No replacement app/assets package was needed at the time of the October 3 comparison. See the October 4 update above for the newer exchange patch.

## Harmless files omitted from the app archive

The local app folder contains ten additional files. Eight are Git directory placeholders; the remaining two are empty, and no references to them were found in application code, tests or Composer configuration. They are unnecessary for the current application.

| Omitted local path | Reason |
| --- | --- |
| `app/Database/Migrations/.gitkeep` | Directory placeholder |
| `app/Database/Seeds/.gitkeep` | Directory placeholder |
| `app/Filters/.gitkeep` | Directory placeholder |
| `app/Helpers/.gitkeep` | Directory placeholder |
| `app/Language/.gitkeep` | Directory placeholder |
| `app/Libraries/.gitkeep` | Directory placeholder |
| `app/Models/.gitkeep` | Directory placeholder |
| `app/ThirdParty/.gitkeep` | Directory placeholder |
| `app/Filters/Filters.php` | Empty, unused file (0 bytes) |
| `app/Models/Branche` | Empty, unused file (0 bytes) |

## Database and other deployment files

These two archives establish the versions of app and asset files only. They do not include the database schema/data, `.env`, `vendor/`, `system/`, `writable/`, or public-root files outside `public/assets/`. Their presence on the running website and the database's applied migration state were not checked in this comparison.

The latest database update prepared for the supplied export is 2026-10-03-all-latest-database-updates.sql (repository file: `database/upgrades/2026-10-03-all-latest-database-updates.sql`). If that update has already been imported, verify that the five retained-record tables expose `is_permanently_deleted` and that the `migrations` table includes `2026_10_03_000009`. Having a migration PHP file in the app archive does not itself establish that its SQL has been applied.

The SQL files were also rechecked against the current project. All nine current migration PHP files are present and identical in the Hostinger app archive. The updated full export contains each current migration version and ends with the complete current upgrade script, unchanged. The combined upgrade includes all four newer migrations through `2026_10_03_000009` and both deletion flags on each of the five retained-record tables. The earlier `2026-10-03-branch-product-trash.sql` remains a partial upgrade for branch Trash only; it does not include the later permanent deletion updates. Use the combined upgrade for an existing database, or the updated full export for an empty database.

## Archive identification

| Archive | SHA-256 |
| --- | --- |
| `app (1).zip` | `cb0604091ee2570bc7331e132e04d8feacae9de9388bac501abb0997b91d90fc` |
| `assets.zip` | `4d8fafb43c2a2dbf21545a772975a2528189d0e9dae936d24621fb123c15b6f5` |

The complete machine-readable file inventory and comparison hashes are saved locally in `writable/receipt-review/hostinger-files-comparison.json`.
