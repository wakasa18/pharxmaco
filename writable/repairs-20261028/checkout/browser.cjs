const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const root = process.cwd(), dir = __dirname, snapshots = JSON.parse(fs.readFileSync(path.join(dir,'snapshots.json')));
const profile = path.join(dir,'chrome-'+Date.now()), delay = ms => new Promise(resolve=>setTimeout(resolve,ms));
const csrfName=Object.values(snapshots)[0].csrf_name;
let server, chrome, ws, q1=0, q2=0, held=0, serialToken=0, csrf=Object.values(snapshots)[0].csrf_hash;
let failNext=false, rejectNext=false, lostResponse=false, failSync=false, redirectCheckout=false, completeCheckout=false;
const posts=[], checks=[], exceptions=[];
const rewrite = text => text.replace(/https?:\/\/[^\s"']*?\/(?=cashier\/sales)/g, origin+'/')
    .replace(/window\._siteUrl = "[^"]*";/, 'window._siteUrl = '+JSON.stringify(origin+'/')+';');
let origin;
const snapshot = (extra={}) => ({...structuredClone(snapshots[`${q1}-${q2}-${held}`]), checkout_token:String(serialToken).padStart(32,'a'),
    selection_scope:'1:'+String(serialToken).padStart(32,'a'), csrf_hash:csrf, ...extra});
const json = (res, data, status=200) => {res.writeHead(status,{'Content-Type':'application/json'});res.end(rewrite(JSON.stringify(data)));};
(async()=>{
 server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname.includes('/cashier/sales')) {
   if(url.pathname.includes('/completed/')){res.setHeader('Content-Type','text/html');res.end('<!doctype html><html><head><style>@view-transition{navigation:auto}</style></head><body><main data-completed>Completed fixture sale</main></body></html>');return;}
   if(url.pathname.endsWith('/checkout-status')){json(res,{ok:true,found:false});return;}
   if(req.method==='GET'){json(res,snapshot());return;}
   let body='';for await(const chunk of req)body+=chunk;
   const fields={};for(const match of body.matchAll(/name="([^"]+)"\r\n\r\n([^\r]*)/g))fields[match[1]]=match[2];
   posts.push({path:url.pathname,fields});
   if(fields[csrfName]!==csrf){json(res,{success:false,message:'Stale fixture CSRF token'},403);return;}
   csrf='csrf-'+posts.length;
   if(url.pathname.endsWith('/sync-cart')){
    if(fields['items[1]']!==undefined)q1=Number(fields['items[1]']);
    if(fields['items[2]']!==undefined)q2=Number(fields['items[2]']);
    if(failSync){failSync=false;res.writeHead(502,{'Content-Type':'text/html'});res.end('Lost sync response');return;}
    json(res,{success:true,csrf_name:csrfName,csrf_hash:csrf});return;
   }
   if(url.pathname.endsWith('/checkout')){
    if(redirectCheckout){redirectCheckout=false;res.writeHead(302,{Location:'/session-expired.html'});res.end();return;}
    if(completeCheckout){completeCheckout=false;json(res,{success:true,redirect_url:origin+'/cashier/sales/completed/999',csrf_name:csrfName,csrf_hash:csrf});return;}
    json(res,{success:false,message:'Fixture rejected payment',csrf_name:csrfName,csrf_hash:csrf},422);return;
   }
   await delay(70);
   if(rejectNext){rejectNext=false;json(res,snapshot({success:false,message:'Not enough stock.'}),422);return;}
   if(url.pathname.endsWith('/add-to-cart')){if(fields.product_id==='1')q1+=Number(fields.quantity);else q2+=Number(fields.quantity);}
   if(url.pathname.endsWith('/remove-cart-item')){if(fields.product_id==='1')q1=0;else q2=0;}
   let newCustomer=false;
   if(url.pathname.endsWith('/hold-sale')){held=1;q1=q2=0;newCustomer=true;}
   if(url.pathname.endsWith('/clear-cart')){q1=q2=0;newCustomer=true;}
   if(url.pathname.includes('/resume-held-sale/')){held=0;q1=2;q2=0;newCustomer=true;}
   if(url.pathname.includes('/discard-held-sale/'))held=0;
   if(newCustomer)serialToken++;
   if(failNext){failNext=false;lostResponse=true;res.writeHead(502,{'Content-Type':'text/html'});res.end('Gateway error after cart action');return;}
   json(res,snapshot({new_customer:newCustomer}));return;
  }
  if(url.pathname==='/session-expired.html'){res.setHeader('Content-Type','text/html');res.end('<p>Sign in again</p>');return;}
  const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir;
  const file=path.resolve(base,'.'+decodeURIComponent(url.pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html; charset=utf-8');
  res.end(file.endsWith('.html')?rewrite(fs.readFileSync(file,'utf8')):fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let debugPort;for(let i=0;i<100;i++){const file=path.join(profile,'DevToolsActivePort');if(fs.existsSync(file)){debugPort=Number(fs.readFileSync(file,'utf8').split('\n')[0]);break;}await delay(100);}
 if(!debugPort)throw Error('Chrome did not start');
 const tabs=await(await fetch('http://127.0.0.1:'+debugPort+'/json')).json();ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
 let serial=0;const pending=new Map();
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const wait=async expression=>{for(let i=0;i<300;i++){if(await evaluate(expression))return;await delay(70);}throw Error('Timed out: '+expression);};
 await send('Page.enable');await send('Runtime.enable');

 for(const width of [1440,390]){
  q1=1;q2=held=serialToken=0;csrf=Object.values(snapshots)[0].csrf_hash;
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
  await evaluate("try{localStorage.clear();sessionStorage.clear();}catch(e){} window.__repairOldDocument=true");
  await send('Page.navigate',{url:origin+'/pos-full.html'});
  await wait("!window.__repairOldDocument && document.readyState==='complete' && !!window.PharxmacoModal");
  await evaluate("localStorage.clear();sessionStorage.clear();document.getElementById('discountSelect').value='auto';document.getElementById('discountSelect').dispatchEvent(new Event('change',{bubbles:true}));document.querySelector('[data-pos-target=payment]').click()");
  const test=async(label,expression)=>{if(!await evaluate(expression))console.log(JSON.stringify({label,exceptions,state:await evaluate("({cart:document.querySelectorAll('.cart-row').length,button:document.getElementById('completeSaleBtn')?.outerHTML,status:document.getElementById('posActionStatus')?.textContent,network:document.getElementById('posNetworkStatus')?.textContent,discount:document.getElementById('discountWarning')?.textContent,paid:document.getElementById('amountPaidInput')?.value,reference:document.getElementById('referenceNoInput')?.value})")}));assert.equal(await evaluate(expression),true,label);checks.push({width,label});};
  await evaluate("document.getElementById('amountPaidInput').value='500';document.getElementById('amountPaidInput').dispatchEvent(new Event('input',{bubbles:true}))");
  await test('Cash tender is editable and reference optional',"!document.getElementById('amountPaidInput').readOnly && !document.getElementById('referenceNoInput').required");
  await test('Cash change remains available',"document.getElementById('changeRow').classList.contains('show') && document.getElementById('changeDisplay').textContent.includes('388')");
  await evaluate("document.querySelector('.pay-method[data-method=gcash]').click()");
  await test('GCash fills exact total and locks tender',"document.getElementById('amountPaidInput').value==='112.00' && document.getElementById('amountPaidInput').readOnly && document.getElementById('amountPaidInput').min==='112.00' && document.getElementById('amountPaidInput').max==='112.00'");
  await test('GCash reference required and no cash change',"document.getElementById('referenceNoInput').required && !document.getElementById('changeRow').classList.contains('show') && document.getElementById('completeSaleBtn').disabled");
  await evaluate("document.getElementById('referenceNoInput').value='  ';document.getElementById('referenceNoInput').dispatchEvent(new Event('input',{bubbles:true}))");
  await test('Whitespace reference keeps completion disabled',"document.getElementById('completeSaleBtn').disabled");
  await evaluate("document.getElementById('referenceNoInput').value='GCASH-123';document.getElementById('referenceNoInput').dispatchEvent(new Event('input',{bubbles:true}))");
  await test('Valid reference permits completion',"!document.getElementById('completeSaleBtn').disabled");
  await evaluate("document.querySelector('.cart-qty-input').value='2';document.querySelector('.cart-qty-input').dispatchEvent(new Event('input',{bubbles:true}))");
  await test('Cart quantity changes update exact noncash total',"document.getElementById('amountPaidInput').value==='224.00' && document.getElementById('amountPaidInput').max==='224.00'");
  await evaluate("document.querySelector('.cart-qty-input').value='1';document.querySelector('.cart-qty-input').dispatchEvent(new Event('input',{bubbles:true}))");
  await evaluate("document.getElementById('discountSelect').value='pwd';document.getElementById('beneficiaryName').value='Test Customer';document.getElementById('beneficiaryId').value='PWD-123';document.getElementById('benefitVerified').checked=true;document.querySelectorAll('input[name^=benefit_products]').forEach(x=>x.checked=true);document.getElementById('discountSelect').dispatchEvent(new Event('change',{bubbles:true}))");
  await test('Benefit recalculation updates exact noncash tender',"document.getElementById('amountPaidInput').value==='80.00' && document.getElementById('referenceNoInput').value==='GCASH-123'");
  await evaluate("document.querySelector('.pay-method[data-method=card]').click()");
  await test('Card uses exact discounted amount and required reference',"document.getElementById('amountPaidInput').value==='80.00' && document.getElementById('amountPaidInput').readOnly && document.getElementById('referenceNoInput').required && !document.getElementById('referenceNoLabel').textContent.includes('optional')");
  await delay(600);await evaluate("document.getElementById('amountPaidInput').scrollIntoView({block:'center',behavior:'instant'})");await delay(100);
  const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(dir,'noncash-'+width+'.png'),Buffer.from(shot.data,'base64'));
  await evaluate("document.querySelector('.pay-method[data-method=cash]').click();document.getElementById('amountPaidInput').value='100';document.getElementById('amountPaidInput').dispatchEvent(new Event('input',{bubbles:true}))");
  await test('Cash re-enables tender after switching back',"!document.getElementById('amountPaidInput').readOnly && !document.getElementById('referenceNoInput').required && document.getElementById('changeDisplay').textContent.includes('20')");
 }
 assert.deepEqual(exceptions,[]);
 fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,exceptions,application_database_accessed:false,fixture_only:true},null,2));
 console.log(JSON.stringify({checks:checks.length,exceptions:exceptions.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
