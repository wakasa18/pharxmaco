---
title: POS cart actions without page reloads
type: implementation-review
status: completed
updated: 2026-10-20
tags: [pos, ajax, cart, testing]
---
# POS cart actions without page reloads

The user clarified that the request to refresh only tables/elements applies to **Point of Sale**. Its product browser is a card grid, while search/filtering, quantities and pricing already run in the browser. The full reloads came from native cart forms submitting after quantity synchronization.

## Result

Add to Cart, Remove, Clear, Hold, Resume and Discard now use AJAX. The server returns explicitly accepted/rejected results, current CSRF/checkout tokens and shared HTML fragments. Only cart lines, totals, benefit choices, discount options and held-sale entries update. Product search/filter controls and the payment form remain mounted.

Ordinary cart edits preserve payment, reference, notes, beneficiary details and the selections of remaining eligible lines. Removed/ineligible lines lose their checked selection. Clear, Hold, Resume and an empty cart reset customer fields; successful customer transitions rotate the checkout token. Eligible product rows, live promotion rules and totals are rebuilt consistently. Draft/held carts still do not reserve stock.

The empty-cart and held-sale controls exist from initial page load, so the first added/held cart works without reloading. Quantity handlers and mobile panel controls work on newly returned rows. The shared held-sale modal remains mounted, and closes after Resume or after its last entry is discarded. Existing cart/held-list scroll positions are retained when replacing their contents.

## Request and recovery behavior

- Cart mutations are serialized; checkout and further cart actions are blocked while a request or saved-sale recovery is pending. CSRF values refresh after each response and after quantity synchronization.
- Server stock/expiry errors appear inline with the cart retained. AJAX cart actions require an active shift when shift tracking is enabled. Existing branch/account scoping remains in the underlying handlers.
- An unconfirmed mutation or failed quantity-sync response offers **Refresh cart**. This performs a read-only GET rather than replaying the action. It restores current cart state and CSRF values before allowing another mutation.
- Checkout validation remains on POS. Unexpected HTML/session redirects trigger saved-sale lookup rather than silently navigating back to POS. A confirmed successful sale deliberately opens its existing completed-sale page.
- Start Shift, My Day and other navigation links retain their normal destinations. Native form routes remain available as a fallback when JavaScript is unavailable.

## Files

`app/Controllers/Cashier/SalesController.php` shares the initial-page/snapshot data path and wraps existing action handlers. `app/Views/cashier/sales/index.php` updates fragments and retains the existing discount/checkout logic. Four shared view partials render `_cart_lines`, `_benefit_products`, `_held_sales` and `_discount_options` for both normal pages and AJAX responses.

Regression cases are in `tests/unit/PosAjaxCases.php`, included by `BusinessLogicTest.php`. Rendered browser fixtures and their action server are in `writable/pos-ajax-review`; the existing responsive POS harness remains in `writable/receipt-review`.

## Verification

- **19 focused PHP tests / 327 assertions passed**, covering new cart responses, rejection, native redirects, stock preservation, account-scoped held carts, current-price resume, token transitions, existing checkout deduplication and benefit/promotion arithmetic. Controllers execute against in-memory SQLite.
- **28 browser scenarios passed** at desktop/mobile widths, including repeated clicks, retained fields, Senior/PWD plus automatic promos, dynamic quantities/dialogs, inline rejection, lost mutation/sync responses, CSRF refresh, unexpected checkout redirects, and intentional completed-sale navigation.
- **68 layout scenarios passed** across seven viewport sizes, filled/empty carts, light/dark themes and mobile panels, plus existing search/filter, quantity, discount and tender-control checks. Desktop/mobile output was visually reviewed.
- All six runtime PHP files pass syntax checks. ZIP entries match current source hashes. No local/hosted application database was used; browser POSTs went only to the fixture server.

These focused results do not resolve the previously recorded nine failures/three errors in inventory/expiry safeguards. No full-suite run, continuous stock polling or hosted deployment was performed. See [Known issues](../project/Known-Issues.md).

## Hosting

`build/hostinger-pos-ajax-2026-10-20.zip` contains six runtime files, a guide and SHA-256 manifest. Upload its `app/` files together to the matching installation and refresh an already-open POS once. **No SQL import is required.** The package assumes the current application's existing modal and discount assets.

Related: [POS behavior](../system/POS-and-Discounts.md), [Checkout repair](2026-10-16-checkout-fixes.md), [Administrator branch selection](2026-10-20-admin-pos-branch.md).
