# Sales History filter update

Use this application-file update with the current Pharxmaco application, including the 20 October administrator login and submit-review update. No SQL import or database migration is required.

## Install

1. Back up the six matching application files on the host.
2. Extract `build/hostinger-sales-history-filters-2026-10-21.zip` locally.
3. Upload the included `app` and `public` directories into the existing application directory, preserving their paths and replacing the matching files. `README.md` and `SHA256SUMS.json` are reference files.
4. Refresh Sales History. Use a hard refresh if the previous toolbar remains cached.

The archive contains the current workspace versions of:

- `app/Controllers/Cashier/SalesController.php`
- `app/Views/cashier/sales/history.php`
- `app/Views/layouts/staff.php`
- `public/assets/js/datatables-init.js`
- `public/assets/js/sales-history.js`
- `public/assets/css/sales-history.css`

## Use

Enter **Sale date from** and/or **Sale date to**, then select **Apply**. This range queries the database, including transactions outside the initially loaded records. The end date includes the entire day. Either date can be left blank. **Clear** returns to the latest transactions and clears URL filters.

Quick search, column filters, sorting and pagination act on loaded records. The list still loads up to 1,000 sales at a time. If that limit is reached in a selected range, narrow the dates further. The small note explains which records were loaded.

Changing a date waits for Apply. Invalid or reversed dates produce a validation message. Existing linked branch, payment, status and keyword filters remain effective when applying dates; non-date linked filters retain the existing disclosure and Show all control. Cashier branch restrictions remain enforced by the database query.

## Verification

Five focused PHP tests / 34 assertions and 22 browser scenarios passed. Checks cover older records beyond the initial cap, date boundaries, invalid ranges, branch restrictions, Apply/Clear, search, sorting, pagination, column filters, empty results and responsive light/dark layouts. Other tables retain their default browser date filters.

Verification used in-memory SQLite and rendered browser fixtures. It did not modify the application database or deploy files to the host.
