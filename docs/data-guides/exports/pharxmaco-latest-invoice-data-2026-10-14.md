---
title: "Updated product data for invoice receipts"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/exports/pharxmaco-latest-invoice-data-2026-10-14.md"
source_sha256: b3fb8874bdef20a9ca5f5e49e95e7a7fdaeb36687383a024d61750fe9c5b28bd
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/exports/pharxmaco-latest-invoice-data-2026-10-14.md`. Findings and test counts refer to the original work. Read [Current state](../../project/Current-State.md) and [Known issues](../../project/Known-Issues.md) for subsequent changes.

# Updated product data for invoice receipts

Prepared from the supplied `pharxmaco.sql` backup on October 14, 2026. Its invoice-field schema was already current. The new files preserve its existing records and fill identifiable product details for the latest receipt layout.

**Local database updated:** 54 products changed in the latest patch. All 55 current products are now VATable as requested. The prepared files fill 39 generic names and 27 strengths across 55 products. Existing nonempty medicine details are retained.

## Files and import choices

- **Existing installation:** import 2026-10-14-product-invoice-data.sql (repository file: `database/upgrades/2026-10-14-product-invoice-data.sql`) after selecting the application database. This is the same repeatable product patch already applied locally. Medicine details match each product by ID, name and SKU and fill empty fields; the final UPDATE sets every product to VATable, including products added after the backup. If the invoice columns are missing on another installation, first import 2026-10-14-receipt-invoice-fields.sql (repository file: `database/upgrades/2026-10-14-receipt-invoice-fields.sql`).
- **VAT classification only:** 2026-10-14-products-vatable.sql (repository file: `database/upgrades/2026-10-14-products-vatable.sql`) marks all current products VATable without changing medicine details.
- **New/empty database:** select the empty target database and import pharxmaco-latest-invoice-data-2026-10-14.sql (repository file: `database/exports/pharxmaco-latest-invoice-data-2026-10-14.sql`). This full backup includes the original tables, product details, sales, users and the retained invoice counter. It contains no database-selection or table-drop commands. MySQL-specific `utf8mb4_0900_ai_ci` collations were changed to `utf8mb4_unicode_ci`.

The original OneDrive backup was preserved. The full export is based on that backup; subsequent local activity is not added to the exported history. Use the product patch to update another existing installation without replacing its records.

## Receipt behavior

New sales and exchange replacement sales capture these generic names and strengths. Past sale-item snapshots remain unchanged. Product details can be completed later under **Products → Edit**.

The full export also includes the latest **Medicine Type** column. Existing products remain **Not specified** until staff select Branded or Generic in Product Edit. For another existing installation, import 2026-10-14-product-medicine-types.sql (repository file: `database/upgrades/2026-10-14-product-medicine-types.sql`) before uploading the new code. Product lists, the shared catalog and POS display the saved type and provide type filters.

The full export includes the **Senior Citizen/PWD** schema as well. Products start with eligibility **Not confirmed**, and old invoices receive no new discount or VAT relief. For an existing installation, import 2026-10-14-senior-pwd-discounts.sql (repository file: `database/upgrades/2026-10-14-senior-pwd-discounts.sql`); then follow the [setup guide](../../upgrades/2026-10-14-senior-pwd-discounts.md).

At the user's request, every current product is classified as **VATable** (`tax_treatment = 'taxable'`). New sales capture this classification for VAT breakdowns. Existing sale-item tax snapshots remain unchanged. The Tax classification dropdown defaults to VATable for new products and existing products with a blank classification; other choices remain available.

Bottle sizes, package counts and tube weights are not treated as medicine strengths. For example, Povidone Iodine 60mL retains an unknown concentration; only its identifiable generic name is filled. Placeholder names and non-medicinal goods without identifiable ingredients retain their empty fields.

## Sources for identifiable brands

- Biogesic 500mg: [Unilab formulation](https://www.unilab.com.ph/products/biogesic).
- Neozep Forte: [Unilab product leaflet](https://assets.unilab.com.ph/uploads/Common/Products/NeozepDrops/neozep_syrup.pdf). The existing product says Forte, so the newer Z+ formulation is not substituted.
- Alaxan FR: [FDA product record](https://verification.fda.gov.ph/drug_productsview.php?export=pdf&registration_number=DR-XY37939).
- Tempra Syrup: [FDA brand/generic record](https://verification.fda.gov.ph/DRUGS_NEW_APPLICATIONSview.php?DOC_TRACK=20240221103202&export=pdf). The unspecified syrup concentration remains empty.
- Erceflora: [manufacturer product information](https://www.erceflora.com/en-ph/products/why-erceflora). Its unspecified variant/strength remains empty.
- Other entries use ingredients and strengths explicitly written in the original product names. Unspecified salt forms and mixture quantities are retained for review.

## Verification

The original and updated dumps were imported into separate, newly created temporary MySQL databases. The product patch was also applied twice. All three resulting data sets agreed except for the intended medicine details and requested VATable product classifications.

Preserved backup counts: **55 products, 472 sales, 969 sale items**. All 16 tables were compared. Existing prices, stocks, timestamps, historical snapshots, accounts and invoice counters were unchanged. The local patch was checked before its transaction committed, with all 55 local products confirmed VATable. Temporary review databases were removed.

## Product review

| ID | Product | Generic name | Strength | Details still to confirm |
| --- | --- | --- | --- | --- |
| 2 | erceflora | Bacillus clausii | — | Variant and strength are unspecified. |
| 3 | ercefloraaaa | — | — | Placeholder/ambiguous product name: review manually. |
| 4 | ercefloraaaaaaaa | — | — | Placeholder/ambiguous product name: review manually. |
| 5 | randommm | — | — | Placeholder/ambiguous product name: review manually. |
| 6 | Biogesic 500mg | Paracetamol | 500 mg | — |
| 7 | Neozep Forte | Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol | 10 mg + 2 mg + 500 mg | — |
| 8 | Alaxan FR | Ibuprofen + Paracetamol | 200 mg + 325 mg | — |
| 9 | Tempra Syrup | Paracetamol | — | The syrup concentration and variant are unspecified. |
| 10 | Cetirizine 10mg | Cetirizine | 10 mg | — |
| 11 | Amoxicillin 500mg | Amoxicillin | 500 mg | — |
| 12 | Vitamin C 500mg | Vitamin C | 500 mg | Specific Vitamin C form is unspecified. |
| 13 | Paracetamol 500mg | Paracetamol | 500 mg | — |
| 14 | Loperamide 2mg | Loperamide | 2 mg | — |
| 15 | Mefenamic Acid 500mg | Mefenamic acid | 500 mg | — |
| 16 | Ibuprofen 200mg Tablet | Ibuprofen | 200 mg | — |
| 17 | Aspirin 80mg Tablet | Aspirin | 80 mg | — |
| 18 | Naproxen Sodium 275mg | Naproxen sodium | 275 mg | — |
| 19 | Diclofenac Sodium 50mg | Diclofenac sodium | 50 mg | — |
| 20 | Paracetamol Syrup 250mg/5mL | Paracetamol | 250 mg / 5 mL | — |
| 21 | Dextromethorphan Cough Syrup | Dextromethorphan | — | Concentration is unspecified. |
| 22 | Ambroxol 30mg Tablet | Ambroxol | 30 mg | — |
| 23 | Carbocisteine 500mg Capsule | Carbocisteine | 500 mg | — |
| 24 | Phenylephrine + Chlorphenamine | Phenylephrine + Chlorphenamine | — | Ingredient salts and strengths are unspecified. |
| 25 | Salbutamol Nebule 2.5mg | Salbutamol | 2.5 mg | — |
| 26 | Omeprazole 20mg Capsule | Omeprazole | 20 mg | — |
| 27 | Famotidine 20mg Tablet | Famotidine | 20 mg | — |
| 28 | Oral Rehydration Salts | Oral rehydration salts | — | Individual ingredients and quantities are unspecified. |
| 29 | Antacid Suspension 120mL | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 30 | Bisacodyl 5mg Tablet | Bisacodyl | 5 mg | — |
| 31 | Loratadine 10mg Tablet | Loratadine | 10 mg | — |
| 32 | Fexofenadine 120mg Tablet | Fexofenadine | 120 mg | — |
| 33 | Diphenhydramine 25mg Capsule | Diphenhydramine | 25 mg | — |
| 34 | Montelukast 10mg Tablet | Montelukast | 10 mg | — |
| 35 | Hydrocortisone Cream 1% | Hydrocortisone | 1% | — |
| 36 | Adult Multivitamins Capsule | Multivitamins | — | Individual ingredients and quantities are unspecified. |
| 37 | Vitamin B Complex Tablet | Vitamin B complex | — | Individual ingredients and quantities are unspecified. |
| 38 | Zinc 20mg Tablet | Zinc | 20 mg | Zinc salt is unspecified. |
| 39 | Calcium + Vitamin D Tablet | Calcium + Vitamin D | — | Ingredient forms and strengths are unspecified. |
| 40 | Iron + Folic Acid Tablet | Iron + Folic acid | — | Ingredient forms and strengths are unspecified. |
| 41 | Isopropyl Alcohol 70% 500mL | Isopropyl alcohol | 70% | — |
| 42 | Povidone Iodine 60mL | Povidone iodine | — | 60 mL is bottle volume; concentration is unspecified. |
| 43 | Sterile Gauze Pads 10s | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 44 | Adhesive Bandage 20s | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 45 | Digital Thermometer | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 46 | Hand Sanitizer 100mL | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 47 | Disposable Face Mask 50s | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 48 | Cotton Balls 100s | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 49 | Baby Powder 100g | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 50 | Feminine Wash 150mL | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 51 | Pediatric Multivitamin Syrup | Multivitamins | — | Individual ingredients and quantities are unspecified. |
| 52 | Diaper Rash Cream 50g | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 53 | Baby Wipes 80s | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 54 | Saline Nasal Drops 15mL | Saline | — | 15 mL is bottle volume; concentration is unspecified. |
| 55 | Mosquito Repellent Lotion 100mL | — | — | No identifiable generic ingredient or strength; some products are non-medicinal. |
| 56 | TESTINGGG | — | — | Placeholder/ambiguous product name: review manually. |

Products with identity/formulation review notes: 30. Non-medicinal products may not need medicine-specific fields.
