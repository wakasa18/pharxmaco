---
title: POS catalog and payment update deployment
type: deployment-guide
status: prepared
updated: 2026-10-29
tags: [deployment, pos, reporting, payments]
---
# POS catalog and payment update deployment

Extract `build/hostinger-pos-catalog-payment-update-2026-10-29.zip` and upload its `app` and `public` contents into the corresponding application folders, preserving paths. This cumulative package includes the current selected audit repairs, expiry limit/calendar work, branded catalog/search changes, payment-reference rules and removal of reorder planning. It targets the matching application/schema already reviewed, including existing quarantine update 000018 and delivery/expiry snapshots.

**No SQL import, migration or Composer update is needed.** Preserve the host's `.env` and writable data. Hosting has not been changed automatically. Close old forms and refresh with **Ctrl+F5** after both PHP and assets are uploaded.

Check with approved demo records:

1. Confirm the selling branch displayed above POS search. Search by brand, generic ingredient and SKU; change medicine type and clear filters. Only eligible stock in that branch should appear. Clearing a retained URL search must restore the eligible catalog.
2. If branded results are empty, review branch assignment, active status, quantity and expiry in Products. The local Patag diagnosis is not proof of hosted balances. Use the optional read-only diagnostic SQL from the repository when needed; do not change dates or fabricate stock to bypass eligibility.
3. GCash requires the 13-digit customer payment-confirmation ID. Card accepts the approval/transaction reference printed by the terminal provider. Check leading zeros, letters and provider separators, exact noncash totals and zero cash change. Cash remains unchanged.
4. Check exchange payment/payout references and corrections. Unchanged historical references remain available for nonfinancial edits; new references and payment differences validate. Use the existing overview before committing a transaction.
5. Reports and notification dialogs must have no reorder suggestions, suggested quantities or days-left. Sales forecasting, saved sales periods and ordinary low-stock alerts remain. CSV/Full PDF/Summary PDF must omit suggestion sections.
6. If hosting has an old `php spark forecast:reorder` cron entry, remove it from the host's scheduler. The uploaded command is already a retired no-write stub. Historical `forecasting_data` rows are retained.

The rebuilt manual keeps its existing download filename and 37-page format. Its report/payment instructions have been updated locally; copy the document separately if it is distributed from another location.

[Implementation and verification](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md). The previously documented A05 dedicated expiry-correction workflow remains outside this update.
