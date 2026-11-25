const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');const root=process.cwd(),dir=__dirname,profile=path.join(dir,'chrome-'+Date.now());
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));let server,chrome,ws,origin;const checks=[],exceptions=[],posts=[];
const rewrite=text=>text.replace(/https?:\/\/[^\s"'<>]*?\/(?=(?:cashier\/sales|presence\/|session\/|server-time))/g,origin+'/').replace(/window\._siteUrl = "[^"]*";/,'window._siteUrl = '+JSON.stringify(origin+'/')+';');
(async()=>{
 server=http.createServer((req,res)=>{
  if(req.method==='POST'){posts.push(req.url);res.writeHead(422);res.end();return;}
  const pathname=new URL(req.url,'http://localhost').pathname;
  const base=pathname.startsWith('/assets/')?path.join(root,'public'):dir;
  const file=path.resolve(base,'.'+decodeURIComponent(pathname));
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
 const wait=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out: '+expression+' '+JSON.stringify(await evaluate("({feedback:document.querySelector('.product-filter-feedback')?.textContent,exception:window.__lastException,location:location.href})")));};
 const test=async(label,expression)=>{const actual=await evaluate(expression);if(actual!==true)console.error(JSON.stringify({label,actual,state:await evaluate("({reference:document.getElementById('referenceNoInput')?.outerHTML,referenceError:document.getElementById('referenceNoInput')?.validationMessage,completeDisabled:document.getElementById('completeSaleBtn')?.disabled,search:document.getElementById('productSearch')?.value})")}));assert.equal(actual,true,label);checks.push(label);};
  await send('Page.enable');await send('Runtime.enable');await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});



 await send('DOM.enable');
 const navigate=async name=>{await evaluate('window.__oldFixture=true');await send('Page.navigate',{url:origin+'/'+name+'.html'});await wait("!window.__oldFixture && document.readyState==='complete' && !!document.querySelector('.pos-shift-notice')");};
 async function geometry(){
  const documentNode=await send('DOM.getDocument');const query=await send('DOM.querySelector',{nodeId:documentNode.root.nodeId,selector:'.pos-shift-notice'});const node=await send('DOM.describeNode',{nodeId:query.nodeId});const before=node.node.pseudoElements.find(p=>p.pseudoType==='before');const box=(await send('DOM.getBoxModel',{backendNodeId:before.backendNodeId})).model.border;
  const message=await evaluate("(()=>{const r=document.querySelector('.pos-shift-message').getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height}})()");
  return {icon:{left:box[0],top:box[1],right:box[2],bottom:box[5]},message,delta:Math.abs((box[1]+box[5])/2-(message.y+message.height/2))};
 }
 const snapshot=async name=>fs.writeFileSync(path.join(dir,name+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});await navigate('open-before');await evaluate("document.documentElement.classList.remove('dark')");
 const before=await geometry();assert.ok(before.delta>6,'reproduce original icon/text offset');checks.push('original open banner reproduces icon/text offset');await snapshot('before-1440');
 const measurements=[];
 for(const width of [1440,768,520,390,320]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  for(const theme of ['light','dark'])for(const state of ['open','closed']){
   await navigate(state);await evaluate("document.documentElement.classList.toggle('dark',"+(theme==='dark')+")");
   const prefix=width+' '+theme+' '+state+': ';
   const aligned=await geometry();assert.ok(aligned.delta<=1,prefix+'icon aligns with message');assert.ok(aligned.icon.right<aligned.message.x,prefix+'separate icon and message');measurements.push({width,theme,state,...aligned});checks.push(prefix+'icon and text align without overlap');
   await test(prefix+'notice retains accessible status and state text',"document.querySelector('.pos-shift-notice').getAttribute('role')==='status'&&document.querySelector('.pos-shift-message strong').textContent==="+JSON.stringify(state==='open'?'Shift #10 is open.':'Start a shift to begin selling.'));
   await test(prefix+'action remains readable and touch sized',"(()=>{const a=document.querySelector('.pos-shift-action'),r=a.getBoundingClientRect();return r.height>=44&&a.scrollWidth<=a.clientWidth&&a.href.includes("+JSON.stringify(state==='open'?'cashier/shifts/10':'cashier/start-shift')+")})()");
   await test(prefix+'layout follows breakpoint',"(()=>{const a=document.querySelector('.pos-shift-action').getBoundingClientRect(),m=document.querySelector('.pos-shift-message').getBoundingClientRect();return "+(width<=520?'a.top>=m.bottom&&a.width>m.width':'a.left>=m.right')+"})()");
   await test(prefix+'banner fits page width',"(()=>{const r=document.querySelector('.pos-shift-notice').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&document.querySelector('.pos-shift-notice').scrollWidth<=document.querySelector('.pos-shift-notice').clientWidth})()");
   if(theme==='light'&&[1440,390].includes(width)){await evaluate("window.scrollTo(0,document.querySelector('.pos-shift-notice').getBoundingClientRect().top+scrollY-135)");await delay(150);await snapshot('after-'+state+'-'+width);}
  }
 }
 assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({passed:checks.length,checks,before,measurements,exceptions,posts:posts.length,widths:[1440,768,520,390,320],themes:['light','dark']},null,2));console.log(JSON.stringify({passed:checks.length,original_offset:before.delta,maximum_fixed_offset:Math.max(...measurements.map(m=>m.delta)),exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
