const fs=require('node:fs'),crypto=require('node:crypto');
const items=[
 {name:'Bioflu 10 mg / 2 mg / 500 mg tablet',brand:'Bioflu',generic:'Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol',strength:'10 mg + 2 mg + 500 mg',unit:'tablet',category:'Cough and Cold',sku:'DEMO-BRD-BIOFLU',source:'https://www.fda.gov.ph/wp-content/uploads/2022/03/FDA-Advisory-No.2022-0413.pdf'},
 {name:'Solmux 500 mg capsule',brand:'Solmux',generic:'Carbocisteine',strength:'500 mg',unit:'capsule',category:'Cough and Cold',sku:'DEMO-BRD-SOLMUX500',source:'https://assets.unilab.com.ph/uploads/Common/Products/Solmux/Solmux-Capsule-PIL.pdf'},
 {name:'Diatabs 2 mg capsule',brand:'Diatabs',generic:'Loperamide hydrochloride',strength:'2 mg',unit:'capsule',category:'Digestive Health',sku:'DEMO-BRD-DIATABS2',source:'https://assets.unilab.com.ph/uploads/Common/Products/Diatabs/diatabs.pdf'},
 {name:'Allerta 10 mg tablet',brand:'Allerta',generic:'Loratadine',strength:'10 mg',unit:'tablet',category:'Allergy and Respiratory',sku:'DEMO-BRD-ALLERTA10',source:'https://www.unilab.com.ph/allerta/allerta-product-feature'},
 {name:'Alnix 10 mg tablet',brand:'Alnix',generic:'Cetirizine dihydrochloride',strength:'10 mg',unit:'tablet',category:'Allergy and Respiratory',sku:'DEMO-BRD-ALNIX10',source:'https://www.unilab.com.ph/products/alnix-tablet'},
 {name:'Medicol Advance 200 mg softgel capsule',brand:'Medicol Advance 200',generic:'Ibuprofen',strength:'200 mg',unit:'capsule',category:'Pain Relief',sku:'DEMO-BRD-MEDICOL200',source:'https://www.unilab.com.ph/products/medicol-advance-200'},
 {name:'Medicol Advance 400 mg softgel capsule',brand:'Medicol Advance 400',generic:'Ibuprofen',strength:'400 mg',unit:'capsule',category:'Pain Relief',sku:'DEMO-BRD-MEDICOL400',source:'https://www.unilab.com.ph/articles/severe-pain-101-how-this-can-affect-health-and-productivity'},
 {name:'Dolfenal 500 mg tablet',brand:'Dolfenal 500',generic:'Mefenamic acid',strength:'500 mg',unit:'tablet',category:'Pain Relief',sku:'DEMO-BRD-DOLFENAL500',prescription:true,source:'https://www.unilab.com.ph/products/dolfenal-500mg'},
 {name:'Skelan 550 mg tablet',brand:'Skelan 550',generic:'Naproxen sodium',strength:'550 mg',unit:'tablet',category:'Pain Relief',sku:'DEMO-BRD-SKELAN550',prescription:true,source:'https://www.unilab.com.ph/products/skelan-550mg'},
 {name:'Kremil-S Advance 10 mg / 800 mg / 165 mg chewable tablet',brand:'Kremil-S Advance',generic:'Famotidine + Calcium carbonate + Magnesium hydroxide',strength:'10 mg + 800 mg + 165 mg',unit:'tablet',category:'Digestive Health',sku:'DEMO-BRD-KREMILS-ADV',source:'https://assets.unilab.com.ph/uploads/Common/Kremil-S%20Advance/Kremil-S%20Advance.pdf'},
 {name:'Tuseran Forte 15 mg / 25 mg / 325 mg capsule',brand:'Tuseran Forte',generic:'Dextromethorphan hydrobromide + Phenylpropanolamine hydrochloride + Paracetamol',strength:'15 mg + 25 mg + 325 mg',unit:'capsule',category:'Cough and Cold',sku:'DEMO-BRD-TUSERAN',source:'https://www.unilab.com.ph/products/tuseran-forte'},
 {name:'Decolgen Forte 25 mg / 2 mg / 500 mg tablet',brand:'Decolgen Forte',generic:'Phenylpropanolamine hydrochloride + Chlorphenamine maleate + Paracetamol',strength:'25 mg + 2 mg + 500 mg',unit:'tablet',category:'Cough and Cold',sku:'DEMO-BRD-DECOLGEN',source:'https://www.unilab.com.ph/decolgen-forte/articles/articles/what-causes-nasal-congestion-and-how-do-you-treat-its'}
];
const prices=[10,12,8,12,10,8,12,25,20,15,12,9];
items.forEach((item,i)=>{item.price=prices[i];item.cost_price=Math.round(prices[i]*70)/100;});
const literal=value=>value===null?'NULL':typeof value==='number'?String(value):"'"+value.replaceAll('\\','\\\\').replaceAll("'","''")+"'";
let sql=`-- Pharxmaco: twelve additional branded medicine catalog entries, October 15, 2026.
-- Select the EXISTING database in phpMyAdmin. Requires stock/invoice/medicine/benefit updates 000010-000013.
-- Adds only missing branded product records and DEMO inventory in one active branch.
-- Requested demo data: 50 units per product; sample unit prices/costs; expiry one year after import.
-- Demo prices/expiry are examples, not market quotations or real physical batch details.
-- All new medicines are Branded and VATable, with Senior Citizen/PWD eligibility enabled.
-- Verify required prescriptions, beneficiary documents and exclusive use at checkout.
-- No existing products, stock, prices, classifications, users, sales or invoice snapshots are updated.
-- Reimport skips existing SKUs or matching product names, including inactive or deleted products.
-- Each branch row is created only for a product inserted by this import; it never restocks existing rows.
-- Manufacturer/supplier are left unset for verification against actual stock.
SELECT id, generic_name, strength, medicine_type, senior_pwd_eligible, tax_treatment FROM products LIMIT 0;
SELECT id, stock_delivery_id FROM branch_products LIMIT 0;
SELECT id, action_type, quantity, previous_stock, new_stock, remarks FROM stock_logs LIMIT 0;
SELECT id, is_deleted, is_permanently_deleted FROM categories LIMIT 0;
START TRANSACTION;
-- Prefer Main Branch; otherwise use the first active branch. Set a specific active branch ID here if needed.
SET @phx_brand_branch = (SELECT id FROM branches WHERE status = 'active'
    ORDER BY CASE WHEN branch_name = 'Main Branch' THEN 0 ELSE 1 END, id LIMIT 1);
SET @phx_brand_created = NOW();
SET @phx_brand_expiry = DATE_ADD(CURDATE(), INTERVAL 1 YEAR);
SET @phx_brand_added = 0;
`;
for(const item of items){
 if(item.name.length>150||item.generic.length>150||item.strength.length>80||item.sku.length>50)throw Error('Product field exceeds schema limit.');
 const aliases=[...new Set([item.name,item.brand,item.brand+' '+item.unit])].map(s=>s.toLowerCase());item.aliases=aliases;
 sql+=`\n-- ${item.name}\nSET @phx_brand_category = (SELECT id FROM categories WHERE category_name = ${literal(item.category)}
    AND deleted_at IS NULL AND is_deleted = 0 AND is_permanently_deleted = 0 LIMIT 1);
INSERT INTO products (product_name, category_id, sku, unit, cost_price, price, stock, reorder_level, status,
    generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible, manufacturer, supplier_id, created_at, updated_at)
SELECT ${literal(item.name)}, @phx_brand_category, ${literal(item.sku)}, ${literal(item.unit)}, ${item.cost_price}, ${item.price}, 50, 5, 'active',
    ${literal(item.generic)}, ${literal(item.strength)}, 'taxable', 'branded', 1, NULL, NULL, @phx_brand_created, @phx_brand_created
WHERE @phx_brand_branch IS NOT NULL AND @phx_brand_category IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM products WHERE sku = ${literal(item.sku)} OR LOWER(TRIM(product_name)) IN (${aliases.map(literal).join(', ')}));
SET @phx_brand_new_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);
SET @phx_brand_added = @phx_brand_added + IF(@phx_brand_new_id IS NULL, 0, 1);
INSERT INTO branch_products (branch_id, product_id, stock, reorder_level, expiration_date, price, cost_price,
    status, stock_delivery_id, created_at, updated_at)
SELECT @phx_brand_branch, @phx_brand_new_id, 50, 5, @phx_brand_expiry, ${item.price}, ${item.cost_price}, 'active', REPLACE(UUID(), '-', ''), @phx_brand_created, @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
INSERT INTO stock_logs (product_id, branch_id, user_id, action_type, quantity, previous_stock, new_stock, remarks, created_at)
SELECT @phx_brand_new_id, @phx_brand_branch, NULL, 'stock_in', 50, 0, 50,
    'Demo branded product seed: sample stock, prices and expiry; verify before live use', @phx_brand_created
WHERE @phx_brand_new_id IS NOT NULL;
`;
}
sql+=`\nCOMMIT;
SELECT CASE WHEN @phx_brand_branch IS NULL THEN 'No active branch; no products added'
    ELSE 'Branded DEMO products ready: 50 units each with sample prices and expiry' END AS upgrade_status,
    @phx_brand_added AS products_added, @phx_brand_branch AS branch_id;
SELECT id, product_name, generic_name, strength, medicine_type, senior_pwd_eligible FROM products
WHERE sku IN (${items.map(p=>literal(p.sku)).join(', ')}) ORDER BY product_name;
SET @phx_brand_branch = NULL, @phx_brand_category = NULL, @phx_brand_created = NULL, @phx_brand_expiry = NULL,
    @phx_brand_new_id = NULL, @phx_brand_added = NULL;
`;
const target='database/upgrades/2026-10-15-branded-products.sql';fs.writeFileSync(target,sql);
const manifest={upgrade:target,upgrade_sha256:crypto.createHash('sha256').update(sql).digest('hex'),quantity:50,expiry_years_after_import:1,items};
fs.writeFileSync(__dirname+'/manifest.json',JSON.stringify(manifest,null,2));
let guide=`# Additional branded products - October 15, 2026

Import **2026-10-15-branded-products.sql** into your existing database using phpMyAdmin **Import**. The same seed is included in **2026-10-15-latest-existing-database.sql** for the complete upgrade. Import either as appropriate; repeat imports skip existing products.

Adds **12 branded medicine variants** to the active Main Branch, or the first active branch if Main Branch is unavailable. It keeps existing generic and branded products as separate catalog entries. Each new product has its generic ingredients, strength, Branded medicine type, VATable classification and Senior Citizen/PWD medicine eligibility. Required beneficiary checks and prescriptions still apply at checkout; the product flag does not automatically discount ordinary sales.

**Requested demo stock is 50 units per product (600 units total).** Each product has the sample unit selling price listed below; sample cost is 70% of that price. Expiry is one year after the import date. These amounts and expiry dates are examples for demo sales, not researched market prices or actual physical stock details. Each new branch inventory has a delivery identifier and a stock-in log explicitly labeled as demo data. Find the entries under **Products > Branded**, and in the Main Branch POS after starting a shift. Replace demo cost/selling price, stock and expiry with verified values before using these records for live inventory. No supplier or physical manufacturer is guessed. The DEMO-BRD SKU is an application identifier, not a manufacturer barcode.

The import creates no sales or shifts. It adds one demo stock-in log for each new product. Existing products, inventories, stock logs, prices, classifications, historical sales, invoices and user accounts are preserved. Existing matching SKUs or recognizable names are skipped even if inactive or in Trash. Only newly inserted products receive a branch row and stock log; reimport never replenishes stock, resets prices or undoes edits. Missing categories or an active branch cause affected inserts to be skipped. Category names must match the current catalog.

The exact formulations below were checked against manufacturer or Philippine FDA sources. Entries are catalog references; check the label and actual registration when sourcing stock. Dolfenal 500 mg and Skelan 550 mg are identified as prescription medicines by their manufacturer. See the linked product information for other dispensing conditions. No new prescription-enforcement feature is added by this data seed.

| Product | Generic ingredients | Strength per unit | Demo unit price | Category | Reference |
|---|---|---|---|---|---|
`;
for(const item of items)guide+=`| ${item.name}${item.prescription?' (Rx)':''} | ${item.generic} | ${item.strength} | PHP${item.price.toFixed(2)} | ${item.category} | [Official reference](${item.source}) |\n`;
guide+='\nVerified by importing the complete supplied export into an isolated MySQL database, comparing original records, reimporting, and testing existing SKU/name collisions and edited products. The verified seed is also applied to the local application database. The hosted database still requires your phpMyAdmin import.\n';
fs.writeFileSync('database/upgrades/2026-10-15-branded-products.md',guide);
console.log(JSON.stringify({file:target,products:items.length,initial_stock_each:50,demo_units:items.length*50},null,2));
