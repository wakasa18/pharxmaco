# System audit fixes — October 4, 2026

Package: `build/hostinger-system-audit-fixes-2026-10-04.zip`.

This patch fixes failed-form password retention, protects sign-out with POST/CSRF, makes checkout/manual stock audit writes transactional, rechecks changing checkout data, validates numeric database limits and checks permanent hiding during stock adjustment.

## Files to upload

Upload these files together, preserving their folders:

```text
app/Config/Routes.php
app/Controllers/Auth.php
app/Controllers/Admin/Users.php
app/Controllers/Admin/BackupRestore.php
app/Controllers/Products.php
app/Controllers/Cashier/SalesController.php
app/Views/layouts/sidebar.php
app/Views/layouts/topbar.php
app/Views/layouts/staff.php
public/assets/css/sidebar.css
public/assets/css/topbar.css
```

Use the deployment layout already used on your hosting account. If the project's `public` contents are deployed directly into `public_html`, upload the two CSS files into `public_html/assets/css/`; do not create a second public directory just for this patch. Upload the `app` files into the existing application folder.

1. Keep copies of the existing files and deploy while users are not submitting sales or stock changes.
2. Replace all eleven runtime files together. Routes and both navigation views must match: sign-out now uses POST.
3. Refresh open pages so they load the new sign-out forms. CSS version parameters have been updated.
4. On a test copy, verify invalid login remembers the username with the password blank; sign in as both roles and sign out using the sidebar and account menu.
5. Verify a discounted checkout, a repeated checkout submission and one add/remove stock action. Check invoice totals, stock logs and activity logs. A failed save must keep stock unchanged; a failed checkout must keep the cart available for review.

No SQL import, database reset or new schema migration is needed for this patch. It assumes the existing database is already on the application's latest October 3 updates.

This package contains only the audit fixes. If your host still matches the previously supplied `app (1).zip` / `assets.zip`, also apply the earlier October 4 [different-product exchange](2026-10-04-exchange-different-products.md) and [discounted sale correction](2026-10-04-discounted-sale-corrections.md) patches. Their feature files are not duplicated here.

Validation: 145 unit tests / 21,619 assertions, application PHP syntax checks, 10 standalone JavaScript syntax checks and 20 local navigation layouts passed. The live Hostinger site and real MySQL concurrency were not exercised by these checks.
