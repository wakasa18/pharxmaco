---
title: "Pharxmaco User Manual"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "writable/pos-catalog-reorder-payments-20261028/manual-original.md"
source_sha256: f8b8fe5976dd439bc2fa666ab23edefc23d367d493d9f6a42038cd2c5b565d8b
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `writable/pos-catalog-reorder-payments-20261028/manual-original.md`. Findings and test counts refer to the original work. Read [Current state](../../project/Current-State.md) and [Known issues](../../project/Known-Issues.md) for subsequent changes.

<!-- page: cover | Pharxmaco User Manual | All users -->
# Pharxmaco User Manual

## Point of Sale and Inventory Operations

Administrator and Cashier Edition

Document PHX UM 001

A practical operating reference for preparing the pharmacy workspace, recording sales, managing branch inventory, reconciling shared cash drawers and reviewing business records.

<!-- page: document-control | About this manual | All users -->
# About this manual

This manual explains how to operate the current Pharxmaco point of sale and inventory application. Follow the numbered procedures for the task you are performing, check the expected result, and use the troubleshooting chapters when the application displays an error.

| Document information | Details |
| --- | --- |
| Document identifier | PHX UM 001 |
| Intended users | Cashiers and administrators |
| Application scope | Current workspace with cashier shifts, automatic product and category promos, invoice fields, expiry resolutions and updated reports |
| Currency and reporting time | Philippine pesos, shown as PHP in this manual; cashier reporting uses Philippine time |
| Document owner | The business administrator maintains the local operating copy |

## How to read the procedures

**Open** identifies the page or navigation path. **Before you begin** states what must be ready. **Expected result** explains what should appear or change after saving. **Important** identifies a condition that can change the transaction or stock balance.

Bold words such as **Complete Sale** refer to application controls. Examples use training amounts and do not create records. A role named Administrator means the application's **admin** account type; there is no separate supervisor permission role.

The software records transactions and product information. The pharmacy team remains responsible for checking the actual product, dispensing requirements, beneficiary documents, physical stock and approved business procedures. Product labels and eligibility flags are entries maintained by staff; they do not perform those checks automatically.

## Reading paths

- **New cashier:** Chapters 1 to 13, then Chapters 15 to 18 and the daily checklist.
- **Inventory staff using a cashier account:** Chapters 19 to 24, within the assigned branch.
- **Administrator:** All chapters, especially account setup, shift review, corrections, reporting, invoice configuration and recovery.
- **Problem at the counter:** Start with Chapters 32 and 33. Record the invoice or shift reference before asking for support.

Keep this manual with the site's approved return, discount and cash handling procedures. Use a current copy when application screens change.

<!-- page: contents | Contents | All users -->
# Contents

The PDF contents entries link to the relevant chapter. Page numbers refer to this edition.

<!-- The builder inserts a linked contents table here. -->

<!-- page: overview | 1 System overview and responsibilities | All users -->
# 1 System overview and responsibilities

Pharxmaco connects branch product balances, POS transactions, customer returns and cashier drawer records. A completed sale reduces the selling branch's stock and creates an invoice, sale items, stock movements and cashier payment activity.

## Work sequence

**Prepare branch and products → Count and start shift → Record sales and returns → Review My Day → Count and close → Administrator review**

| Task | Cashier | Administrator |
| --- | --- | --- |
| POS, sales history and receipts | Assigned branch | Operating branch; broader history access |
| Open, use and close a drawer | Own shift in assigned branch | Own shift; may close another cashier's shift with a reason |
| View My Day and shift details | Own activity and shifts | Own activity; review staff shifts |
| Add products and adjust stock | Assigned branch | Branch selected for the action |
| Edit product details and manage Trash | Administrator required | Available |
| Resolve expiry stock | Own branch; replacement or removal | Branch selected; may also deactivate inventory |
| Categories, suppliers and discount rules | Administrator required | Available |
| Business reports, users, branches and recovery | Administrator required | Available |
| Interface preferences | Available | Available |

The system starts with **one shared drawer per branch**. Only one cashier can hold that drawer at a time, and a cashier can have only one open shift. A named handover can reserve it for the next cashier.

## Branch scope

Check the branch shown in the workspace before recording any movement. A cashier cannot select another branch to bypass an assignment. An administrator using POS operates in the branch of the active drawer. Administrative inventory and report pages may allow broader branch selection.

**Important:** Adding an item to the cart or holding a sale does not complete a sale or reserve stock. Stock and prices are checked again at checkout. A receipt reprint does not create another sale.

<!-- page: navigation | 2 Navigation tables and preferences | All users -->
# 2 Navigation tables and preferences

**Open:** The main navigation menu after signing in.

Use **Daily work** for My Day, Dashboard when available, Point of Sale and Sales History. Use **Inventory** for Products, Categories, Stock Logs, Expiry Report and Suppliers. **Business** contains Cashier Shifts, Reports and Discounts. **Administration** contains Users, Branches and Activity Logs.

Items appear according to account role. The sidebar's **Find a page** field searches navigation labels, not product or sale records. On a narrow screen, open the navigation menu to access the same pages.

## Search and work with tables

1. Apply the page's branch, date, status or medicine type filters when provided.
2. Use **Quick search** to search the rows currently loaded in a DataTable.
3. Select a column heading to change sort order. On mobile, use **Sort by** when shown.
4. Choose **Rows per page** and use the paging controls to move through the results.
5. Clear quick filters before concluding that a record is missing. Scroll sideways when a wide table has additional columns.

Quick search is separate from filters sent to the server. A table can show only a loaded batch or a capped result set. Cashier Shifts loads up to 200 shifts or review movements per batch; use its older-record links for earlier batches. My Day recent activity shows the latest 100 movements in the selected period.

**Expected result:** Table search, sorting and pagination change what is displayed. They do not edit the records. Exports use their applied page filters, not necessarily the current quick search or browser page.

## Set interface preferences

1. Open **Interface Settings**.
2. Choose a theme, accent, text size and spacing. Adjust larger controls, contrast or reduced animations as needed.
3. Review the preview, then select **Save preferences**.
4. Use **Discard changes** to abandon unsaved choices, or **Use defaults** and then save to return to default choices.

Preferences are stored in this browser. They are not seller registration, receipt, tax or business settings and may differ on another device.

<!-- page: access | 3 Sign in and manage access | All users -->
# 3 Sign in and manage access

**Before you begin:** Have the application address and your own active account. Cashiers need an active branch assignment. Obtain credentials from the administrator; this manual does not publish shared passwords.

## Sign in

1. Open the application sign-in page.
2. Enter your username and password, then submit the form.
3. Check your displayed name, account role and branch.
4. A cashier without an open shift proceeds to **Start Shift**. A cashier with an existing open shift resumes POS. An administrator normally lands on **Dashboard**.

**Expected result:** The navigation and actions match your role. An unavailable administrative action cannot be enabled by changing the page address.

## If access fails

- **Invalid credentials:** Check the username and keyboard case, then carefully re-enter the password.
- **Inactive account or branch problem:** Ask an administrator to check the account and assignment.
- **Too many failed attempts:** Wait for the lockout shown by the application. Repeated failures can lock the network address for 15 minutes, affecting other users on the same connection.
- **Account changed while signed in:** A password, role, assignment or account status change can invalidate the session. Sign in again after the administrator confirms access.

Passwords are managed by an administrator through **Users**. A user should report a forgotten password to that administrator rather than creating a duplicate account.

## Sign out correctly

Finish the transaction or use **Hold** to save a cart, then select **Sign out** from the navigation. An ordinary active cart belongs to the session and should not be relied on after sign-out. Saved held sales remain available to their owner.

**Important:** Signing out does not count or close the physical drawer. Close the shift separately when handing over responsibility or ending the working period. A shift remains open until the closing procedure is saved.

<!-- page: start-shift | 4 Start a cashier shift | Cashier and Administrator -->
# 4 Start a cashier shift

**Open:** **Start Shift** after cashier sign-in, or **My Day** when an administrator needs a drawer.

**Before you begin:** Confirm that you are taking responsibility for this branch drawer. Physically count notes and coins, including the change float. Check any handover with the previous cashier.

1. Select the available **Shared branch drawer**. Cashiers are limited to their assigned branch.
2. Review the previous closing cash left, when displayed.
3. Enter **Cash counted at opening** using the actual physical count. Do not enter expected sales or a target amount.
4. Enter an **Opening note**. A reason is required if the counted amount differs from the cash left at the last closing.
5. Select **Start shift and open POS**, or **Start shift** from My Day.
6. Check that the open shift and operating branch are correct.

**Expected result:** The drawer is assigned to you and sales can be completed in that branch. Opening cash contributes to expected drawer cash but does not count as sales revenue.

## Drawer unavailable

| Message or state | Action |
| --- | --- |
| In use by another cashier | Ask that cashier to count and close the shift; do not use their login |
| Handover reserved for another user | The named cashier accepts it, or an administrator releases it with a reason |
| No configured or enabled drawer | Administrator opens Cashier Shifts and enables the branch drawer |
| Availability changed during submission | Refresh availability and review the latest state |

A drawer can span midnight without a new shift being created automatically. Keep using the same shift until it is physically counted and closed. My Day's calendar totals can therefore differ from the open shift's totals.

**Training example:** A previous cashier left PHP 500.00. You count PHP 490.00. Enter PHP 490.00 and explain the PHP 10.00 opening shortage; do not change the entry to PHP 500.00 simply to match the previous record.

<!-- page: pos-cart | 5 Build and review a POS cart | Cashier and Administrator -->
# 5 Build and review a POS cart

**Open:** **Point of Sale**. **Before you begin:** Open your shift and verify the selling branch.

1. Find the product using its name or SKU. Use category and **Medicine type** filters to narrow the list.
2. Confirm the exact product, generic ingredient, strength and selling unit against the physical item. Branded and Generic are catalog classifications, not a substitution instruction.
3. Select the product, enter a whole-number quantity and choose **Add to Cart**.
4. Review the cart. Increase or decrease quantities using the quantity controls; use the remove control for an unwanted line.
5. Confirm each quantity, unit price, line amount and any displayed promotional deduction.
6. Select a manual or beneficiary discount only when needed. Chapter 7 explains the default **No Discount** choice.
7. Proceed to the payment section. On a phone, use the Products, Cart and Payment panels.

**Expected result:** The cart shows the intended sale and a calculated total. It remains an uncompleted transaction until checkout succeeds.

## Stock and availability

The selected branch must have active, available stock. Out-of-stock, inactive, trashed or expired inventory may be unavailable for sale. Another cashier or stock action can change availability after you add an item. Checkout checks the current records again and rejects a quantity that cannot be supplied.

Do not change product classification, expiry or stock merely to force an unavailable product through checkout. Ask the administrator to investigate a real discrepancy. Check actual packaging when the catalog is incomplete.

## Useful controls

- **Clear** empties the active cart. Check that it is not another customer's held order first.
- **Hold** saves the cart for later; it does not reserve its products.
- Press **/** outside a text field to focus product search.
- **Ctrl+Enter** or **Command+Enter** can submit a ready checkout. Use it only after reviewing the payment and beneficiary details.

For units belonging to different beneficiaries or buyers, use separate sales when only some units of an identical product should receive a benefit. A selected beneficiary line applies to every unit on that line.

<!-- page: checkout | 6 Take payment and complete a sale | Cashier and Administrator -->
# 6 Take payment and complete a sale

**Before you begin:** Review the cart, discounts, beneficiary checks and final total. Verify the actual payment before recording it.

1. Select **Cash**, **GCash** or **Card** to match how the customer paid.
2. For GCash or Card, record the actual payment reference. Verify payment using the business's payment process; selecting a method does not contact a bank or payment provider.
3. Enter **Amount paid**. For cash, use **Exact** or a quick cash amount when appropriate and check the calculated change. For electronic payment, enter the exact settled amount.
4. Add an operational note if needed. Do not place card security information or passwords in notes.
5. Select **Complete Sale** once and wait for the result.
6. Confirm that the completed-sale page and invoice reference appear. Give the customer the correct change and receipt.

**Expected result:** The application saves the sale, item pricing and deductions, reduces stock, records stock and payment activity, and opens the completion page. A successful sale clears that checkout's cart.

## If checkout does not finish normally

If a validation message appears, read it before resubmitting. Correct insufficient payment, unavailable stock, beneficiary details, changed promos or an expired form as directed. If your shift closed elsewhere, start a valid shift and review the kept cart.

If the connection fails or the result is uncertain, use **Check Sale Status** when offered. Check Sales History for the exact transaction before retrying or collecting payment again. The application can recover an already completed checkout without creating a duplicate. Offline checkout is paused until the connection returns.

**Important:** A refreshed page or an HTTP error alone does not prove whether a sale was saved. Do not start a second payment or rebuild the order as a new checkout until the first attempt's status has been established.

For a cash sale, only the amount retained after change increases drawer cash. GCash and Card collections appear in their own payment totals and do not increase physical cash.

<!-- page: discounts | 7 Understand discounts and automatic promos | Cashier and Administrator -->
# 7 Understand discounts and automatic promos

**Open:** The POS **Discount** field and cart deductions.

The default **No Discount** means no additional selected manual or beneficiary discount. Matching active **product and category promos still apply automatically**. Those automatic offers are intentionally omitted from the dropdown.

| Selection | Result to review |
| --- | --- |
| No Discount | Automatic product and category promos can reduce matching lines |
| Senior Citizen or PWD | Verified selected lines receive the more favorable applicable treatment; promos remain available elsewhere |
| A manual all-products offer | The selected manual offer is calculated for matching cart spend; review the recalculated total rather than adding promo amounts yourself |

## How automatic offers are calculated

A product promo applies to its specified product. A category promo applies to products in that category. Offers must be active and within any start and end dates. A minimum matching purchase tests the spend covered by the rule, rather than unrelated cart products.

Where automatic offers overlap, the system selects the better offer for a line. A category's fixed amount or capped percentage is shared across its matching lines once; it is not repeated in full for every product. An offer's allocated share is not redistributed just because another treatment wins on a line.

Amounts are calculated and allocated in centavos. Use the displayed line deductions and final total rather than multiplying a rounded per-unit figure for a large basket.

## If the expected promo is absent

1. Check the product and category actually assigned to the cart line.
2. Ask the administrator to verify the rule's availability, dates, target, minimum and cap.
3. Refresh the POS and review any changed prices or deductions.
4. If checkout reports a changed promo, review the new quote before completing the sale.

**Important:** Selecting a beneficiary benefit does not stack it on top of a promo for the same units. The payable result, including applicable VAT relief and rounding, determines the more favorable treatment. Chapter 8 gives an example.

<!-- page: senior-pwd | 8 Apply Senior Citizen or PWD benefits | Cashier and Administrator -->
# 8 Apply Senior Citizen or PWD benefits

**Before you begin:** Verify the buyer's documents and the products under the business's approved procedure. The administrator must have configured seller tax status and confirmed product eligibility.

1. Choose **Senior Citizen** or **PWD** in the Discount dropdown.
2. Enter the **Beneficiary name** and **Valid ID / reference number**.
3. Select only the eligible product lines for that beneficiary's exclusive use. Lines marked unconfirmed or not eligible cannot receive this benefit.
4. Check the valid ID and any required prescription, booklet or supporting documents, then tick the verification confirmation.
5. Review the discount, VAT relief and any automatic promotional deductions. Complete the sale only when the final total is correct.

**Expected result:** The saved sale retains the beneficiary information and the applied pricing. It can be reviewed in **Sale Details** and the receipt. A product's Branded or Generic label alone does not establish its eligibility.

## How the configured calculation works

For a VAT-registered seller using the supported 12% configuration, an eligible VATable amount is first relieved of its included VAT. The 20% beneficiary discount is calculated on that resulting base. For a Non-VAT seller or a line already VAT-exempt or zero-rated, the system does not remove VAT a second time.

| Training example for one eligible VATable line | Amount |
| --- | --- |
| Amount before beneficiary treatment | PHP 112.00 |
| Base after VAT relief | PHP 100.00 |
| VAT relief | PHP 12.00 |
| Beneficiary discount at 20% of the base | PHP 20.00 |
| Payable with beneficiary treatment | PHP 80.00 |

A 25% ordinary promo on PHP 112.00 would leave PHP 84.00, so the beneficiary treatment is more favorable. A 30% promo would leave PHP 78.40, so the promo is more favorable. These treatments are compared, not added together.

**Important:** Every unit on a selected line belongs to this beneficiary. Use a separate sale for another buyer's units. The verification checkbox records your check; it does not perform it.

<!-- page: receipts | 9 View and print sales invoices | Cashier and Administrator -->
# 9 View and print sales invoices

**Open:** The sale completion page, **Sales History**, or **Sale Details**.

1. Open the recorded sale and select **View Receipt** or **Print Receipt**.
2. Check the branch, date, invoice reference, products, quantities, discounts and final total.
3. Review the seller tax details and VAT or Non-VAT breakdown where recorded. Beneficiary details appear when a benefit was used.
4. Choose the thermal paper width, **80 mm** or **58 mm**, to match the printer.
5. Select the print control. In the browser print dialog, select the correct printer and matching paper size. Turn off browser headers and footers.
6. Use **Save as PDF** in the print dialog when a file copy is required.

**Expected result:** A printed or saved copy of the existing sale. Reprinting does not reduce stock, create a new transaction or allocate a new invoice serial.

## Invoice references and registered serials

The application always has a transaction reference. When a registered invoice range is configured, checkout also allocates the next serial from that range. Failed transactions do not intentionally consume a serial, and concurrent checkouts use the shared sequence safely.

With an unconfigured registered range, the receipt uses its existing transaction reference. **Not configured** and **Not recorded** identify missing setup or historical information; they are not actual seller registration values. Ask the administrator to resolve them before relying on the document for registered invoicing. Chapter 29 covers setup.

## Historical documents

Saved item names, generic names, strengths, tax treatment and applicable pricing details support later review. Older records can lack these fields. Branch address and contact information are maintained through branch records, so review business identity changes carefully when reprinting older documents.

A refund has its own refund slip and is also visible in transaction history. A reprinted original receipt should not be mistaken for evidence that a refunded amount is still payable.

<!-- page: held-sales | 10 Hold resume and discard a sale | Cashier and Administrator -->
# 10 Hold resume and discard a sale

**Open:** The POS cart, **Held Sales**, or the Held sales section of **My Day**.

Use a held sale when a customer temporarily leaves the counter or an order needs further checking. A held sale saves a cart for its owner and branch; it does not collect payment, reserve inventory or create an invoice.

## Hold an order

1. Check the cart and make sure the order has not already completed.
2. Choose **Hold** and enter an identifying label when requested.
3. Confirm that the order appears in **Held Sales**.
4. Continue with the next customer's cart.

## Resume an order

1. Complete, hold or clear the current cart first. Do not mix two customers' items.
2. Open **Held Sales** or find the order under My Day.
3. Select **Resume** or **Resume sale** for the correct order.
4. Recheck product quantities, current selling prices, stock, automatic promos and beneficiary information.
5. Complete payment using the normal checkout procedure.

**Expected result:** The held order returns to the active cart with current information. A product may have become unavailable, and its previous quoted price or promo may no longer apply.

## Discard an abandoned order

Select **Discard** for the correct held sale and confirm the action. This removes the saved cart, not a completed invoice. If money was already collected for a recorded sale, use the applicable refund or exchange procedure instead.

Held sales remain saved after sign-out in the current cashier workspace. Another cashier cannot resume your held orders. Resolve them before closing your shift. An administrator closing for an unavailable cashier may explicitly discard that cashier's held carts with a recorded reason.

**Important:** Never use a new held order to replace an uncertain checkout until **Check Sale Status** or Sales History confirms what happened to the original attempt.

<!-- page: sale-details | 11 Find and review a recorded sale | Cashier and Administrator -->
# 11 Find and review a recorded sale

**Open:** **Sales History**.

1. Apply the date range and available branch or status filters.
2. Search for the invoice, cashier or other displayed transaction details. Clear quick filters if the sale is missing.
3. Choose **View Details** for the intended sale.
4. Compare its final total, invoice number, status, sale date, branch and cashier with the customer's document.
5. Review the payment method, reference, amount received, change and notes.
6. Inspect purchased products, quantities and deductions. Where a benefit was saved, review the beneficiary name, ID/reference and selected treatment.

**Expected result:** You identify the exact transaction before printing, refunding, exchanging or requesting a correction. Cashiers are limited to sales in their assigned branch; administrators have broader review access.

| Status or action | Meaning |
| --- | --- |
| Completed | Recorded sale that may still have refundable quantities |
| Partial Refund | Some quantities have been refunded; review the remaining quantities |
| Refunded | All refundable quantities have been returned through refund records |
| View Receipt or Print Receipt | Open a copy of the existing invoice |
| Refund or Process Refund | Start a return for selected remaining quantities |
| Exchange Product | Review a return and replacement settlement |
| Correct or Correct Sale | Administrator action for eligible completed sales |

Use the invoice and sale status together. A customer can present an old receipt after a partial return; the remaining quantity in the system takes priority over the original printed quantity when determining what can be returned again.

Preserve beneficiary and payment information as business records. Do not share sale detail screens unnecessarily or include unrelated customers' information in support screenshots.

**Important:** Choosing an action may open a form without saving anything. For refunds and exchanges, read the final totals and confirmation step before submitting the transaction.

<!-- page: refunds | 12 Process a customer refund | Cashier and Administrator -->
# 12 Process a customer refund

**Open:** **Sales History → Refund**, or **Sale Details → Process Refund**.

**Before you begin:** Verify the original transaction, the actual returned goods and the business's return authorization. An open shift in the sale's branch is required for the operator processing the refund.

1. Confirm that the invoice is Completed or Partially Refunded and review its prior returns.
2. Choose the **Refund payment method** that will actually repay the customer.
3. Enter a whole-number quantity for each returned item. Leave **0** for items not being returned. **Refund All** selects the remaining refundable quantities.
4. Select the correct return condition for each selected line.
5. Enter the refund reason and review the displayed total. Refund values use the remaining original amount paid after applicable deductions, not today's catalog price.
6. Submit the refund once, confirm success and provide the refund slip. Carry out the actual payout through the recorded method.

| Return condition | Available stock effect |
| --- | --- |
| Quarantine | Recorded outside available stock, pending physical inspection |
| Resellable | Returned to available stock when the system's stock checks allow it |
| Damaged | Recorded as returned; not added to available stock |
| Expired | Recorded as returned; not added to available stock |

**Expected result:** Refund items and the payout are recorded, remaining refundable quantities decrease, and the sale status becomes Partially Refunded or Refunded. The processing shift records the money movement; an earlier closed shift is not rewritten.

Cash payouts cannot exceed recorded expected drawer cash. If additional physical cash is authorized and supplied, record it as cash added before the payout. Never select GCash or Card merely to bypass a shortage.

**Important:** Use separate refund submissions when units of the same item have different physical conditions. Quarantine is a recorded condition, not a separate warehouse inspection workflow. Keep those goods physically segregated and obtain inspection instructions before any later stock addition. A printed return-policy statement does not itself enforce a refund deadline in the software.

<!-- page: exchanges | 13 Exchange returned products | Cashier and Administrator -->
# 13 Exchange returned products

**Open:** **Sales History → Exchange Product**.

**Before you begin:** Open a valid shift in the original selling branch. Verify the customer's return and the goods. The ordinary POS cart is kept separately from this exchange workflow.

1. Under **Items being returned**, enter quantities no greater than the remaining returnable amounts and select the condition for each line.
2. Under **Replacement products**, find a different available product and enter the replacement quantity. Current branch prices apply.
3. Under **Reason and settlement**, enter the exchange reason and select the actual payment or payout method.
4. Review any available promotional discount on replacements. An original discount may be selected when still valid; retained original items can affect its minimum and remaining fixed amount or cap.
5. Enter the payment or payout reference when a GCash or Card difference is involved.
6. Select **Review exchange**. No exchange has been saved at this stage.
7. Check the returned credit, replacement total, conditions and exact settlement. Tick the confirmation and select **Confirm exchange** once.

| Review result | What happens financially |
| --- | --- |
| Collect from customer | Replacements cost more than the allowed returned credit |
| Pay back to customer | Returned credit exceeds the replacement total |
| Even exchange | No additional collection or payout |

**Expected result:** The system links the original return with its replacement transaction, updates eligible stock movements, and records only the actual difference collected or paid out as new collections. Returned credit is not a second payment.

**Important:** The exchange screen's discounts are promotional offers; do not assume that the POS automatic-promo or beneficiary flow is repeated there. For Senior Citizen or PWD treatment on replacement items, refund the returned items and create a new POS sale with beneficiary verification.

If stock, prices, the returnable quantity or the original sale changes after the review, reopen and review the exchange again. Do not use a second unrelated invoice to hide an unrecorded settlement.

<!-- page: corrections | 14 Correct an eligible sale | Administrator -->
# 14 Correct an eligible sale

**Open:** **Sales History → Correct**, or **Sale Details → Correct Sale**.

A correction is an administrative change to an eligible completed sale. Use Refund for an actual customer return and Exchange Product for the dedicated replacement workflow. Sales with refunds or exchange links are not eligible for this correction process.

1. Open the exact sale and verify the invoice, branch, original quantities and payment details.
2. Change only the fields that need correction: allowed quantities, payment information, reference or notes.
3. Review the recalculated amounts and the collection or payout difference.
4. If the correction changes money or quantities, have an active shift in the same branch. Collect the additional payment or repay the customer using the actual method, and confirm the exact difference as required.
5. Record an explanatory note or reason and save once.
6. Review the corrected Sale Details, stock movement and current-shift activity.

**Expected result:** Permitted sale and stock changes are recorded with an audit trail. A closed shift's saved totals remain unchanged; a later financial difference belongs to the shift that processes it.

## Restrictions to respect

- A completed sale must keep at least one item. Use Refund to reverse the whole purchase.
- A payment method can be changed only within the sale's original open shift. For an older reconciled sale, document the issue and use an appropriate refund and new sale when money must change.
- Quantities on an invoice with automatic product or category promo snapshots cannot be changed here. Refund affected items and create a new POS sale so current promos are checked.
- Changes to discounted or beneficiary sales can be restricted by their saved pricing context. Follow the displayed instruction rather than replacing original deductions manually.
- Increasing quantities requires active, unexpired branch stock. A stale form, changed discount or concurrent refund requires a fresh review.

**Important:** Correcting notes is different from correcting money. Do not change historical payment labels to make today's drawer match a count. Investigate and record the actual discrepancy instead.

<!-- page: my-day | 15 Read My Day correctly | Cashier and Administrator -->
# 15 Read My Day correctly

**Open:** **My Day**. It shows the signed-in operator's activity for the displayed branch and selected calendar dates, rather than all cashiers' branch sales.

1. Check the user, branch and **From / To** dates.
2. Select **Today**, **Yesterday**, or a range of up to 31 calendar days, then **Show dates**.
3. Read the summary cards and their descriptions.
4. Review Payment breakdown, Products sold and returned, Recent activity and Your recent shifts. Use each table's search, sorting and pagination.
5. Open invoice links or a shift's **Review** action when an amount needs investigation.

| Figure | Meaning |
| --- | --- |
| Sales recorded | Sale amounts after discounts and VAT relief; returns are listed separately |
| Sales transactions | Each recorded sale counted once; a refund does not erase its original transaction count |
| Units sold | Original sale units; replacement and returned units are distinguished in product movements |
| Refund payouts | Refund money processed during the selected period |
| Net collections | Payment receipts less payouts, excluding returned exchange credit |
| Discounts on new sales | Recorded sales deductions, with VAT relief identified separately |
| Expected cash now | Active shift's physical cash expectation, including opening float and cash movements |

The tables refresh while My Day is visible and connected. The live summary normally refreshes about every 30 seconds. Check **Updated** and refresh if another window changed your shift status.

**Important:** Calendar dates and shift boundaries are different. A shift opened yesterday can still be open today. My Day for Today includes today's events; the shift detail includes that shift's own events across midnight.

An older refund may have an unknown payment method or no event identifier. The application identifies these limitations instead of inventing a method or transaction count. Review those records before comparing cash, GCash or Card totals.

<!-- page: cash-movements | 16 Record and reconcile drawer cash | Cashier and Administrator -->
# 16 Record and reconcile drawer cash

**Open:** **My Day → Cash added or removed**. **Before you begin:** Have an active shift and a physical reason for the movement, such as an approved float top-up or safe drop.

1. Choose **Cash added** or **Cash removed**.
2. Enter the actual positive peso amount.
3. Enter a clear **Reason**, including the relevant reference when useful.
4. Select **Record cash movement** once.
5. Check the updated expected cash and confirm that the physical money has moved as recorded.

**Expected result:** Cash added increases expected drawer cash. Cash removed decreases it. Neither entry increases or reduces sales revenue. Do not enter GCash or Card settlement here.

## Drawer equation

**Expected cash = opening cash + cash received after change - cash payouts + cash added - cash removed**

| Training example | Effect | Running cash |
| --- | --- | --- |
| Opening physical count | + PHP 500.00 | PHP 500.00 |
| Cash payments retained after change | + PHP 2,400.00 | PHP 2,900.00 |
| Cash refund payouts | - PHP 200.00 | PHP 2,700.00 |
| Float top-up | + PHP 100.00 | PHP 2,800.00 |
| Safe drop during shift | - PHP 1,000.00 | PHP 1,800.00 |

If the closing physical count is PHP 1,790.00, the cash difference is **PHP -10.00**, a shortage. If PHP 500.00 is left for the next shift, **PHP 1,290.00** is removed at closing. GCash or Card takings are reconciled separately and do not explain physical cash in this example.

**Important:** Record actual cash movements when they happen. Do not create a fictitious cash addition to remove a shortage from the closing report. Explain a real overage or shortage in the closing note and submit it for review.

<!-- page: close-shift | 17 Close a shift and hand over | Cashier and Administrator -->
# 17 Close a shift and hand over

**Open:** **My Day → Count and close shift**.

**Before you begin:** Finish transactions, establish the result of uncertain checkouts, and resolve held sales. Stop using the physical drawer while it is being counted.

1. Review the shift's drawer reconciliation and payment breakdown.
2. Count the actual notes and coins, then enter **Cash counted**.
3. Review the displayed overage or shortage. Enter a closing reason when there is a difference.
4. Enter **Cash left for the next shift**. This cannot exceed the counted cash; the remainder is recorded as removed at closing.
5. Select a named **Handover to** cashier when responsibility is being transferred. Choose **No named handover** when ending the day without reserving the drawer for a person.
6. Review the note, count and retained amount, then select **Save count and close shift**.
7. Confirm Closed status, print the shift report as needed, complete the physical handover and sign out.

**Expected result:** The closing summary is saved. The drawer becomes available subject to any named handover reservation. The next cashier must count the cash and start their own shift.

## Changes during the count

If a new sale, refund or cash movement arrives while the closing form is open, the closing revision can become stale. Refresh, check the new activity and recount before saving. Do not keep submitting the old count.

## Administrator closing for another user

An administrator may close an unavailable cashier's shift, but must provide a reason and physically verify the count. Outstanding held carts require an explicit discard confirmation with the recorded reason. Coordinate with the cashier so their drawer is not still in use.

**Important:** Closing cash removal is recorded by the closing process. Do not also enter the same removal as a separate cash-out movement. Signing out, printing a report or deleting a test-history file does not replace the normal count-and-close procedure.

<!-- page: shift-review | 18 Review shifts and print reports | Administrator and shift owner -->
# 18 Review shifts and print reports

**Open:** Administrator **Cashier Shifts**; a cashier opens their own shift from **My Day**.

## Review a closing

1. In Cashier Shifts, review **Shared branch drawers** for active use or pending handovers.
2. Use **Shifts and cash differences** to find the cashier and shift. Search, sort dates or amounts, or select **Closings awaiting review**.
3. Select the row's **Review** button. The shift number is a reference; use the action button to open its details.
4. Compare opening cash, payment receipts and payouts, additions, removals, counted cash, closing difference and cash left.
5. Check notes, product movements and activity. Investigate discrepancies using sale links and the actual payment records.
6. For a closed shift, enter a manager review note and select **Record manager review**.

**Expected result:** The closing review records the administrator and note. Review documents a check; it does not change the counted amount or settle a discrepancy automatically.

## Review exceptional movements

Under **Movements awaiting review**, inspect a refund, exchange, correction or cash movement. Enter a meaningful review note and choose **Reviewed**. The page loads the latest 200 unreviewed movements; use older-movement navigation for earlier batches.

If a named handover must be released, open its shift, record **Release handover reason** and submit **Release named handover**. Do not bypass the reservation by using someone else's account.

## Print the shift record

Open **Print shift report**. Use **Print summary** for reconciliation and sign-off, or **Print with details** for products and complete shift activity. Use A4 paper and review the preview. The printed details include the complete shift ledger, rather than only the on-screen recent-activity sample.

An open shift prints as **Interim**. A closed shift prints saved final closing totals. A printout alone does not close or approve a shift; signatures and manager review are separate steps.

<!-- page: product-create | 19 Create products and use the catalog | Cashier and Administrator -->
# 19 Create products and use the catalog

**Open:** **Products**. Cashiers see their assigned branch; administrators can filter branches.

## Prefer an existing product identity

Before creating a product, search by product name and SKU. A SKU should identify the same product, strength, pack or selling unit throughout the catalog. Avoid duplicates that split stock and sales history.

Cashiers can select **Add from Existing Products**, find an active shared catalog product not yet assigned to their branch, and choose it. Review its identity, then enter the branch's stock, cost, selling price, reorder level and expiry. Save the branch assignment using the form's action.

**Expected result:** The branch receives an inventory row for the existing product identity. Initial units are logged as stock received. Another branch's physical stock is not transferred by this action.

## Create a genuinely new product

1. Choose the product creation action on Products.
2. Enter the product name, category, SKU and selling unit.
3. Complete medicine type, generic ingredient, strength, tax classification and benefit eligibility from verified product information.
4. Enter the branch selling price, cost, initial stock, reorder level and expiry. Selling price must not be below cost in the product form.
5. Select manufacturer and supplier information when available.
6. An administrator selects the target branch. A cashier's branch is locked to their assignment.
7. Select **Save Product** or **Add to My Branch** and verify the saved branch row.

If an entered SKU already belongs to a matching active product, the application may reuse that identity rather than create a duplicate. Conflicting identity details, inactive products or records in Trash require administrator review.

**Important:** Enter quantities in the product's selling unit. If the item is sold per tablet, a box delivery must be converted to the correct tablet count before entry. There is no automatic purchase-order, supplier-credit or interbranch transfer created by adding a catalog assignment.

<!-- page: product-edit | 20 Maintain product details and classifications | Administrator -->
# 20 Maintain product details and classifications

**Open:** **Products → Edit** for the intended branch row.

1. Confirm the editing branch and the exact product identity.
2. Review shared details: name, category, SKU, unit, manufacturer, supplier, generic name, strength and classification fields.
3. Review branch values: selling price, cost, stock, reorder level, expiry and active/inactive status.
4. If changing a stock count, enter the final physical quantity and **Reason for Stock Change**. An absolute count replaces the old quantity; it is not an added delivery.
5. Save, then verify the product in that branch and check Stock Logs when the quantity changed.

| Field | What to record |
| --- | --- |
| Medicine type | Branded, Generic, Non-medicine, or Not specified until confirmed |
| Generic medicine name | Ingredient name, independent of any brand name |
| Strength | Actual strength and basis, such as 500 mg or 125 mg / 5 mL |
| Tax classification | VATable, VAT-exempt, Zero-rated, or Not specified pending confirmation |
| Senior Citizen/PWD medicine benefit | Eligible only after confirmation; otherwise Not eligible or Not confirmed |
| Reorder level | Branch threshold used to identify low stock |
| Status | Whether this branch inventory is available for normal use |

A branded medicine can also have a generic ingredient name. Generic medicine classification and beneficiary eligibility are independent fields. Supplies should use **Non-medicine**, rather than being labeled Generic solely because they have no brand.

Shared product identity fields affect the catalog across branches. Branch stock and pricing belong to the selected branch row. Check both scopes before saving, especially when changing a name or selling unit already used in history.

**Important:** The current inventory model stores one expiry date per product per branch. Do not replace that date with a later delivery date while older units remain without a documented physical stock decision. Use Chapter 22 for actual replacement or removal. Changing an expiry label does not dispose of goods or create independent batch balances.

<!-- page: stock-adjustments | 21 Add remove and count stock | Cashier and Administrator -->
# 21 Add remove and count stock

**Open:** **Products**, at the intended branch and product row. Cashiers are restricted to their own branch.

## Add stock

1. Count and inspect the actual incoming quantity in selling units.
2. Verify the existing branch expiry and the incoming goods. If expiry handling requires replacement, use the dedicated expiry workflow rather than relabeling old stock.
3. Select **Add stock** in the row's stock action.
4. Enter the quantity being added and a clear reason or delivery reference.
5. Save once and compare the new balance with the physical quantity.

## Remove stock

Select **Remove stock**, enter the quantity physically leaving available stock, and record a specific reason. Review the branch and quantity before submitting. The removal cannot exceed current stock. For expired goods or supplier returns, prefer the expiry resolution action because it captures the disposition explicitly.

| Operation on a starting balance of 20 | Entry | Result |
| --- | --- | --- |
| Add stock | 5 units received | 25 units |
| Remove stock | 5 units removed | 15 units |
| Administrator absolute count in Edit Product | Final counted stock of 5 | 5 units |

## Perform a physical count

An administrator can use **Edit Product** to enter the final counted stock and a stock-change reason. Coordinate the count so new sales or receiving do not invalidate it. If the application says stock changed while the page was open, refresh and reconcile the latest movements before submitting a new count.

**Expected result:** The branch balance changes, the product's overall recorded balance is synchronized, and Stock Logs show the movement, previous stock, new stock, operator and reason.

**Important:** These forms record inventory quantities. They do not pay a supplier, create an accounts-payable balance or automatically maintain multiple manufacturer lots. An accidental stock adjustment is corrected through a documented opposite adjustment or verified count, not by deleting stock history.

<!-- page: expiry-actions | 22 Resolve expired and replaced stock | Cashier and Administrator -->
# 22 Resolve expired and replaced stock

**Open:** **Expiry Report**, then the product's **View history**, **Replace stock** or **Dispose / Return** action.

Check the branch, product, current units and recorded expiry. Separate affected goods physically before recording a resolution. Cashiers may replace or remove stock in their branch; only an administrator may deactivate branch inventory.

## Dispose or return to the supplier

1. Choose **Dispose / Return**.
2. Select **Disposed** or **Returned to supplier** to describe what happened.
3. Enter the actual quantity removed, no greater than current stock.
4. Enter the reason or supplier reference and confirm the product, branch and quantities.
5. Select **Confirm removal** and verify the remaining balance and resolution history.

Remaining stock keeps its existing expiry. A supplier return is an inventory record only; a supplier refund or credit is handled outside this workflow.

## Replace with new stock

1. Choose **Replace stock** or **Replace with new stock**.
2. Confirm that **all existing units** in this branch row are being removed and separated from the incoming stock.
3. Select the old stock's disposition.
4. Enter the new quantity and a valid future expiry date, then add a reason/reference.
5. Confirm the replacement and check the separate stock-out and stock-in entries.

**Expected result:** Old units leave the available balance before new units are received. The row now holds the new quantity and expiry; current selling and cost prices remain unchanged.

## Deactivate in this branch

An administrator may use **Deactivate in branch** to stop normal sales without changing the recorded units. This does not physically dispose of goods and does not deactivate the product in other branches. Reactivation is handled through Edit Product after review.

**Important:** Replacement is not an additive delivery. If 12 old units remain and 30 new units are entered, the replacement balance is 30, not 42. Reopen the record if stock, expiry or status changed while the form was open.

<!-- page: inventory-reports | 23 Review stock logs and expiry reports | Cashier and Administrator -->
# 23 Review stock logs and expiry reports

## Stock Logs

**Open:** **Stock Logs**. Set the available branch, action type, date and search filters, then review the loaded records. Cashiers see their assigned branch.

Use the product and SKU, branch, operator, recorded time and remarks to trace a movement. **Stock in** increases quantity, **Stock out** decreases it, and a recorded adjustment can describe a non-quantity action. Compare **Previous stock** and **New stock** rather than treating every positive quantity field as an increase.

Sales, resellable refunds, exchanges, initial stock, manual adjustments and expiry resolutions can produce log records. A signed change in the PDF is the new balance minus the previous balance. Receipt or delivery references help link the movement to its source.

Do not delete a movement to conceal an error. Correct the actual balance using an authorized action with a reason. Use **Export PDF** or the spreadsheet export for the applied filter scope, as described in Chapter 28.

## Expiry Report

**Open:** **Expiry Report**. Select the relevant branch and expiry status when filters are available.

| Status | How to use it |
| --- | --- |
| Expired | Inspect and remove affected goods from sale; use a documented resolution |
| Near expiry | Prioritize physical review and stock planning; the current default horizon is 30 days |
| Good or later expiry | Recorded expiry is outside the near-expiry window; still check physical goods |
| Missing expiry | Verify the product and record the correct information through an authorized edit |

Select **View history** to inspect recent expiry resolutions. **Replace stock**, **Dispose / Return** and administrator **Deactivate in branch** lead to the workflows in Chapter 22.

**Important:** The report uses recorded branch inventory dates. It is not a physical inspection, independent manufacturer-lot register or proof that every unit has the same date. Exported source references are internal receiving identifiers where available; **Not recorded** means the system does not have that value.

<!-- page: supporting-records | 24 Manage categories suppliers and Trash | Administrator -->
# 24 Manage categories suppliers and Trash

## Categories

**Open:** **Categories**. Create a clear category name, save it, and use **Edit** when it needs correction. Assign products to the category through product creation or editing.

Categories affect browsing, reporting and category promos. Before renaming or moving products, review any active offers targeting that category. A category action does not itself move physical stock.

## Suppliers

**Open:** **Suppliers**. Create or edit the supplier's name, contact person, phone, email, address and status as provided by the supplier. Select the supplier on a product when it is relevant to that catalog identity.

Supplier details support identification and stock records. They do not create purchase orders, payment schedules, supplier credits or a payable ledger. Record actual delivery and supplier-return references in the appropriate stock reason.

## Move and restore records in Trash

1. Find the exact product, category, supplier or discount record.
2. Choose its delete or Trash action and review the confirmation.
3. To reverse an ordinary Trash action, open that module's **Trash**, find the record and choose **Restore**.
4. Verify its previous status and dependencies before expecting it to be available again. Restored does not always mean Active.

For products, Trash actions on a branch row affect **the selected branch only**. Recorded stock is retained; moving a product to Trash does not dispose of goods. Other branches' rows remain separate. Some older shared product deletions have a specific legacy restore action and require administrator review.

## Permanent removal from the workspace

The permanent-delete action first requires the record to be in Trash. In this application it hides retained data from normal use and Trash; it does not erase the database history needed by prior transactions. It is not a stock write-off and cannot normally be reversed through Restore.

**Important:** Review the record, branch and linked business activity before confirming a permanent action. Use expiry disposal or documented stock removal for physical goods; use account deactivation, rather than catalog Trash, to stop a user's access.

<!-- page: branches-users | 25 Maintain branches users and audit records | Administrator -->
# 25 Maintain branches users and audit records

## Create or update a branch

**Open:** **Branches**. Enter the branch name, code, business address, contact number and status. Save and verify the details. The branch address and contact can appear on its sales receipt.

For a new operating branch, enable its shared drawer under **Cashier Shifts**, assign appropriate cashier accounts and create its product inventory. Creating the branch alone does not supply stock or open a drawer.

Before deactivating a branch, close its shifts and reassign or deactivate its active cashiers. The application blocks branch deactivation when active cashier accounts remain assigned. Coordinate physical inventory and operations before making the change.

## Create a user

1. Open **Users** and choose its create action.
2. Enter the full name, a unique username and a password of at least eight characters. Confirm the password.
3. Choose **Cashier** or **Administrator**. For a cashier, select a valid active branch; administrators are not assigned like cashiers.
4. Save, verify the account's status and give the credentials privately to the named user.
5. Have the cashier confirm their displayed branch and Start Shift destination on first use.

## Maintain access

Use Edit to change account details, branch or role. Use the password action to set and confirm a replacement password. Use **Deactivate** when access should stop and **Activate** after review when restoring it.

Close or hand over an open drawer before reassigning or deactivating its owner. An account change can revoke an existing session but does not physically count or settle the drawer. The system protects against deactivating your own account or removing the last active administrator.

## Review Activity Logs

Open **Activity Logs** and filter by the relevant user, dates or activity text. Use these records to trace logins and administrative actions alongside sales, stock and shift records. Audit text supports investigation; it is not a substitute for inspecting the associated transaction and actual payment.

<!-- page: discount-setup | 26 Create and maintain discount rules | Administrator -->
# 26 Create and maintain discount rules

**Open:** **Discounts → Create** or the applicable rule's **Edit** action.

1. Enter a recognizable **Discount Name** and description.
2. Set **Cashier Availability** to Available when dates allow, or Not available.
3. Choose **Percentage (%)** or **Fixed Amount** and enter the value. A percentage must not exceed 100.
4. Enter a **Maximum Peso Discount** when the rule needs a cap.
5. Choose **Apply Discount To**: all products in the cart, one category, or one specific product. Select the category or product when needed.
6. Set any start and end dates and **Minimum Matching Purchase**.
7. Save, then test the intended basket in POS without completing an unwanted live sale.

**Expected result:** Active product and category offers can be applied automatically in POS. An active all-products offer remains a selectable manual offer in its Discount dropdown. The special Senior Citizen/PWD choices use beneficiary verification and do not require creating an ordinary 20% promo rule.

## Verify the scope before using the offer

| Training rule | Expected check |
| --- | --- |
| 10% off one product | Only that product's matching spend is reduced |
| PHP 50 off a category, minimum PHP 500 | Matching category spend must meet PHP 500; the PHP 50 is allocated once |
| 15% off a category, cap PHP 100 | Total discount for that category rule is limited by the cap |
| All-cart fixed offer | Cashier selects the manual offer and reviews the recalculated basket |

Use the availability/status control to stop future use. Changing or disabling a rule does not rewrite saved completed invoice deductions. Category or product changes can also affect future matching, so verify the relevant catalog assignment.

**Important:** Do not represent an ordinary promotion as a verified statutory beneficiary transaction. Keep the beneficiary workflow separate, and review overlap through the POS quote rather than adding two reductions manually.

<!-- page: reports | 27 Review performance and forecasts | Administrator -->
# 27 Review performance and forecasts

## Dashboard

**Open:** **Dashboard** for a business overview. Check the displayed branch scope and the period labels on each card, chart or list. Daily net sales, longer sales trends, recent sales, stock alerts and branch comparisons do not all use the same historical window.

Follow stock and expiry alerts to their underlying records. A forecast or an alert is a review prompt, not an automatically created stock order or physical inventory movement.

## Sales reports

1. Open **Reports** and choose the branch or **All Branches**.
2. Under **Sales report filters**, choose Daily, Weekly or Monthly grouping and the start and end dates.
3. Select **Apply report filters**.
4. Read the applied period and review net sales, discounts, VAT relief/refunds where shown, cost of goods, gross profit, transaction measures and trends.
5. Review payment movements separately when reconciling collections. A refund can be paid on a different date from the original sale.

Sales reporting describes the sale and its remaining value; payment movement reporting describes money received or paid during its own period. Neither equals drawer cash without opening float and cash-movement adjustments. Gross profit is not final business profit; operating expenses such as rent and payroll are not deducted by this sales calculation.

## Update the forecast

1. Under **Sales forecast settings**, choose the forecast grouping and historical **Use sales from / to** dates.
2. Select **Update Forecast** to apply and save the seven future sales periods and low-stock product estimates.
3. Review **Sales forecast**, **Restock Suggestions** and **Saved records**, including their basis dates and branch scope.

The forecast is calculated from recorded history and its trend. Restock suggestions use a 30-day coverage assumption; they do not include a separately configured supplier lead time. Sparse history, unusual returns or temporary promotions can change the result substantially.

**Important:** Review stock on hand, expiry, actual demand, supplier lead time and budget before placing an order outside the system. Applying a sales report filter is separate from saving new forecast settings.

<!-- page: exports | 28 Export reports and print documents | All users within their role -->
# 28 Export reports and print documents

Apply page filters before exporting. Review the branch and date information on the page, then use the export control appropriate to the task.

| Document | Output and scope |
| --- | --- |
| Sales Reports | Full PDF or Summary PDF, using applied sales and forecast settings |
| Sales Reports spreadsheet | Excel CSV for the applied report scope |
| Stock Logs | PDF or spreadsheet export using applied stock-log filters |
| Expiry Report | PDF or spreadsheet export using selected branch/status scope |
| Sales invoice or refund slip | Browser print; choose 80 mm or 58 mm thermal width |
| Shift report | A4 Print summary or Print with details |

## Choose the right report

**Full PDF** includes the report's detailed sections. **Summary PDF** is intended for a compact management overview. Sections explicitly labeled Top 10 or Latest 10 remain limited to those records even in a full report; they are not an invoice-by-invoice account extract.

Sales PDFs separate original sales, discounts, VAT relief and refunds in reconciliation. An Other adjustments line, when shown for older data, identifies a difference that needs review rather than silently forcing a match. Stock-log PDFs retain long notes in a continuation section when necessary. Inventory source identifiers are internal references where available, not invented manufacturer lot numbers.

## Print or save

1. Open the PDF or printable document and check its title, branch, dates, generated time and page count.
2. For reports, use A4 and the orientation indicated by the document; tabular PDF exports use landscape layouts.
3. For receipts, match the chosen thermal width to the printer's paper setting and disable browser headers/footers.
4. Review the preview before printing all pages. Use Save as PDF for a file copy.

**Important:** Browser quick search and pagination usually control on-screen rows only. Exports use their own applied filter scope. Excel CSV is a text-based spreadsheet file, not a formatted XLSX workbook. Keep exported beneficiary, payment and business data in approved storage.

<!-- page: invoice-setup | 29 Configure invoices and numbering | Administrator and technical custodian -->
# 29 Configure invoices and numbering

Invoice configuration is maintained separately from browser **Interface Settings**. Coordinate registration and series details with the business's responsible administrator before enabling a registered invoice range.

## Check the business identity

Maintain the selling location's address and contact through **Branches**. Receipt headers use the branch's details. Business display-name and receipt-message defaults are maintained in the application's `app/Libraries/SystemSettings.php`; there is no business-settings editor on the current Interface Settings page.

Have the technical custodian back up and edit `app/Config/ReceiptInvoice.php` for these values:

| Setting | Purpose |
| --- | --- |
| tin | Actual seller taxpayer identification number |
| taxStatus | Seller setting: vat or non_vat |
| vatRate | Configured VAT rate; the beneficiary calculation supports the 12% VAT setting |
| serialPrefix | Prefix of the actual registered series |
| serialStart and serialEnd | Authorized number range; serialEnd of 0 leaves registered serial allocation disabled |
| serialDigits | Zero-padding width for the numeric portion |

## Verify the setup

1. Confirm actual seller details and the authorized range from the business records.
2. Back up configuration and have the custodian enter the values without inventing registration information.
3. Verify on a test environment that a new invoice shows the intended identity, tax breakdown and serial format.
4. Confirm how the business will handle an exhausted range before operating it live.

The serial counter is shared by its configured prefix. Reprints retain their serial; failed transactions roll back allocation. An exhausted range stops new allocation until the correct authorized configuration is supplied.

**Important:** Do not reset counters or reuse a series to clear shift history or remove a gap. Confirm registered invoice setup separately; this manual describes application behavior and does not certify registration or invoice compliance.

<!-- page: recovery | 30 Create backups and restore data | Administrator -->
# 30 Create backups and restore data

**Open:** **Backup & Restore**. If it is not linked in your navigation, use your application's base address followed by `/admin/backup-restore` while signed in as an administrator.

## Create and keep a backup

1. Select **Create Backup** and wait for success.
2. Find the new stored backup, check its date and size, then choose Download.
3. Keep an off-device copy in approved storage and record which site and revision it belongs to.
4. Confirm that the downloaded file is present and nonempty. Keep application files and configuration backups separately.

The built-in database backup uses **.pmbak**. It stores database records and structure metadata for compatibility checking; it is not a copy of application code, configuration or uploads. Deleting a stored backup removes that server copy, not the current business records.

## Restore a compatible backup

1. Schedule a maintenance window, pause POS/stock work and notify users. Make a fresh backup before recovery.
2. Choose a stored .pmbak backup or upload the intended .pmbak file. Confirm the site and backup date.
3. Enter the **current administrator password** and type **RESTORE** in the confirmation field.
4. Select **Restore Database** and wait. The application validates compatibility and saves a safety copy of the current data before replacing it.
5. Follow the success message. You may need to sign in using an administrator account present in the restored backup.
6. Verify branches, users, product balances, recent invoices, invoice numbering and drawer state before resuming operations.

**Expected result:** Compatible database data is restored. Current records are replaced, not merged. Restored open shifts and counters reflect the backup date and must be reconciled with actual current operations.

**Important:** Only .pmbak files are accepted by the built-in restore; its maximum supported size is 256 MB, subject to the smaller upload limit displayed by the server. SQL exports, upgrade patches and cleaned full SQL backups belong to a separate phpMyAdmin/technical recovery procedure. An existing-database patch and a full backup are not interchangeable. Never import a destructive reset as part of an ordinary day-end close.

<!-- page: checklists | 31 Daily operating checklists | Cashier and Administrator -->
# 31 Daily operating checklists

## Before opening the counter

- Confirm your account, branch, network connection and printer.
- Review relevant low-stock and expiry alerts; inspect affected goods.
- Count the actual drawer and agree any handover discrepancy.
- Start your own shift and verify the operating branch.
- Check current promos and confirm that any required invoice setup is available.

## For each sale

- Match the physical item, strength, unit and quantity to the cart.
- Review automatic promos and any selected manual or beneficiary treatment.
- For beneficiary treatment, verify documents and select only the eligible exclusive-use lines.
- Confirm the actual payment method, settled amount, reference and change.
- Submit once; establish the outcome before retrying an uncertain checkout.
- Check completion and provide the correct invoice or receipt copy.

## Before closing or handing over

- Finish or resolve pending checkouts and held orders.
- Check refunds, exchange settlements and documented cash movements.
- Reconcile electronic receipts separately from physical cash.
- Count the drawer; record actual cash, cash left and a reason for differences.
- Close the shift, print the appropriate report and complete the handover.
- Sign out after responsibility has been transferred or the counter closed.

## Administrator daily review

- Review closing differences and exceptional movements; record meaningful review notes.
- Investigate missing references, unknown older refund methods and unexpected balances.
- Verify stock exceptions and expiry actions against physical activity.
- Review business reports using their applied branch/date scope.
- Create and download a database backup under the site's retention practice.
- Confirm that staff access, active branches, drawer availability and invoice series remain appropriate.

These are operating checks. They do not create automatic approvals or replace any required physical inspection, payment-provider reconciliation or authorized business sign-off.

<!-- page: troubleshooting-pos | 32 Troubleshoot sales and drawer issues | All users within their role -->
# 32 Troubleshoot sales and drawer issues

## Complete Sale refreshes or shows an error

Read the POS message first. Check that your shift is open, the cart is nonempty, the branch is active and the amount paid covers the total. Verify any beneficiary selection and document confirmation. If the outcome is uncertain, use Check Sale Status and Sales History before retrying. Record the time and invoice reference if one exists.

## A product or category promo is missing

Check the exact target product/category, active status, dates, minimum matching purchase and cap. No Discount still permits automatic promos. A selected manual all-products offer uses its own pricing path. A beneficiary selection can replace an overlapping promo when it gives the better payable result. Refresh and review the quote after a rule change.

## Senior Citizen or PWD cannot be applied

Confirm seller VAT/Non-VAT setup, the product's explicit benefit eligibility and tax classification, a positive-priced selected line, beneficiary name/ID and the document verification checkbox. Branded or Generic classification alone does not enable the benefit. Refer unresolved eligibility questions to the responsible administrator/pharmacy staff.

## Drawer cannot open or close

Check whether another user holds the drawer, a handover reserves it, or the drawer is disabled. At closing, resolve held orders and enter a valid physical count and cash-left amount. A stale closing form requires a refresh and recount after checking new activity. A difference needs an explanatory note.

## Cash payout rejected

Compare the actual drawer with expected cash. Review unrecorded authorized top-ups or removals. Record money that really enters the drawer before paying a cash refund. Choose another payout method only if it is the method actually used.

## My Day differs from the shift printout

Compare calendar dates, cashier, branch and whether the shift crosses midnight. Distinguish sales, refunds and net collections from opening cash and cash movements. Check whether the report is Interim or saved Closed totals. An older refund with unknown method also affects payment comparisons.

<!-- page: troubleshooting-records | 33 Troubleshoot inventory reports and recovery | All users within their role -->
# 33 Troubleshoot inventory reports and recovery

## Product cannot be found or sold

Clear page and quick filters. Check branch assignment, active status, stock, expiry, category and Trash. An existing catalog identity can be absent from a cashier's branch until assigned there. Do not create a second SKU identity just to make the product appear.

## Stock changed while the form was open

Refresh the product or expiry record. Review sales, refunds and other stock logs that occurred after the original page loaded, then recount or calculate the actual intended addition/removal. Re-enter the action against the latest balance. Do not submit the old absolute count repeatedly.

## Refund or exchange quantities changed

Reopen the original sale and inspect earlier returns. Another operation may have consumed the remaining refundable units. Review the new allowable amount and obtain the correct settlement before submitting again.

## Export does not match the table on screen

Check the applied branch/date/status filters, the PDF report/forecast basis, and whether the browser table is quick-filtered or paginated. Top 10/Latest 10 sections are limited by design. Confirm that you applied changed report or forecast settings before exporting.

## Receipt prints too wide or cuts off

Match the application's 58 mm or 80 mm choice to the printer paper setting. Disable browser headers/footers and review scaling and margins in print preview. For shift reports use A4; tabular PDF exports may require landscape.

## Backup or restore rejected

Check the message, actual .pmbak type, file size and displayed upload limit. A restore requires a compatible current schema and the correct administrator password and confirmation. A SQL file cannot be renamed to .pmbak. Ask the technical custodian to investigate storage permissions or schema mismatch without repeatedly trying a destructive restore.

## Still unresolved

Stop only the affected task and preserve the physical goods or payment evidence. Do not clear browser recovery state, delete history or edit database balances to conceal an unexplained result. An administrator or technical custodian should review the associated records before normal operation resumes.

<!-- page: glossary-support | 34 Terms and definitions | All users -->
# 34 Terms and definitions

| Term | Meaning in Pharxmaco |
| --- | --- |
| Branch product | A product's inventory, price, cost, expiry and status for one branch |
| SKU | Catalog identifier used to distinguish a specific product identity |
| Shared drawer | Physical branch cash drawer assigned to one active shift |
| Shift | Responsibility period from counted opening to counted closing |
| Expected cash | Calculated physical cash balance before the closing count |
| Cash difference | Counted cash minus expected cash; positive is over, negative is short |
| Net collections | Actual payment receipts less payouts in the reporting period |
| VAT relief | Included VAT removed for an applicable saved beneficiary treatment |
| Automatic promo | An eligible product/category offer calculated by POS without a dropdown selection |
| Snapshot | Information saved with a transaction for later review |
| Quarantine | A returned item's recorded hold condition, outside available stock |
| Transaction reference | Application identifier used to locate a sale |
| Invoice serial | Number from a configured registered range when enabled |
| .pmbak | Built-in database backup format with compatibility metadata |

**End of manual.**
