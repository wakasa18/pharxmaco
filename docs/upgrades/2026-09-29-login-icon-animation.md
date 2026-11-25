---
title: "Animated login sign-in icon"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-09-29-login-icon-animation.md"
source_sha256: 8f6adfb16bc1e9260c19834e53fc525539beeb0261c3fff18331ddf7d21094be
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-09-29-login-icon-animation.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Animated login sign-in icon

Package: `build/hostinger-login-icon-animation-2026-09-29.zip`.

The Staff sign in badge gently fades and scales into place. Its arrow moves toward the door twice, then settles. The sequence ends within 2.5 seconds and does not move form fields. Existing device and saved reduced-motion preferences disable the animations.

No database changes are required. Login behavior is unchanged.

Upload `public/assets/css/login.css` first, then `app/Views/auth/login.php`. If Hostinger uses `public_html` for the contents of `public/`, put the stylesheet under `public_html/assets/css/`. Its cache version is `20260929-2`.

This package includes the preceding Staff sign in label/icon change. It has not been uploaded to Hostinger. After upload, reload the login page to see the short animation; enable reduced motion to verify the icon stays still.
