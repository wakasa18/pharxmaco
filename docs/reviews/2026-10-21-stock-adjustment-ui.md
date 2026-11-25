---
title: Product stock adjustment form polish
type: implementation-review
status: completed
updated: 2026-10-21
tags: [products, stock, ui, validation]
---
# Product stock adjustment form polish

The user selected five changes from the screenshot review: prevent clipped action text, improve current-stock grammar, rename Units to Quantity, group expiry information and require a positive quantity/reason before submission.

`app/Views/products/index.php` now uses wider labeled controls on desktop and a stacked form on small screens. Current stock and balance previews show `35 boxes`, `1 box` or `1 tablet` as applicable. Legacy pcs displays as pieces; measurement abbreviations such as mL remain unchanged. Stored units and quantities are not converted.

Expiry dates use a time element with nowrap styling, grouped with their status badge under the Expiry label. The visual label does not alter the text parsed for date sorting. The mobile table's existing Expiry label is retained without a duplicate inner label.

The Add/Remove action is enabled only for whole quantities from 1 to 1,000,000, within the removal limit, and a trimmed 3–200-character reason. Zero, negative, fractional, exponent and excessive quantities are rejected. Editing the mode/quantity/reason or returning through browser history refreshes the preview and validity. Native form submission retains CSRF, stock token, branch and previous-stock fields.

The server already enforced positive Add/Remove quantities and trimmed reasons; the controller was not changed. Receiving policy, expired-stock handling and separate stock lots are outside these five UI changes. See the existing [inventory limitations](../project/Known-Issues.md).

## Verification

- Four existing stock tests / 26 assertions passed: Add/Remove movement values and replay prevention, quantity/current-count/branch rejection, required reason/token/locked-count checks, and legacy count/receive behavior.
- Eighteen browser scenarios passed using the actual rendered view. Checks included singular/plural labels, invalid inputs, blank/short trimmed reasons, removal bounds, unit-aware previews, grouped expiry, date sorting and a successful trimmed-reason POST to the fixture server.
- Layout checks at 320, 390, 768 and 1440 pixels, in light/dark themes, found no page/form overflow or clipped action text. Desktop and mobile screenshots of the 35-box expired product were visually inspected.
- PHP syntax passed. The full application suite was not rerun; unrelated tracked inventory/expiry issues remain open.

Evidence is in `writable/stock-adjustment-review/`: `browser-results.json`, screenshots, rendered fixture, `focused-junit.xml` and `package-verification.json`.

The [single-view upload package](../upgrades/2026-10-21-stock-adjustment-ui.md) requires no SQL import. Application data and hosted installation were not changed.
