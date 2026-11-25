# Cashier workspace — October 15, 2026

Cashiers without an open shift land on the dedicated **Start Shift** page after login. They count opening cash and choose **Start shift and open POS**. An existing open shift resumes at the POS. **My Day** remains available in the sidebar for daily totals and closing; administrators keep their Dashboard.

The POS also shows **Start shift to complete sale** when an administrator has no open drawer. If a shift closes after the POS was loaded, checkout returns to Start Shift and preserves the cart. Checkout validation messages now appear directly on the POS with the payment form retained, rather than being lost during a redirect. This checkout fix requires the matching controller and view together, with no additional SQL import.

**No Discount** is the default discount choice and means no additional manual or beneficiary discount. Product and category promos still apply automatically. Each item receives its best product offer or allocated category offer, with the lowest discount ID breaking ordinary ties. A category promo tests its minimum against matching category spend and shares its fixed amount or capped percentage once across those lines; better product/statutory treatment can replace a line's share without redistributing it. All-product manual offers remain selectable. Choices persist while adding products to the same cart and reset for a new checkout. Each cart line shows its deduction. Checkout verifies every displayed promo and amount, rechecks applied rules/categories, and saves the per-item pricing in existing receipt metadata. No further SQL import is needed.

**Senior Citizen** and **PWD** keep qualifying product/category promos available. Each overlapping item receives whichever treatment lowers its payable amount; comparison includes statutory VAT relief, and ties retain statutory treatment. A promo-only line keeps its ordinary tax treatment. Promos remain visible while entering beneficiary details, but checkout requires the documents and eligible products to be confirmed. Promotions, Senior/PWD and VAT relief totals appear separately in POS, receipts and Sale Details. Completed automatic-promo invoices require a refund and new POS sale for quantity changes, so current offers can be checked again; notes and payment details remain correctable. Install all files in `build/hostinger-automatic-promotions-2026-10-16.zip` together. No SQL import or historical sale rewrite is needed for this discount update.

## Installing on the existing database

Product/category promo offers and the Automatic product promos option are omitted from the POS Discount dropdown. No Discount (the default), Senior Citizen, PWD, and manual all-product offers remain selectable. Returning to No Discount clears beneficiary benefits and keeps automatic product/category promos. This update changes application files only.

1. Back up the database. If invoice, medicine type and Senior/PWD updates 000010–000013 are already installed, import `2026-10-15-cashier-workspace.sql` into that same database in phpMyAdmin.
2. For the older `u315645644_pharxmacotest (3).sql` database, use the updated `2026-10-14-latest-existing-database.sql` instead. It now includes updates 000010–000014, identifiable medicine details and the previously requested VATable product update.
3. Upload the matching application files from `build/hostinger-cashier-workspace-2026-10-15.zip` into the application root, preserving folders. Keep the site's `.env`, writable contents, uploads and configured `app/Config/ReceiptInvoice.php`. The package omits those configuration files. If this older site has no `ReceiptInvoice.php` yet, copy the included `ReceiptInvoice.php.example` to that name and configure its actual seller details and numbering before issuing registered invoices.
4. Refresh / sign in, select the shared drawer on Start Shift and count opening cash. Cashiers continue directly to the POS after opening. Each existing branch gets a Main drawer. For a newly created branch, an administrator enables its drawer under **Cashier Shifts**.

Both SQL files are repeatable patches. Neither imports old sales nor replaces accounts, stock, prices or payment records. The new tables contain drawer assignments, immutable financial movements, held carts, closing reports and review history. Existing transactions remain visible as activity before shift tracking; no historical floats or shifts are invented.

## Daily use

- **My Day:** the signed-in cashier's branch activity, Today / Yesterday / a date range up to 31 days, sales after discount, completed sale count, units, refund payouts, discounts, payment receipts/payouts, net collections, sold/returned/replacement products, recent invoice links, saved carts and low-stock/expiry alerts. Totals refresh every 30 seconds while the page is visible and online.
- **Start shift:** one active cashier per shared branch drawer, and one drawer per cashier at a time. Count the actual cash. A reason is required if it differs from the previous cashier's closing cash left.
- **Start page first:** opening the POS directly without an open shift sends a cashier to Start Shift. Read-only reports and history remain available before opening and after closing. A busy or reserved drawer shows its availability and prevents opening it. Failed opening submissions return to the start form; repeated submissions do not create duplicate shifts.
- **During shift:** sales, refunds, exchanges and financial sale corrections require an open shift in that branch. Record float top-ups and safe drops as cash added/removed, with reasons. Cash payouts cannot exceed recorded drawer cash.
- **Held sales:** carts persist after sign-out, belong to their cashier and branch, and recheck current stock/pricing at checkout. Resolve them before closing. Another cashier cannot resume them. An administrator closing on behalf of an unavailable cashier can explicitly discard held carts, with a reason recorded.
- **Close shift:** finish / hold / clear the active cart, resolve held carts, count cash and state how much remains in the drawer. The rest is recorded as removed at closing. Give a reason for a difference. New drawer activity invalidates the count page and requires a refresh and recount.
- **Handover:** choose an active cashier in the same branch to reserve the drawer. That cashier counts opening cash to accept responsibility. The prior closing totals stay fixed. Administrators may release a reservation with an audited reason. For end of day, leave the handover unnamed.
- **Review:** administrators use **Cashier Shifts** for drawers, opening/closing differences, refund/exchange/correction/cash movement review notes and older pending records. A closed shift has a printable final report; an open shift's printout is clearly marked interim.

## Amounts and dates

All workspace dates use Philippine time. Calendar-day activity and shifts are separate: a shift crossing midnight remains open until counted and closed.

Cashiers see their assigned branch. Administrators using the POS see their active drawer's branch, or their most recent drawer's branch after closing, so their daily totals remain available after the shift ends.

Expected drawer cash = opening cash + net cash payments + cash added − cash removed. Cash payment receipts retain the amount after change. GCash/card payments do not increase the physical cash balance. Exchange return credit is not a new receipt; only the actual difference collected or paid out enters collections. Original sales count once; refunds do not erase that original count.

A later refund or financial correction belongs to the operator and shift processing it. Closed shift totals are saved and do not change later. Old refund rows with no payout method are identified as unknown rather than assigned to cash; rows lacking a refund event ID do not invent a refund transaction count.

Payment-method corrections are limited to the sale's original open shift and must reflect the payment actually received. Changing the payment label on an already reconciled sale could move current drawer cash incorrectly; use a documented note or a refund/new sale for older transactions. Quantity corrections with actual additional payments or payouts are recorded in the processing shift.

## Verification

New workflow checks cover opening ownership, counted handover, stale closing counts, exact cash amounts, persistent carts, checkout retries, refunds, exchange differences, legacy correction baselines, role/branch access and rendered pages. Native MySQL checks exercise repeatable-read snapshots and two simultaneous drawer opening requests. Browser checks cover light/dark layouts at 320/390/768/1440px, count validation, safe live updates and printing.

The combined SQL was imported twice against the supplied backup in an isolated local database: all original fields on 54 products, 776 sales and 1,326 sale items were preserved. The local application database also received only the additive cashier schema upgrade; its existing application records were compared before and after and preserved. No test transaction was posted to that database.

Current validation, including Start Shift: the focused cashier/login run passed 17 tests with 159 assertions. The broader run passed 184 tests with 22,022 assertions after excluding 12 known stock-expiry/provenance tests. Those same 12 issues (3 errors and 9 failures) were reproduced against the earlier checkout controller; the full suite is therefore not entirely green. Cashier/Start Shift browser checks passed 81 cases. Existing POS/product/receipt checks passed another 83 during the initial workspace verification. Native MySQL snapshot/handover/concurrent opening checks and repeated SQL import checks passed. All three demo logins were verified against the new Start Shift destination in an isolated database. This page/redirect update requires no further SQL import when the workspace schema is already installed.
