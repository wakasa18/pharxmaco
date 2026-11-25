---
title: "Change Password Show/Hide controls"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-21-password-visibility.md"
source_sha256: 81efed00253c6788046fa41c0ec890e1e85f6151ffa5209abba60448a5053e25
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-21-password-visibility.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Change Password Show/Hide controls

The New Password and Confirm Password fields now have independent Show/Hide buttons. Both start masked and are masked again on form reset or browser-history return. Buttons support keyboard focus and accessible Show/Hide state labels. Toggling preserves entered values.

The revised UI groups each input and button inside one border, removes the tall divider and keeps a fixed button width. The controls fit when larger controls are enabled, and entered text cannot overlap the buttons.

For hosting, back up `app/Views/admin/users/password.php`, extract `build/hostinger-password-visibility-ui-2026-10-21.zip`, and upload its matching view to the same application path. No SQL import is needed. This package replaces the earlier password-visibility upload.

The archive contains one runtime view, this README and its SHA-256 manifest. Password requirements, matching validation, CSRF, autocomplete and server-side save behavior remain unchanged.

PHP syntax, independent toggling, unchanged input values and reset/history masking were checked. The revised UI passed 12 combinations of 320/390/1440px widths, light/dark themes and standard/large controls. Buttons remained contained, both field heights matched and no page overflow or input/button overlap occurred. Desktop and mobile screenshots were inspected. No real passwords were changed and no form was submitted.
