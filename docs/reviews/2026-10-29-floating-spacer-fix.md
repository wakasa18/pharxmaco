---
title: Floating spacer after page refresh
type: implementation-review
status: implemented-locally
updated: 2026-10-29
tags: [interface, dropdowns, css, testing]
---
# Floating spacer after page refresh

The thin white box above Sale Details was an empty `<div class="sp"></div>`, rendered after a refund error message. Existing management templates also use `sp` for spacing. The themed select-picker stylesheet assigned `.sp` fixed positioning, a 160-pixel minimum width, six-pixel padding and a border. Ordinary empty spacers consequently became floating 160 × 14 pixel popup shells without a dropdown being opened.

The select-picker popup root now uses the dedicated `phx-select-popup` class in JavaScript and all matching root CSS selectors. Option/search styles and behavior remain intact. Legacy spacing elements receive no popup styling. The staff layout versions both assets to invalidate cached copies. No refund or stock logic changed.

## Verification

- Rendered actual refund error/clean views using isolated SQLite fixtures. The previous stylesheet reproduced the screenshot's **160 × 14 pixel fixed box** exactly.
- **42 browser checks pass** at 1440 and 390 pixels in light/dark themes: error text retention, spacer without fixed positioning/border/height, no unsolicited popup, dropdown opening/viewport placement, selection, Escape/outside close, long-list search, clean page and page reload. No POSTs or JavaScript exceptions occurred. Before/after screenshots visually inspected.
- Changed JavaScript and PHP layout pass syntax checks. The corrected stylesheet has no bare `.sp` popup-root selectors. Evidence: `writable/select-popup-fix-20261029/`.
- The full suite was not repeated for this CSS/JavaScript namespace change. The preceding background-filter checkpoint records 435 tests with the same three outstanding A05 expiry-correction cases.

Runtime changes: `public/assets/css/select-picker.css`, `public/assets/js/select-picker.js` and `app/Views/layouts/staff.php`. No SQL, schema, application records or hosting changes. [Upload guide](../development/Select-Popup-Fix-Deployment.md).

Upload: `build/hostinger-select-popup-fix-2026-10-29.zip`, 213,524 bytes, 52 runtime files plus guide and SHA-256 manifest. All 54 ZIP entries verified. SHA-256: `0b566668ab4cb18a507c6f7ef9a5740b1ea9cefbd4a39ca5c0584ea1562c9f77`.
