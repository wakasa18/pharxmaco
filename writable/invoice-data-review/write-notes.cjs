const fs = require('fs');
const manifest = JSON.parse(fs.readFileSync(__dirname+'/manifest.json','utf8'));
const verified = JSON.parse(fs.readFileSync(__dirname+'/verification.json','utf8'));
if (fs.existsSync(__dirname+'/export-verification.json')) verified.row_counts = JSON.parse(fs.readFileSync(__dirname+'/export-verification.json','utf8')).row_counts;
const escape = value => (value ?? '—').toString().replaceAll('|','\\|').replaceAll('\n',' ');
const needsReview = manifest.products.filter(p=>p.review);
const allVatable = manifest.all_products_vatable;
const lines = [
 '# Updated product data for invoice receipts', '',
 'Prepared from the supplied `pharxmaco.sql` backup on October 14, 2026. Its invoice-field schema was already current. The new files preserve its existing records and fill identifiable product details for the latest receipt layout.', '',
 `**Local database updated:** ${verified.local_changed_products} products changed in the latest patch.${allVatable ? ` All ${verified.local_vatable_products} current products are now VATable as requested.` : ''} The prepared files fill ${manifest.generic_names_filled} generic names and ${manifest.strengths_filled} strengths across ${manifest.source_product_count} products. Existing nonempty medicine details are retained.`, '',
 '## Files and import choices', '',
 '- **Existing installation:** import [2026-10-14-product-invoice-data.sql](../upgrades/2026-10-14-product-invoice-data.sql) after selecting the application database. This is the same repeatable product patch already applied locally. Medicine details match each product by ID, name and SKU and fill empty fields; the final UPDATE sets every product to VATable, including products added after the backup. If the invoice columns are missing on another installation, first import [2026-10-14-receipt-invoice-fields.sql](../upgrades/2026-10-14-receipt-invoice-fields.sql).',
 '- **VAT classification only:** [2026-10-14-products-vatable.sql](../upgrades/2026-10-14-products-vatable.sql) marks all current products VATable without changing medicine details.',
 '- **New/empty database:** select the empty target database and import [pharxmaco-latest-invoice-data-2026-10-14.sql](pharxmaco-latest-invoice-data-2026-10-14.sql). This full backup includes the original tables, product details, sales, users and the retained invoice counter. It contains no database-selection or table-drop commands. MySQL-specific `utf8mb4_0900_ai_ci` collations were changed to `utf8mb4_unicode_ci`.', '',
 'The original OneDrive backup was preserved. The full export is based on that backup; subsequent local activity is not added to the exported history. Use the product patch to update another existing installation without replacing its records.', '',
 '## Receipt behavior', '',
 'New sales and exchange replacement sales capture these generic names and strengths. Past sale-item snapshots remain unchanged. Product details can be completed later under **Products → Edit**.', '',
 'The full export also includes the latest **Medicine Type** column. Existing products remain **Not specified** until staff select Branded or Generic in Product Edit. For another existing installation, import [2026-10-14-product-medicine-types.sql](../upgrades/2026-10-14-product-medicine-types.sql) before uploading the new code. Product lists, the shared catalog and POS display the saved type and provide type filters.', '',
 'The full export includes the **Senior Citizen/PWD** schema as well. Products start with eligibility **Not confirmed**, and old invoices receive no new discount or VAT relief. For an existing installation, import [2026-10-14-senior-pwd-discounts.sql](../upgrades/2026-10-14-senior-pwd-discounts.sql); then follow the [setup guide](../upgrades/2026-10-14-senior-pwd-discounts.md).', '',
 'At the user\'s request, every current product is classified as **VATable** (`tax_treatment = \'taxable\'`). New sales capture this classification for VAT breakdowns. Existing sale-item tax snapshots remain unchanged. The Tax classification dropdown defaults to VATable for new products and existing products with a blank classification; other choices remain available.', '',
 'Bottle sizes, package counts and tube weights are not treated as medicine strengths. For example, Povidone Iodine 60mL retains an unknown concentration; only its identifiable generic name is filled. Placeholder names and non-medicinal goods without identifiable ingredients retain their empty fields.', '',
 '## Sources for identifiable brands', '',
 '- Biogesic 500mg: [Unilab formulation](https://www.unilab.com.ph/products/biogesic).',
 '- Neozep Forte: [Unilab product leaflet](https://assets.unilab.com.ph/uploads/Common/Products/NeozepDrops/neozep_syrup.pdf). The existing product says Forte, so the newer Z+ formulation is not substituted.',
 '- Alaxan FR: [FDA product record](https://verification.fda.gov.ph/drug_productsview.php?export=pdf&registration_number=DR-XY37939).',
 '- Tempra Syrup: [FDA brand/generic record](https://verification.fda.gov.ph/DRUGS_NEW_APPLICATIONSview.php?DOC_TRACK=20240221103202&export=pdf). The unspecified syrup concentration remains empty.',
 '- Erceflora: [manufacturer product information](https://www.erceflora.com/en-ph/products/why-erceflora). Its unspecified variant/strength remains empty.',
 '- Other entries use ingredients and strengths explicitly written in the original product names. Unspecified salt forms and mixture quantities are retained for review.', '',
 '## Verification', '',
 'The original and updated dumps were imported into separate, newly created temporary MySQL databases. The product patch was also applied twice. All three resulting data sets agreed except for the intended medicine details and requested VATable product classifications.', '',
 `Preserved backup counts: **${verified.row_counts.products} products, ${verified.row_counts.sales} sales, ${verified.row_counts.sale_items} sale items**. All ${Object.keys(verified.row_counts).length} tables were compared. Existing prices, stocks, timestamps, historical snapshots, accounts and invoice counters were unchanged. The local patch was checked before its transaction committed, with all ${verified.local_vatable_products} local products confirmed VATable. Temporary review databases were removed.`, '',
 '## Product review', '',
 '| ID | Product | Generic name | Strength | Details still to confirm |',
 '| --- | --- | --- | --- | --- |',
 ...manifest.products.map(p=>`| ${p.id} | ${escape(p.product_name)} | ${escape(p.generic_name)} | ${escape(p.strength)} | ${escape(p.review || '—')} |`), '',
 `Products with identity/formulation review notes: ${needsReview.length}. Non-medicinal products may not need medicine-specific fields.`, ''
];
fs.writeFileSync('database/exports/pharxmaco-latest-invoice-data-2026-10-14.md',lines.join('\n'));
console.log('Wrote import instructions, verification results and product review table.');
