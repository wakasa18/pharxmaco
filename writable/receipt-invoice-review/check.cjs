const fs = require('fs');
const path = require('path');
const http = require('http');
const assert = require('assert/strict');
const {spawn} = require('child_process');
const root = process.cwd(), dir = __dirname;
const profile = path.join(dir, 'chrome-profile-' + Date.now());
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let chrome, ws, server;
(async () => {
 server = http.createServer((req,res) => {
  const url = new URL(req.url, 'http://localhost');
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
 const checks=[];
 for (const variant of ['unconfigured','non-vat','vat','senior','pwd','reprint','legacy']) {
  await send('Page.navigate',{url:`http://127.0.0.1:${port}/${variant}.html`});
  for(let i=0;i<60;i++){if(await evaluate("!!document.querySelector('.receipt-paper') && document.readyState === 'complete'"))break;await delay(50);}
  const content=await evaluate("document.querySelector('.receipt-paper').textContent");
  assert.ok(content.includes('SALES INVOICE'));
  assert.ok(content.includes('Generic: Paracetamol'));
  assert.ok(content.includes('Strength: 500 mg'));
  assert.ok(content.includes('123 Sample Street, Barangay San Jose, Santa Maria, Bulacan'));
  if(variant==='unconfigured') {assert.ok(content.includes('Not configured'));assert.ok(!content.includes('123-456-789-000'));}
  if(variant==='legacy')assert.ok(content.includes('Not recorded'));
  if(variant==='senior')assert.ok(content.includes('Senior Citizen'));
  if(variant==='pwd')assert.ok(content.includes('PWD'));
  if(variant==='reprint')assert.ok(content.includes('REPRINT'));
  if(variant==='vat') {
   for(const phrase of ['VATable sales','VAT amount (12%)','VAT-exempt sales','Zero-rated sales'])assert.ok(content.includes(phrase));
   assert.ok(content.includes('₱90.00'));assert.ok(content.includes('₱10.80'));
  }
  if(['non-vat','senior','pwd'].includes(variant))assert.ok(content.includes('Non-VAT sales'));
  for(const width of [320,390,768,1440]) {
   await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
   const overflow=await evaluate("({page:document.documentElement.scrollWidth>document.documentElement.clientWidth,paper:document.querySelector('.receipt-paper').scrollWidth>document.querySelector('.receipt-paper').clientWidth})");
   assert.equal(overflow.page,false,variant+' page '+width);assert.equal(overflow.paper,false,variant+' paper '+width);
   checks.push({variant,width,overflow});
   if(width===1440&&['unconfigured','vat'].includes(variant)) {
    const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
    fs.writeFileSync(path.join(dir,variant+'-desktop.png'),Buffer.from(shot.data,'base64'));
   }
  }
  for(const paper of ['58','80']) {
   await evaluate(`document.querySelector('[name="receipt-paper-width"][value="${paper}"]').click()`);
   await send('Emulation.setEmulatedMedia',{media:'print'});
   const print=await evaluate(`({width:document.querySelector('.receipt-paper').getBoundingClientRect().width,overflow:document.querySelector('.receipt-paper').scrollWidth>document.querySelector('.receipt-paper').clientWidth,bg:getComputedStyle(document.querySelector('.receipt-paper')).backgroundColor,ink:getComputedStyle(document.querySelector('.receipt-items td')).color})`);
   assert.ok(Math.abs(print.width-Number(paper)*96/25.4)<1);
   assert.equal(print.overflow,false);assert.equal(print.bg,'rgb(255, 255, 255)');assert.equal(print.ink,'rgb(0, 0, 0)');
   checks.push({variant,paper,print});
   if(variant==='vat') {
    const box=await evaluate("(()=>{const r=document.querySelector('.receipt-paper').getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,scale:2}})()");
    const shot=await send('Page.captureScreenshot',{format:'png',clip:box,captureBeyondViewport:true});
    fs.writeFileSync(path.join(dir,'vat-print-'+paper+'mm.png'),Buffer.from(shot.data,'base64'));
   }
   await send('Emulation.setEmulatedMedia',{media:'screen'});
  }
 }
 await evaluate("window.printCount=0;window.print=()=>window.printCount++;document.querySelector('[data-print-receipt]').click()");
 assert.equal(await evaluate('window.printCount'),1);
 fs.writeFileSync(path.join(dir,'checks.json'),JSON.stringify(checks,null,2));
 console.log(`Passed ${checks.length} invoice screen/print checks and print-button check.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
