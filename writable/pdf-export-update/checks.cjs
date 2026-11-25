const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const root=path.resolve('tmp/pdfs/pdf-export-update');
const manifest=JSON.parse(fs.readFileSync('writable/pdf-export-update/manifest.json','utf8'));
const parsed=new Map();let count=0;
for(const entry of manifest){
 assert.ok(Math.abs(entry.width_pt-841.89)<.1 && Math.abs(entry.height_pt-595.28)<.1);
 execFileSync(process.execPath,['writable/pdf-export-review/inspect-streams.cjs',entry.name,'--quiet'],{env:{...process.env,PDF_REVIEW_DIR:root}});
 const pages=JSON.parse(fs.readFileSync(path.join(root,entry.name+'-text.json'),'utf8'));
 assert.equal(pages.length,entry.pages);
 const reference=pages[0].items.map(i=>i.text).join(' ').match(/RPT-\d{8}-\d{6}-[A-F0-9]{6}/)?.[0];
 assert.ok(reference,entry.name+' report reference');
 for(const p of pages){
  const texts=p.items.map(i=>i.text);
  assert.ok(texts.includes(`Page ${p.page} of ${pages.length}`),entry.name+' page number');
  assert.ok(texts.some(t=>t.includes(`PHARXMACO | ${p.page}/${pages.length} |`)),entry.name+' running identity');
  assert.ok(texts.some(t=>t.includes('Main Branch - Santa Maria')),entry.name+' branch');
  assert.ok(texts.some(t=>t.includes('2026-10-16')),entry.name+' scope/date');
  assert.ok(texts.some(t=>t.includes(reference)),entry.name+' repeated report reference');
 }
 parsed.set(entry.name,pages);count+=pages.length;
}
const texts=name=>parsed.get(name).flatMap(p=>p.items.map(i=>i.text));
const stock=texts('stock-long');
assert.deepEqual(stock.filter(t=>/^#\d+$/.test(t)).map(t=>Number(t.slice(1))),Array.from({length:35},(_,i)=>i+1));
for(const p of parsed.get('stock-long')){
 if(p.items.some(i=>/^#\d+$/.test(i.text)))assert.ok(p.items.some(i=>i.text==='Log / time (PHT)'),'stock continuation heading');
}
const notes=stock.join('').replace(/\s+/g,'');
assert.equal((notes.match(/Recountcompletedaftercheckingthereceivingnoteandshelfquantities\./g)||[]).length,35,'all complete long-note text');
const expiry=texts('expiry-long');
const expiryJoined=expiry.join('').replace(/\s+/g,'');
for(let i=1;i<=36;i++)assert.equal(expiryJoined.split(`SYNTHETIC-${String(i).padStart(3,'0')}`).length-1,1,'expiry product '+i);
for(const p of parsed.get('expiry-long'))if(p.items.some(i=>i.text.includes('SYNTHETIC-')))assert.ok(p.items.some(i=>i.text==='Product / ID'),'expiry continuation heading');
for(const p of parsed.get('sales-long')){
 const values=p.items.map(i=>i.text);
 if(values.includes('₱110.00'))assert.ok(values.includes('Actual remaining sales'),'forecast column labels');
 if(values.includes('₱40.00'))assert.ok(values.includes('Original sales'),'period column labels');
}
for(const name of ['sales-empty','sales-summary','sales-long-summary'])assert.equal(parsed.get(name).length,1,name+' one page');
for(const expected of ['₱224.00','₱40.00','₱24.00','₱80.00'])assert.ok(texts('sales-summary').includes(expected),'reconciliation '+expected);
assert.ok(texts('sales-legacy-adjustment').includes('Other adj.'),'legacy period reconciliation');
const result={samples:manifest.length,pages:count,A4_landscape:true,all_running_identity_and_page_numbers:true,
 stock_records:35,expiry_records:36,complete_long_note_repetitions:35,continuation_headings_verified:true,
 empty_sales_one_page:true,summary_variants_one_page:true,financial_reconciliation_verified:true,
 scope:'Synthetic actual-view/Dompdf exports; visual review plus scoped CPDF text inspection; no application database writes'};
fs.writeFileSync('writable/pdf-export-update/check-results.json',JSON.stringify(result,null,2));
console.log(JSON.stringify(result,null,2));
