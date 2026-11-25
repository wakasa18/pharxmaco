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
  if(url.pathname==='/admin/products'||url.pathname==='/cashier/products'){
   const role=url.pathname.split('/')[1];const manufacturer=url.searchParams.get('manufacturer')||'';
   const stock=url.searchParams.get('stock_filter')||'',medicine=url.searchParams.get('medicine_type')||'';
   const suffix=manufacturer==="O'Connor & Sons / Pharma"?'-special':manufacturer==='Unilab'?(stock==='out'?'-empty':stock==='in'?'-unilab-in':'-unilab'):manufacturer==='Acme'?(stock==='low'?(medicine==='branded'?'-filtered':'-low'):'-manufacturer'):stock==='out'?'-out':'';
   res.setHeader('Content-Type','text/html; charset=utf-8');res.end(fs.readFileSync(path.join(dir,role+suffix+'.html')));return;
  }
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
  await send('Page.enable');await send('Runtime.enable');await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});

 const table="document.querySelector('table[aria-label=Products]')";
 const ready="!window.__oldProducts && document.readyState==='complete' && !!window.jQuery && !!jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable("+table+")";
 const visibleIds=()=>evaluate("jQuery("+table+").DataTable().rows({search:'applied'}).nodes().toArray().map(row=>Number(row.cells[0].textContent.trim())).sort((a,b)=>a-b)");
 const navigate=async url=>{await evaluate('window.__oldProducts=true');await send('Page.navigate',{url});await wait(ready);};
 const change=async(id,value,width)=>{
  await evaluate("var f=document.getElementById('products-filter-form');f.addEventListener('submit',()=>sessionStorage.setItem('submitted',JSON.stringify([...new FormData(f)])),{once:true});window.__oldProducts=true;"+
   (width<=900?"document.querySelector('.dt-mobile-filters').open=true;":"")+
   "var c=document.getElementById("+JSON.stringify(id+(width<=900?'-mobile':''))+");c.value="+JSON.stringify(value)+";c.dispatchEvent(new Event('change',{bubbles:true}));");
  await wait(ready);
 };
 for(const width of [1440,768,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  const check=(label,expression)=>test(width+': '+label,expression);
  for(const role of ['admin','cashier']){
   await navigate(origin+'/'+role+'/products?branch_id=1');
   await check(role+' server controls occupy matching header cells',"document.querySelector('.dt-filter-row').cells[4].querySelector('select').id==='products-manufacturer' && document.querySelector('.dt-filter-row').cells[10].querySelector('select').id==='products-stock-status'");
   await check(role+' only Medicine type remains above table',"document.querySelectorAll('#products-filter-form select').length===1 && !document.querySelector('[data-dt-filter-field]') && document.querySelectorAll('.dt-link-note').length===0");
   await check(role+' server controls retain GET form ownership',"['products-manufacturer','products-stock-status'].every(id=>document.getElementById(id).form===document.getElementById('products-filter-form')) && new FormData(document.getElementById('products-filter-form')).getAll('manufacturer').length===1 && new FormData(document.getElementById('products-filter-form')).getAll('stock_filter').length===1");
   await check(role+' manufacturer options remain branch scoped',"JSON.stringify([...document.getElementById('products-manufacturer').options].map(o=>o.value))===JSON.stringify(['','Acme',\"O'Connor & Sons / Pharma\",'Unilab'])");
   await check(role+' accessible desktop and mobile controls',"[...document.querySelectorAll('.dt-server-filter')].length===4 && [...document.querySelectorAll('.dt-server-filter')].every(e=>e.getAttribute('aria-label')?.startsWith('Filter by ')) && [...document.querySelectorAll('.dt-mobile-filters .dt-server-filter')].every(e=>!e.name)");
   await check(role+' mobile panel follows viewport',"(getComputedStyle(document.querySelector('.dt-mobile-filters')).display!=='none')==="+(width<=900));
   assert.deepEqual(await visibleIds(),[1,2,3,4,5,9]);checks.push(width+': '+role+' original rows preserved');
   await evaluate("void jQuery("+table+").DataTable().order([4,'desc']).draw()");
   await check(role+' header sorting still works',"Number(jQuery("+table+").DataTable().rows({order:'applied'}).nodes().toArray()[0].cells[0].textContent.trim())===4");
   await evaluate("var c=document.querySelector('.dt-filter-row').cells[12].querySelector('select');c.value=[...c.options].find(o=>o.value.toLowerCase()==='inactive').value;c.dispatchEvent(new Event('change',{bubbles:true}));");
   await wait("jQuery("+table+").DataTable().rows({search:'applied'}).count()===1");assert.deepEqual(await visibleIds(),[9]);checks.push(width+': '+role+' existing status column filter works');
   await evaluate("document.querySelector('.dt-clear').click()");assert.deepEqual(await visibleIds(),[1,2,3,4,5,9]);checks.push(width+': '+role+' quick reset restores original rows');
   await change('products-manufacturer','Acme',width);
   await check(role+' manufacturer applies immediately',"new URL(location.href).searchParams.get('manufacturer')==='Acme' && new URL(location.href).searchParams.get('branch_id')==='1'");
   assert.deepEqual(await visibleIds(),[1,2,3,9]);checks.push(width+': '+role+' manufacturer matches across all retained branch rows');
   await check(role+' mobile sync submits exactly one value',"JSON.parse(sessionStorage.getItem('submitted')).filter(([k])=>k==='manufacturer').length===1 && JSON.parse(sessionStorage.getItem('submitted')).some(([k,v])=>k==='manufacturer'&&v==='Acme') && document.getElementById('products-manufacturer-mobile').value==='Acme'");
   await evaluate("var s=document.querySelector('.dt-search input');s.value='FILTER-2';s.dispatchEvent(new Event('input',{bubbles:true}));");
   await wait("jQuery("+table+").DataTable().rows({search:'applied'}).count()===1");assert.deepEqual(await visibleIds(),[2]);checks.push(width+': '+role+' quick search works alongside server filters');
   await evaluate("document.querySelector('.dt-clear').click()");
   await check(role+' quick reset leaves server selections intact',"document.getElementById('products-manufacturer').value==='Acme' && document.getElementById('products-manufacturer-mobile').value==='Acme'");
   assert.deepEqual(await visibleIds(),[1,2,3,9]);checks.push(width+': '+role+' quick reset returns selected manufacturer rows');
   await change('products-stock-status','low',width);assert.deepEqual(await visibleIds(),[1,2,3,9]);checks.push(width+': '+role+' stock auto submit preserves manufacturer');
   await evaluate("window.__oldProducts=true;document.getElementById('products-medicine-type').value='branded';document.querySelector('#products-filter-form button[type=submit]').click()");await wait(ready);
   assert.deepEqual(await visibleIds(),[2,9]);checks.push(width+': '+role+' Medicine type combines with moved controls');
   await check(role+' header and mobile filters survive fewer than five rows',"document.querySelectorAll('.dt-filter-row .dt-server-filter').length===2 && document.querySelectorAll('.dt-mobile-filters .dt-server-filter').length===2 && document.getElementById('products-stock-status-mobile').value==='low'");
   if(width<=900)await evaluate("document.querySelector('.dt-mobile-filters').open=true");
   await check(role+' no page horizontal overflow','document.documentElement.scrollWidth<=innerWidth');
   await evaluate("window.scrollTo(0,document.querySelector('.dt-toolbar').getBoundingClientRect().top+scrollY-135)");await delay(200);
   fs.writeFileSync(path.join(dir,'columns-'+role+'-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
   await evaluate("window.__oldProducts=true;[...document.querySelectorAll('#products-filter-form a')].find(a=>a.textContent.includes('Clear filters')).click()");await wait(ready);
   await check(role+' Clear filters preserves branch and resets moved controls',"location.search==='?branch_id=1' && document.getElementById('products-stock-status').value==='' && document.getElementById('products-manufacturer').value==='' && document.getElementById('products-medicine-type').value===''");
   assert.deepEqual(await visibleIds(),[1,2,3,4,5,9]);checks.push(width+': '+role+' Clear restores branch catalog');
   await navigate(origin+'/'+role+'/products?branch_id=1&manufacturer=Unilab&stock_filter=out');
   await check(role+' empty results retain both column controls',"jQuery("+table+").DataTable().rows().count()===0 && document.querySelectorAll('.dt-filter-row .dt-server-filter').length===2 && document.getElementById('products-manufacturer').value==='Unilab' && document.getElementById('products-stock-status').value==='out'");
   await change('products-stock-status','in',width);assert.deepEqual(await visibleIds(),[4]);checks.push(width+': '+role+' changing an empty result filter restores records');
   await navigate(origin+'/'+role+'/products?branch_id=1');await change('products-manufacturer',"O'Connor & Sons / Pharma",width);assert.deepEqual(await visibleIds(),[5]);checks.push(width+': '+role+' punctuation survives automatic form submission');
  }
 }
 assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,application_posts:0,synthetic_posts:posts.length,widths:[1440,768,390],reduced_motion:true},null,2));console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
