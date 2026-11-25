const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=process.cwd(),dir=__dirname,profile=path.join(dir,'chrome-'+Date.now()),delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let server,chrome,ws;const posts=[],checks=[],errors=[];
(async()=>{
 server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/session/activity'){res.setHeader('Content-Type','application/json');res.end('{"success":true}');return;}
  if(req.method==='POST'&&url.pathname==='/fixture-submit'){
   let body='';for await(const chunk of req)body+=chunk;posts.push(Object.fromEntries(new URLSearchParams(body)));
   res.setHeader('Content-Type','text/html');res.end('<!doctype html><html><head><style>@view-transition{navigation:auto}</style></head><body><main data-saved>Fixture saved</main></body></html>');return;
  }
  const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir,file=path.resolve(base,'.'+decodeURIComponent(url.pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html; charset=utf-8');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let debugPort;for(let i=0;i<100;i++){const file=path.join(profile,'DevToolsActivePort');if(fs.existsSync(file)){debugPort=Number(fs.readFileSync(file,'utf8').split('\n')[0]);break;}await delay(100);}
 if(!debugPort)throw Error('Chrome did not start');
 const tabs=await(await fetch('http://127.0.0.1:'+debugPort+'/json')).json();ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
 let serial=0;const pending=new Map();
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const wait=async expression=>{for(let i=0;i<150;i++){if(await evaluate(expression))return;await delay(50);}throw Error('UI timeout: '+expression);};
 const open=async name=>{await send('Page.navigate',{url:origin+'/'+name+'.html'});await wait("document.readyState==='complete' && !!document.querySelector('form[data-submit-review] button[aria-haspopup]')");};
 const submit=()=>evaluate("document.querySelector('form[data-submit-review]').requestSubmit()");
 const opened=()=>wait("document.querySelector('.product-review-overlay').classList.contains('open')");
 const set=async(name,value)=>evaluate(`(()=>{const input=document.querySelector('form[data-submit-review]').elements.namedItem(${JSON.stringify(name)});input.value=${JSON.stringify(value)};input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
 const values=async name=>evaluate(`[...document.querySelector('[data-review-field=${name}]').querySelectorAll('dd')].map(node=>node.textContent)`);
 const close=()=>evaluate("document.querySelector('[data-review-back]').click()");
 const confirm=()=>evaluate("document.querySelector('[data-review-confirm]').click()");
 const check=(name)=>checks.push(name);
 await send('Page.enable');await send('Runtime.enable');
 await open('product-edit');
 assert.equal(await evaluate("document.querySelector('[name=price]').value"),'12.00');assert.equal(await evaluate("document.querySelector('[name=tax_treatment]').value"),'');
 await submit();await opened();assert.equal(await evaluate("document.querySelectorAll('[data-review-field]').length"),0);assert.equal(await evaluate("document.querySelector('[data-review-confirm]').disabled"),true);assert.equal(posts.length,0);check('unchanged product has no changes and cannot post');await close();
 await set('price','15.00');await set('medicine_type','generic');await set('supplier_id','');await submit();await opened();
 assert.equal(await evaluate("document.querySelectorAll('[data-review-field]').length"),3);assert.match((await values('price'))[0],/12\.00/);assert.match((await values('price'))[1],/15\.00/);assert.deepEqual(await values('medicine_type'),['Branded','Generic']);assert.match((await values('supplier_id'))[0],/Mercury Supply/);check('product review lists only changed values against saved branch');
 for(const width of [320,390,768,1440])for(const dark of [false,true]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:850,deviceScaleFactor:1,mobile:false});await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);await delay(350);
  assert.equal(await evaluate("document.documentElement.scrollWidth>innerWidth+1 || document.querySelector('.product-review-dialog').scrollWidth>document.querySelector('.product-review-dialog').clientWidth+1"),false);check('product review layout '+width+'/'+dark);
  if([390,1440].includes(width)){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'product-'+width+'-'+dark+'.png'),Buffer.from(shot.data,'base64'));}
 }
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});assert.equal(await evaluate("document.querySelector('.product-review-overlay').contains(document.activeElement)"),true);check('keyboard focus stays in review');
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
 assert.equal(await evaluate("document.querySelector('form[data-submit-review] button[type=submit]')===document.activeElement"),true);assert.equal(await evaluate("document.querySelector('[name=price]').value"),'15.00');check('Escape restores focus and preserves edits');
 await set('stock','121');await submit();assert.equal(await evaluate("document.querySelector('.product-review-overlay').classList.contains('open')"),false);assert.equal(await evaluate("document.querySelector('[name=stock_reason]').required"),true);assert.equal(posts.length,0);check('stock change requires reason before review');
 await set('stock_reason','Received delivery');await submit();await opened();assert.match(await evaluate("document.querySelector('[data-review-note]').textContent"),/Received delivery/);
 await set('price','16.00');await confirm();assert.equal(posts.length,0);assert.match((await values('price'))[1],/16\.00/);check('changed values after opening require a fresh review');
 await evaluate("document.querySelector('[data-review-confirm]').click();document.querySelector('[data-review-confirm]').click()");await wait("!!document.querySelector('[data-saved]')");assert.equal(posts.length,1);assert.equal(posts[0].price,'16.00');assert.equal(posts[0].old_stock,'120');assert.equal(posts[0].stock_reason,'Received delivery');assert.ok(Object.keys(posts[0]).some(key=>key.startsWith('csrf_')));check('one confirmed native product POST retains CSRF and stock concurrency fields');
 await open('product-redisplay');await submit();await opened();assert.match((await values('price'))[0],/12\.00/);assert.match((await values('price'))[1],/15\.00/);check('validation redisplay keeps saved product baseline');
 await open('discount-edit');await submit();await opened();assert.equal(await evaluate("document.querySelectorAll('[data-review-field]').length"),0);assert.equal(await evaluate("document.querySelector('[data-review-confirm]').disabled"),true);check('numeric formatting creates no false discount changes');await close();
 await set('applies_to','category');await set('category_id','2');await set('discount_type','fixed');await submit();await opened();
 assert.deepEqual(await values('discount_value'),['20%', '₱20.00']);assert.deepEqual(await values('category_id'),['Not applicable','Supplies']);assert.match((await values('product_id'))[0],/Biogesic/);assert.equal((await values('product_id'))[1],'Not applicable');assert.deepEqual(await values('max_discount_amount'),['₱50.00','Not applicable']);check('scope/type changes show removed target and maximum');
 for(const width of [320,390,1440]){await send('Emulation.setDeviceMetricsOverride',{width,height:850,deviceScaleFactor:1,mobile:false});await delay(350);assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);check('discount diff layout '+width);if(width!==320){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'discount-'+width+'.png'),Buffer.from(shot.data,'base64'));}}
 await confirm();await wait("!!document.querySelector('[data-saved]')");assert.equal(posts.length,2);assert.equal(posts[1].applies_to,'category');assert.equal(posts[1].category_id,'2');assert.equal(posts[1].product_id,undefined);assert.equal(posts[1].max_discount_amount,undefined);check('discount edit posts only enabled scope/type fields');
 await open('discount-create');await submit();assert.equal(await evaluate("document.querySelector('.product-review-overlay').classList.contains('open')"),false);check('invalid discount cannot open review');
 await set('discount_name','Autumn medicine offer');await set('discount_value','25');await set('applies_to','product');await set('product_id','2');await set('minimum_purchase','100');await set('max_discount_amount','50');await set('status','inactive');
 const untrusted='<img src=x onerror="window.reviewInjected=1">';await set('description',untrusted);await submit();await opened();
 assert.equal((await values('description'))[0],untrusted);assert.equal(await evaluate("!!document.querySelector('.product-review-body img') || !!window.reviewInjected"),false);assert.match(await evaluate("document.querySelector('[data-review-note]').textContent"),/automatically/);assert.match(await evaluate("document.querySelector('[data-review-note]').textContent"),/unavailable/);check('new offer reviews details, automatic scope and availability with safe text');
 await evaluate("document.querySelector('[data-submit-review-close]').click()");assert.equal(await evaluate("document.querySelector('[name=description]').value"),untrusted);assert.equal(posts.length,2);check('closing create review retains form without saving');
 await submit();await opened();await evaluate("document.querySelector('.product-review-overlay').click()");assert.equal(await evaluate("document.querySelector('.product-review-overlay').classList.contains('open')"),false);check('backdrop dismissal does not save');
 await submit();await opened();await confirm();await wait("!!document.querySelector('[data-saved]')");assert.equal(posts.length,3);assert.equal(posts[2].discount_value,'25');assert.equal(posts[2].description,untrusted);assert.equal(posts[2].status,'inactive');check('discount create saves once after confirmation');
 await open('discount-redisplay');await submit();await opened();assert.deepEqual(await values('discount_value'),['20%','30%']);assert.equal(await evaluate("document.querySelectorAll('[data-review-field]').length"),1);check('rejected discount edit compares against database baseline');
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,confirmed_fixture_posts:posts.length,javascript_errors:errors,application_database_accessed:false},null,2));
 console.log(`Passed ${checks.length} review scenarios; ${posts.length} confirmed fixture POSTs, no application database access.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
