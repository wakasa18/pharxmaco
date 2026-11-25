const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {records}=require('./records.cjs');
const sourcePath=process.argv[2];if(!sourcePath)throw Error('Provide source SQL export.');
const source=fs.readFileSync(sourcePath,'utf8');
const inspection=JSON.parse(fs.readFileSync(__dirname+'/inspection.json','utf8'));
const hash=crypto.createHash('sha256').update(source).digest('hex');
if(hash!==inspection.sha256)throw Error('Source changed after inspection.');
const required={products:['generic_name','strength','tax_treatment','medicine_type','senior_pwd_eligible'],
 branch_products:['stock_delivery_id'],sales:['receipt_metadata','checkout_token_hash'],
 sale_items:['generic_name_snapshot','strength_snapshot','tax_treatment_snapshot','stock_delivery_id','expiration_date_at_sale','vat_exemption_amount','statutory_discount_kind'],
 users:['session_version'],refund_items:['refund_method','refund_event_id','return_condition']};
for(const [table,columns] of Object.entries(required))for(const column of columns)if(!inspection.tables[table]?.some(c=>c.name===column))throw Error(`Expected existing update missing: ${table}.${column}`);
const additions=['cashier_drawers','cashier_shifts','cashier_events','cashier_held_sales','cashier_shift_reviews','cashier_event_reviews','cashier_shift_actions'];
for(const table of additions)if(inspection.tables[table])throw Error('This source already has cashier tables; review its state before preparing a different patch.');
const upgrades=['database/upgrades/2026-10-15-cashier-workspace.sql','database/upgrades/2026-10-15-demo-users.sql','database/upgrades/2026-10-15-product-catalog.sql','database/upgrades/2026-10-15-branded-products.sql'];
const target='database/upgrades/2026-10-15-latest-existing-database.sql';
let sql=`-- Pharxmaco: latest upgrade for the EXISTING database, October 15, 2026.
-- Prepared against ${path.basename(sourcePath)}.
-- Source SHA-256: ${hash}
-- Select the existing application database in phpMyAdmin, then import this ENTIRE file.
-- The supplied export already includes updates 000010 through 000013.
-- Adds cashier update 000014 and the three previously requested demo accounts if absent.
-- Includes the requested product-name, Branded/Generic/Non-medicine and benefit-eligibility data update.
-- Adds twelve missing branded medicines with requested DEMO stock (50 units each), prices and expiry.
-- Preserves existing users/passwords, stock, prices, tax classifications, sales and invoice details.
-- No backup records are reinserted. No existing table or record is deleted or replaced.
-- New branch drawers start without shifts, events, counts or fabricated sales history.
-- Demo credentials are in the companion .md guide. Existing matching usernames are skipped.
-- Use the current cashier workspace application files for Start Shift, My Day and Review buttons.
-- Schema changes commit automatically; stop the import on any SQL error.

SELECT id, generic_name, strength, tax_treatment, medicine_type, senior_pwd_eligible FROM products LIMIT 0;
SELECT id, stock_delivery_id FROM branch_products LIMIT 0;
SELECT id, receipt_metadata, checkout_token_hash FROM sales LIMIT 0;
SELECT id, generic_name_snapshot, strength_snapshot, tax_treatment_snapshot, stock_delivery_id,
       expiration_date_at_sale, vat_exemption_amount, statutory_discount_kind FROM sale_items LIMIT 0;
SELECT id, refund_method, refund_event_id, return_condition FROM refund_items LIMIT 0;
SELECT id, session_version FROM users LIMIT 0;
`;
for(const file of upgrades)sql+='\n-- Included update: '+path.basename(file)+'\n'+fs.readFileSync(file,'utf8')+'\n';
sql+=`\nSELECT 'Latest database upgrade complete' AS upgrade_status;
SELECT version, class, namespace, \`group\` FROM migrations
WHERE version = '2026_10_15_000014' AND namespace = 'App' AND \`group\` = 'default';
SELECT COUNT(*) AS cashier_drawers FROM cashier_drawers;
`;
if(/^\s*(?:USE|DROP|TRUNCATE|DELETE|REPLACE|ALTER)\b/im.test(sql))throw Error('Unexpected destructive modification of existing data.');
fs.writeFileSync(target,sql,'utf8');
const manifest={source_path:sourcePath,source_sha256:hash,upgrade:path.resolve(target),upgrades,
 expected_existing_tables:Object.keys(inspection.tables),expected_new_tables:additions,
 original_row_counts:Object.fromEntries(Object.keys(inspection.tables).map(table=>[table,records(source,table).length])),
 demo_usernames:['demo_admin','demo_cashier1','demo_cashier2'],source_demo_users:records(source,'users').filter(u=>['demo_admin','demo_cashier1','demo_cashier2'].includes(u.username)).length};
fs.writeFileSync(__dirname+'/manifest.json',JSON.stringify(manifest,null,2));
console.log(JSON.stringify({upgrade:manifest.upgrade,added_tables:additions.length,source_sales:manifest.original_row_counts.sales,source_sale_items:manifest.original_row_counts.sale_items,source_users:manifest.original_row_counts.users},null,2));
