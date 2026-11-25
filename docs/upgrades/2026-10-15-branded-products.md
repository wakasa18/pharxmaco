---
title: "Additional branded products - October 15, 2026"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-15-branded-products.md"
source_sha256: 95a5c458da6bd956a7fafa73a202ef4a18c5b3a11cc77b6df89a0d6f93ac192a
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-15-branded-products.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Additional branded products - October 15, 2026

Import **2026-10-15-branded-products.sql** into your existing database using phpMyAdmin **Import**. The same seed is included in **2026-10-15-latest-existing-database.sql** for the complete upgrade. Import either as appropriate; repeat imports skip existing products.

Adds **12 branded medicine variants** to the active Main Branch, or the first active branch if Main Branch is unavailable. It keeps existing generic and branded products as separate catalog entries. Each new product has its generic ingredients, strength, Branded medicine type, VATable classification and Senior Citizen/PWD medicine eligibility. Required beneficiary checks and prescriptions still apply at checkout; the product flag does not automatically discount ordinary sales.

**Requested demo stock is 50 units per product (600 units total).** Each product has the sample unit selling price listed below; sample cost is 70% of that price. Expiry is one year after the import date. These amounts and expiry dates are examples for demo sales, not researched market prices or actual physical stock details. Each new branch inventory has a delivery identifier and a stock-in log explicitly labeled as demo data. Find the entries under **Products > Branded**, and in the Main Branch POS after starting a shift. Replace demo cost/selling price, stock and expiry with verified values before using these records for live inventory. No supplier or physical manufacturer is guessed. The DEMO-BRD SKU is an application identifier, not a manufacturer barcode.

The import creates no sales or shifts. It adds one demo stock-in log for each new product. Existing products, inventories, stock logs, prices, classifications, historical sales, invoices and user accounts are preserved. Existing matching SKUs or recognizable names are skipped even if inactive or in Trash. Only newly inserted products receive a branch row and stock log; reimport never replenishes stock, resets prices or undoes edits. Missing categories or an active branch cause affected inserts to be skipped. Category names must match the current catalog.

The exact formulations below were checked against manufacturer or Philippine FDA sources. Entries are catalog references; check the label and actual registration when sourcing stock. Dolfenal 500 mg and Skelan 550 mg are identified as prescription medicines by their manufacturer. See the linked product information for other dispensing conditions. No new prescription-enforcement feature is added by this data seed.

| Product | Generic ingredients | Strength per unit | Demo unit price | Category | Reference |
|---|---|---|---|---|---|
| Bioflu 10 mg / 2 mg / 500 mg tablet | Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol | 10 mg + 2 mg + 500 mg | PHP10.00 | Cough and Cold | [Official reference](https://www.fda.gov.ph/wp-content/uploads/2022/03/FDA-Advisory-No.2022-0413.pdf) |
| Solmux 500 mg capsule | Carbocisteine | 500 mg | PHP12.00 | Cough and Cold | [Official reference](https://assets.unilab.com.ph/uploads/Common/Products/Solmux/Solmux-Capsule-PIL.pdf) |
| Diatabs 2 mg capsule | Loperamide hydrochloride | 2 mg | PHP8.00 | Digestive Health | [Official reference](https://assets.unilab.com.ph/uploads/Common/Products/Diatabs/diatabs.pdf) |
| Allerta 10 mg tablet | Loratadine | 10 mg | PHP12.00 | Allergy and Respiratory | [Official reference](https://www.unilab.com.ph/allerta/allerta-product-feature) |
| Alnix 10 mg tablet | Cetirizine dihydrochloride | 10 mg | PHP10.00 | Allergy and Respiratory | [Official reference](https://www.unilab.com.ph/products/alnix-tablet) |
| Medicol Advance 200 mg softgel capsule | Ibuprofen | 200 mg | PHP8.00 | Pain Relief | [Official reference](https://www.unilab.com.ph/products/medicol-advance-200) |
| Medicol Advance 400 mg softgel capsule | Ibuprofen | 400 mg | PHP12.00 | Pain Relief | [Official reference](https://www.unilab.com.ph/articles/severe-pain-101-how-this-can-affect-health-and-productivity) |
| Dolfenal 500 mg tablet (Rx) | Mefenamic acid | 500 mg | PHP25.00 | Pain Relief | [Official reference](https://www.unilab.com.ph/products/dolfenal-500mg) |
| Skelan 550 mg tablet (Rx) | Naproxen sodium | 550 mg | PHP20.00 | Pain Relief | [Official reference](https://www.unilab.com.ph/products/skelan-550mg) |
| Kremil-S Advance 10 mg / 800 mg / 165 mg chewable tablet | Famotidine + Calcium carbonate + Magnesium hydroxide | 10 mg + 800 mg + 165 mg | PHP15.00 | Digestive Health | [Official reference](https://assets.unilab.com.ph/uploads/Common/Kremil-S%20Advance/Kremil-S%20Advance.pdf) |
| Tuseran Forte 15 mg / 25 mg / 325 mg capsule | Dextromethorphan hydrobromide + Phenylpropanolamine hydrochloride + Paracetamol | 15 mg + 25 mg + 325 mg | PHP12.00 | Cough and Cold | [Official reference](https://www.unilab.com.ph/products/tuseran-forte) |
| Decolgen Forte 25 mg / 2 mg / 500 mg tablet | Phenylpropanolamine hydrochloride + Chlorphenamine maleate + Paracetamol | 25 mg + 2 mg + 500 mg | PHP9.00 | Cough and Cold | [Official reference](https://www.unilab.com.ph/decolgen-forte/articles/articles/what-causes-nasal-congestion-and-how-do-you-treat-its) |

Verified by importing the complete supplied export into an isolated MySQL database, comparing original records, reimporting, and testing existing SKU/name collisions and edited products. The verified seed is also applied to the local application database. The hosted database still requires your phpMyAdmin import.
