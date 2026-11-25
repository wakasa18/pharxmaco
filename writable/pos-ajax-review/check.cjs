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
  q1=q2=held=serialToken=0;csrf=Object.values(snapshots)[0].csrf_hash;
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
  await send('Page.navigate',{url:origin+'/pos.html'});
  await wait("document.readyState==='complete' && !!window.PharxmacoModal");
  await evaluate("window.posMarker='same-document';window.confirm=()=>true;window.prompt=()=> 'Waiting customer';sessionStorage.clear();localStorage.clear();window.originalPayment=document.getElementById('checkoutForm');window.originalProducts=document.getElementById('posProducts')");
 const stable=async label=>{
   assert.equal(await evaluate("window.posMarker==='same-document' && window.originalPayment===document.getElementById('checkoutForm') && window.originalProducts===document.getElementById('posProducts')"),true,label+' replaced page or stable fields');
   assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false,'Page overflow');
   checks.push({width,action:label,document_preserved:true});
  };
  const idle=()=>wait("document.getElementById('posCart').getAttribute('aria-busy')==='false'");
  const add=async pid=>{await evaluate(`document.querySelector('[data-pos-target=products]').click();document.getElementById('productSearch').value='';document.getElementById('clearProductSearch').click();document.querySelector('.prod-card[data-pid="${pid}"]').click();document.getElementById('quickAddForm').requestSubmit()`);await idle();};
  if(!process.argv.includes('--session-only')){
  await evaluate("document.getElementById('productSearch').value='Paracetamol';document.getElementById('productSearch').dispatchEvent(new Event('input',{bubbles:true}))");await delay(180);
  const start=posts.length;
  await evaluate("document.querySelector('.prod-card[data-pid=\"1\"]').click();document.getElementById('quickAddForm').requestSubmit();document.getElementById('quickAddForm').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))");await idle();
  assert.equal(posts.length-start,1,'Double-click caused duplicate cart POST');
  assert.equal(q1,1);assert.equal(await evaluate("document.getElementById('productSearch').value"),'Paracetamol');await stable('add + duplicate guard + search preservation');
  await evaluate("document.querySelector('.qty-inc').click();document.getElementById('discountSelect').value='senior_citizen';document.getElementById('discountSelect').dispatchEvent(new Event('change'));document.getElementById('beneficiaryName').value='Ana Santos';document.getElementById('beneficiaryId').value='SC-123';document.querySelector('.benefit-product').checked=true;document.getElementById('benefitVerified').checked=true;document.getElementById('benefitVerified').dispatchEvent(new Event('change',{bubbles:true}));document.getElementById('amountPaidInput').value='500';document.getElementById('referenceNoInput').value='REF-100';document.getElementById('saleNotes').value='Keep these entries';document.getElementById('amountPaidInput').dispatchEvent(new Event('input'))");
  await stable('quantity and Senior benefit');
  await add(2);assert.equal(q1,2);assert.equal(q2,1);
  assert.equal(await evaluate("document.getElementById('amountPaidInput').value==='500' && document.getElementById('referenceNoInput').value==='REF-100' && document.getElementById('saleNotes').value==='Keep these entries' && document.getElementById('beneficiaryName').value==='Ana Santos' && document.querySelector('.benefit-product[data-product-id=\"1\"]').checked && document.getElementById('benefitVerified').checked"),true);
  assert.match(await evaluate("document.getElementById('coFinalTotal').textContent"),/205\.00/);await stable('add with Senior + automatic promo + preserved payment');
  await evaluate("document.querySelector('.cart-row[data-product-id=\"2\"] form').requestSubmit()");await idle();assert.equal(q2,0);assert.equal(await evaluate("document.querySelector('.benefit-product').checked"),true);await stable('remove + beneficiary retained');
  rejectNext=true;await add(2);assert.equal(q2,0);assert.match(await evaluate("document.getElementById('posActionMessage').textContent"),/stock/);await stable('server validation remains inline');
  await evaluate("document.getElementById('holdSaleBtn').click()");await idle();assert.equal(q1,0);assert.equal(held,1);
  assert.equal(await evaluate("document.getElementById('amountPaidInput').value==='' && document.getElementById('beneficiaryName').value==='' && document.getElementById('discountSelect').value==='auto' && !document.getElementById('heldSalesTrigger').hidden"),true);await stable('hold + reset customer');
  await evaluate("document.getElementById('heldSalesTrigger').click();document.querySelector('form[action*=resume-held-sale]').requestSubmit()");await idle();assert.equal(q1,2);assert.equal(held,0);assert.equal(await evaluate("document.getElementById('heldModalOverlay').classList.contains('show')"),false);await stable('resume + close dialog');
  await evaluate("document.querySelector('form[action*=clear-cart]').requestSubmit()");await idle();assert.equal(q1,0);await stable('clear');
  await add(1);await evaluate("document.getElementById('holdSaleBtn').click()");await idle();
  await evaluate("document.getElementById('heldSalesTrigger').click();document.querySelector('form[action*=discard-held-sale]').requestSubmit()");await idle();assert.equal(held,0);assert.equal(await evaluate("document.getElementById('heldSalesTrigger').hidden"),true);await stable('discard + empty held list');
  failNext=true;await add(1);assert.equal(lostResponse,true);assert.equal(q1,1);
  assert.equal(await evaluate("document.getElementById('refreshPosCart').hidden"),false);assert.equal(await evaluate("document.getElementById('completeSaleBtn').disabled"),true);
  const before=posts.length;await evaluate("document.getElementById('quickAddForm').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))");await delay(120);assert.equal(posts.length,before,'Uncertain action was repeated');
  await evaluate("document.getElementById('refreshPosCart').click()");await idle();assert.equal(posts.length,before,'Refresh repeated a POST');assert.equal(q1,1);assert.equal(await evaluate("document.querySelector('.cart-qty-input').value"),'1');await stable('502 recovery reads cart without repeat');
  failSync=true;const beforeSync=posts.length;await add(2);assert.equal(posts.length,beforeSync+1,'Failed sync sent another cart action');assert.equal(q2,0);assert.equal(await evaluate("document.getElementById('refreshPosCart').hidden"),false);
  await evaluate("document.getElementById('refreshPosCart').click()");await idle();assert.equal(posts.length,beforeSync+1);await stable('lost quantity-sync response recovers CSRF through GET');
  await evaluate("document.querySelector('[data-pos-target=payment]').click();document.querySelector('[data-tender=exact]').click();document.getElementById('checkoutForm').requestSubmit()");await wait("document.getElementById('posNetworkStatusTitle').textContent==='Sale was not completed'");await stable('checkout validation preserves document');
  assert.equal(await evaluate("document.getElementById('checkCheckoutStatusBtn').getClientRects().length"),0,'Unavailable recovery button remained visible');
  assert.equal(await evaluate("document.getElementById('posActionStatus').hidden"),true,'Old cart message remained above checkout error');
  redirectCheckout=true;await evaluate("document.getElementById('checkoutForm').requestSubmit()");await wait("document.getElementById('posNetworkStatusTitle').textContent==='No completed sale found'");await stable('unexpected HTML redirect checks outcome and preserves POS');
  await evaluate("document.querySelector('[data-pos-target=cart]').click();window.scrollTo(0,0)");
  const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(dir,'pos-ajax-'+width+'.png'),Buffer.from(shot.data,'base64'));
  // Only a confirmed successful sale deliberately leaves POS for its confirmation page.
  await evaluate("document.querySelector('[data-pos-target=payment]').click();document.getElementById('refreshPosCart').hidden=false;document.getElementById('refreshPosCart').click()");await idle();
  completeCheckout=true;await evaluate("document.getElementById('checkoutForm').requestSubmit()");await wait("!!document.querySelector('[data-completed]')");checks.push({width,action:'confirmed checkout opens completed sale',intentional_navigation:true});
  }
  await send('Page.navigate',{url:origin+'/pos-full.html'});await wait("document.readyState==='complete' && !!document.getElementById('checkoutForm')");
  assert.equal(await evaluate("document.getElementById('completeSaleBtn').disabled"),false);
  const postCount=posts.length;
  await evaluate("document.getElementById('amountPaidInput').value='500';document.getElementById('saleNotes').value='Keep local draft';localStorage.setItem('pharxmaco-pending-checkout',JSON.stringify({token:'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',createdAt:Date.now()}));window.dispatchEvent(new CustomEvent('pharxmaco:session-ended',{detail:{message:'Cashier login ended by administrator'}}));window.dispatchEvent(new CustomEvent('pharxmaco:session-ended',{detail:{message:'Repeated notice'}}));document.getElementById('checkoutForm').requestSubmit()");
  assert.equal(await evaluate("document.querySelectorAll('#cashierSessionNotice').length===1 && document.getElementById('completeSaleBtn').disabled && document.querySelector('.cart-qty-input').disabled && document.getElementById('amountPaidInput').value==='500' && document.getElementById('saleNotes').value==='Keep local draft' && !!localStorage.getItem('pharxmaco-pending-checkout')"),true);
  assert.equal(posts.length,postCount);checks.push({width,action:'ended cashier login blocks POS and preserves draft/recovery state',document_preserved:true});
  await evaluate("document.querySelector('[data-pos-target=cart]').click()");await delay(650);
  const endedShot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(dir,'cashier-session-ended-'+width+'.png'),Buffer.from(endedShot.data,'base64'));
  await evaluate("localStorage.clear();sessionStorage.clear()");
 }
 assert.deepEqual(exceptions,[]);
 fs.writeFileSync(path.join(dir,process.argv.includes('--session-only')?'browser-session-results.json':'browser-results.json'),JSON.stringify({checks,posts:posts.length,javascript_errors:exceptions,application_database_accessed:false},null,2));
 console.log(`Passed ${checks.length} POS action scenarios at desktop/mobile widths; ${posts.length} fixture POSTs, no application database access.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
