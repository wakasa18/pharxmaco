const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=process.cwd(),dir=process.argv.includes('--management')?path.join(__dirname,'../management-form-review'):__dirname,profile=path.join(dir,'chrome-'+process.pid+'-'+Date.now());
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let server,chrome,ws;const posts=[],checks=[];
(async()=>{
 server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(req.method==='POST'&&url.pathname==='/products/store'){
   let body='';req.on('data',chunk=>body+=chunk);req.on('end',()=>{posts.push(Object.fromEntries(new URLSearchParams(body)));res.setHeader('Content-Type','text/html');res.end('<main data-saved>Fixture saved; no application database used.</main>');});return;
  }
  const base=url.pathname.startsWith('/assets/')?path.join(root,'public'):dir;
  const file=path.resolve(base,'.'+decodeURIComponent(url.pathname));
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript':'text/html; charset=utf-8');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const port=server.address().port;
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
 let debugPort;for(let i=0;i<100;i++){const file=path.join(profile,'DevToolsActivePort');if(fs.existsSync(file)){debugPort=Number(fs.readFileSync(file,'utf8').split('\n')[0]);break;}await delay(100);}
 if(!debugPort)throw Error('Headless Chrome did not start.');
 const tabs=await(await fetch('http://127.0.0.1:'+debugPort+'/json')).json();ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
 let serial=0;const pending=new Map();
 ws.addEventListener('message',event=>{const data=JSON.parse(event.data),p=pending.get(data.id);if(p){pending.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result);}});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial,timer=setTimeout(()=>{pending.delete(id);reject(Error('Timed out: '+method));},15000);pending.set(id,{resolve:data=>{clearTimeout(timer);resolve(data);},reject:error=>{clearTimeout(timer);reject(error);}});ws.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const data=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(data.exceptionDetails)throw Error(JSON.stringify(data.exceptionDetails));return data.result.value;};
 const wait=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(50);}throw Error('UI did not become ready: '+expression);};
 await send('Page.enable');
 if(process.argv.includes('--management')){
  const views=['product-create','product-edit','category-create','category-edit','user-create','user-edit','branch-create','branch-edit','supplier-create','supplier-edit','discount-create','discount-edit'];
  const samples={
   product_name:['Vitamin C + Zinc (10%)','Vitamin<script>'],generic_name:['Ascorbic Acid + Zinc','@@@'],strength:['500 mg / 5 mL (10%)','500<script>'],unit:['tablet','!!!'],manufacturer:["O’Connor & Sons, Inc.",'@@@'],
   category_name:['Cough, Cold & Allergy','@@@'],supplier_name:["O’Connor & Sons, Inc.",'Maker<script>'],discount_name:['Vitamin C + Zinc (20% off)','@@@'],branch_name:['St. Niño - Main (2)','@@@'],
   full_name:['Ma. José O’Connor-Santos','Ana123'],contact_person:['Santos, Juan Jr.','Ana123'],sku:['RX_500-A.1/02','SKU 500'],branch_code:['BR_02-A','BR@02'],username:['ana.cruz-2','ana@cruz'],
   contact_number:['+63 (917) 123-4567','0917+1234567'],address:['Unit #3, 12/F, St. Niño St.\nCagayan de Oro','<b>Address</b>'],
   cost_price:['4.50','1e2'],price:['7.50','1e2'],stock:['120','1e2'],reorder_level:['20','1e2'],
   discount_value:['20','1e2'],minimum_purchase:['100','1e2'],max_discount_amount:['50','1e2'],
  };
  for(const view of views){
   await send('Page.navigate',{url:`http://127.0.0.1:${port}/${view}.html`});await wait("document.readyState==='complete' && !!document.querySelector('form[data-management-form]') && !!document.querySelector('[id^=management-field-error]')");
   const names=await evaluate("[...document.querySelector('form[data-management-form]').elements].map(field=>field.name)");
   for(const [name,[valid,invalid]]of Object.entries(samples)){
    if(!names.includes(name))continue;
    const checkValue=async value=>evaluate(`(()=>{const field=document.querySelector('form[data-management-form]').elements.namedItem(${JSON.stringify(name)});field.value=${JSON.stringify(value)};field.dispatchEvent(new Event('input',{bubbles:true}));field.dispatchEvent(new Event('blur'));const message=document.getElementById(field.getAttribute('aria-describedby').split(' ').at(-1));return {valid:field.checkValidity(),error:field.validationMessage,shown:!message.hidden&&getComputedStyle(message).display!=='none',aria:field.getAttribute('aria-invalid')}})()`);
    const bad=await checkValue(invalid);assert.equal(bad.valid,false,view+'/'+name+' should reject '+invalid);assert.equal(bad.shown,true);assert.equal(bad.aria,'true');
    const good=await checkValue(valid);assert.equal(good.valid,true,view+'/'+name+' should accept '+valid+': '+good.error);assert.equal(good.shown,false);assert.equal(good.aria,'false');checks.push({view,field:name,rejected:invalid,accepted:valid});
   }
   if(view.startsWith('product-')){
    assert.equal(await evaluate("document.querySelector('[name=sku]').required && document.querySelector('[name=sku]').maxLength===50"),true);
    await evaluate("for(const field of document.querySelector('form[data-management-form]').elements){if(field.required&&field.tagName==='SELECT'&&!field.value){field.value=[...field.options].find(option=>option.value)?.value||'';field.dispatchEvent(new Event('change',{bubbles:true}));}}document.documentElement.classList.remove('dark')");
    await evaluate("const field=document.querySelector('[name=sku]');field.value='';field.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('form[data-management-form]').requestSubmit()");
    assert.equal(posts.length,0);assert.equal(await evaluate("document.querySelector('[name=sku]').validity.valueMissing"),true);checks.push({view,required_sku:true,no_post:true});
   }
   if(view==='user-create'){
    assert.equal(await evaluate("const field=document.querySelector('[name=password]');field.value='!@#$%^&*()_+-=';field.checkValidity()"),true);checks.push({view,password_symbols_allowed:true});
   }
   if(view.startsWith('supplier-')){
    assert.equal(await evaluate("const field=document.querySelector('[name=email]');field.value='orders+tag@example.com';field.checkValidity()"),true);checks.push({view,email_plus_allowed:true});
   }
   if(view==='product-create')for(const width of [390,1440]){
    await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});await evaluate("document.querySelector('[name=sku]').scrollIntoView({block:'center'})");await delay(400);
    const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'sku-error-'+width+'.png'),Buffer.from(shot.data,'base64'));
   }
  }
  fs.writeFileSync(path.join(dir,'browser-checks.json'),JSON.stringify({checks,posts:posts.length,application_database_accessed:false},null,2));
  console.log(`Passed ${checks.length} management validation checks across ${views.length} create/edit forms; no data submitted.`);return;
 }
 const load=async role=>{await send('Page.navigate',{url:`http://127.0.0.1:${port}/${role}.html`});await wait("document.readyState==='complete' && !!document.getElementById('product-create-form') && !!window.PharxmacoModal");};
 const open=()=>evaluate("document.querySelector('#product-create-form button[type=submit]').click()");
 const isOpen="document.getElementById('product-create-review').classList.contains('open')";
 const field=(name,value)=>evaluate(`{const input=document.getElementById('product-create-form').elements.namedItem(${JSON.stringify(name)});input.value=${JSON.stringify(value)};input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));}`);
 const fill=async role=>{
  const values={product_name:'Biogesic 500 mg',category_id:'1',sku:'BIO500-DEMO',unit:'tablet',medicine_type:'branded',generic_name:'Paracetamol',strength:'500 mg',tax_treatment:'taxable',senior_pwd_eligible:'1',cost_price:'4.50',price:'7.50',stock:'120',reorder_level:'20',expiration_date:'2027-10-20',manufacturer:'Unilab',supplier_id:'1'};
  if(role==='admin')values.branch_id='2';
  await evaluate(`{const form=document.getElementById('product-create-form');for(const [key,value]of Object.entries(${JSON.stringify(values)}))form.elements.namedItem(key).value=value;}`);
 };
 for(const role of ['admin','cashier']){
  await load(role);
  assert.equal(await evaluate("document.querySelector('[name=sku]').placeholder"),'e.g. PAR500-001');
  await open();assert.equal(await evaluate(isOpen),false);assert.equal(posts.length,role==='admin'?0:1);checks.push(role+' invalid form does not review or save');
  await fill(role);await evaluate("document.querySelector('[name=product_name]').focus()");
  assert.equal(await evaluate("document.getElementById('product-create-form').checkValidity()"),true);
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,text:'\r'});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});await wait(isOpen);
  const summary=await evaluate("Object.fromEntries([...document.querySelectorAll('[data-product-review]')].map(el=>[el.dataset.productReview,el.textContent]))");
  assert.equal(summary.product_name,'Biogesic 500 mg');assert.equal(summary.branch_id,role==='admin'?'Patag Branch':'Main Branch');
  assert.equal(summary.medicine_type,'Branded');assert.equal(summary.tax_treatment,'VATable');assert.match(summary.senior_pwd_eligible,/Eligible/);
  assert.equal(summary.cost_price,'₱4.50');assert.equal(summary.price,'₱7.50');assert.equal(summary.stock,'120 tablet');assert.equal(summary.expiration_date,'2027-10-20');assert.match(summary.supplier_id,/Mercury Supply/);
  assert.equal(summary.unit,'Tablet');assert.equal(await evaluate("document.querySelector('[name=unit]').tagName==='SELECT' && document.querySelector('[name=unit]').required"),true);
  assert.equal(await evaluate('document.activeElement.id'),'product-review-edit');
  assert.equal(await evaluate("document.querySelector('#product-create-form button[type=submit]').disabled"),false);
  checks.push(role+' complete overview has current labels and values');
  await evaluate("document.getElementById('product-review-save').focus()");
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
  assert.equal(await evaluate("document.activeElement===document.querySelector('#product-create-review [data-product-review-close]')"),true);
  checks.push(role+' Enter opens review and keyboard focus stays inside');
  for(const width of [390,768,1440])for(const dark of [false,true]){
   await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);await delay(400);
   const state=await evaluate("(()=>{const dialog=document.querySelector('.product-review-dialog'),r=dialog.getBoundingClientRect(),body=dialog.querySelector('.product-review-body');return {bounds:{left:r.left,right:r.right,top:r.top,bottom:r.bottom},inside:r.left>=0&&r.right<=innerWidth+1&&r.top>=0&&r.bottom<=innerHeight+1,overflow:dialog.scrollWidth>dialog.clientWidth+1,footer:dialog.querySelector('footer').getBoundingClientRect().bottom<=innerHeight,scrollable:body.scrollHeight>body.clientHeight}})()");
   if(!state.inside){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'layout-failure.png'),Buffer.from(shot.data,'base64'));}
   assert.equal(state.inside,true,role+'/'+width+' dialog outside viewport: '+JSON.stringify(state));assert.equal(state.overflow,false);assert.equal(state.footer,true);checks.push({role,width,dark,...state});
   if(role==='admin'&&[390,1440].includes(width)&&!dark){const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(dir,'overview-'+width+'.png'),Buffer.from(shot.data,'base64'));}
  }
  await evaluate("document.getElementById('product-review-edit').click()");assert.equal(await evaluate(isOpen),false);assert.equal(await evaluate("document.querySelector('[name=product_name]').value"),'Biogesic 500 mg');
  assert.equal(await evaluate("document.activeElement===document.querySelector('#product-create-form button[type=submit]')"),true);checks.push(role+' back keeps entries and restores focus');
  await field('price','8.25');await open();assert.equal(await evaluate("document.querySelector('[data-product-review=price]').textContent"),'₱8.25');
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});assert.equal(await evaluate(isOpen),false);checks.push(role+' Escape closes without saving');
  await open();const earlierPosts=posts.length;await field('price','9.00');await evaluate("document.getElementById('product-review-save').click()");
  assert.equal(posts.length,earlierPosts);assert.equal(await evaluate(isOpen),true);assert.equal(await evaluate("document.querySelector('[data-product-review=price]').textContent"),'₱9.00');checks.push(role+' changed values require a fresh confirmation');
  await evaluate("document.getElementById('product-create-review').dispatchEvent(new MouseEvent('click',{bubbles:true}))");assert.equal(await evaluate(isOpen),false);checks.push(role+' backdrop closes without saving');await field('price','8.25');
  await field('product_name','<img src=x onerror="window.reviewInjection=true">');await open();assert.equal(await evaluate(isOpen),false);assert.equal(await evaluate("document.querySelector('[name=product_name]').validity.customError && !window.reviewInjection"),true);checks.push(role+' markup is rejected before review');
  await field('product_name','Biogesic 500 mg');await field('sku','');await open();assert.equal(await evaluate(isOpen),false);assert.equal(await evaluate("document.querySelector('[name=sku]').required && !document.querySelector('[name=sku]').checkValidity()"),true);checks.push(role+' blank SKU is required before review');
  await field('sku','BIO500-DEMO');await open();await wait(isOpen);
  const before=posts.length;
  await evaluate("document.getElementById('product-review-save').click();document.getElementById('product-review-save').click()");
  await wait("!!document.querySelector('[data-saved]')");assert.equal(posts.length,before+1,'Only one final POST is allowed');
  const posted=posts.at(-1);assert.equal(posted.product_name,'Biogesic 500 mg');assert.equal(posted.price,'8.25');assert.equal(posted.stock,'120');assert.equal(posted.sku,'BIO500-DEMO');assert.equal(posted.branch_id,role==='admin'?'2':'1');assert.equal(Object.keys(posted).some(key=>key.includes('csrf')),true);checks.push(role+' final confirmation posts once with branch, entered values and CSRF');
 }
 fs.writeFileSync(path.join(dir,'browser-checks.json'),JSON.stringify({checks,posted_count:posts.length,application_database_accessed:false},null,2));
 console.log(`Passed ${checks.length} product overview checks; two final submissions went only to the fixture server.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
