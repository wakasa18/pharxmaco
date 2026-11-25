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
 const visit=async name=>{await evaluate('window.__oldForm=true');await send('Page.navigate',{url:origin+'/'+name+'.html'});await wait("!window.__oldForm && document.readyState==='complete' && !!window.PharxmacoPaymentReference");};
 for(const width of [1440,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  const check=(label,expression)=>test(width+': '+label,expression);
  await visit('exchange-form');
  await evaluate("window.ref=document.getElementById('exchangeReference');window.method=document.getElementById('exchangeMethod');window.setMethod=value=>{method.value=value;method.dispatchEvent(new Event('change',{bubbles:true}));};window.setRef=value=>{ref.value=value;ref.dispatchEvent(new Event('input',{bubbles:true}));};");
  await check('Exchange cash reference optional','!ref.required && ref.checkValidity()');
  await evaluate("setMethod('gcash');setRef('')");
  await check('Even exchange may leave GCash reference blank','!ref.required && ref.checkValidity()');
  await evaluate("setRef('1234567890')");await check('Exchange rejects short supplied GCash reference','!ref.checkValidity()');
  await evaluate("setRef('0001234567890')");await check('Exchange accepts13digit GCash confirmation','ref.checkValidity() && ref.maxLength===13');
  await evaluate("setMethod('card');setRef('0bea058b-ae8e-4bfc-90d0-7fa0ff6fa5a6')");await check('Exchange accepts provider UUID reference','ref.checkValidity() && ref.maxLength===100');
  await evaluate("setRef('REF@123')");await check('Exchange rejects unsupported reference characters','!ref.checkValidity()');
  await evaluate("setRef('05754C');ref.scrollIntoView({block:'center'})");await delay(250);fs.writeFileSync(path.join(dir,'exchange-reference-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
  await check('Exchange reference helper fits page','document.documentElement.scrollWidth<=innerWidth');
  await visit('sale-correction-plain');
  await evaluate("window.ref=document.getElementById('referenceNo');window.method=document.getElementById('paymentMethod');window.setMethod=value=>{method.value=value;method.dispatchEvent(new Event('change',{bubbles:true}));};window.setRef=value=>{ref.value=value;ref.dispatchEvent(new Event('input',{bubbles:true}));};setMethod('gcash')");
  await check('Correction changed method requires reference','ref.required && !ref.checkValidity()');
  await evaluate("setRef('0001234567890')");await check('Correction accepts GCash confirmation','ref.checkValidity()');
  await evaluate("setMethod('card');setRef('00001234')");await check('Correction accepts8character provider approval','ref.checkValidity() && ref.maxLength===100');
  await evaluate("setRef('invalid<script>')");await check('Correction rejects markup','!ref.checkValidity()');
  await visit('sale-correction-legacy');
  await evaluate("window.ref=document.getElementById('referenceNo');window.setRef=value=>{ref.value=value;ref.dispatchEvent(new Event('input',{bubbles:true}));};");
  await check('Unchanged historical reference remains valid',"ref.value==='OLD-LEGACY-REFERENCE' && ref.checkValidity() && !ref.required && !ref.hasAttribute('pattern')");
  await evaluate("setRef('SHORT')");await check('Changed historical reference uses current format','!ref.checkValidity() && ref.required');
  await evaluate("setRef('OLD-LEGACY-REFERENCE')");await check('Restoring original reference retains history','ref.checkValidity() && !ref.required');
  await evaluate("const q=document.querySelector('.qty-input');q.value=Number(q.value)+1;q.dispatchEvent(new Event('input',{bubbles:true}));");
  await check('Financial correction requires current reference format','!ref.checkValidity() && ref.required && ref.maxLength===13');
  await evaluate("setRef(' 0001234567890 ');ref.dispatchEvent(new FocusEvent('blur'));ref.scrollIntoView({block:'center'})");
  await check('Reference trimming retains leading zeros',"ref.value==='0001234567890' && ref.checkValidity()");
  await check('Correction reference helper fits page','document.documentElement.scrollWidth<=innerWidth');
  await delay(250);fs.writeFileSync(path.join(dir,'correction-reference-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
 }
 assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);fs.writeFileSync(path.join(dir,'after-sale-browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,application_posts:0,synthetic_posts:posts.length,widths:[1440,390]},null,2));console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
