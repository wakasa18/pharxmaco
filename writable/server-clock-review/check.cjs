const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const dir=__dirname,root=process.cwd(),profile=path.join(dir,'chrome-'+Date.now()),delay=ms=>new Promise(r=>setTimeout(r,ms));
let server,chrome,ws,fail=false;const requests=[],checks=[],errors=[];
(async()=>{
 server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/server-time'){
   requests.push({cookie:req.headers.cookie||null,activity:req.headers['x-pharxmaco-activity']||null});
   if(fail){res.writeHead(503,{'Content-Type':'application/json'});res.end('{}');return;}
   try{const response=await fetch('http://localhost/pharxmaco/public/server-time',{headers:{Accept:'application/json'}});const body=await response.text();assert.equal(response.status,200);assert.equal(response.headers.get('set-cookie'),null);res.writeHead(response.status,{'Content-Type':response.headers.get('content-type'),'Cache-Control':'no-store'});res.end(body);}catch(e){errors.push(e.message);res.writeHead(502);res.end();}return;
  }
  const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir,file=path.resolve(base,'.'+decodeURIComponent(url.pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html; charset=utf-8');res.end(fs.readFileSync(file));
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let port;for(let i=0;i<100;i++){const f=path.join(profile,'DevToolsActivePort');if(fs.existsSync(f)){port=Number(fs.readFileSync(f,'utf8').split('\n')[0]);break;}await delay(100);}if(!port)throw Error('Chrome did not start');
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise((r,j)=>{ws.addEventListener('open',r,{once:true});ws.addEventListener('error',j,{once:true});});
 let serial=0;const pending=new Map();ws.addEventListener('message',event=>{const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 const read=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const wait=async expression=>{for(let i=0;i<150;i++){if(await read(expression))return;await delay(50);}throw Error('Clock UI timed out: '+expression);};
 await send('Page.enable');await send('Runtime.enable');
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.__deviceWall=Date.parse('2001-01-01T00:00:00Z');const NativeDate=Date;window.Date=class extends NativeDate{constructor(...args){super(...(args.length?args:[window.__deviceWall]));}static now(){return window.__deviceWall;}};"});
 await send('Page.navigate',{url:origin+'/clock.html'});await wait("document.getElementById('topbarClock')?.dataset.clockState==='synchronized'");
 assert.equal(await read('new Date().getFullYear()'),2001);assert.ok(Math.abs(Date.now()-Date.parse(await read("document.getElementById('clockDisplay').getAttribute('datetime')")))<3000);checks.push('actual local HTTP server time overrides a device set to 2001');
 await read("window.__deviceWall=Date.parse('2099-01-01T00:00:00Z')");await delay(1100);assert.equal(await read('new Date().getFullYear()'),2099);assert.ok(Math.abs(Date.now()-Date.parse(await read("document.getElementById('clockDisplay').getAttribute('datetime')")))<3000);checks.push('changing device wall time does not change displayed server time');
 const expected=new Intl.DateTimeFormat('en-PH',{month:'long',day:'numeric',year:'numeric',timeZone:'Asia/Manila'}).format(new Date());assert.equal(await read("document.getElementById('clockDate').textContent"),expected);checks.push('date displays in Asia/Manila');
 assert.ok(requests.every(r=>r.cookie===null&&r.activity===null));checks.push('clock requests omit credentials/activity and the actual server sets no cookie');
 fail=true;await read("window.dispatchEvent(new Event('focus'))");await wait("document.getElementById('topbarClock').dataset.clockState==='estimated'");const before=Date.parse(await read("document.getElementById('clockDisplay').getAttribute('datetime')"));await delay(1100);assert.ok(Date.parse(await read("document.getElementById('clockDisplay').getAttribute('datetime')"))>before);checks.push('failed sync continues from the last server sample');
 fail=false;await read("window.dispatchEvent(new Event('online'))");await wait("document.getElementById('topbarClock').dataset.clockState==='synchronized'");checks.push('connection return synchronizes again');
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:950,deviceScaleFactor:1,mobile:false});const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'server-clock-1440.png'),Buffer.from(shot.data,'base64'));
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,javascript_errors:errors,clock_requests:requests.length,real_local_clock_endpoint_used:true,authenticated_requests:false,application_records_changed:false},null,2));console.log(`Passed ${checks.length} browser clock scenarios against the actual local server-time endpoint.`);
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
