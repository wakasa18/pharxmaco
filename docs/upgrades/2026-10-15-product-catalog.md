---
title: "Product catalog update - October 15, 2026"
type: source-snapshot
status: historical
updated: 2026-10-29
generated_by: tools/docs/sync.cjs
source_path: "database/upgrades/2026-10-15-product-catalog.md"
source_sha256: 94e7b430a6ed63e52279717cd4a30c83be0806c25dcde70dbe44db60fb6edf06
tags: [documentation, archive]
---
> [!info] Dated source snapshot
> Source: `database/upgrades/2026-10-15-product-catalog.md`. Findings and test counts refer to the original work. Read [Current state](../project/Current-State.md) and [Known issues](../project/Known-Issues.md) for subsequent changes.

# Product catalog update - October 15, 2026

Use **2026-10-15-product-catalog.sql** if your database already has the invoice, medicine-type and Senior Citizen/PWD updates. The same product update is also included in **2026-10-15-latest-existing-database.sql**; import either file as appropriate, not both. Repeated imports are safe for matching unchanged records.

In phpMyAdmin, export a backup, select the existing application database, open **Import**, choose the SQL file and click **Go**. Upload the matching application files to show the new **Non-medicine** choice in product forms, catalog filters, badges and POS filters. No new database column or migration is needed for this choice.

## Catalog result

- Hosting export: **54 products**: **4 Branded**, **35 Generic**, **15 Non-medicine**.
- Local catalog: the same products plus one unidentified test item, **55 products** total.
- These are counts for this baseline product cleanup. The subsequent **2026-10-15-branded-products.sql** adds twelve separate branded medicine variants and requested demo inventory. With that seed, hosting has 66 products (16 Branded) and local has 67 products (16 Branded). See its companion guide for prices, quantities and sources.
- **31 additional medicines** are enabled for the existing per-product Senior Citizen/PWD benefit. The existing adhesive-bandage flag remains enabled, giving **32 eligible records**. This import does not independently establish that device's legal eligibility.
- **14 records remain Not confirmed**: three Erceflora entries; seven vitamin/mineral entries; isopropyl alcohol; gauze; thermometer; and diaper rash cream. Ordinary personal care items and unidentified food/test records are not enabled.

Recognizable brand labels are retained: Biogesic, Neozep Forte, Alaxan FR and Tempra. Generic labels retain their ingredient names. Existing generic stock is not relabeled as an invented commercial brand. Names and unit spacing are cleaned up; no unknown concentration, formulation or manufacturer is invented. Neozep Forte retains its recorded three-ingredient formulation, not the different Neozep Z+ formulation.

## Records requiring product verification

The three Erceflora-like names are normalized to **Erceflora (variant unrecorded)** as apparent spelling variants, without merging their IDs or stock. Confirm each physical product's identity, exact variant, strength and registration. Current Philippine Erceflora Gut Defense/Gut Restore products are food supplements; these records are provisionally Non-medicine and their benefit remains Not confirmed. Older drug registrations do not establish which variant these stock records represent. [Manufacturer](https://www.erceflora.com/en-ph), [FDA food registration example](https://verification.fda.gov.ph/food_products_2view.php?ACCOUNTCODE=4000011190664).

Vitamin/mineral entries use generic ingredient labels, but their eligibility stays unconfirmed until the actual registration/formulation and required prescription are checked. Senior Citizen coverage includes medically prescribed vitamins/minerals; PWD coverage has additional medicine-classification conditions. The shared product flag cannot express different eligibility for these two groups. [FDA Senior Citizen guidance](https://www.fda.gov.ph/fda-advisory-no-2020-1547-reiteration-of-doh-administrative-order-no-2012-0007-as-amended/), [DOH PWD guidelines via NCDA](https://ncda.gov.ph/disability-laws/administrative-orders/doh-ao-2017-0008-implementing-guidelines-of-republic-act-10754-otherwise-known-as-an-act-expanding-the-benefits-and-privileges-of-persons-with-disability-for/).

Medical supplies/devices are Non-medicine. Some can qualify under applicable rules; this import does not automatically enable them. The pre-existing bandage eligibility is preserved for the owner to review. Diaper rash cream is provisionally Non-medicine because its active ingredient and registration are missing; verify whether it is a medicinal formulation. Isopropyl alcohol also requires confirmation of the actual registered product. Product type and benefit eligibility remain independently editable under **Products > Edit**.

The old **randommm** row becomes **Unidentified food item (verify label)** based on its existing Foods category. The local-only **TESTINGGG** row becomes **Unidentified test item (verify label)**. Replace these labels only after identifying the actual items; neither receives a medicine discount.

At checkout, select only qualifying lines for the beneficiary's exclusive use and verify identification and required supporting documents. An Eligible flag makes a line selectable; it does not automatically discount every sale. The existing 20% discount and applicable VAT relief calculation is unchanged.

## Preserved data and repeat imports

Only product_name, generic_name, strength, medicine_type and senior_pwd_eligible are included in the update. Product IDs, SKUs, stock, prices, tax classifications, timestamps, manufacturers, suppliers, categories and existing sales/invoice snapshots remain unchanged. In particular, the existing VATable settings are retained.

Each update requires the product ID, SKU and all five original field values to match the reviewed export/local record exactly. A renamed or edited product is skipped rather than overwritten. Review skipped records manually; newly added unrelated products are not classified by guessing. The patch includes a guarded local-only test-item row, which does nothing on the supplied hosting export.

The complete export was imported into an isolated MySQL database for verification. All other product fields and existing tables were compared; repeat import and later-edit preservation were checked. Both Senior Citizen and PWD calculations accepted the newly enabled branded/generic lines and rejected an ineligible personal care line.

The verified product patch was also applied to the local **pharxmaco_drugstore** database. All 55 local products were updated; every other product field and all other tables were compared and preserved. A pre-update product snapshot is retained under writable/product-catalog-review. The hosted database still requires the phpMyAdmin import.

Brand/formulation references: [Biogesic FDA entry](https://verification.fda.gov.ph/ALL_DrugProductsview.php?registration_number=DR-XY39670), [Neozep Forte FDA advisory](https://www.fda.gov.ph/fda-advisory-no-2022-0413-a-babala-sa-publiko-tungkol-sa-paggamit-ng-mga-sumusunod-na-beripikadong-pekeng-gamot-na/), [Alaxan manufacturer](https://www.unilab.com.ph/articles/frequently-asked-questions-on-alaxan), [Tempra FDA entry](https://verification.fda.gov.ph/DRUGS_NEW_APPLICATIONSview.php?DOC_TRACK=20240221103234). Tempra's syrup concentration is not inferred from a different variant's entry.

## Product mapping

| ID | Previous name | Updated name | Type | SC/PWD setting |
|---|---|---|---|---|
| 2 | erceflora | Erceflora (variant unrecorded) | Non-medicine | Not confirmed |
| 3 | ercefloraaaa | Erceflora (variant unrecorded) | Non-medicine | Not confirmed |
| 4 | ercefloraaaaaaaa | Erceflora (variant unrecorded) | Non-medicine | Not confirmed |
| 5 | randommm | Unidentified food item (verify label) | Non-medicine | Not eligible |
| 6 | Biogesic 500mg | Biogesic 500 mg tablet | Branded | Eligible |
| 7 | Neozep Forte | Neozep Forte tablet | Branded | Eligible |
| 8 | Alaxan FR | Alaxan FR 200 mg / 325 mg capsule | Branded | Eligible |
| 9 | Tempra Syrup | Tempra syrup | Branded | Eligible |
| 10 | Cetirizine 10mg | Cetirizine 10 mg | Generic | Eligible |
| 11 | Amoxicillin 500mg | Amoxicillin 500 mg | Generic | Eligible |
| 12 | Vitamin C 500mg | Vitamin C 500 mg | Generic | Not confirmed |
| 13 | Paracetamol 500mg | Paracetamol 500 mg | Generic | Eligible |
| 14 | Loperamide 2mg | Loperamide 2 mg | Generic | Eligible |
| 15 | Mefenamic Acid 500mg | Mefenamic acid 500 mg | Generic | Eligible |
| 16 | Ibuprofen 200mg Tablet | Ibuprofen 200 mg tablet | Generic | Eligible |
| 17 | Aspirin 80mg Tablet | Aspirin 80 mg tablet | Generic | Eligible |
| 18 | Naproxen Sodium 275mg | Naproxen sodium 275 mg | Generic | Eligible |
| 19 | Diclofenac Sodium 50mg | Diclofenac sodium 50 mg | Generic | Eligible |
| 20 | Paracetamol Syrup 250mg/5mL | Paracetamol 250 mg / 5 mL syrup | Generic | Eligible |
| 21 | Dextromethorphan Cough Syrup | Dextromethorphan cough syrup | Generic | Eligible |
| 22 | Ambroxol 30mg Tablet | Ambroxol 30 mg tablet | Generic | Eligible |
| 23 | Carbocisteine 500mg Capsule | Carbocisteine 500 mg capsule | Generic | Eligible |
| 24 | Phenylephrine + Chlorphenamine | Phenylephrine + chlorphenamine | Generic | Eligible |
| 25 | Salbutamol Nebule 2.5mg | Salbutamol 2.5 mg nebule | Generic | Eligible |
| 26 | Omeprazole 20mg Capsule | Omeprazole 20 mg capsule | Generic | Eligible |
| 27 | Famotidine 20mg Tablet | Famotidine 20 mg tablet | Generic | Eligible |
| 28 | Oral Rehydration Salts | Oral rehydration salts | Generic | Eligible |
| 29 | Antacid Suspension 120mL | Antacid suspension (120 mL) | Generic | Eligible |
| 30 | Bisacodyl 5mg Tablet | Bisacodyl 5 mg tablet | Generic | Eligible |
| 31 | Loratadine 10mg Tablet | Loratadine 10 mg tablet | Generic | Eligible |
| 32 | Fexofenadine 120mg Tablet | Fexofenadine 120 mg tablet | Generic | Eligible |
| 33 | Diphenhydramine 25mg Capsule | Diphenhydramine 25 mg capsule | Generic | Eligible |
| 34 | Montelukast 10mg Tablet | Montelukast 10 mg tablet | Generic | Eligible |
| 35 | Hydrocortisone Cream 1% | Hydrocortisone 1% cream | Generic | Eligible |
| 36 | Adult Multivitamins Capsule | Multivitamins capsule (adult) | Generic | Not confirmed |
| 37 | Vitamin B Complex Tablet | Vitamin B complex tablet | Generic | Not confirmed |
| 38 | Zinc 20mg Tablet | Zinc 20 mg tablet | Generic | Not confirmed |
| 39 | Calcium + Vitamin D Tablet | Calcium + vitamin D tablet | Generic | Not confirmed |
| 40 | Iron + Folic Acid Tablet | Iron + folic acid tablet | Generic | Not confirmed |
| 41 | Isopropyl Alcohol 70% 500mL | Isopropyl alcohol 70% (500 mL) | Generic | Not confirmed |
| 42 | Povidone Iodine 60mL | Povidone iodine (60 mL) | Generic | Eligible |
| 43 | Sterile Gauze Pads 10s | Sterile gauze pads (10 pieces) | Non-medicine | Not confirmed |
| 44 | Adhesive Bandage 20s | Adhesive bandages (20 pieces) | Non-medicine | Eligible |
| 45 | Digital Thermometer | Digital thermometer | Non-medicine | Not confirmed |
| 46 | Hand Sanitizer 100mL | Hand sanitizer (100 mL) | Non-medicine | Not eligible |
| 47 | Disposable Face Mask 50s | Disposable face masks (50 pieces) | Non-medicine | Not eligible |
| 48 | Cotton Balls 100s | Cotton balls (100 pieces) | Non-medicine | Not eligible |
| 49 | Baby Powder 100g | Baby powder (100 g) | Non-medicine | Not eligible |
| 50 | Feminine Wash 150mL | Feminine wash (150 mL) | Non-medicine | Not eligible |
| 51 | Pediatric Multivitamin Syrup | Multivitamins syrup (pediatric) | Generic | Not confirmed |
| 52 | Diaper Rash Cream 50g | Diaper rash cream (50 g) | Non-medicine | Not confirmed |
| 53 | Baby Wipes 80s | Baby wipes (80 pieces) | Non-medicine | Not eligible |
| 54 | Saline Nasal Drops 15mL | Saline nasal drops (15 mL) | Generic | Eligible |
| 55 | Mosquito Repellent Lotion 100mL | Mosquito repellent lotion (100 mL) | Non-medicine | Not eligible |
| 56 (local only) | TESTINGGG | Unidentified test item (verify label) | Non-medicine | Not eligible |
