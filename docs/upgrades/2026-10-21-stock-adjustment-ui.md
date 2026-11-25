---
title: "Product stock adjustment UI update"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-21-stock-adjustment-ui.md"
source_sha256: 673439e2355fff023aade7499083581134c5586e768e88c1b20132d2af3d9616
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-21-stock-adjustment-ui.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Product stock adjustment UI update

This update changes `app/Views/products/index.php` in the current Pharxmaco application.

1. Back up the matching view on the host.
2. Extract `build/hostinger-stock-adjustment-ui-2026-10-21.zip`.
3. Upload the included `app/Views/products/index.php` to the same path in the application and refresh Products.

No SQL import or migration is needed. The archive contains the updated view, this README and a verified SHA-256 manifest.

## Changes

- Wider Add stock/Remove stock selector and responsive stacked controls on small screens.
- Current stock labels use singular/plural units, such as `35 boxes` and `1 tablet`; measurement abbreviations remain unchanged.
- Visible Quantity and required reason/reference labels replace the Units placeholder.
- Expiry date and status stay grouped under Expiry; dates stay on one line.
- The action stays disabled until a whole positive quantity and a trimmed reason of 3–200 characters are valid. Removal cannot exceed available stock. Balance previews use the correct unit.

The server already enforced positive Add/Remove quantities, required reasons, branch restrictions, stock tokens and current-count checks. Those rules remain. This is a presentation and browser-validation update; it does not implement separate stock lots or change receiving/expiry policy.

Four existing stock tests / 26 assertions and 18 browser scenarios passed, including phone/tablet/desktop light/dark layouts, numeric date sorting, invalid values and a valid POST to a fixture server. No application stock, data or hosted installation was modified.
