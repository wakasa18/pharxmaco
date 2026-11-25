# Administrator multiple-login update

Administrator accounts can sign in from multiple independent browsers or devices. Each browser keeps its own session/cart, and signing out in one does not end another. The cashier-specific 15-minute lease/idle restriction no longer applies to administrators; normal application session expiry and account/password/permission checks still apply.

Cashiers retain one active login per account, 15-minute inactivity expiry, trusted-activity renewal and administrator **Users → End login**. That button now appears only for cashier accounts.

## Install on the host

This is a follow-up to the current 20/21 October application updates, including the administrator login/submit-review and Sales History updates.

1. Back up the seven matching application files.
2. Extract `build/hostinger-admin-multiple-logins-2026-10-21.zip` locally.
3. Upload its `app` and `public` files into the existing application folder, preserving paths and replacing the matching files.
4. Refresh the application; use a hard refresh if needed.

**No SQL import or migration is required.** Keep `cashier_login_sessions`: cashiers still need it. Old administrator lease rows are ignored. Existing valid administrator sessions keep their carts and discard their local legacy lease token on the next authenticated request.

The archive contains:

- `app/Libraries/CashierLoginSession.php`
- `app/Libraries/AccountSession.php`
- `app/Controllers/Auth.php`
- `app/Controllers/Admin/Users.php`
- `app/Views/admin/users/index.php`
- `app/Views/layouts/staff.php`
- `public/assets/js/cashier-session.js`

Product/discount submit reviews and the Sales History date-filter update remain in place. No application data or hosted deployment was changed while preparing this package.

## Verification

34 focused PHP tests / 263 assertions, 18 activity/notice checks and two POS session-notice scenarios passed. Verification used in-memory SQLite and rendered browser fixtures; no real accounts were logged in and no financial records were changed.
