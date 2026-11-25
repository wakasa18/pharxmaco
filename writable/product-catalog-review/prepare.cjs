const fs=require('node:fs'),crypto=require('node:crypto');
const {records}=require('../latest-database-review/records.cjs');
const sourcePath='C:/Users/warre/Downloads/u315645644_pharxmacotest (4).sql';
const source=fs.readFileSync(sourcePath,'utf8');
const sourceHash=crypto.createHash('sha256').update(source).digest('hex');
if(sourceHash!=='3a3e54b64fdf2bea5f4f506733da7ce50de4b3aa19fc2047f04974a5248209b5')throw Error('Unexpected source export.');
const original=records(source,'products');
const local=JSON.parse(fs.readFileSync(__dirname+'/local-products.json','utf8'));
const names={
 2:'Erceflora (variant unrecorded)',3:'Erceflora (variant unrecorded)',4:'Erceflora (variant unrecorded)',
 5:'Unidentified food item (verify label)',6:'Biogesic 500 mg tablet',7:'Neozep Forte tablet',
 8:'Alaxan FR 200 mg / 325 mg capsule',9:'Tempra syrup',10:'Cetirizine 10 mg',11:'Amoxicillin 500 mg',
 12:'Vitamin C 500 mg',13:'Paracetamol 500 mg',14:'Loperamide 2 mg',15:'Mefenamic acid 500 mg',
 16:'Ibuprofen 200 mg tablet',17:'Aspirin 80 mg tablet',18:'Naproxen sodium 275 mg',19:'Diclofenac sodium 50 mg',
 20:'Paracetamol 250 mg / 5 mL syrup',21:'Dextromethorphan cough syrup',22:'Ambroxol 30 mg tablet',
 23:'Carbocisteine 500 mg capsule',24:'Phenylephrine + chlorphenamine',25:'Salbutamol 2.5 mg nebule',
 26:'Omeprazole 20 mg capsule',27:'Famotidine 20 mg tablet',28:'Oral rehydration salts',
 29:'Antacid suspension (120 mL)',30:'Bisacodyl 5 mg tablet',31:'Loratadine 10 mg tablet',
 32:'Fexofenadine 120 mg tablet',33:'Diphenhydramine 25 mg capsule',34:'Montelukast 10 mg tablet',
 35:'Hydrocortisone 1% cream',36:'Multivitamins capsule (adult)',37:'Vitamin B complex tablet',
 38:'Zinc 20 mg tablet',39:'Calcium + vitamin D tablet',40:'Iron + folic acid tablet',
 41:'Isopropyl alcohol 70% (500 mL)',42:'Povidone iodine (60 mL)',43:'Sterile gauze pads (10 pieces)',
 44:'Adhesive bandages (20 pieces)',45:'Digital thermometer',46:'Hand sanitizer (100 mL)',
 47:'Disposable face masks (50 pieces)',48:'Cotton balls (100 pieces)',49:'Baby powder (100 g)',
 50:'Feminine wash (150 mL)',51:'Multivitamins syrup (pediatric)',52:'Diaper rash cream (50 g)',
 53:'Baby wipes (80 pieces)',54:'Saline nasal drops (15 mL)',55:'Mosquito repellent lotion (100 mL)',
 56:'Unidentified test item (verify label)'
};
const branded=[6,7,8,9];
const nonMedicine=[2,3,4,5,43,44,45,46,47,48,49,50,52,53,55,56];
const eligible=[6,7,8,9,10,11,...Array.from({length:23},(_,i)=>i+13),42,54];
const notEligible=[5,46,47,48,49,50,53,55,56];
const fields=['product_name','generic_name','strength','medicine_type','senior_pwd_eligible'];
const picked=row=>Object.fromEntries(fields.map(field=>[field,row[field]===null?null:field==='senior_pwd_eligible'?Number(row[field]):row[field]]));
const plan=[];
for(const product of [...original,...local.filter(p=>!original.some(o=>o.id===Number(p.id)))]){
 const id=Number(product.id);if(!names[id])throw Error('Unreviewed product: '+id);
 const after={...picked(product),product_name:names[id],medicine_type:branded.includes(id)?'branded':nonMedicine.includes(id)?'non_medicine':'generic',
  senior_pwd_eligible:eligible.includes(id)?1:notEligible.includes(id)?0:null};
 if(id===44)after.senior_pwd_eligible=1; // Preserve the owner's existing qualifying-device decision.
 if([3,4].includes(id))after.generic_name='Bacillus clausii';
 const variants=[picked(product)];const localProduct=local.find(p=>Number(p.id)===id);
 if(localProduct){if(localProduct.sku!==product.sku)throw Error('Local SKU differs for '+id);
  const variant=picked(localProduct);if(!variants.some(v=>JSON.stringify(v)===JSON.stringify(variant)))variants.push(variant);}
 plan.push({id,sku:product.sku,before:variants,after,source_product:original.some(p=>p.id===id)});
}
const literal=value=>value===null?'NULL':typeof value==='number'?String(value):"'"+value.replaceAll('\\','\\\\').replaceAll("'","''")+"'";
let sql=`-- Pharxmaco product names and classifications, October 15, 2026.
-- Prepared for the catalog in u315645644_pharxmacotest (4).sql and the matching local catalog.
-- Select the EXISTING database in phpMyAdmin. Requires invoice/medicine/benefit updates 000011-000013.
-- Upload matching application files to display the new Non-medicine choice.
-- Product names, generic details, medicine types and benefit flags only; no stock, prices or tax changes.
-- No historical invoice or sale item is changed. Existing product IDs and SKUs are retained.
-- Full old-value guards skip different or subsequently edited products; repeat imports preserve later edits.
-- Medicines with clear identities become eligible. Supplements and uncertain devices remain Not confirmed.
-- The existing adhesive-bandage eligibility is retained, not newly certified by this import.
-- Erceflora variant/registration is unrecorded; current food supplements are not treated as medicines.
-- Missing concentrations and unidentified food/test identities are not invented. Review the companion guide.
SELECT id, product_name, generic_name, strength, medicine_type, senior_pwd_eligible FROM products LIMIT 0;
START TRANSACTION;
`;
for(const p of plan){
 const guards=p.before.map(v=>'('+fields.map(f=>(f==='senior_pwd_eligible'?'':'BINARY ')+'`'+f+'` <=> '+(f==='senior_pwd_eligible'?'':'BINARY ')+literal(v[f])).join(' AND ')+')').join(' OR ');
 sql+='UPDATE `products` SET '+fields.map(f=>'`'+f+'` = '+literal(p.after[f])).join(', ')+', `updated_at` = `updated_at` WHERE `id` = '+p.id+' AND BINARY `sku` <=> BINARY '+literal(p.sku)+' AND ('+guards+');\n';
}
sql+=`COMMIT;
SELECT medicine_type, COUNT(*) AS products FROM products GROUP BY medicine_type;
SELECT senior_pwd_eligible, COUNT(*) AS products FROM products GROUP BY senior_pwd_eligible;
SELECT 'Product catalog update complete; review unmatched or unconfirmed products in Products' AS upgrade_status;
`;
const target='database/upgrades/2026-10-15-product-catalog.sql';fs.writeFileSync(target,sql);
const summary=rows=>({products:rows.length,types:rows.reduce((r,p)=>(r[p.after.medicine_type]=(r[p.after.medicine_type]||0)+1,r),{}),
 eligible:rows.filter(p=>p.after.senior_pwd_eligible===1).length,unconfirmed:rows.filter(p=>p.after.senior_pwd_eligible===null).length,not_eligible:rows.filter(p=>p.after.senior_pwd_eligible===0).length});
const manifest={source_path:sourcePath,source_sha256:sourceHash,upgrade:target,upgrade_sha256:crypto.createHash('sha256').update(sql).digest('hex'),fields,plan,hosting:summary(plan.filter(p=>p.source_product)),local:summary(plan)};
fs.writeFileSync(__dirname+'/manifest.json',JSON.stringify(manifest,null,2));console.log(JSON.stringify({hosting:manifest.hosting,local:manifest.local},null,2));
