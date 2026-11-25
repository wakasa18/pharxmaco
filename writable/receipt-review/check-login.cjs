const fs = require('fs');
const path = require('path');
const http = require('http');
const assert = require('assert/strict');
const {spawn} = require('child_process');
const root = process.cwd(), dir = __dirname;
const profile = path.join(dir, 'chrome-profile-' + Date.now());
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let chrome, ws, server;
(async () => {
 server = http.createServer((req,res) => {
  const url = new URL(req.url, 'http://localhost');
  const base = url.pathname.startsWith('/assets/') ? path.join(root,'public') : dir;
  const file = path.resolve(base,'.' + decodeURIComponent(url.pathname));
  if (!file.startsWith(base + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type', file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':'text/html; charset=utf-8');
  res.end(fs.readFileSync(file));
 });
 await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
 const port=server.address().port;
 fs.mkdirSync(profile,{recursive:true});
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
  '--headless=new','--disable-gpu','--hide-scrollbars','--no-first-run','--no-default-browser-check','--disable-extensions',
  '--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'
 ],{stdio:'ignore',windowsHide:true});
 let debugPort;
 for(let i=0;i<100;i++) {
  const portFile=path.join(profile,'DevToolsActivePort');
  if(fs.existsSync(portFile)){debugPort=Number(fs.readFileSync(portFile,'utf8').split('\n')[0]);break;}
  await delay(100);
 }
 if(!debugPort)throw new Error('Headless Chrome did not start');
 const tabs=await (await fetch('http://127.0.0.1:'+debugPort+'/json', {signal:AbortSignal.timeout(5000)})).json();
 ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error('Browser connection timed out')),10000);ws.addEventListener('open',()=>{clearTimeout(timer);resolve();},{once:true});ws.addEventListener('error',()=>{clearTimeout(timer);reject(Error('Browser connection failed'));},{once:true});});
 let serial=0; const pending=new Map();
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser timed out: '+method));},15000);pending.set(id,{resolve:r=>{clearTimeout(timer);resolve(r);},reject:e=>{clearTimeout(timer);reject(e);}});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 await send('Page.enable');
 const results=[],errors=[];
 await send('Runtime.enable');
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 const navigate=async state=>{
  await send('Page.navigate',{url:`http://127.0.0.1:${port}/login-${state}.html`});
  for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'&&!!document.querySelector('#loginSubmit')"))break;await delay(100);}
 };
 if(process.argv.includes('--live')) {
  await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
  await send('Page.navigate',{url:'http://localhost/pharxmaco/public/login'});
  let loaded=false;
  for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete' && !!document.getElementById('loginForm')")){loaded=true;break;}await delay(100);}
  assert.equal(loaded,true);
  assert.equal(await evaluate("document.getElementById('togglePass').hidden"),false);
  await evaluate("document.getElementById('togglePass').click()");
  assert.equal(await evaluate("document.getElementById('password').type"),'text');
  await evaluate("document.getElementById('togglePass').click();document.getElementById('loginThemeToggle').click()");
  assert.equal(await evaluate("document.getElementById('password').type"),'password');
  assert.equal(await evaluate("document.getElementById('loginThemeToggle').getAttribute('aria-pressed')===String(document.documentElement.classList.contains('dark'))"),true);
  assert.equal(await evaluate("document.querySelector('script[src*=\"login.js\"]').src.includes('20261012-v7')"),true);
  assert.deepEqual(errors,[]);
  console.log('Live login page initializes, toggles password visibility and switches theme without browser exceptions.');return;
 }
 if(process.argv.includes('--null-controls')) {
  await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
  const missing=['loginThemeToggle','loginThemeNotice','togglePass','capsLockNotice','loginSubmitLabel','loginForm','username','password','loginSubmit'];
  for(const id of missing) {
   const seed=await send('Page.addScriptToEvaluateOnNewDocument',{source:`document.addEventListener('DOMContentLoaded',()=>document.getElementById(${JSON.stringify(id)})?.remove(),{once:true})`});
   await send('Page.navigate',{url:`http://127.0.0.1:${port}/login-normal.html`});
   let loaded=false;
   for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'")){loaded=true;break;}await delay(100);}
   assert.equal(loaded,true);
   await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:seed.identifier});
   assert.equal(await evaluate(`document.getElementById(${JSON.stringify(id)})===null`),true);
   if(id==='loginThemeToggle') {
    await evaluate("document.getElementById('togglePass').click()");
    assert.equal(await evaluate("document.getElementById('password').type"),'text','Missing theme control must not prevent password setup');
   }
   if(id==='loginThemeNotice') {
    await evaluate("document.getElementById('loginThemeToggle').click();window.originalSetItem=Storage.prototype.setItem;Storage.prototype.setItem=()=>{throw Error('blocked')};document.getElementById('loginThemeToggle').click();Storage.prototype.setItem=window.originalSetItem");
   }
   if(['togglePass','capsLockNotice','loginSubmitLabel'].includes(id)) {
    await evaluate("document.getElementById('password').dispatchEvent(new KeyboardEvent('keyup',{modifierCapsLock:true}));document.getElementById('password').dispatchEvent(new Event('blur'));document.getElementById('loginForm').addEventListener('submit',e=>e.preventDefault());document.getElementById('username').value='preview';document.getElementById('password').value='preview-only';document.getElementById('loginForm').requestSubmit()");
    assert.equal(await evaluate("document.getElementById('loginSubmit').disabled"),true);
    await evaluate("window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}))");
    assert.equal(await evaluate("document.getElementById('loginSubmit').disabled"),false);
   }
   assert.deepEqual(errors,[], 'Unexpected browser exception when missing '+id);
  }
  // Loading the script after DOMContentLoaded must initialize controls as well.
  const original=fs.readFileSync(path.join(dir,'login-normal.html'),'utf8');
  fs.writeFileSync(path.join(dir,'login-late-script.html'),original.replace(/<script[^>]+src="[^\"]*login\.js[^\"]*"[^>]*><\/script>/,''));
  await send('Page.navigate',{url:`http://127.0.0.1:${port}/login-late-script.html`});
  for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(100);}
  assert.equal(await evaluate("document.getElementById('togglePass').hidden"),true);
  await evaluate("new Promise((resolve,reject)=>{const script=document.createElement('script');script.src='/assets/js/login.js';script.onload=resolve;script.onerror=reject;document.head.append(script)})");
  assert.equal(await evaluate("document.getElementById('togglePass').hidden"),false);
  await evaluate("document.getElementById('togglePass').click()");
  assert.equal(await evaluate("document.getElementById('password').type"),'text');
  assert.deepEqual(errors,[]);
  console.log('Passed nine missing-control cases and login initialization after DOMContentLoaded without browser exceptions.');return;
 }
 if(process.argv.includes('--regressions')) {
  await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
  await send('Emulation.setFocusEmulationEnabled',{enabled:true});
  await navigate('normal');
  await delay(1000);
  await evaluate("document.querySelector('#username').focus();document.querySelector('#username').blur()");
  assert.equal(await evaluate("document.querySelector('.login-form-content').getAnimations().length"),0,'Leaving an input must not replay the page entrance');
  await evaluate("window.originalSetItem=Storage.prototype.setItem;Storage.prototype.setItem=()=>{throw Error('blocked')};document.querySelector('#loginThemeToggle').click()");
  assert.equal(await evaluate("document.querySelector('#loginThemeNotice').hidden"),false);
  await evaluate("Storage.prototype.setItem=window.originalSetItem;document.querySelector('#loginThemeToggle').click()");
  assert.equal(await evaluate("document.querySelector('#loginThemeNotice').hidden"),true,'Successful retry clears stale storage warning');
  await evaluate("localStorage.setItem('pharxmaco-ui-settings',JSON.stringify({theme:'dark',reduceMotion:true}));window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}))");
  assert.equal(await evaluate("document.documentElement.classList.contains('dark')&&document.documentElement.classList.contains('reduce-motion')"),true,'Back navigation reloads saved preferences');
  await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.nativeMatchMedia=window.matchMedia;window.matchMedia=q=>{const media=window.nativeMatchMedia(q);media.addEventListener=undefined;return media}"});
  await navigate('normal');
  assert.equal(await evaluate("document.querySelector('#togglePass').hidden"),false,'Legacy media-query API must not break form controls');
  await evaluate("document.querySelector('#togglePass').click()");
  assert.equal(await evaluate("document.querySelector('#password').type"),'text');
  assert.deepEqual(errors,[]);
  console.log('Passed login regression checks: focus/blur, storage retry, restored preferences and legacy media-query API.');return;
 }
 for(const state of ['normal','error','success'])for(const width of [320,390,768,1024,1440])for(const dark of [false,true]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
  await navigate(state);
  await evaluate(`localStorage.setItem('pharxmaco-ui-settings',JSON.stringify({theme:'${dark?'dark':'light'}',accent:'purple'}));window.dispatchEvent(new StorageEvent('storage',{key:'pharxmaco-ui-settings'}));`);
  await delay(800);
  assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true,`overflow ${state} ${width}`);
  assert.equal(await evaluate("document.querySelector('#loginForm').getAttribute('action')"),'/login');
  assert.equal(await evaluate("document.querySelector('#loginForm').method"),'post');
  assert.equal(await evaluate("!!document.querySelector('#loginForm input[type=hidden]')"),true);
  assert.equal(await evaluate("[...document.querySelectorAll('button')].filter(x=>x.getClientRects().length).every(x=>x.getBoundingClientRect().height>=44&&x.getBoundingClientRect().width>=44)"),true,'Touch targets');
  if(state==='error')assert.equal(await evaluate("document.querySelector('#username').value"),'staff"<test>');
  await evaluate("document.querySelector('#togglePass').click()");
  assert.equal(await evaluate("document.querySelector('#password').type"),'text');
  await evaluate("document.querySelector('#togglePass').click()");
  assert.equal(await evaluate("document.querySelector('#password').type"),'password');
  if(state==='normal'&&[390,1440].includes(width)){
   const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,`login-${width}-${dark?'dark':'light'}.png`),Buffer.from(shot.data,'base64'));
  }
  await evaluate("document.documentElement.classList.add('high-contrast','reduce-motion');document.documentElement.dataset.textSize='extra-large';document.querySelector('.login-help').open=true");
  assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true,'Large text overflow');
  results.push({state,width,dark});
 }
 await navigate('subfolder');
 assert.equal(await evaluate("document.querySelector('#loginForm').getAttribute('action')"),'/pharmacy/login');
 await navigate('normal');
 assert.equal(await evaluate("document.querySelector('#loginForm').checkValidity()"),false,'Required fields');
 await evaluate("document.querySelector('#loginThemeToggle').click()");
 await delay(1100); // Let the theme reveal finish before testing the next click.
 assert.equal(await evaluate("JSON.parse(localStorage.getItem('pharxmaco-ui-settings')).accent"),'purple','Preserve settings');
 await evaluate("document.querySelector('#password').dispatchEvent(new KeyboardEvent('keyup',{key:'A',modifierCapsLock:true}))");
 assert.equal(await evaluate("document.querySelector('#capsLockNotice').hidden"),false);
 await evaluate("document.querySelector('#loginForm').addEventListener('submit',e=>e.preventDefault());document.querySelector('#username').value='test-user';document.querySelector('#password').value='test-only';document.querySelector('#loginForm').requestSubmit()");
 assert.equal(await evaluate("document.querySelector('#loginSubmit').disabled&&document.querySelector('#loginForm').getAttribute('aria-busy')==='true'"),true,'Submit feedback');
 assert.equal(await evaluate("document.querySelector('#loginForm').dispatchEvent(new Event('submit',{cancelable:true}))"),false,'Repeated submission stopped');
 await evaluate("window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}))");
 assert.equal(await evaluate("document.querySelector('#loginSubmit').disabled"),false,'Back navigation reset');
 await evaluate("Storage.prototype.setItem=()=>{throw Error('blocked')};document.querySelector('#loginThemeToggle').click()");
 assert.equal(await evaluate("document.querySelector('#loginThemeNotice').hidden"),false,'Storage blocked feedback');
 await send('Emulation.setDeviceMetricsOverride',{width:844,height:390,deviceScaleFactor:1,mobile:false});
 assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true,'Landscape overflow');
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
 await navigate('normal');
 await evaluate("localStorage.removeItem('pharxmaco-ui-settings');window.dispatchEvent(new StorageEvent('storage',{key:'pharxmaco-ui-settings'}))");
 await delay(1800); // The current artwork starts its float after a 1.4-second entrance delay.
 assert.equal(await evaluate("getComputedStyle(document.querySelector('.pack-float')).animationName"),'pack-float','Artwork animation active');
 const initialArt=await evaluate("getComputedStyle(document.querySelector('.pack-float')).transform");
 await delay(1100);
 assert.notEqual(await evaluate("getComputedStyle(document.querySelector('.pack-float')).transform"),initialArt,'Artwork moves');
 await evaluate("document.documentElement.classList.add('reduce-motion')");
 assert.equal(await evaluate("document.getAnimations().filter(a=>a.playState==='running').length"),0,'Saved reduced motion');
 await evaluate("document.documentElement.classList.remove('reduce-motion')");
 await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
 assert.equal(await evaluate("document.getAnimations().filter(a=>a.playState==='running').length"),0,'Device reduced motion');
 await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'no-preference'}]});
 await evaluate("document.querySelector('#loginForm').setAttribute('aria-busy','true')");
 assert.equal(await evaluate("getComputedStyle(document.querySelector('.login-submit-chip'),'::after').animationName"),'login-spin','Loading spinner');
 await evaluate("document.querySelector('#loginForm').removeAttribute('aria-busy')");
 assert.deepEqual(errors,[]);
 fs.writeFileSync(path.join(dir,'login-checks.json'),JSON.stringify(results,null,2));
 console.log(`Passed ${results.length} login layouts, form interactions, animated artwork, loading spinner and both reduced-motion preferences.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
