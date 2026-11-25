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
   const suffix=manufacturer==="O'Connor & Sons / Pharma"?'-special':url.searchParams.get('stock_filter')==='low'&&manufacturer==='Acme'?'-filtered':'';
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
 const visibleIds=()=>evaluate("jQuery(document.querySelector('table[aria-label=Products]')).DataTable().rows({search:'applied'}).nodes().toArray().map(row=>Number(row.cells[0].textContent.trim())).sort((a,b)=>a-b)");
 for(const width of [1440,768,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  const check=(label,expression)=>test(width+': '+label,expression);
  for(const role of ['admin','cashier']){
   await evaluate('window.__oldProducts=true');await send('Page.navigate',{url:origin+'/'+role+'/products?branch_id=1'});
   await wait("!window.__oldProducts && document.readyState==='complete' && !!window.jQuery && !!jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(document.querySelector('table[aria-label=Products]'))");
   await check(role+' stock and manufacturer controls present',"document.querySelectorAll('form[aria-label=\"Product filters\"] select').length===3 && document.getElementById('products-stock-status').options.length===5");
   await check(role+' manufacturer choices are scoped and deduplicated',"JSON.stringify([...document.getElementById('products-manufacturer').options].map(o=>o.value))===JSON.stringify(['','Acme',\"O'Connor & Sons / Pharma\",'Unilab'])");
   await check(role+' controls have accessible labels and touch sizes',"[...document.querySelectorAll('form[aria-label=\"Product filters\"] select')].every(e=>!!document.querySelector('label[for=\"'+e.id+'\"]')&&e.getBoundingClientRect().height>=44)");
   const ids=await visibleIds();assert.deepEqual(ids,[1,2,3,4,5,9]);checks.push(width+': '+role+' DataTables receives all branch records');
   await evaluate("void jQuery(document.querySelector('table[aria-label=Products]')).DataTable().order([4,'desc']).draw()");
   await check(role+' manufacturer sorting stays available',"Number(jQuery(document.querySelector('table[aria-label=Products]')).DataTable().rows({search:'applied',order:'applied'}).nodes().toArray()[0].cells[0].textContent.trim())===4");
   await evaluate("var s=document.querySelector('.dt-search input');s.value='Unilab';s.dispatchEvent(new Event('input',{bubbles:true}));");
   await wait("jQuery(document.querySelector('table[aria-label=Products]')).DataTable().rows({search:'applied'}).count()===1");
   assert.deepEqual(await visibleIds(),[4]);checks.push(width+': '+role+' DataTables quick search remains functional');
   await evaluate("var s=document.querySelector('.dt-search input');s.value='';s.dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('products-medicine-type').value='branded';document.getElementById('products-stock-status').value='low';document.getElementById('products-manufacturer').value='Acme';document.querySelector('form[aria-label=\"Product filters\"] button[type=submit]').click();");
   await wait("document.readyState==='complete' && location.search.includes('manufacturer=Acme') && !!window.jQuery && jQuery.fn.dataTable?.isDataTable(document.querySelector('table[aria-label=Products]')) && document.getElementById('products-stock-status').value==='low'");
   await check(role+' GET applies all filters and keeps branch',"new URL(location.href).searchParams.get('branch_id')==='1' && new URL(location.href).searchParams.get('stock_filter')==='low' && new URL(location.href).searchParams.get('medicine_type')==='branded' && new URL(location.href).searchParams.get('manufacturer')==='Acme'");
   assert.deepEqual(await visibleIds(),[2,9]);checks.push(width+': '+role+' filtered rows match stock manufacturer and type');
   await check(role+' page has no horizontal overflow','document.documentElement.scrollWidth<=innerWidth');
   await evaluate("window.scrollTo(0,document.querySelector('form[aria-label=\"Product filters\"]').getBoundingClientRect().top+scrollY-135)");await delay(250);
   fs.writeFileSync(path.join(dir,'filters-'+role+'-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
   await evaluate("[...document.querySelectorAll('form[aria-label=\"Product filters\"] a')].find(a=>a.textContent.includes('Clear filters')).click()");
   await wait("document.readyState==='complete' && location.search==='?branch_id=1' && !!window.jQuery && jQuery.fn.dataTable?.isDataTable(document.querySelector('table[aria-label=Products]')) && document.getElementById('products-stock-status').value===''");
   await check(role+' clear resets selections while preserving branch',"document.getElementById('products-stock-status').value==='' && document.getElementById('products-manufacturer').value==='' && document.getElementById('products-medicine-type').value===''");
   assert.deepEqual(await visibleIds(),[1,2,3,4,5,9]);checks.push(width+': '+role+' clear restores branch catalog');
   await evaluate("document.getElementById('products-stock-status').value='in';document.getElementById('products-manufacturer').value=\"O'Connor & Sons / Pharma\";document.querySelector('form[aria-label=\"Product filters\"] button[type=submit]').click()");
   await wait("document.readyState==='complete' && location.search.includes('manufacturer=') && document.getElementById('products-manufacturer').value===\"O'Connor & Sons / Pharma\" && !!window.jQuery && jQuery.fn.dataTable?.isDataTable(document.querySelector('table[aria-label=Products]'))");
   assert.deepEqual(await visibleIds(),[5]);checks.push(width+': '+role+' manufacturer punctuation survives GET submission');
  }
 }
 assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,application_posts:0,synthetic_posts:posts.length,widths:[1440,768,390],reduced_motion:true},null,2));console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
