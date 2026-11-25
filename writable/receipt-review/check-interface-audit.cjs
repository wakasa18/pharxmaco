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
 const audit = {};
 const defaults = {theme:'light',accent:'blue',textSize:'normal',density:'comfortable',largeControls:false,reduceMotion:false,highContrast:false,stickyHeaders:false};
 const key = 'pharxmaco-ui-settings';
 const ready = async selector => {
  for (let i=0;i<100;i++) {
   if (await evaluate(`document.readyState==='complete' && !!document.querySelector(${JSON.stringify(selector)})`)) return;
   await delay(100);
  }
  throw Error('Page did not load: ' + selector);
 };
 const navigate = async (page,selector) => {
  await send('Page.navigate',{url:`http://127.0.0.1:${port}/${page}`});
  await ready(selector);
 };
 const apply = async value => {
  await evaluate(`localStorage.setItem('${key}',${JSON.stringify(JSON.stringify({...defaults,...value}))});window.dispatchEvent(new Event('pharxmaco:ui-settings-saved'))`);
  await delay(550);
 };
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await navigate('table-actions-products.html','.content .table-wrap table');
 audit.typography = {};
 for (const textSize of ['normal','large','extra-large']) {
  await apply({textSize});
  audit.typography[textSize] = await evaluate(`Object.fromEntries(['.page-header h1','.page-header p','.content .table-wrap th','.content .table-wrap td','.content label','.content .input'].map(selector=>[selector,document.querySelector(selector)?getComputedStyle(document.querySelector(selector)).fontSize:null]))`);
 }
 for (const selector of Object.keys(audit.typography.normal)) {
  assert.ok(parseFloat(audit.typography.large[selector]) > parseFloat(audit.typography.normal[selector]), 'Large text must enlarge ' + selector);
  assert.ok(parseFloat(audit.typography['extra-large'][selector]) > parseFloat(audit.typography.large[selector]), 'Extra-large text must enlarge ' + selector);
 }
 assert.equal(audit.typography['extra-large']['.content .table-wrap td'],'16px','Inline record size follows preferences');
 audit.spacing = {};
 for (const density of ['compact','comfortable','spacious']) {
  await apply({density});
  audit.spacing[density] = await evaluate(`Object.fromEntries(['.content','.card-head','.card-body','.content .table-wrap th','.content .table-wrap td'].map(selector=>[selector,document.querySelector(selector)?getComputedStyle(document.querySelector(selector)).padding:null]))`);
 }
 for (const selector of ['.card-head','.content .table-wrap th','.content .table-wrap td']) {
  assert.ok(parseFloat(audit.spacing.compact[selector]) < parseFloat(audit.spacing.comfortable[selector]), 'Compact spacing must reduce ' + selector);
  assert.ok(parseFloat(audit.spacing.spacious[selector]) > parseFloat(audit.spacing.comfortable[selector]), 'Spacious spacing must increase ' + selector);
 }
 audit.contrast = {};
 for (const highContrast of [false,true]) {
  await apply({theme:'dark',highContrast});
  audit.contrast[highContrast] = await evaluate(`Object.fromEntries(['.sidebar','.sidebar .sidebar-context','.sidebar .sidebar-search','.content'].map(selector=>{const css=getComputedStyle(document.querySelector(selector));return [selector,{color:css.color,borderColor:css.borderColor,muted:css.getPropertyValue('--s-muted').trim(),line:css.getPropertyValue('--s-line').trim()}]}))`);
 }
 assert.notEqual(audit.contrast.true['.sidebar .sidebar-context'].color,audit.contrast.false['.sidebar .sidebar-context'].color);
 assert.notEqual(audit.contrast.true['.sidebar .sidebar-search'].borderColor,audit.contrast.false['.sidebar .sidebar-search'].borderColor);
 await apply({});
 await navigate('settings-admin.html','#interfacePreview');
 await evaluate(`document.querySelector('input[name=accent][value=orange]').click();window.auditOriginalSetItem=Storage.prototype.setItem;Storage.prototype.setItem=function(key,value){if(key==='${key}')throw new DOMException('Blocked','SecurityError');return window.auditOriginalSetItem.call(this,key,value)};document.getElementById('themeToggle').click()`);
 await delay(1200);
 audit.failedTopbarSave = await evaluate(`({stored:JSON.parse(localStorage.getItem('${key}')),applied:document.documentElement.dataset.themePreference,selected:document.querySelector('input[name=theme]:checked').value,feedback:document.getElementById('settingsFeedback').textContent,errorShown:!document.getElementById('settingsError').hidden,dirty:document.getElementById('settingsStatus').textContent})`);
 assert.equal(audit.failedTopbarSave.applied,'light');
 assert.equal(audit.failedTopbarSave.selected,'light');
 assert.equal(audit.failedTopbarSave.errorShown,true);
 assert.equal(audit.failedTopbarSave.feedback,'');
 assert.equal(audit.failedTopbarSave.dirty,'1 unsaved change','Failed theme save preserves the unsaved accent');
 await evaluate('Storage.prototype.setItem=window.auditOriginalSetItem;document.getElementById("discardUiSettings").click()');
 await send('Page.reload');await delay(150);await ready('#interfacePreview');
 audit.afterReload = await evaluate(`({applied:document.documentElement.dataset.themePreference,selected:document.querySelector('input[name=theme]:checked').value})`);
 const keyboard = async (key,code,vk,text) => {
  await send('Input.dispatchKeyEvent',{type:'keyDown',key,code,windowsVirtualKeyCode:vk,...(text?{text}:{})});
  await send('Input.dispatchKeyEvent',{type:'keyUp',key,code,windowsVirtualKeyCode:vk});
 };
 await evaluate("document.querySelector('input[name=largeControls]').focus()");
 await keyboard(' ','Space',32,' ');
 const dialog = new Promise(resolve=>{
  ws.addEventListener('message',function listener(event){
   const message=JSON.parse(event.data);
   if(message.method==='Page.javascriptDialogOpening'){ws.removeEventListener('message',listener);resolve(message.params);}
  });
 });
 await evaluate(`document.querySelector('.topbar-brand').href='http://127.0.0.1:${port}/settings-cashier.html';document.querySelector('.topbar-brand').focus()`);
 const entered = keyboard('Enter','Enter',13);
 const prompt = await Promise.race([dialog,delay(4000).then(()=>{throw Error('Missing unsaved warning');})]);
 assert.equal(prompt.type,'beforeunload');
 await send('Page.handleJavaScriptDialog',{accept:false});
 await entered;
 await delay(700);
 audit.cancelledNavigation = await evaluate(`({page:location.pathname,progressRunning:document.querySelector('.ws-progress').classList.contains('run'),dirty:document.getElementById('settingsStatus').textContent})`);
 assert.equal(audit.cancelledNavigation.progressRunning,false);
 assert.equal(audit.cancelledNavigation.dirty,'1 unsaved change');
 await evaluate("document.getElementById('discardUiSettings').click()");
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:1000,deviceScaleFactor:1,mobile:false});
 audit.mobileText = {};
 for (const textSize of ['normal','large','extra-large']) {
  await apply({textSize});
  audit.mobileText[textSize] = await evaluate(`Object.fromEntries(['.settings-option > span','.settings-switch strong','#saveUiSettings','#settingsStatus'].map(selector=>[selector,getComputedStyle(document.querySelector(selector)).fontSize]))`);
 }
 for (const selector of Object.keys(audit.mobileText.normal)) {
  assert.ok(parseFloat(audit.mobileText.large[selector]) > parseFloat(audit.mobileText.normal[selector]), 'Mobile large text must enlarge ' + selector);
  assert.ok(parseFloat(audit.mobileText['extra-large'][selector]) > parseFloat(audit.mobileText.large[selector]), 'Mobile extra-large text must enlarge ' + selector);
 }
 for (const width of [320,390,768,1440]) {
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  await navigate('table-actions-products.html','.content .table-wrap table');
  for (const density of ['compact','comfortable','spacious']) {
   await apply({textSize:'extra-large',density});
   assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false,'Products overflow: ' + width + ' ' + density);
  }
  const shot=await send('Page.captureScreenshot',{format:'png'});
  fs.writeFileSync(path.join(dir,`interface-products-fixed-${width}.png`),Buffer.from(shot.data,'base64'));
 }
 await apply({accent:'orange'});
 await navigate('settings-user-1.html','#interfacePreview');
 assert.equal(await evaluate('PharxmacoUiPreferences.key'),key+':user:1');
 assert.equal(await evaluate("document.querySelector('input[name=accent]:checked').value"),'orange','Existing browser choices seed the account');
 await evaluate("document.querySelector('input[name=accent][value=green]').click();document.querySelector('input[name=theme][value=dark]').click();document.getElementById('saveUiSettings').click()");
 await navigate('settings-user-2.html','#interfacePreview');
 assert.equal(await evaluate('PharxmacoUiPreferences.key'),key+':user:2');
 assert.equal(await evaluate("document.querySelector('input[name=accent]:checked').value"),'orange','Second account does not inherit first account edits');
 await evaluate("document.querySelector('input[name=accent][value=purple]').click();document.getElementById('saveUiSettings').click()");
 await evaluate(`const other={...JSON.parse(localStorage.getItem('${key}:user:1')),density:'compact'};localStorage.setItem('${key}:user:1',JSON.stringify(other));window.dispatchEvent(new StorageEvent('storage',{key:'${key}:user:1',newValue:JSON.stringify(other),storageArea:localStorage}))`);
 assert.equal(await evaluate("document.documentElement.dataset.accent"),'purple','Other account storage events do not alter the workspace');
 assert.equal(await evaluate("document.querySelector('input[name=density]:checked').value"),'comfortable');
 await navigate('settings-user-1.html','#interfacePreview');
 assert.equal(await evaluate("document.documentElement.dataset.accent"),'green');
 assert.equal(await evaluate("document.documentElement.dataset.density"),'compact');
 assert.equal(await evaluate("document.documentElement.classList.contains('dark')"),true);
 assert.equal(await evaluate(`JSON.parse(localStorage.getItem('${key}')).accent`),'orange','Account choices do not overwrite sign-in/browser preferences');
 await evaluate("document.querySelector('input[name=largeControls]').click()");
 assert.equal(await evaluate("getComputedStyle(document.querySelector('.sample-input')).minHeight"),'48px');
 assert.equal(await evaluate("document.querySelector('.sample-input').readOnly"),true);
 await evaluate("document.getElementById('discardUiSettings').click()");
 assert.equal(await evaluate("getComputedStyle(document.querySelector('.sample-input')).minHeight"),'36px');
 audit.accountIsolation = true;
 audit.sampleInput = true;
 fs.writeFileSync(path.join(dir,'interface-audit-results.json'),JSON.stringify(audit,null,2));
 console.log('Passed text size, spacing, dark-sidebar contrast, failed theme saves, cancelled navigation, mobile product layouts, per-account persistence and preview input checks.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});