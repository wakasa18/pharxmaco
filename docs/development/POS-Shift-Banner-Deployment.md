---
title: POS shift banner deployment
type: deployment-guide
status: prepared
updated: 2026-10-29
tags: [deployment, pos, interface]
---
# POS shift banner deployment

Extract `build/hostinger-pos-shift-banner-2026-10-29.zip` and upload its `app` and `public` contents into the corresponding application folders, preserving paths. This cumulative package includes the preceding dropdown/spacer correction and Products background-filter changes, with the matching earlier application updates.

**No SQL import or migration is required.** Keep the host's `.env` and writable data. Hosting was not changed automatically. Reopen POS after upload.

The shift banner's icon and message should align vertically. At desktop widths the action remains on the right; at 520 pixels or below it moves beneath the message as a full-width button. Verify both **Shift is open** and **Start a shift** states. [Cause and checks](../reviews/2026-10-29-pos-shift-banner-alignment.md).
