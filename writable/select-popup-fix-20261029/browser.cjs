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


 const navigate=async filename=>{await evaluate('window.__oldFixture=true');await send('Page.navigate',{url:origin+'/'+filename});await wait("!window.__oldFixture && document.readyState==='complete' && !!window.__pxSelectPicker");};
 const snapshot=async name=>fs.writeFileSync(path.join(dir,name+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
 for(const width of [1440,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  const check=(label,expression)=>test(width+': '+label,expression);
  await navigate('before.html');await evaluate("document.documentElement.classList.remove('dark')");
  await check('old CSS reproduces the 160 x 14 floating spacer',"(()=>{const s=document.querySelector('.content .sp'),r=s.getBoundingClientRect();return getComputedStyle(s).position==='fixed'&&r.width===160&&r.height===14})()");
  await snapshot('before-'+width);
  for(const theme of ['light','dark']){
   await navigate('error.html');await evaluate("document.documentElement.classList.toggle('dark',"+(theme==='dark')+")");
   await check(theme+' flash error retains text',"document.querySelector('.content .alert').textContent.includes('Review the entered details')");
   await check(theme+' spacer has no popup styling',"[...document.querySelectorAll('.content .sp')].every(s=>getComputedStyle(s).position==='static'&&s.getBoundingClientRect().height===0&&getComputedStyle(s).borderTopWidth==='0px')");
   await check(theme+' no popup exists before a select is opened',"document.querySelectorAll('.phx-select-popup').length===0");
   await evaluate("var s=document.getElementById('refund-method');s.scrollIntoView({block:'center'});s.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,button:0}));");
   await check(theme+' dropdown opens with distinct popup class',"document.querySelectorAll('.phx-select-popup').length===1&&document.querySelector('.phx-select-popup .sp-list').getAttribute('role')==='listbox'&&getComputedStyle(document.querySelector('.phx-select-popup')).position==='fixed'");
   await check(theme+' real popup fits viewport',"(()=>{const r=document.querySelector('.phx-select-popup').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()");
   await evaluate("document.querySelector('.phx-select-popup .sp-list').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));");
   await check(theme+' Escape closes popup',"!document.querySelector('.phx-select-popup')&&!document.getElementById('refund-method').hasAttribute('aria-expanded')");
   await evaluate("var s=document.getElementById('refund-method');s.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,button:0}));document.querySelectorAll('.phx-select-popup .sp-opt')[1].click();");
   await check(theme+' choosing an option updates original field and closes popup',"document.getElementById('refund-method').selectedIndex===1&&!document.querySelector('.phx-select-popup')");
   await evaluate("var s=document.getElementById('refund-method');s.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,button:0}));document.querySelector('.content h1').dispatchEvent(new MouseEvent('mousedown',{bubbles:true,button:0}));");
   await check(theme+' outside click closes popup',"!document.querySelector('.phx-select-popup')");
   await evaluate("window.scrollTo(0,document.querySelector('.content .alert').getBoundingClientRect().top+scrollY-135)");await delay(150);await snapshot('after-'+theme+'-'+width);
   await evaluate("window.__oldFixture=true");await send('Page.reload');await wait("!window.__oldFixture && document.readyState==='complete'&&!!window.__pxSelectPicker");
   await check(theme+' page refresh does not create a floating box',"document.querySelectorAll('.phx-select-popup').length===0&&getComputedStyle(document.querySelector('.content .sp')).position==='static'");
  }
  await navigate('clean.html');await check('no-message page remains free of spacer popup',"!document.querySelector('.content .sp')&&!document.querySelector('.phx-select-popup')");
  await evaluate("var s=document.createElement('select');s.id='long-fixture';for(var i=1;i<=14;i++)s.add(new Option('Item '+i,String(i)));document.querySelector('.content').prepend(s);s.scrollIntoView({block:'center'});s.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,button:0}));var q=document.querySelector('.phx-select-popup input');q.value='Item 14';q.dispatchEvent(new Event('input',{bubbles:true}));");
  await check('long-list search still works',"[...document.querySelectorAll('.phx-select-popup .sp-opt')].filter(e=>!e.hidden).length===1&&[...document.querySelectorAll('.phx-select-popup .sp-opt')].find(e=>!e.hidden).textContent==='Item 14'");
 }
 assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);const result={passed:checks.length,checks,exceptions,posts:posts.length,widths:[1440,390],themes:['light','dark'],application_database_accessed:false};fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
