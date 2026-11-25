---
title: Research scope and limitations
type: research-note
status: draft
updated: 2026-10-29
tags: [research, scope]
---
# Research scope and limitations

This draft describes the implemented system. Add the actual study location, participants, evaluation method, testing dates and dataset size before using it in a paper. No respondent or live-operation results are inferred here.

## Scope

Pharxmaco is a web-based point-of-sale and inventory management system for pharmacy operations. It provides administrator and cashier roles, branch-specific access, a shared product catalog, branch inventory, categories, suppliers, product classification, stock adjustments, expiry monitoring and movement logs.

The selling workflow includes cart management, payment recording, product/category promotions, Senior Citizen/PWD benefit calculations for confirmed eligible items, invoices, sales history, refunds, exchanges and administrator corrections. Cashier operations include shift opening/closing, daily personal sales and transaction totals, sold-product summaries, cash movements, reconciliation and handover.

Administrative functions include account/branch management, review of closing differences and exceptional movements, sales/forecast reports, PDF/CSV exports, activity records and application database backup/restore.

## Limitations

1. The web application requires an available server and database. Offline checkout is not implemented; online deployment requires network access to the server.
2. Input quality depends on staff entry and verification. Beneficiary documents, medicine eligibility, physical counts and prescriptions require operational checks.
3. A branch-product inventory record holds one balance and expiry. Multiple independently counted manufacturer lots, FEFO and complete batch traceability are not implemented.
4. Cash, GCash and Card are recorded by the application. Provider/terminal authorization and actual settlement are not integrated.
5. Sales forecasts estimate future revenue from recorded history. Reorder forecasting and suggestions are removed; low-stock threshold alerts remain. Supplier lead-time planning, automatic purchasing and outstanding-order management are outside the current system.
6. Merchandise profit reporting does not include complete business accounting, payroll, overhead, supplier-credit accounting or a full purchase-order workflow.
7. Invoice fields and calculation support do not establish BIR accreditation or certify legal compliance. Actual registration values and verified tax/benefit classifications remain necessary.
8. The [known issue register](Known-Issues.md) identifies unresolved inventory/expiry/receiving/payment safeguards. Describe the actual evaluated version and its results rather than claiming all workflows are defect-free.

If evaluation uses demo accounts, sample prices or synthetic sales, identify those conditions and avoid claims of sustained real-world performance that were not measured.

Basis: [Current state](Current-State.md), [user manual](../manual/Pharxmaco_User_Manual.md) and [recorded system review](../reviews/2026-10-15-pos-inventory-audit.md).
