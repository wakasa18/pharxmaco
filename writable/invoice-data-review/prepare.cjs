const fs = require('fs');
const crypto = require('crypto');
const path = require('path');
const sourcePath = process.argv[2];
const allVatable = process.argv.includes('--all-vatable');
if (!sourcePath) throw Error('Provide the source SQL backup path.');
const source = fs.readFileSync(sourcePath, 'utf8');
const sqlQuote = text => "'" + text.replaceAll('\\', '\\\\').replaceAll("'", "''") + "'";
const decode = value => value.toUpperCase() === 'NULL' ? null : value.startsWith("'")
  ? value.slice(1, -1).replace(/\\([\\'"nrt0Z])|''/g, (all, escaped) => escaped ? ({n:'\n',r:'\r',t:'\t','0':'\0',Z:'\x1a'}[escaped] ?? escaped) : "'")
  : Number(value);

// Split VALUES tuples while preserving every original SQL token outside the two new fields.
function tuples(text) {
  const rows = []; let row = null, token = '', quoted = false;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (quoted) {
      token += ch;
      if (ch === '\\') token += text[++i];
      else if (ch === "'") {
        if (text[i + 1] === "'") token += text[++i];
        else quoted = false;
      }
    } else if (ch === "'") { quoted = true; token += ch; }
    else if (ch === '(') { if (row !== null) throw Error('Unexpected nested tuple.'); row = []; token = ''; }
    else if (ch === ',' && row !== null) { row.push(token.trim()); token = ''; }
    else if (ch === ')') { row.push(token.trim()); rows.push(row); row = null; token = ''; }
    else if (row !== null) token += ch;
  }
  if (quoted || row !== null) throw Error('Incomplete product tuple.');
  return rows;
}
const match = /insert\s+into\s+`products`\s*\(([^)]+)\)\s+values\s*([\s\S]*?);\s*\r?\n/i.exec(source);
if (!match) throw Error('Product INSERT not found.');
const columns = [...match[1].matchAll(/`([^`]+)`/g)].map(m => m[1]);
const indexes = Object.fromEntries(columns.map((c,i) => [c,i]));
for (const name of ['id','product_name','sku','generic_name','strength','tax_treatment']) if (!(name in indexes)) throw Error('Missing latest product column: '+name);
const rows = tuples(match[2]);
const label = 'Existing product name';
const biogesic = 'https://www.unilab.com.ph/products/biogesic';
const neozep = 'https://assets.unilab.com.ph/uploads/Common/Products/NeozepDrops/neozep_syrup.pdf';
const alaxan = 'https://verification.fda.gov.ph/drug_productsview.php?export=pdf&registration_number=DR-XY37939';
const tempra = 'https://verification.fda.gov.ph/DRUGS_NEW_APPLICATIONSview.php?DOC_TRACK=20240221103202&export=pdf';
const erceflora = 'https://www.erceflora.com/en-ph/products/why-erceflora';
const details = new Map([
 ['erceflora', ['Bacillus clausii', null, erceflora, 'Variant and strength are unspecified.']],
 ['Biogesic 500mg', ['Paracetamol', '500 mg', biogesic]],
 ['Neozep Forte', ['Phenylephrine hydrochloride + Chlorphenamine maleate + Paracetamol', '10 mg + 2 mg + 500 mg', neozep]],
 ['Alaxan FR', ['Ibuprofen + Paracetamol', '200 mg + 325 mg', alaxan]],
 ['Tempra Syrup', ['Paracetamol', null, tempra, 'The syrup concentration and variant are unspecified.']],
 ['Cetirizine 10mg', ['Cetirizine', '10 mg', label]],
 ['Amoxicillin 500mg', ['Amoxicillin', '500 mg', label]],
 ['Vitamin C 500mg', ['Vitamin C', '500 mg', label, 'Specific Vitamin C form is unspecified.']],
 ['Paracetamol 500mg', ['Paracetamol', '500 mg', label]],
 ['Loperamide 2mg', ['Loperamide', '2 mg', label]],
 ['Mefenamic Acid 500mg', ['Mefenamic acid', '500 mg', label]],
 ['Ibuprofen 200mg Tablet', ['Ibuprofen', '200 mg', label]],
 ['Aspirin 80mg Tablet', ['Aspirin', '80 mg', label]],
 ['Naproxen Sodium 275mg', ['Naproxen sodium', '275 mg', label]],
 ['Diclofenac Sodium 50mg', ['Diclofenac sodium', '50 mg', label]],
 ['Paracetamol Syrup 250mg/5mL', ['Paracetamol', '250 mg / 5 mL', label]],
 ['Dextromethorphan Cough Syrup', ['Dextromethorphan', null, label, 'Concentration is unspecified.']],
 ['Ambroxol 30mg Tablet', ['Ambroxol', '30 mg', label]],
 ['Carbocisteine 500mg Capsule', ['Carbocisteine', '500 mg', label]],
 ['Phenylephrine + Chlorphenamine', ['Phenylephrine + Chlorphenamine', null, label, 'Ingredient salts and strengths are unspecified.']],
 ['Salbutamol Nebule 2.5mg', ['Salbutamol', '2.5 mg', label]],
 ['Omeprazole 20mg Capsule', ['Omeprazole', '20 mg', label]],
 ['Famotidine 20mg Tablet', ['Famotidine', '20 mg', label]],
 ['Oral Rehydration Salts', ['Oral rehydration salts', null, label, 'Individual ingredients and quantities are unspecified.']],
 ['Bisacodyl 5mg Tablet', ['Bisacodyl', '5 mg', label]],
 ['Loratadine 10mg Tablet', ['Loratadine', '10 mg', label]],
 ['Fexofenadine 120mg Tablet', ['Fexofenadine', '120 mg', label]],
 ['Diphenhydramine 25mg Capsule', ['Diphenhydramine', '25 mg', label]],
 ['Montelukast 10mg Tablet', ['Montelukast', '10 mg', label]],
 ['Hydrocortisone Cream 1%', ['Hydrocortisone', '1%', label]],
 ['Adult Multivitamins Capsule', ['Multivitamins', null, label, 'Individual ingredients and quantities are unspecified.']],
 ['Vitamin B Complex Tablet', ['Vitamin B complex', null, label, 'Individual ingredients and quantities are unspecified.']],
 ['Zinc 20mg Tablet', ['Zinc', '20 mg', label, 'Zinc salt is unspecified.']],
 ['Calcium + Vitamin D Tablet', ['Calcium + Vitamin D', null, label, 'Ingredient forms and strengths are unspecified.']],
 ['Iron + Folic Acid Tablet', ['Iron + Folic acid', null, label, 'Ingredient forms and strengths are unspecified.']],
 ['Isopropyl Alcohol 70% 500mL', ['Isopropyl alcohol', '70%', label]],
 ['Povidone Iodine 60mL', ['Povidone iodine', null, label, '60 mL is bottle volume; concentration is unspecified.']],
 ['Pediatric Multivitamin Syrup', ['Multivitamins', null, label, 'Individual ingredients and quantities are unspecified.']],
 ['Saline Nasal Drops 15mL', ['Saline', null, label, '15 mL is bottle volume; concentration is unspecified.']],
]);
const productReport = []; const updates = []; const sqlUpdates = []; const taxUpdates = [];
for (const row of rows) {
  if (row.length !== columns.length) throw Error('Product column count mismatch.');
  const id = decode(row[indexes.id]), name = decode(row[indexes.product_name]), sku = decode(row[indexes.sku]);
  const found = details.get(name);
  const review = found?.[3] ?? (found ? '' : /ercefloraa|randommm|TESTINGGG/i.test(name) ? 'Placeholder/ambiguous product name: review manually.' : 'No identifiable generic ingredient or strength; some products are non-medicinal.');
  const changed = {};
  if (found) for (const [field,value] of [['generic_name',found[0]],['strength',found[1]]]) {
    const existing = decode(row[indexes[field]]);
    if (value !== null && (existing === null || String(existing).trim() === '')) { row[indexes[field]] = sqlQuote(value); changed[field] = value; }
  }
  if (Object.keys(changed).length) {
    updates.push({id,name,sku,...changed});
    const assignments = Object.entries(changed).map(([field,value]) => `\`${field}\` = CASE WHEN \`${field}\` IS NULL OR TRIM(\`${field}\`) = '' THEN ${sqlQuote(value)} ELSE \`${field}\` END`);
    assignments.push('`updated_at` = `updated_at`');
    sqlUpdates.push(`UPDATE \`products\` SET ${assignments.join(', ')} WHERE \`id\` = ${id} AND \`product_name\` = ${sqlQuote(name)} AND \`sku\` <=> ${sku === null ? 'NULL' : sqlQuote(sku)};`);
  }
  if (allVatable) {
    if (decode(row[indexes.tax_treatment]) !== 'taxable') taxUpdates.push(id);
    row[indexes.tax_treatment] = sqlQuote('taxable');
  }
  productReport.push({id,product_name:name,generic_name:decode(row[indexes.generic_name]),strength:decode(row[indexes.strength]),tax_treatment:decode(row[indexes.tax_treatment]),basis:found?.[2] ?? 'Unchanged',review});
}
const productBlock = match[0].slice(0, match[0].indexOf(match[2])) + rows.map(row => '('+row.join(',')+')').join(',\r\n') + ';\r\n';
let full = source.slice(0, match.index) + productBlock + source.slice(match.index + match[0].length);
// A portable full import targets the selected empty database and never drops existing tables.
full = full.replace(/^CREATE DATABASE[^\r\n]*\r?\n/gm,'').replace(/^USE\s+`[^`]+`;\s*\r?\n/gm,'').replace(/^DROP TABLE IF EXISTS[^\r\n]*\r?\n/gm,'');
full = full.replaceAll('utf8mb4_0900_ai_ci','utf8mb4_unicode_ci').replace('SET NAMES utf8 */','SET NAMES utf8mb4 */');
full = '-- FULL BACKUP: import into a selected EMPTY database. For an existing installation use the product-data patch.\r\n-- Updated product generic names/strengths' + (allVatable ? ' and set every product VATable' : '') + '; original historical snapshots and financial data retained.\r\n' + full;
const root = process.cwd(), output = path.join(root,'database/exports/pharxmaco-latest-invoice-data-2026-10-14.sql');
const medicineUpgrade = path.join(root,'database/upgrades/2026-10-14-product-medicine-types.sql');
if (fs.existsSync(medicineUpgrade)) full += '\n-- Latest Medicine Type schema. Existing classifications remain Not specified.\n' + fs.readFileSync(medicineUpgrade,'utf8');
const statutoryUpgrade = path.join(root,'database/upgrades/2026-10-14-senior-pwd-discounts.sql');
if (fs.existsSync(statutoryUpgrade)) full += '\n-- Senior Citizen/PWD invoice schema. Product eligibility requires confirmation.\n' + fs.readFileSync(statutoryUpgrade,'utf8');
const patchPath = path.join(root,'database/upgrades/2026-10-14-product-invoice-data.sql');
const vatStatement = "UPDATE `products` SET `tax_treatment` = 'taxable', `updated_at` = `updated_at` WHERE NOT (`tax_treatment` <=> 'taxable');";
const dataDescription = allVatable ? 'Fills empty generic names/strengths and marks ALL products VATable as requested; preserves timestamps, stock, prices and past sales.' : 'Fills empty generic names/strengths only; preserves tax classifications, timestamps, stock, prices and past sales.';
const delta = '-- Product invoice details from pharxmaco.sql, October 14, 2026.\n-- Select the EXISTING application database. Apply the invoice-field schema upgrade first if necessary.\n-- ' + dataDescription + '\nSTART TRANSACTION;\n' + sqlUpdates.join('\n') + (allVatable ? '\n\n-- Apply to every product, including products added after the supplied backup.\n' + vatStatement : '') + '\nCOMMIT;\n';
fs.writeFileSync(output,full); fs.writeFileSync(patchPath,delta);
if (allVatable) fs.writeFileSync(path.join(root,'database/upgrades/2026-10-14-products-vatable.sql'), '-- Select the EXISTING application database. Requires products.tax_treatment from the invoice-field upgrade.\n-- Marks every product VATable as requested. Repeatable; existing sale snapshots, prices, stock and timestamps are preserved.\nSTART TRANSACTION;\n' + vatStatement + '\nCOMMIT;\n');
const affected = new Set([...updates.map(u=>u.id), ...taxUpdates]);
const report = {source_path:sourcePath,source_sha256:crypto.createHash('sha256').update(source).digest('hex'),source_product_count:rows.length,changed_product_count:affected.size,generic_names_filled:updates.filter(u=>u.generic_name).length,strengths_filled:updates.filter(u=>u.strength).length,all_products_vatable:allVatable,tax_updates:taxUpdates,full_export:output,patch:patchPath,updates,products:productReport};
fs.writeFileSync(path.join(__dirname,'manifest.json'),JSON.stringify(report,null,2)+'\n');
console.log(JSON.stringify({products:rows.length,updated:affected.size,genericNames:report.generic_names_filled,strengths:report.strengths_filled,allVatable,fullExport:output,patch:patchPath}));
