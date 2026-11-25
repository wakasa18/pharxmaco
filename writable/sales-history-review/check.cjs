const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=process.cwd(),dir=__dirname,profile=path.join(dir,'chrome-'+Date.now()),delay=ms=>new Promise(r=>setTimeout(r,ms));
const requests=[],checks=[],errors=[];let server,chrome,ws;
(async()=>{
 server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/session/activity'){res.setHeader('Content-Type','application/json');res.end('{"success":true}');return;}
  let pathname=url.pathname;
  if(pathname==='/history.html') {requests.push(Object.fromEntries(url.searchParams));pathname=url.searchParams.has('date_from')||url.searchParams.has('date_to')?'/range.html':'/history.html';}
  const base=pathname.startsWith('/assets/')?path.join(root,'public'):dir,file=path.resolve(base,'.'+decodeURIComponent(pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html; charset=utf-8');res.end(fs.readFileSync(file));
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let port;for(let i=0;i<100;i++){const file=path.join(profile,'DevToolsActivePort');if(fs.existsSync(file)){port=Number(fs.readFileSync(file,'utf8').split('\n')[0]);break;}await delay(100);}
 if(!port)throw Error('Chrome did not start');
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
 let serial=0;const pending=new Map();
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{let r;try{r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});}catch(error){throw Error(expression+': '+error.message);}if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const wait=async expression=>{for(let i=0;i<150;i++){if(await evaluate(expression))return;await delay(50);}throw Error('UI timeout: '+expression);};
 const open=async url=>{await send('Page.navigate',{url:origin+url});await wait("document.readyState==='complete' && !!document.querySelector('.dt-toolbar')");};
 const check=name=>checks.push(name);
 await send('Page.enable');await send('Runtime.enable');
 // Disable navigation animation in this form/table check so rapid fixture changes do not abort native transitions.
 await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
 await open('/history.html');
 assert.equal(await evaluate("document.querySelectorAll('input[type=date]').length"),2);
 assert.equal(await evaluate("document.querySelectorAll('.dt-date').length"),0);
 assert.equal(await evaluate("document.querySelectorAll('.dt-cap-note').length"),0);
 assert.match(await evaluate("document.getElementById('sales-history-note').textContent"),/latest 1,000 sales/);check('single server range and quiet limit/search note');
 const count=requests.length;
 await evaluate("{const f=document.getElementById('sales-history-range');f.elements.date_from.value='2026-10-01';f.elements.date_to.value='2026-10-20';for(const input of f.querySelectorAll('input[type=date]'))input.dispatchEvent(new Event('change',{bubbles:true}));}");await delay(750);
 assert.equal(requests.length,count);check('date changes wait for explicit Apply');
 await evaluate("document.querySelector('#sales-history-range button[type=submit]').click()");
 await wait("location.search.includes('date_from=2026-10-01') && document.readyState==='complete' && !!document.querySelector('.dt-toolbar')");
 assert.deepEqual(requests.at(-1),{date_from:'2026-10-01',date_to:'2026-10-20'});
 assert.equal(await evaluate("document.querySelectorAll('.dt-link-note').length"),0);check('Apply sends database date range without duplicate link-filter banner');
 await evaluate("document.querySelector('.sales-history-actions a').click()");await wait("!location.search && document.readyState==='complete' && !!document.querySelector('.dt-toolbar')");
 assert.deepEqual(requests.at(-1),{});assert.equal(await evaluate("document.getElementById('history-date-from').value"),'');check('Clear returns to latest records with blank dates');
 const before=requests.length;
 await evaluate("{const f=document.getElementById('sales-history-range');f.elements.date_from.value='2026-10-20';f.elements.date_to.value='2026-10-01';f.elements.date_to.dispatchEvent(new Event('input',{bubbles:true}));f.requestSubmit();}");await delay(250);
 assert.equal(requests.length,before);assert.equal(await evaluate("document.getElementById('history-date-to').validity.customError"),true);check('reversed date range blocked before navigation');
 await evaluate("{const input=document.getElementById('history-date-from');input.value='2026-09-01';input.dispatchEvent(new Event('input',{bubbles:true}));}");
 assert.equal(await evaluate("document.getElementById('history-date-to').checkValidity()"),true);check('correcting either bound clears range error');
 await open('/history.html');
 await evaluate("{const input=document.querySelector('.dt-search input');input.value='DEMO-025';input.dispatchEvent(new Event('input',{bubbles:true}));}");await delay(250);
 assert.equal(await evaluate("jQuery('table.js-datatable').DataTable().rows({search:'applied'}).count()"),1);check('quick search filters loaded rows without navigation');
 await evaluate("document.querySelector('.dt-clear').click()");
 await evaluate("{const input=document.querySelector('.dt-length select');input.value='25';input.dispatchEvent(new Event('change',{bubbles:true}));}");
 assert.equal(await evaluate("jQuery('table.js-datatable').DataTable().rows({page:'current'}).count()"),25);check('rows-per-page and quick-filter clearing remain functional');
 await evaluate("void jQuery('table.js-datatable').DataTable().order([3,'desc']).draw()");
 assert.match(await evaluate("document.querySelector('table.js-datatable tbody tr td').textContent"),/DEMO-025/);check('numeric sorting preserved');
 await evaluate("{const ctl=document.querySelector('.dt-filter-row th:nth-child(2) select');ctl.value='Main Branch';ctl.dispatchEvent(new Event('change',{bubbles:true}));}");await delay(150);
 assert.equal(await evaluate("jQuery('table.js-datatable').DataTable().rows({search:'applied'}).count()"),13);check('column filtering preserved');
 await open('/history.html');
 for(const width of [320,390,768,1440])for(const dark of [false,true]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:false});await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);await delay(200);
  assert.equal(await evaluate("document.documentElement.scrollWidth>innerWidth+1 || document.getElementById('sales-history-range').scrollWidth>document.getElementById('sales-history-range').clientWidth+1"),false);check('responsive layout '+width+'/'+dark);
  if([390,1440].includes(width)){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'history-'+width+'-'+dark+'.png'),Buffer.from(shot.data,'base64'));}
 }
 await open('/empty.html');assert.equal(await evaluate("jQuery('table.js-datatable').DataTable().rows().count()"),0);assert.equal(await evaluate("document.querySelectorAll('input[type=date]').length"),2);check('empty results retain range, search and clear controls');
 await open('/generic.html?date_from=2026-10-01');assert.equal(await evaluate("document.querySelectorAll('.dt-date').length"),2);assert.equal(await evaluate("document.querySelectorAll('.dt-link-note').length"),1);check('other tables keep default browser date range and link note');
 await open('/history.html?branch_id=2');assert.equal(await evaluate("document.querySelectorAll('.dt-link-note').length"),1);check('non-date link filters remain disclosed');
 await send('Emulation.setScriptExecutionDisabled',{value:true});
 await send('Page.navigate',{url:origin+'/history.html'});await delay(500);
 await send('Emulation.setScriptExecutionDisabled',{value:false});
 assert.equal(await evaluate("document.querySelectorAll('.dt-toolbar').length"),0);
 await evaluate("{const f=document.getElementById('sales-history-range');f.elements.date_from.value='2026-10-01';f.elements.date_to.value='2026-10-20';f.requestSubmit();}");await wait("location.search.includes('date_from=2026-10-01') && document.readyState==='complete'");
 assert.deepEqual(requests.at(-1),{date_from:'2026-10-01',date_to:'2026-10-20'});check('native GET form works when page loaded without JavaScript');
 assert.deepEqual(errors,[]);
 fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,javascript_errors:errors,application_database_accessed:false},null,2));console.log(`Passed ${checks.length} sales-history scenarios; no application database access.`);
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
