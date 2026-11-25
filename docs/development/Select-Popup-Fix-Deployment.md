---
title: Floating spacer fix deployment
type: deployment-guide
status: prepared
updated: 2026-10-29
tags: [deployment, interface, dropdowns]
---
# Floating spacer fix deployment

Extract `build/hostinger-select-popup-fix-2026-10-29.zip` and upload its `app` and `public` contents into the corresponding application folders, preserving paths. This cumulative package includes the preceding Products background-filter changes and matching earlier catalog/payment, reporting and expiry updates.

**No SQL import or migration is required.** Keep the host's `.env` and writable data. Hosting was not changed automatically. Upload the corrected dropdown CSS and JavaScript together with the staff layout's asset versions. Reopen the application; use Ctrl+F5 once if an older page remains open.

An empty spacing element must no longer appear as a floating white pill above Sale Details after a refund-page error. Other management pages using those spacers receive the same correction. Dropdowns should still open, select, search and close normally. [Cause and verification](../reviews/2026-10-29-floating-spacer-fix.md).
