# POS updates without full-page reloads

20 October 2026

Upload all six `app/` files together into the matching directories of the current Pharxmaco installation. Keep the previous versions first. Refresh an already-open POS once after uploading to load the updated script.

This package updates Add to Cart, Remove, Clear, Hold, Resume and Discard through AJAX. Only cart lines, totals, discount options, benefit choices and held-sale entries are refreshed. Product search, filters and the payment form remain in the same document. Ordinary cart changes retain entered payment/beneficiary details and checked eligible lines. Clear, Hold, Resume and an empty cart reset customer details; a new customer receives a new checkout token.

Cart requests run one at a time with refreshed CSRF tokens. An unconfirmed action or quantity sync offers **Refresh cart**, which reads the server state without repeating a cart mutation. Checkout validation stays on POS; uncertain checkout results retain the saved-sale status lookup. A confirmed successful sale intentionally opens the existing completed-sale page. Starting a shift and leaving POS through navigation remain normal page navigation.

No SQL, credentials, new dependencies or database changes are included. Existing discounts, payment processing, sale snapshots and inventory writes are retained. The update is intended for the current application version, including its existing shared modal and discount-calculation assets. It does not add continuous background inventory polling or unit conversion.

Verification: 19 focused PHP tests / 327 assertions passed in in-memory SQLite. Browser fixtures passed 28 action scenarios at desktop/mobile sizes and 68 layout scenarios. Fixture submissions did not use an application database. The earlier tracked inventory/expiry failures remain outside this change; the whole business suite was not rerun. Hosting was not deployed automatically.
