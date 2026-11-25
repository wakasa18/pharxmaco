---
title: Project decision log
type: decisions
status: current
updated: 2026-10-23
tags: [project-memory, decisions]
---
# Project decision log

These decisions record accepted project behavior. The dated guides provide implementation context; changes to these choices should receive a new decision entry rather than silently rewriting the history.

| ID | Decision | Effect and evidence |
| --- | --- | --- |
| D01 | Work in `pharxmaco`, with one final `o` | The alternate IDE folder `pharxmacoo` is not the active workspace. |
| D02 | One shared drawer per branch, handed between cashiers | One active operator owns a drawer. Count at opening/closing and preserve handover records. [Cashier workspace](../upgrades/2026-10-15-cashier-workspace.md) |
| D03 | Cashiers land on Start Shift when no shift is open | An existing open shift resumes the selling workflow. [Cashier shifts](../system/Cashier-Shifts.md) |
| D04 | Demo accounts initially create accounts only | No fabricated shift or sale history accompanies that account seed. Later product seeds include explicitly requested demo stock/prices. [Demo users](../upgrades/2026-10-15-demo-users.md) |
| D05 | Keep seller registration configurable until supplied | Empty TIN and disabled registered serial range remain explicit. Business address appears on the receipt using branch information. [Invoices](../system/Invoices-and-Receipts.md) |
| D06 | Classify medicines as Branded/Generic; supplies as Non-medicine | Ingredient/strength and benefit eligibility are independent fields. [Product catalog](../upgrades/2026-10-15-product-catalog.md) |
| D07 | Product and category promos apply automatically | Default dropdown is No Discount; automatic offer names are hidden from it. [Automatic promos](../reviews/2026-10-16-automatic-category-promos.md) |
| D08 | Use the favorable promo or Senior/PWD treatment for overlapping items | Include VAT relief in the comparison. Avoid adding both deductions to the same item. [Combined benefits](../reviews/2026-10-15-combined-benefit-promos.md) |
| D09 | All-product ordinary offers stay manually selectable | Automatic product/category pricing remains the normal No Discount path. [POS](../system/POS-and-Discounts.md) |
| D10 | Preserve historical transactions and unknown provenance | Do not invent old shifts, floats, manufacturer lots or delivery identities. [Inventory](../system/Inventory-and-Expiry.md) |
| D11 | Sale #41 refund discrepancy was confirmed as test data | Correct recorded refunds to PHP 500, retaining backup and audit evidence. [Repair guide](../upgrades/2026-10-16-reconcile-sale41-refunds.md) |
| D12 | Cashier shift history reset is delivered as a targeted patch | Preserve sales, money events and unrelated records; a full cleaned export is for an empty database. [Reset guide](../upgrades/2026-10-16-clear-cashier-shifts.md) |
| D13 | Use human sample full names for existing placeholder accounts | Seven local accounts renamed; usernames, authentication and permissions retained. [Recent changes](Change-Log.md) |
| D14 | Use `docs` as the Obsidian documentation and project-memory vault | Maintain current system notes alongside dated reviews, provenance and a generated catalog. [Workflow](../development/Documentation-Workflow.md) |
| D15 | Single-login leases apply to cashiers; administrators can use multiple sessions | User requested removal of the administrator extension on 21 October. Cashier ownership/idle rules remain; admin password/permission revocation remains. [Current policy](../reviews/2026-10-21-admin-multiple-logins.md) |
| D16 | Show recent presence separately from enabled account status | Users shows Online/Offline and Last seen using a two-minute server window and 30-second polling; independent admin sessions aggregate and presence cannot extend login expiry. [Presence](../reviews/2026-10-21-user-presence.md) |
| D17 | Cashiers view their own login history; retain single-login protection | User narrowed the device/session manager request to personal authentication history. Existing audit records supply time/result/IP; no remote termination or multiple cashier sessions. [Login history](../reviews/2026-10-21-cashier-login-history.md) |
| D18 | Enforce cashier references in the database with restrictive actions | User authorized 24 FKs, preserving history and optional NULLs. Preflight does not repair orphan data; explicit resets detach references and restores validate before commit. [Migration 000017](../reviews/2026-10-21-cashier-foreign-keys.md) |
| D19 | Show an overview before processing a refund, exchange or sale correction | Refund/correction dialogs show the current payload and changed values; exchange keeps its existing server quote as its overview. Cancellation retains entries, and changed payloads need renewed confirmation. [Implementation](../reviews/2026-10-23-transaction-overviews.md) |
| D20 | Expire stock from the start of its recorded expiry date | Owner explicitly chose Expired and blocked starting today. Server Philippine date governs POS, exchange, held carts, resellable returns/quarantine release and expiry reporting. No physical count or historical snapshot rewrite. [Expiry policy update](../reviews/2026-10-23-expiry-date-cutoff.md) |
| D21 | Initial expiry-range design included administrator review; superseded by D22 | Earlier implementation and evidence are retained in the [historical review](../reviews/2026-10-28-expiry-year-range.md). |
| D22 | Apply the same expiry-date limit to every account; remove administrator override | Owner explicitly requested the limit only. New/changed dates run from tomorrow through December 31 of the Philippine year plus ten. Unchanged stored dates stay intact; replacement is new stock. [Current implementation](../reviews/2026-10-28-expiry-limit-only.md) |

| D23 | Remove reorder forecasts and restock suggestions; retain sales revenue forecasting and threshold alerts | Historical product forecasts remain stored; the old scheduled command no longer writes. [Removal](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md) |
| D24 | Record supported customer GCash confirmation IDs and provider-specific card references | GCash requires 13 digits; card references use a bounded provider-flexible format. No payment-provider capture is added. [Verified sources](../reviews/2026-10-29-pos-catalog-payments-reorder-removal.md#payment-reference-formats) |

The earlier all-VATable catalog choice is a requested demo-data setting. It is not evidence that every actual pharmacy product has the same tax treatment. Record a later verified classification change as a data decision with its source.

Related: [Current state](Current-State.md) · [Decision template](../templates/Decision.md).
