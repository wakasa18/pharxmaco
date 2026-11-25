---
title: "Login bug fixes"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-09-24-login-bugfix-deployment.md"
source_sha256: 7dbb7bc1a4e4a8df6c7b706b04896b925fc2cefd9ffb12bd6c1bc4b3f6aaec27
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-09-24-login-bugfix-deployment.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Login bug fixes

Package: `build/hostinger-login-bugfix-2026-09-24.zip`.

Includes the creative login design, animations, and these fixes. No database changes are required.

## Fixed

- Leaving an input could restart the entire page entrance because its animation was disabled only while focus remained inside the form. The entrance now stops permanently after the first interaction.
- A browser-storage warning stayed visible after saving preferences successfully on a later attempt. Successful saves now clear it.
- Returning through the browser's back/forward cache could restore outdated theme and accessibility settings. Restored pages now reread saved preferences, reset submission controls, and clear the Caps Lock notice.
- Browsers with the older media-query listener API could stop running the login script before password controls initialized. Theme listeners now support both APIs.

## Upload

Back up and replace these three files, uploading assets before the view:

- `public/assets/css/login.css`
- `public/assets/js/login.js`
- `app/Views/auth/login.php`

For a Hostinger installation where `public_html` contains the contents of `public/`, upload assets under `public_html/assets/`. Upload the view into the existing application's `app/Views/auth/` directory. Keep the existing logo and favicon assets. The view now requests asset version `20260924-3`.

## Validation

PHP and JavaScript syntax checks passed. Targeted browser regressions cover focus/blur, storage failure followed by retry, restored preferences, and a simulated older media-query API. The existing 30 layout scenarios and animation/form checks were also rerun.

These checks use local rendered fixtures, without submitting real credentials or modifying database records. This package has not been uploaded to Hostinger.

After uploading, focus Username, then click outside the form: the page should stay still. Check Show/Hide, switch themes, and sign in using an existing account.
