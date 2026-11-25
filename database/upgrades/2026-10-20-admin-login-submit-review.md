# Administrator single login and save reviews

20 October 2026

This update extends single-login protection to administrator accounts and adds save reviews to Edit Product and New/Edit Discount.

## Install on hosting

1. Retain a database backup and copies of the files being replaced.
2. If update `2026_10_20_000015` is not installed, select the existing database in phpMyAdmin and import `database/upgrades/2026-10-20-cashier-single-login.sql`. Reimporting is safe and preserves current login leases. **There is no additional schema migration for this update.** The existing per-user login table supports both roles.
3. Upload all `app/` and `public/` files from `build/hostinger-admin-login-submit-review-2026-10-20.zip` to the matching directories. Keep the host's own `.env` and invoice configuration.
4. Refresh open pages once. Test administrator login from two independent browser sessions, then review a product edit and a discount create/edit before saving.

The local source is updated. The configured local MySQL server was unavailable during this task; no application-database import was needed or performed for this extension. Hosting was not deployed automatically.

## Login rules

Administrators and cashiers each have one active login per account. A second independent session is rejected; different accounts remain independent. Sign-out releases ownership immediately. Fifteen minutes without activity expires the lease. Actual interaction renews access; background polling does not renew it by itself. Tabs sharing a browser session share the same login.

Another administrator can use **Users → End login** for either role. Use **Sign out** for your own account. Ending a login leaves any shift open. Account changes continue to revoke sessions. Both roles now require database update 000015; apply it before uploading the code if it is missing.

## Review before saving

- **Edit Product:** Save Changes opens a list of changed fields with current and new values for the selected branch. Branch prices initialize from its inventory record. An existing unspecified tax classification stays unspecified until edited. A changed stock quantity requires a reason. With no effective changes, Confirm & save is disabled.
- **New Discount:** Create Discount opens the offer details, target, values, dates, requirements and availability. The review states whether the offer is selected manually or applies automatically to matching products/categories.
- **Edit Discount:** Save Changes lists changed rules, including target/cap removal when scope or type changes. Numeric formatting alone does not count as a change. Rejected submissions still compare against saved values.

Back to edit, close, Escape and backdrop dismissal retain the form. Confirmation sends the normal validated POST with its CSRF and concurrency fields. If values change after review opens, they must be reviewed again. Client reviews do not replace server validation or existing inventory checks.

## Verification

63 focused PHP cases / 21,007 assertions passed; 28 new review scenarios, 32 existing product overview checks, 55 management validation checks and 15 activity-script checks passed. Isolated MySQL tests ran eight competing logins for each role and produced one owner per account. These targeted checks do not resolve the earlier inventory/expiry issues or constitute a full-suite pass.
