const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const sourcePath = process.argv[2];
if (!sourcePath) throw Error('Provide the source SQL backup.');
const source = fs.readFileSync(sourcePath, 'utf8');
const decode = token => token.toUpperCase() === 'NULL' ? null : token.startsWith("'")
  ? token.slice(1,-1).replace(/\\([\\'"nrt0Z])|''/g, (all, escaped) => escaped ? ({n:'\n',r:'\r',t:'\t','0':'\0',Z:'\x1a'}[escaped] ?? escaped) : "'")
  : Number(token);
function tuples(text) {
  const rows = []; let row = null, token = '', quoted = false;
  for (let i=0;i<text.length;i++) {
    const ch = text[i];
    if (quoted) {
      token += ch;
      if (ch === '\\') token += text[++i];
      else if (ch === "'") { if (text[i+1] === "'") token += text[++i]; else quoted = false; }
    } else if (ch === "'") { quoted=true;token+=ch; }
    else if (ch === '(') { if (row) throw Error('Nested product expression.'); row=[];token=''; }
    else if (ch === ',' && row) { row.push(token.trim());token=''; }
    else if (ch === ')') { row.push(token.trim());rows.push(row);row=null;token=''; }
    else if (row) token+=ch;
  }
  if (quoted || row) throw Error('Incomplete product tuple.');
  return rows;
}
const inserts = [...source.matchAll(/INSERT\s+INTO\s+`products`\s*\(([^)]+)\)\s+VALUES\s*([\s\S]*?);\s*\r?\n/gi)];
if (!inserts.length) throw Error('No products found in the supplied backup.');
const products = new Map();
for (const match of inserts) {
  const columns = [...match[1].matchAll(/`([^`]+)`/g)].map(x=>x[1]);
  for (const row of tuples(match[2])) {
    if (row.length !== columns.length) throw Error('Malformed product row.');
    const product = Object.fromEntries(columns.map((column,i)=>[column,decode(row[i])]));
    if (products.has(product.id)) throw Error('Duplicate product ID.');
    products.set(product.id,product);
  }
}
const existingProductPatch = fs.readFileSync('database/upgrades/2026-10-14-product-invoice-data.sql','utf8');
const updateLines = existingProductPatch.split(/\r?\n/).filter(line=>line.startsWith('UPDATE `products` SET `generic_name`'));
const updates = [];
for (const line of updateLines) {
  const identity = /WHERE `id` = (\d+) AND `product_name` = ('(?:[^']|'')*') AND `sku` <=> (NULL|'(?:[^']|'')*');$/.exec(line);
  if (!identity) throw Error('A product update has no supported identity guard.');
  const product = products.get(Number(identity[1]));
  if (!product || product.product_name !== decode(identity[2]) || product.sku !== decode(identity[3])) continue;
  const generic = /THEN ('(?:[^']|'')*') ELSE `generic_name`/.exec(line);
  const strength = /THEN ('(?:[^']|'')*') ELSE `strength`/.exec(line);
  updates.push({id:product.id,name:product.product_name,sku:product.sku,
    generic_name:generic ? decode(generic[1]) : null,strength:strength ? decode(strength[1]) : null,sql:line});
}
const missingColumns = [];
for (const [table,names] of Object.entries({
  branch_products:['stock_delivery_id'],
  products:['generic_name','strength','tax_treatment','medicine_type','senior_pwd_eligible'],
  sales:['receipt_metadata'],
  sale_items:['stock_delivery_id','expiration_date_at_sale','generic_name_snapshot','strength_snapshot','tax_treatment_snapshot','vat_exemption_amount','statutory_discount_kind']
})) {
  const tableMatch = new RegExp('CREATE TABLE `'+table+'` \\(([\\s\\S]*?)\\) ENGINE=', 'i').exec(source);
  if (!tableMatch) throw Error('Required table is missing: '+table);
  const actual = new Set([...tableMatch[1].matchAll(/^\s*`([^`]+)`/gm)].map(x=>x[1]));
  missingColumns.push(...names.filter(name=>!actual.has(name)).map(name=>table+'.'+name));
}
const upgradePaths = [
  'database/upgrades/2026-10-04-stock-expiry-safeguards.sql',
  'database/upgrades/2026-10-14-receipt-invoice-fields.sql',
  'database/upgrades/2026-10-14-product-medicine-types.sql',
  'database/upgrades/2026-10-14-senior-pwd-discounts.sql',
  'database/upgrades/2026-10-15-cashier-workspace.sql'
];
const sourceHash = crypto.createHash('sha256').update(source).digest('hex');
const target = 'database/upgrades/2026-10-14-latest-existing-database.sql';
let sql = `-- Pharxmaco: latest upgrade for the EXISTING application database, October 15, 2026.
-- Prepared against ${path.basename(sourcePath)}.
-- Source SHA-256: ${sourceHash}
-- Select the existing application database in phpMyAdmin, then import this entire file.
-- Adds missing updates 000010 through 000014. Safe to repeat; retains existing nonempty product details.
-- Includes the previously requested identifiable generic names/strengths and ALL products VATable.
-- Keeps existing stock, prices, timestamps, users, sales, payments and recorded invoice details.
-- Medicine type and SC/PWD eligibility are preserved when set; new fields start Not specified/Not confirmed.
-- No historical medicine/tax/delivery/beneficiary snapshots are invented.
-- SQL schema changes commit automatically. Stop on any SQL error.
-- This upgrade adds structure and updates matching product details; it does not import backup records.

SELECT id, checkout_token_hash FROM sales LIMIT 0;
SELECT id, return_condition, refund_method, refund_event_id FROM refund_items LIMIT 0;
SELECT id, session_version FROM users LIMIT 0;
SELECT id, email FROM suppliers LIMIT 0;
`;
for (const file of upgradePaths) sql += '\n-- Included upgrade: '+path.basename(file)+'\n'+fs.readFileSync(file,'utf8')+'\n';
sql += '\n-- Fill only identifiable product details that remain empty; identity guards match the supplied backup.\nSTART TRANSACTION;\n'
  + updates.map(x=>x.sql).join('\n')
  + "\n\n-- Every current product VATable, as previously requested. Past sale tax snapshots remain unchanged.\nUPDATE `products` SET `tax_treatment` = 'taxable', `updated_at` = `updated_at` WHERE NOT (`tax_treatment` <=> 'taxable');\nCOMMIT;\n"
  + "\nSELECT 'Latest schema and product updates completed' AS upgrade_status;\nSELECT COUNT(*) AS current_products, SUM(tax_treatment = 'taxable') AS vatable_products FROM products;\nSELECT version, class, `group`, namespace FROM migrations WHERE version IN ('2026_10_04_000010','2026_10_14_000011','2026_10_14_000012','2026_10_14_000013','2026_10_15_000014') AND namespace = 'App' AND `group` = 'default' ORDER BY version;\n";
if (/^(?:USE\s|DROP\s|TRUNCATE\s|DELETE\s|REPLACE\s)/mi.test(sql)) throw Error('Unsafe upgrade statement found.');
fs.writeFileSync(target,sql,'utf8');
const manifest = {source_path:sourcePath,source_sha256:sourceHash,upgrade:path.resolve(target),
  product_count:products.size,missing_columns:missingColumns,upgrades:upgradePaths,
  generic_names:updates.filter(x=>x.generic_name).length,strengths:updates.filter(x=>x.strength).length,updates};
fs.writeFileSync(path.join(__dirname,'manifest.json'),JSON.stringify(manifest,null,2));
console.log(JSON.stringify({upgrade:manifest.upgrade,products:products.size,missingColumns:missingColumns.length,genericNames:manifest.generic_names,strengths:manifest.strengths}));
