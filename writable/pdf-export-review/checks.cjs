const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const dir = path.resolve('tmp/pdfs/pdf-export-review');
const manifest = JSON.parse(fs.readFileSync('writable/pdf-export-review/manifest.json','utf8'));
let pageCount = 0;
const parsed = new Map();
for(const entry of manifest) {
  assert.ok(Math.abs(entry.width_pt-841.89)<0.1 && Math.abs(entry.height_pt-595.28)<0.1, entry.name+' A4 landscape');
  execFileSync(process.execPath,['writable/pdf-export-review/inspect-streams.cjs',entry.name,'--quiet']);
  const pages=JSON.parse(fs.readFileSync(path.join(dir,entry.name+'-text.json'),'utf8'));
  assert.equal(pages.length,entry.pages);
  pages.forEach((page,index)=>assert.ok(page.items.some(item=>item.text===`Page ${index+1} of ${pages.length}`), entry.name+' footer '+(index+1)));
  parsed.set(entry.name,pages);
  pageCount+=pages.length;
}
const stockIds=parsed.get('stock-long').flatMap(page=>page.items.filter(item=>{
  const x=parseFloat(item.position);
  return x>40 && x<65 && /^\d+$/.test(item.text);
}).map(item=>Number(item.text)));
assert.deepEqual(stockIds,Array.from({length:35},(_,i)=>i+1),'All synthetic stock log records, no duplicate IDs');
const expiryText=parsed.get('expiry-long').flatMap(page=>page.items.map(item=>item.text));
for(let i=1;i<=36;i++) assert.equal(expiryText.filter(text=>text===String(i).padStart(3,'0')).length,1,'Expiry SKU '+i);
const forecastContinuation=parsed.get('sales-long')[5].items.map(item=>item.text);
assert.equal(forecastContinuation.includes('Period'),false,'Confirmed missing continuation heading');
assert.ok(forecastContinuation.includes('October 1, 2026'));
const financial=JSON.parse(fs.readFileSync('writable/pdf-export-review/financial-probe.json','utf8'));
assert.equal(financial.displayed_gross_minus_discount,104);
assert.equal(financial.printed_net,80);
const result={pdf_samples:manifest.length,pages:pageCount,all_A4_landscape:true,all_page_numbers:true,
  stock_log_ids_complete:stockIds.length,expiry_skus_complete:36,
  confirmed_financial_presentation_gap:24,confirmed_missing_table_heading:'sales-long page 6',
  method:'Dompdf views/footers; visual review plus narrowly scoped CPDF content inspection. Synthetic fixtures; not a general PDF validator.'};
fs.writeFileSync('writable/pdf-export-review/check-results.json',JSON.stringify(result,null,2));
console.log(JSON.stringify(result,null,2));
