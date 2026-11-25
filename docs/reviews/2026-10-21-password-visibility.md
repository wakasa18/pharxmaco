---
title: Change Password visibility controls
type: implementation-review
status: completed
updated: 2026-10-21
tags: [passwords, forms, accessibility]
---
# Change Password visibility controls

Added independent Show/Hide buttons to New Password and Confirm Password in `app/Views/admin/users/password.php`. Both fields start masked. The buttons are keyboard accessible, use `type="button"`, identify their input through `aria-controls` and update their accessible label/state. Form reset and browser-history return restore masking; toggles retain entered values.

The controls follow existing workspace colors and fonts. Right padding keeps text clear of the buttons, and the existing responsive editor layout is retained. Without JavaScript, the fields remain masked and the inactive buttons stay hidden. Password rules, confirmation, CSRF and server save behavior are unchanged.

PHP syntax passed. A focused script and actual rendered Chrome page verified independent toggles, preserved dummy values and masking on reset. Browser-history masking was checked in the focused script. The 1440px desktop and 390px layouts were visually inspected; mobile controls fit without horizontal overflow. Evidence: `writable/password-visibility-review/verification.json` and the desktop/mobile screenshots. No regression tests were added for this small UI change; the backend suite was not rerun. No form was submitted, real password changed or application database accessed.

The [hosting update](../upgrades/2026-10-21-password-visibility.md) contains the single view and needs no SQL import. Hosted files were not deployed automatically.

## UI alignment follow-up

Following the user's screenshot, replaced absolute button positioning and its vertical divider with one flex input group. Buttons now have consistent inset spacing and width within the field border. The text input flexes beside the button, with separate keyboard/focus indicators; changing Show to Hide does not move or overlap it. This also avoids the previous minimum-button-height conflict when large controls are enabled.

Twelve browser layout combinations passed at 320/390/1440px, in light/dark themes with standard/large controls: both fields matched heights, buttons stayed inside their borders, inputs did not overlap the buttons and there was no horizontal page overflow. Independent toggling and value preservation were rechecked without submitting a form. Updated desktop/mobile screenshots were inspected. Evidence: `writable/password-visibility-review/ui-verification.json`, `password-ui-*.png` and `ui-package-verification.json`.

Current upload: `build/hostinger-password-visibility-ui-2026-10-21.zip`. No server password rules, application data or hosted files were changed.
