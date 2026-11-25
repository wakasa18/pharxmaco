const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=process.cwd(),dir=__dirname,profile=path.join(dir,'chrome-'+Date.now()),delay=ms=>new Promise(r=>setTimeout(r,ms));
let server,chrome,ws;const checks=[],posts=[],errors=[];
(async()=>{
 server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/session/activity'){res.setHeader('Content-Type','application/json');res.end('{"success":true}');return;}
  if(req.method==='POST'&&url.pathname==='/fixture-stock'){let body='';for await(const c of req)body+=c;posts.push(Object.fromEntries(new URLSearchParams(body)));res.setHeader('Content-Type','text/html');res.end('<!doctype html><main data-saved>Fixture saved</main>');return;}
  const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir,file=path.resolve(base,'.'+decodeURIComponent(url.pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html; charset=utf-8');res.end(fs.readFileSync(file));
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let port;for(let i=0;i<100;i++){const file=path.join(profile,'DevToolsActivePort');if(fs.existsSync(file)){port=Number(fs.readFileSync(file,'utf8').split('\n')[0]);break;}await delay(100);}
 if(!port)throw Error('Chrome did not start');
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
 let serial=0;const pending=new Map();ws.addEventListener('message',event=>{const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const wait=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(50);}throw Error('UI timeout: '+expression);};
 const field=async(name,value)=>evaluate(`(()=>{const input=document.querySelector('.stock-update-form [name="${name}"]');input.value=${JSON.stringify(value)};input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
 await send('Page.enable');await send('Runtime.enable');await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
 await send('Page.navigate',{url:origin+'/products.html'});await wait("document.readyState==='complete' && !!document.querySelector('.dt-toolbar')");
 assert.deepEqual(await evaluate("[...document.querySelectorAll('.stock-current')].map(n=>n.textContent.trim())"),['Current stock: 35 boxes','Current stock: 1 tablet','Current stock: 2 pieces','Current stock: 0 bottles','Current stock: 50 mL']);checks.push('correct singular/plural labels and unchanged measurement abbreviation');
 assert.equal(await evaluate("[...document.querySelectorAll('.stock-update-form button')].every(b=>b.disabled)"),true);checks.push('blank quantity/reason keeps actions disabled');
 await field('stock','2');assert.equal(await evaluate("document.querySelector('.stock-submit').disabled"),true);
 for(const reason of ['','   ','ab','  ab  ']){await field('remarks',reason);assert.equal(await evaluate("document.querySelector('.stock-submit').disabled"),true);}checks.push('quantity alone and blank/short trimmed reasons cannot submit');
 await field('remarks','Delivery DR-1024');assert.equal(await evaluate("document.querySelector('.stock-submit').disabled"),false);assert.match(await evaluate("document.querySelector('.stock-preview').textContent"),/35 \+ 2 = 37 boxes/);checks.push('valid positive quantity and reason enables action with unit-aware balance');
 for(const quantity of ['0','-1','1.5','1000001','1e2']){await field('stock',quantity);assert.equal(await evaluate("document.querySelector('.stock-submit').disabled"),true);}checks.push('zero/negative/fractional/over-limit/exponent quantities rejected');
 await field('stock_mode','remove');await field('stock','36');assert.equal(await evaluate("document.querySelector('.stock-submit').disabled"),true);assert.match(await evaluate("document.querySelector('.stock-preview').textContent"),/Cannot remove/);checks.push('removal remains limited to available stock');
 await field('stock','34');assert.equal(await evaluate("document.querySelector('.stock-submit').disabled"),false);assert.match(await evaluate("document.querySelector('.stock-preview').textContent"),/= 1 box after saving/);checks.push('removal preview uses singular unit');
 assert.equal(await evaluate("document.querySelector('.stock-expiry time').textContent"),'April 7, 2026');assert.equal(await evaluate("document.querySelector('.stock-expiry .badge').textContent"),'Expired');checks.push('expiry date and status grouped together');
 await evaluate("void jQuery('table.js-datatable').DataTable().order([11,'asc']).draw()");assert.deepEqual(await evaluate("jQuery('table.js-datatable').DataTable().rows({order:'applied'}).indexes().toArray().filter(i=>i<3)"),[0,1,2]);checks.push('date sorting remains functional');
 await evaluate("void jQuery('table.js-datatable').DataTable().order([0,'asc']).draw()");
 for(const width of [320,390,768,1440])for(const dark of [false,true]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);await delay(200);
  await evaluate("document.querySelector('.stock-update-form').scrollIntoView({block:'center',inline:'center'})");await delay(100);
  assert.equal(await evaluate("document.documentElement.scrollWidth>innerWidth+1 || document.querySelector('.stock-update-form').scrollWidth>document.querySelector('.stock-update-form').clientWidth+1"),false);
  assert.equal(await evaluate("{const select=document.querySelector('.stock-update-form select');const css=getComputedStyle(select);const ctx=document.createElement('canvas').getContext('2d');ctx.font=css.font;ctx.measureText('Remove stock').width+parseFloat(css.paddingLeft)+parseFloat(css.paddingRight)<=select.clientWidth;}"),true);
  checks.push('no clipping/overflow '+width+'/'+dark);
  if([390,1440].includes(width)){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'stock-'+width+'-'+dark+'.png'),Buffer.from(shot.data,'base64'));}
 }
 await field('stock_mode','add');await field('stock','2');await field('remarks','   Delivery DR-1024   ');
 await evaluate("document.querySelector('.stock-update-form').requestSubmit()");await wait("!!document.querySelector('[data-saved]')");
 assert.equal(posts.length,1);assert.equal(posts[0].stock,'2');assert.equal(posts[0].remarks,'Delivery DR-1024');assert.equal(posts[0].stock_mode,'add');assert.ok(posts[0].stock_token);checks.push('valid native POST trims reason and retains stock token');
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,fixture_posts:posts.length,javascript_errors:errors,application_database_accessed:false},null,2));console.log(`Passed ${checks.length} stock form scenarios; no application database access.`);
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
