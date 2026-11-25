const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=process.cwd(),dir=__dirname,profile=path.join(dir,'chrome-'+Date.now());
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let server,chrome,ws,origin;const checks=[],exceptions=[],posts=[];
const rewrite=text=>text.replace(/https?:\/\/[^\s"'<>]*?\/(?=(?:cashier\/sales|presence\/|session\/|server-time))/g,origin+'/').replace(/window\._siteUrl = "[^"]*";/,'window._siteUrl = '+JSON.stringify(origin+'/')+';');
(async()=>{
 server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(req.method==='POST'){posts.push(url.pathname);res.writeHead(422,{'Content-Type':'application/json'});res.end('{"success":false,"message":"Synthetic fixture accepts no POSTs"}');return;}
  if(url.pathname.endsWith('/checkout-status')){res.setHeader('Content-Type','application/json');res.end('{"ok":true,"found":false}');return;}
  const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir;
  const file=path.resolve(base,'.'+decodeURIComponent(url.pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html; charset=utf-8');res.end(file.endsWith('.html')?rewrite(fs.readFileSync(file,'utf8')):fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let port;for(let i=0;i<100;i++){const file=path.join(profile,'DevToolsActivePort');if(fs.existsSync(file)){port=Number(fs.readFileSync(file,'utf8').split('\n')[0]);break;}await delay(100);}if(!port)throw Error('Chrome did not start');
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
 let serial=0;const pending=new Map();ws.addEventListener('message',event=>{const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const wait=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out: '+expression);};
 const test=async(label,expression)=>{const actual=await evaluate(expression);if(actual!==true)console.error(JSON.stringify({label,actual,state:await evaluate("({reference:document.getElementById('referenceNoInput')?.outerHTML,referenceError:document.getElementById('referenceNoInput')?.validationMessage,completeDisabled:document.getElementById('completeSaleBtn')?.disabled,search:document.getElementById('productSearch')?.value})")}));assert.equal(actual,true,label);checks.push(label);};
 const go=async name=>{
  await evaluate("try{localStorage.clear();sessionStorage.clear();}catch(e){}window.__oldPosDocument=true");await send('Page.navigate',{url:origin+'/'+name+'.html'});await wait("!window.__oldPosDocument && document.readyState==='complete' && !!window.PharxmacoModal && !!document.getElementById('productSearch')");
  await evaluate("window.ref=document.getElementById('referenceNoInput');window.amount=document.getElementById('amountPaidInput');window.complete=document.getElementById('completeSaleBtn');window.setRef=value=>{ref.value=value;ref.dispatchEvent(new Event('input',{bubbles:true}));};window.setSearch=value=>{const input=document.getElementById('productSearch');input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));};window.visibleIds=()=>[...document.querySelectorAll('.prod-card')].filter(c=>getComputedStyle(c).display!=='none').map(c=>Number(c.dataset.pid)).sort();window.setType=value=>{const input=document.getElementById('posMedicineType');input.value=value;input.dispatchEvent(new Event('change',{bubbles:true}));};");
 };
 await send('Page.enable');await send('Runtime.enable');
 for(const width of [1440,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});const check=(label,expr)=>test(width+': '+label,expr);
  await go('pos-catalog');
  await check('Generic branded and supply all appear initially',"JSON.stringify(visibleIds())==='[1,2,3]'");
  await evaluate("setType('branded')");
  await check('Branded filter shows Bioflu',"JSON.stringify(visibleIds())==='[2]' && !document.querySelector('.prod-card[data-pid=\"2\"]').classList.contains('disabled')");
  await evaluate("document.querySelector('.prod-card[data-pid=\"2\"]').scrollIntoView({block:'center'})");await delay(350);fs.writeFileSync(path.join(dir,'pos-branded-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
  await evaluate("setSearch('Paracetamol')");await delay(220);
  await check('Ingredient search retains branded matching medicine',"JSON.stringify(visibleIds())==='[2]'");
  await evaluate("setType('');setSearch('paracetamol')");await delay(220);
  await check('Ingredient finds generic and branded',"JSON.stringify(visibleIds())==='[1,2]'");
  await evaluate("setSearch('BRD-BIOFLU')");await delay(220);
  await check('SKU search finds branded item',"JSON.stringify(visibleIds())==='[2]'");
  await evaluate("document.getElementById('resetProductFilters').click()");
  await check('Reset restores every available product',"JSON.stringify(visibleIds())==='[1,2,3]' && document.getElementById('productSearch').value==='' && document.getElementById('posMedicineType').value===''");
  await evaluate("setType('branded');setSearch('unmatched-search')");await delay(220);
  await check('Filtered empty state remains visible',"visibleIds().length===0 && !document.getElementById('productFilterEmpty').hidden");
  await go('pos-query');
  await check('Server search remains visible in search input',"document.getElementById('productSearch').value==='Paracetamol'");
  await check('Initial client filtering matches retained search',"JSON.stringify(visibleIds())==='[1,2]'");
  await evaluate("history.replaceState(null,'','?q=Paracetamol');document.getElementById('clearProductSearch').click()");
  await check('Clearing retained search restores all sellable products',"JSON.stringify(visibleIds())==='[1,2,3]'");
  await check('Clearing retained search removes URL keyword',"!new URL(location.href).searchParams.has('q')");
  await go('pos-empty-branded');await evaluate("setType('branded')");
  await check('Unavailable branded stock gives branch stock expiry guidance',"visibleIds().length===0 && !document.getElementById('productFilterEmpty').hidden && /branch|stock|expiry/i.test(document.getElementById('productFilterEmpty').textContent)");
  await go('pos-catalog');
  await evaluate("document.querySelector('[data-pos-target=payment]')?.click();amount.value='500';amount.dispatchEvent(new Event('input',{bubbles:true}))");
  await check('Cash tender remains editable and reference optional','!amount.readOnly && !ref.required');
  await evaluate("document.querySelector('.pay-method[data-method=gcash]').click()");
  await check('GCash fills exact amount and requires reference',"amount.readOnly && amount.value==='112.00' && ref.required && complete.disabled");
  for(const value of ['1234567890','123456789012','12345678901234','ABCDEFGHIJKLM','123 4567890123']){
   await evaluate('setRef('+JSON.stringify(value)+')');await check('GCash rejects '+JSON.stringify(value),'!ref.checkValidity() && complete.disabled');
  }
  await evaluate("setRef('0001234567890')");
  await check('GCash accepts13digits and keeps leading zeros',"ref.checkValidity() && ref.value==='0001234567890' && !complete.disabled");
  await evaluate("document.querySelector('.pay-method[data-method=card]').click();setRef('A1B2C3')");
  await check('Card approval can be alphanumeric6characters','ref.checkValidity() && !complete.disabled && amount.readOnly');
  await evaluate("setRef('000123456789')");
  await check('Card terminal retrieval reference can be12digits','ref.checkValidity() && !complete.disabled');
  await evaluate("setRef('')");await check('Card requires a reference','!ref.checkValidity() && complete.disabled');
  await evaluate("setRef('A1B2C3');ref.scrollIntoView({block:'center'})");await delay(350);fs.writeFileSync(path.join(dir,'pos-card-reference-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
  await evaluate("document.querySelector('.pay-method[data-method=cash]').click();setRef('');amount.value='500';amount.dispatchEvent(new Event('input',{bubbles:true}))");
  await check('Switching back to cash removes noncash validation','!ref.required && ref.checkValidity() && !amount.readOnly && !complete.disabled');
  await check('Mobile page has no horizontal overflow','document.documentElement.scrollWidth<=innerWidth');
 }
 assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);fs.writeFileSync(path.join(dir,'pos-browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,application_posts:0,synthetic_posts:posts.length,widths:[1440,390]},null,2));console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
