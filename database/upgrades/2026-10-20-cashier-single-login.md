# Cashier single-login protection

This guide records the initial cashier-only update. Administrator protection and save reviews were subsequently added; use [the current deployment guide](2026-10-20-admin-login-submit-review.md) for the latest behavior and package.

20 October 2026

Each cashier account can have one active authenticated session. Another device or browser session using the same account is rejected after the credentials are verified. Cashiers with different accounts can sign in independently; the existing shared-drawer shift rules still apply. Tabs sharing one browser session remain part of the same login. Administrator logins keep their existing behavior.

## Hosting with phpMyAdmin

1. Retain a current database backup and copies of the application files being replaced.
2. Select the existing application database in phpMyAdmin. Import the entire `2026-10-20-cashier-single-login.sql` file. It creates one login-ownership table and records migration `2026_10_20_000015`.
3. Upload all `app/` and `public/` files from `build/hostinger-cashier-single-login-2026-10-20.zip` to the matching directories. The archive includes the current POS fragment files as well as the login, filters, activity script and administrator action.
4. Refresh existing cashier pages once to load the activity script. Sign in to test with two separate browser sessions. The first valid legacy session claims the account while retaining its cart; other legacy sessions must sign in again. Save/hold work before rollout.

Import the SQL **before** uploading the code. Cashier access fails closed if the required table is absent; administrator access remains available for recovery. Reimporting preserves existing active login leases and does not rewrite users, passwords, stock, sales, shifts or drawers.

## Daily use

- Sign out to release the account immediately before using another device. Signing out does not close the shift.
- A cashier lease expires after **15 minutes without activity**. Actual typing, clicks and other interaction on a visible page renew it through a lightweight request. Background My Day polling and hidden tabs do not keep it alive by themselves. A closed browser leaves no further activity and the lease expires.
- An expired or ended session cannot renew itself or release a replacement login. The old browser is rejected on its next server request. POS shows a Sign in notice and blocks further cart/checkout actions while retaining the visible draft and local checkout-recovery information until navigation. Held carts remain stored separately.
- For an abandoned device, an administrator can choose **Users → End login** for the cashier. Confirm the action after saving/holding any active cart. This revokes the old session, records an audit entry and allows a fresh login. It leaves the cashier's shift open for resumption or the existing close/handover workflow.

Login ownership is checked in the server's account filters, rather than relying on disabled UI controls. Password, role, branch and status changes continue to revoke access through account fingerprints. The activity endpoint does not rotate CSRF tokens or interfere with POS requests.

## Verification and local status

Applied locally to `pharxmaco_drugstore`; hashes confirmed that existing records in 23 application tables were preserved, and a repeated import passed. The hosted database and hosted files were not changed automatically.

42 focused PHP tests / 492 assertions passed. Fifteen JavaScript activity checks and 30 desktop/mobile POS scenarios passed. Seven isolated MySQL verification groups passed, including eight simultaneous PHP login processes producing exactly one owner, repeated-import preservation and stale-token protection. Existing inventory/expiry defects remain outside this update.
