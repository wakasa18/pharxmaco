---
title: "Login label update"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-09-29-login-label-deployment.md"
source_sha256: c123d16942194d428af3dc29ac4929e0ec4143312a0c915e1fa7db92b5cf4fde
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-09-29-login-label-deployment.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Login label update

Replace the decorative plus beside "Your daily starting point" with a sign-in line icon and the clearer label "Staff sign in". The icon is decorative, excluded from screen readers, and uses the existing light/dark colors. Its old rotation animation is removed.

No database changes are required. Login behavior is unchanged.

Upload these two files from `build/hostinger-login-label-2026-09-29.zip`:

- `public/assets/css/login.css`
- `app/Views/auth/login.php`

Where Hostinger uses `public_html` for the contents of `public/`, place the stylesheet at `public_html/assets/css/login.css`. Upload it before the view. The stylesheet cache version is `20260929-1`.

After upload, reload the login page and verify the Staff sign in label in light and dark mode. This package has not been uploaded to Hostinger.
