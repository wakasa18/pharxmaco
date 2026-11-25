const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=process.cwd(),dir=__dirname,profile=path.join(dir,'chrome-'+Date.now()),delay=ms=>new Promise(r=>setTimeout(r,ms));
let server,chrome,ws,origin;const checks=[],exceptions=[],posts=[];
(async()=>{
 server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(req.method==='POST'){posts.push(url.pathname);res.setHeader('Content-Type','application/json');res.end('{"ok":true}');return;}
  const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir,file=path.resolve(base,'.'+decodeURIComponent(url.pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html; charset=utf-8');
  let body=fs.readFileSync(file);if(file.endsWith('.html'))body=body.toString().replaceAll('http://localhost/pharxmaco/public',origin).replaceAll('http:\\/\\/localhost\\/pharxmaco\\/public',origin).replace(/<link[^>]*href="https:\/\/fonts\.[^>]*>/g,'');res.end(body);
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let port;for(let i=0;i<100;i++){const f=path.join(profile,'DevToolsActivePort');if(fs.existsSync(f)){port=Number(fs.readFileSync(f,'utf8').split('\n')[0]);break;}await delay(100);}if(!port)throw Error('Chrome did not start');
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise((r,j)=>{ws.addEventListener('open',r,{once:true});ws.addEventListener('error',j,{once:true});});
 let serial=0;const pending=new Map();ws.addEventListener('message',e=>{const m=JSON.parse(e.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const wait=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out '+expression);};
 const test=async(label,expression)=>{assert.equal(await evaluate(expression),true,label);checks.push(label);};
 await send('Page.enable');await send('Runtime.enable');
 for(const width of [1440,390])for(const state of ['full','empty']){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
  await evaluate('window.__oldReportDocument=true');await send('Page.navigate',{url:origin+'/reports-ui-'+state+'.html'});
  await wait("!window.__oldReportDocument && document.readyState==='complete' && !!window.PharxmacoModal");
  const check=(label,expression)=>test(width+'/'+state+': '+label,expression);
  await check('No restock or reorder suggestion controls',"!document.getElementById('forecastModalTrigger') && !document.querySelector('[data-open-restock],#restockCurrent,#restockControls') && !document.body.textContent.includes('Restock Suggestions')");
  await check('Sales forecast and threshold count remain',"!!document.getElementById('forecastChart') && !!document.getElementById('salesForecastFilters') && document.body.textContent.includes('Low-stock products')");
  await evaluate("document.getElementById('savedSalesForecastTrigger').click()");
  await check('Saved sales modal opens',"document.getElementById('forecastModalOverlay').getAttribute('aria-hidden')==='false' && document.getElementById('forecastModalTitle').textContent==='Saved Sales Forecasts'");
  await check('Saved modal has no product quantity planning',"!document.getElementById('forecastModalOverlay').textContent.includes('Estimated Qty') && !document.getElementById('forecastModalOverlay').textContent.includes('Suggested')");
  if(state==='full')await check('Seven saved sales periods remain',"document.querySelectorAll('#forecastModalOverlay tbody tr').length===7 && document.getElementById('forecastModalOverlay').textContent.includes('Branch #1')");
  else await check('Empty saved sales message remains',"document.getElementById('forecastModalOverlay').textContent.includes('No saved sales forecasts yet')");
  await delay(250);fs.writeFileSync(path.join(dir,'saved-sales-'+width+'-'+state+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
  await evaluate("document.getElementById('forecastModalDone').click()");
  await check('Close returns focus to sales trigger',"document.getElementById('forecastModalOverlay').getAttribute('aria-hidden')==='true' && document.activeElement.id==='savedSalesForecastTrigger'");
  await evaluate("document.getElementById('savedSalesForecastTrigger').click();document.getElementById('forecastModalOverlay').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}))");
  await check('Escape closes saved sales modal',"document.getElementById('forecastModalOverlay').getAttribute('aria-hidden')==='true'");
  await evaluate("document.getElementById('forecastGrouping').value='weekly';document.getElementById('forecastGrouping').dispatchEvent(new Event('change',{bubbles:true}))");
  await check('Sales forecast filter change still shows pending notice',"!document.getElementById('forecastPendingFilters').hidden");
  await check('No horizontal overflow',"document.documentElement.scrollWidth<=innerWidth");
 }
 assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,synthetic_posts:posts.length,application_posts:0,widths:[1440,390]},null,2));console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(r=>server.close(r));});
