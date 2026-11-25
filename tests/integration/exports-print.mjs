import {createRequire} from 'node:module'; const require=createRequire(import.meta.url);
const fs=require('fs'),path=require('path'),http=require('http'),{spawn}=require('child_process');
const root=process.cwd(),dir=path.join(root,'writable/export-audit-20261023'),out=path.join(root,'tmp/pdfs/export-audit'),profile=path.join(dir,'chrome-'+Date.now());
const sleep=ms=>new Promise(r=>setTimeout(r,ms));const results=[],errors=[];let chrome,ws;
const server=http.createServer((req,res)=>{const url=new URL(req.url,'http://localhost');const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir;const f=path.resolve(base,'.'+decodeURIComponent(url.pathname));if(!f.startsWith(base+path.sep)||!fs.existsSync(f)){res.writeHead(404);res.end();return;}let bytes=fs.readFileSync(f);res.setHeader('Content-Type',f.endsWith('.js')?'text/javascript':f.endsWith('.css')?'text/css':f.endsWith('.jpg')?'image/jpeg':'text/html; charset=utf-8');if(f.endsWith('.html'))bytes=bytes.toString().replaceAll(/http:\/\/127\.0\.0\.1:\d+\//g,'/');res.end(bytes);});
try{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const base='http://127.0.0.1:'+server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let port;for(let i=0;i<100;i++){const f=path.join(profile,'DevToolsActivePort');if(fs.existsSync(f)){port=+fs.readFileSync(f,'utf8').split('\n')[0];break;}await sleep(100);}if(!port)throw Error('Chrome startup');
 const tabs=await(await fetch('http://127.0.0.1:'+port+'/json')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));let id=0;const pending=new Map();ws.addEventListener('message',ev=>{const m=JSON.parse(ev.data);if(pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{pending.set(++id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});const read=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
 await send('Page.enable');await send('Runtime.enable');await send('Page.addScriptToEvaluateOnNewDocument',{source:'window.__prints=0;window.print=()=>window.__prints++'});
 await send('Emulation.setDeviceMetricsOverride',{width:794,height:1123,deviceScaleFactor:1,mobile:false});
 for(const name of ['receipt','reprint','refund-slip','shift']){
  await send('Page.navigate',{url:base+'/'+name+'.html'});for(let i=0;i<100;i++){if(await read("document.readyState==='complete'&&!!document.querySelector('button')"))break;await sleep(40);}await read('document.fonts.ready.then(()=>true)');
  const modes=name==='shift'?['summary','full']:['receipt'];
  for(const mode of modes){
   const button=name==='shift'?`button[data-print-mode="${mode}"]`:'button[data-print-receipt]';const invoked=await read(`(()=>{const before=window.__prints;document.querySelector(${JSON.stringify(button)}).click();return window.__prints>before;})()`);if(!invoked)throw Error(name+' print control');
   await send('Emulation.setEmulatedMedia',{media:'print'});const pdf=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,displayHeaderFooter:false,paperWidth:name==='shift'?8.2677:3.1496,paperHeight:11.6929,marginTop:0,marginBottom:0,marginLeft:0,marginRight:0});fs.writeFileSync(path.join(out,'print-'+name+'-'+mode+'.pdf'),Buffer.from(pdf.data,'base64'));
   results.push({name,mode,button_invoked:true,title:await read('document.title'),characters:(await read('document.body.innerText')).length});await send('Emulation.setEmulatedMedia',{media:''});
  }
 }
 if(errors.length)throw Error(errors.join('\n'));
 fs.writeFileSync(path.join(dir,'print-results.json'),JSON.stringify({results,errors},null,2));console.log('Five browser print actions produced PDF output without JavaScript errors.');
}finally{if(ws)ws.close();if(chrome)chrome.kill();server.close();}
