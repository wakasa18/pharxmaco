const fs = require('fs');
const path = require('path');
const http = require('http');
const assert = require('assert/strict');
const {spawn} = require('child_process');
const root = process.cwd(), dir = process.argv.includes('--manual-design-only') ? path.join(root,'writable/system-manual') : process.argv.some(arg=>['--cashier-workspace-only','--my-day-tables-only','--cashier-shift-tables-only','--shift-print-review-only'].includes(arg)) ? path.join(root,'writable/cashier-day-review') : __dirname;
const profile = path.join(dir, 'chrome-profile-' + Date.now());
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let chrome, ws, server;
(async () => {
 server = http.createServer((req,res) => {
  const url = new URL(req.url, 'http://localhost');
  if (process.argv.includes('--checkout-feedback-only') && url.pathname === '/cashier/start-shift') {
   res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<!doctype html><title>Shift destination fixture</title><h1>Start Shift</h1>');return;
  }
  const base = url.pathname.startsWith('/assets/') ? path.join(root,'public') : dir;
  const file = path.resolve(base,'.' + decodeURIComponent(url.pathname));
  if (!file.startsWith(base + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type', file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':'text/html; charset=utf-8');
  res.end(fs.readFileSync(file));
 });
 await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
 const port=server.address().port;
 fs.mkdirSync(profile,{recursive:true});
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
  '--headless=new','--disable-gpu','--hide-scrollbars','--no-first-run','--no-default-browser-check','--disable-extensions',
  '--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'
 ],{stdio:'ignore',windowsHide:true});
 let debugPort;
 for(let i=0;i<100;i++) {
  const portFile=path.join(profile,'DevToolsActivePort');
  if(fs.existsSync(portFile)){debugPort=Number(fs.readFileSync(portFile,'utf8').split('\n')[0]);break;}
  await delay(100);
 }
 if(!debugPort)throw new Error('Headless Chrome did not start');
 const tabs=await (await fetch('http://127.0.0.1:'+debugPort+'/json', {signal:AbortSignal.timeout(5000)})).json();
 ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error('Browser connection timed out')),10000);ws.addEventListener('open',()=>{clearTimeout(timer);resolve();},{once:true});ws.addEventListener('error',()=>{clearTimeout(timer);reject(Error('Browser connection failed'));},{once:true});});
 let serial=0; const pending=new Map();
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser timed out: '+method));},15000);pending.set(id,{resolve:r=>{clearTimeout(timer);resolve(r);},reject:e=>{clearTimeout(timer);reject(e);}});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 await send('Page.enable');
 const errors=[];
 await send('Runtime.enable');
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.fetch=async()=>({ok:true,json:async()=>({success:true,available:true,found:false})});"});
 const checks=[];
 if (process.argv.includes('--manual-design-only')) {
  fs.copyFileSync(path.join(root,'docs/manual/Pharxmaco_User_Manual.html'),path.join(dir,'manual.html'));
  await require('../system-manual/check-design.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  fs.writeFileSync(path.join(dir,'design-browser-checks.json'),JSON.stringify(checks,null,2));
  console.log(`Passed ${checks.length} manual design browser checks.`);return;
 }
 if (process.argv.includes('--cashier-shift-tables-only')) {
  await require('../cashier-day-review/shift-tables.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  fs.writeFileSync(path.join(dir,'shift-table-checks.json'),JSON.stringify(checks,null,2));
  console.log(`Passed ${checks.length} Cashier Shifts DataTables scenarios.`);return;
 }
 if (process.argv.includes('--shift-print-review-only')) {
  await require('../shift-print-review/browser.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');return;
 }
 if (process.argv.includes('--latest-audit-only')) {
  await require('../latest-update-audit/browser.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  console.log(`Passed ${checks.length} checkout audit regression checks.`);return;
 }
 if (process.argv.includes('--benefit-promos-only')) {
  await require('../benefit-promo-review/browser.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  fs.writeFileSync(path.join(dir,'benefit-promo-checks.json'),JSON.stringify(checks,null,2));
  console.log(`Passed ${checks.length} combined benefit/promo browser checks.`);return;
 }
 if (process.argv.includes('--product-promo-only')) {
  await require('../promo-review/browser.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  fs.writeFileSync(path.join(dir,'product-promo-checks.json'),JSON.stringify(checks,null,2));
  console.log(`Passed ${checks.length} automatic product promo browser checks.`);return;
 }
 if (process.argv.includes('--checkout-feedback-only')) {
  await require('../pos-checkout-review/browser.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  fs.writeFileSync(path.join(dir,'checkout-feedback-checks.json'),JSON.stringify(checks,null,2));
  console.log(`Passed ${checks.length} checkout feedback browser checks.`);return;
 }
 if (process.argv.includes('--my-day-tables-only')) {
  const report=JSON.parse(fs.readFileSync(path.join(dir,'report.json'),'utf8'));
  await send('Page.addScriptToEvaluateOnNewDocument',{source:`window.__cashierSummary={report:${JSON.stringify(report)},active_shift_id:1,expected_cash:130};window.fetch=async()=>({ok:true,json:async()=>structuredClone(window.__cashierSummary)});`});
  await require('../cashier-day-review/datatables.cjs')({send,evaluate,port,delay,checks});
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  fs.writeFileSync(path.join(dir,'datatable-checks.json'),JSON.stringify(checks,null,2));
  console.log(`Passed ${checks.length} My Day DataTables scenarios.`);return;
 }
 if (process.argv.includes('--cashier-workspace-only')) {
  await require('../cashier-day-review/browser.cjs')({send,evaluate,port,dir,delay,checks,errors});
  return;
 }
 const detailsOnly = process.argv.includes('--sale-details-only');
 const detailViews = ['sale-details-senior_citizen','sale-details-pwd','sale-details-plain'];
 for(const view of detailsOnly ? detailViews : ['pos','products','catalog','catalog-selected','create','edit','edit-nonmedicine',...detailViews]) {
  await send('Page.navigate',{url:`http://127.0.0.1:${port}/${view}.html`});
  for(let i=0;i<80;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(50);}
  if(['create','edit','edit-nonmedicine'].includes(view))assert.equal(await evaluate("document.querySelector('#product-medicine-type').value"),view==='edit'?'branded':view==='edit-nonmedicine'?'non_medicine':'');
  if(['products','catalog'].includes(view)) {
   const labels=await evaluate("[...document.querySelectorAll('.medicine-type-badge')].map(x=>x.textContent.trim())");
   assert.deepEqual(labels,['Branded','Generic','Not specified','Non-medicine']);
  }
  if(view.startsWith('sale-details-')) {
   const info = await evaluate("[...document.querySelectorAll('.sale-info-item')].map(x=>({label:x.querySelector('.label').textContent.trim(),value:x.querySelector('.value').textContent.trim()}))");
   if(view==='sale-details-plain') assert.equal(info.some(x=>x.label.startsWith('Beneficiary')),false);
   else {
    assert.equal(info.find(x=>x.label==='Beneficiary Name')?.value,'Test Beneficiary');
    assert.equal(info.find(x=>x.label==='Beneficiary ID / Reference Number')?.value,'TEST-ID-123');
   }
  }
  for(const width of [320,390,768,1440]) {
   await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
   for(const dark of [false,true]) {
    await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);
    await delay(50);
    const overflow=await evaluate("document.documentElement.scrollWidth>innerWidth+1");
    assert.equal(overflow,false,view+' overflow '+width);
    if(view==='products') {
     const layouts=await evaluate(`(()=>{const root=document.documentElement,originalDensity=root.getAttribute('data-density'),originalText=root.getAttribute('data-text-size'),results=[];
      try{for(const density of ['default','compact','spacious'])for(const text of ['normal','extra-large']){
       root.setAttribute('data-density',density);root.setAttribute('data-text-size',text);
       const select=document.querySelector('#products-medicine-type').getBoundingClientRect(),button=document.querySelector('.medicine-type-filter-form button').getBoundingClientRect();
       results.push({density,text,select:{top:select.top,bottom:select.bottom,left:select.left,width:select.width,height:select.height},button:{top:button.top,bottom:button.bottom,left:button.left,width:button.width,height:button.height}});
      }}finally{for(const [key,value]of [['data-density',originalDensity],['data-text-size',originalText]])value===null?root.removeAttribute(key):root.setAttribute(key,value);}return results;})()`);
     for(const layout of layouts){
      assert.ok(layout.select.height>=44&&layout.button.height>=44,'Filter touch targets');
      if(width>520){assert.ok(Math.abs(layout.select.top-layout.button.top)<1,'Filter tops '+JSON.stringify(layout));assert.ok(Math.abs(layout.select.bottom-layout.button.bottom)<1,'Filter bottoms');}
      else {assert.ok(layout.button.top>=layout.select.bottom+10,'Mobile filter spacing');assert.ok(Math.abs(layout.button.left-layout.select.left)<1&&Math.abs(layout.button.width-layout.select.width)<1,'Mobile filter widths');}
     }
     checks.push({filterAlignment:true,width,dark,densityAndTextCombinations:layouts.length});
    }
    checks.push({view,width,dark});
    if(!dark&&[390,1440].includes(width)&&(['pos','products','create'].includes(view)||view==='sale-details-senior_citizen')) {
     await delay(450);
     const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
     fs.writeFileSync(path.join(dir,view+'-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }
   }
  }
  if(view==='pos') {
   const visible="[...document.querySelectorAll('.prod-card')].filter(x=>x.style.display!=='none')";
   for(const value of ['branded','generic','unspecified','non_medicine']) {
    await evaluate(`document.querySelector('#posMedicineType').value='${value}';document.querySelector('#posMedicineType').dispatchEvent(new Event('change'));`);
    assert.equal(await evaluate(visible+'.length'),1);
    assert.equal(await evaluate("document.querySelector('#prodCount').textContent"),'1');
   }
   await evaluate("document.querySelector('#posMedicineType').value='branded';document.querySelector('#productSearch').value='paracetamol';document.querySelector('#posMedicineType').dispatchEvent(new Event('change'));");
   assert.equal(await evaluate(visible+'[0].dataset.label'),'Biogesic 500mg','Branded medicine must be searchable by generic ingredient.');
   await evaluate("document.querySelector('#productSearch').value='no-such-medicine';document.querySelector('#posMedicineType').dispatchEvent(new Event('change'));");
   assert.equal(await evaluate(visible+'.length'),0);
   assert.equal(await evaluate("document.querySelector('#productFilterEmpty').hidden"),false);
   await evaluate("document.querySelector('#resetProductFilters').click()");
   assert.equal(await evaluate(visible+'.length'),4);
   assert.equal(await evaluate("document.querySelector('#posMedicineType').value"),'');
   assert.equal(await evaluate("document.querySelector('#productSearch').value"),'');
   checks.push({filters:'Branded/Generic/Non-medicine/Not specified, combined ingredient search, empty state and reset'});
  }
 }
 if(detailsOnly) {
  assert.deepEqual(errors,[],'Browser JavaScript errors');
  fs.writeFileSync(path.join(dir,'sale-details-checks.json'),JSON.stringify(checks,null,2));
  console.log(`Passed ${checks.length} Sale Details browser checks for Senior Citizen, PWD and ordinary sales.`);
  return;
 }
 await send('Page.navigate',{url:`http://127.0.0.1:${port}/pos-benefits.html`});
 for(let i=0;i<80;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(50);}
 const moneyText = selector => evaluate(`document.querySelector('${selector}').textContent.replace(/[^0-9.]/g,'')`);
 assert.equal(await evaluate("getComputedStyle(document.querySelector('#statutoryBenefitPanel')).display"),'none');
 assert.equal(await evaluate("getComputedStyle(document.querySelector('#coVatExemptionRow')).display"),'none');
 for(const width of [320,390,1440]) {
  await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:false});
  for(const dark of [false,true]) {
   await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);
   for(const kind of ['senior_citizen','pwd']) {
    await evaluate(`document.querySelector('#benefitVerified').checked=false;document.querySelector('#discountSelect').value='${kind}';document.querySelector('#discountSelect').dispatchEvent(new Event('change'));`);
    assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),true);
    assert.equal(await evaluate("document.querySelector('.benefit-product[data-product-id=\"2\"]').disabled"),true);
    await evaluate("document.querySelector('#beneficiaryName').value='Test Beneficiary';document.querySelector('#beneficiaryId').value='TEST-ID-123';document.querySelector('.benefit-product[data-product-id=\"1\"]').checked=true;document.querySelector('#benefitVerified').checked=true;document.querySelector('#benefitVerified').dispatchEvent(new Event('change',{bubbles:true}));document.querySelector('#amountPaidInput').value='224';document.querySelector('#amountPaidInput').dispatchEvent(new Event('input'));" );
    assert.equal(await moneyText('#coSubtotal'),'224.00');
    assert.equal(await moneyText('#coDiscountAmt'),'20.00');
    assert.equal(await moneyText('#coVatExemptionAmt'),'12.00');
    assert.equal(await moneyText('#coFinalTotal'),'192.00');
    assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),false);
    assert.equal(await evaluate("document.documentElement.scrollWidth>innerWidth+1"),false,'Benefit checkout overflow');
   }
   if(!dark) {
    await evaluate("document.querySelector('[data-pos-go=payment]')?.click()");
    await evaluate("document.querySelector('#benefitVerified').scrollIntoView({block:'center'});");
    assert.equal(await evaluate("document.querySelector('#benefitVerified').getBoundingClientRect().bottom < document.querySelector('#completeSaleBtn').getBoundingClientRect().top"),true,'Beneficiary confirmation must remain reachable above checkout button');
    const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
    fs.writeFileSync(path.join(dir,'benefits-'+width+'.png'),Buffer.from(shot.data,'base64'));
   }
   await evaluate("document.querySelector('#discountSelect').value='auto';document.querySelector('#discountSelect').dispatchEvent(new Event('change'));" );
   assert.equal(await moneyText('#coFinalTotal'),'212.80');
   assert.equal(await moneyText('#coDiscountAmt'),'11.20');
   assert.equal(await evaluate("getComputedStyle(document.querySelector('#statutoryBenefitPanel')).display"),'none');
   assert.equal(await evaluate("getComputedStyle(document.querySelector('#coVatExemptionRow')).display"),'none');
   assert.equal(await evaluate("document.querySelector('#beneficiaryName').disabled"),true);
   checks.push({statutory:'Senior/PWD verification, product selection, VAT relief and No Discount keeps product promos',width,dark});
  }
 }
 await send('Page.navigate',{url:`http://127.0.0.1:${port}/receipt-benefits.html`});
 for(let i=0;i<80;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(50);}
 assert.equal(await evaluate("document.body.textContent.includes('Test Beneficiary')&&document.body.textContent.includes('TEST-ID-123')"),true);
 for(const paper of ['58','80']) {
  await evaluate(`document.querySelector('input[name="receipt-paper-width"][value="${paper}"]').click()`);
  for(const width of [390,1440]) {
   await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:false});
   await send('Emulation.setEmulatedMedia',{media:'print'});
   await delay(50);
   const tax = await evaluate("[...document.querySelectorAll('[aria-label=\"Tax amounts\"] tr')].map(x=>x.textContent.replace(/\\s+/g,' ').trim())");
   assert.ok(tax.some(x=>x.includes('VAT-exempt sales')&&x.includes('80.00')));
   assert.ok(tax.some(x=>x.includes('VATable sales')&&x.includes('100.00')));
   assert.ok(tax.some(x=>x.includes('VAT amount (12%)')&&x.includes('12.00')));
   assert.equal(await evaluate("document.querySelectorAll('.receipt-paper *').length>0"),true);
   assert.equal(await evaluate("[...document.querySelectorAll('.receipt-paper *')].some(x=>x.scrollWidth>x.clientWidth+2&&getComputedStyle(x).display!=='inline'&&x.clientWidth>0)"),false,'Receipt clipping');
   if(width===1440){const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(dir,'benefit-receipt-'+paper+'.png'),Buffer.from(shot.data,'base64'));}
   checks.push({statutory:'Beneficiary, saved VAT exemption and mixed tax receipt',paper,width});
  }
 }
 assert.deepEqual(errors,[],'Browser JavaScript errors');
 fs.writeFileSync(path.join(dir,'checks.json'),JSON.stringify(checks,null,2));
 console.log(`Passed ${checks.length} medicine-type browser checks with no JavaScript errors.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
