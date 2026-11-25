const assert = require('node:assert/strict');

module.exports = async ({send, evaluate, port, delay, checks}) => {
 const base = `http://127.0.0.1:${port}`;
 // Static destination fixtures do not share the app shell's navigation transition opt-in.
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"document.addEventListener('DOMContentLoaded',()=>{const style=document.createElement('style');style.textContent='@view-transition { navigation: none; }';document.head.appendChild(style);});"});
 async function wait(expression) {
  for (let attempt=0; attempt<100; attempt++) {if(await evaluate(expression))return; await delay(50);}
  throw Error('Checkout browser condition timed out: '+expression+' '+JSON.stringify(await evaluate("({url:location.href,title:document.querySelector('#posNetworkStatusTitle')?.textContent,message:document.querySelector('#posNetworkStatusMessage')?.textContent,invalid:[...document.querySelectorAll(':invalid')].map(x=>({id:x.id,value:x.value,min:x.min})),busy:document.querySelector('#completeSaleBtn')?.disabled})")));
 }
 async function page(name) {
  await send('Page.navigate',{url:base+'/'+name});
  await wait("document.readyState==='complete'&&!!document.querySelector('#checkoutForm')");
 }
 await page('pos-benefits.html');
 await evaluate(`window.__posts=[];window.__reply={success:false,message:'Insufficient payment.',redirect_url:null,checkout_token:'${'b'.repeat(32)}',csrf_name:'csrf_token',csrf_hash:'${'c'.repeat(64)}'};
  window.fetch=async(url,options)=>{window.__posts.push({url,fields:Object.fromEntries(options.body),accept:options.headers.Accept});return new Response(JSON.stringify(window.__reply),{status:window.__reply.success?200:422,headers:{'Content-Type':'application/json'}});};
  document.querySelector('#amountPaidInput').value='250';document.querySelector('#saleNotes').value='Keep my payment form';document.querySelector('#completeSaleBtn').click();`);
 await wait("document.querySelector('#posNetworkStatusTitle').textContent==='Sale was not completed'");
 assert.equal(await evaluate('location.href'),base+'/pos-benefits.html');checks.push('Validation does not reload the POS');
 assert.equal(await evaluate("document.querySelector('#posNetworkStatusMessage').textContent"),'Insufficient payment.');checks.push('Exact server validation message is visible');
 assert.equal(await evaluate("document.querySelector('#amountPaidInput').value"),'250');
 assert.equal(await evaluate("document.querySelector('#saleNotes').value"),'Keep my payment form');
 assert.equal(await evaluate("document.querySelectorAll('.cart-row').length"),2);checks.push('Payment, notes and cart are retained');
 assert.equal(await evaluate("document.querySelector('[name=_checkout_token]').value"),'b'.repeat(32));
 assert.equal(await evaluate("[...document.querySelectorAll('[name=csrf_token]')].every(input=>input.value==='"+'c'.repeat(64)+"')"),true);checks.push('Checkout and CSRF tokens refresh before retry');
 assert.equal(await evaluate("localStorage.getItem('pharxmaco-pending-checkout')"),null);
 assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),false);checks.push('A rejected sale is safe to retry');
 await evaluate("window.__reply.message='Quantity exceeds stock.';document.querySelector('#amountPaidInput').value='224';document.querySelector('#completeSaleBtn').click();document.querySelector('#completeSaleBtn').click();");
 await wait("document.querySelector('#posNetworkStatusMessage').textContent==='Quantity exceeds stock.'");
 const posts=await evaluate('window.__posts');assert.equal(posts.length,2);assert.equal(posts[1].fields._checkout_token,'b'.repeat(32));assert.equal(posts[1].fields.csrf_token,'c'.repeat(64));assert.equal(posts[1].accept,'application/json');checks.push('Retry uses fresh tokens and double-click submits once');
 await evaluate(`window.__reply={success:true,redirect_url:'${base}/receipt-benefits.html'};document.querySelector('#completeSaleBtn').click();`);
 await wait(`location.href==='${base}/receipt-benefits.html'&&document.readyState==='complete'`);checks.push('Successful checkout opens the receipt destination');
 await page('pos-benefits.html');
 await evaluate(`window.fetch=async()=>new Response(JSON.stringify({success:false,message:'Start a cashier shift. Your cart was kept.',redirect_url:'${base}/cashier/start-shift'}),{status:422,headers:{'Content-Type':'application/json'}});document.querySelector('#amountPaidInput').value='224';document.querySelector('#completeSaleBtn').click();`);
 await wait(`location.href==='${base}/cashier/start-shift'`);checks.push('Shift closed after page load redirects to Start Shift');
 // Recovery tests use real Response objects, including malformed gateway/status bodies.
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"const nativeTimeout=window.setTimeout;window.setTimeout=(fn,ms,...args)=>nativeTimeout(fn,ms===1800?5:ms,...args);"});
 async function uncertain(reply,status=502,body='Bad Gateway',contentType='text/html') {
  await page('pos-benefits.html');
  await evaluate(`localStorage.removeItem('pharxmaco-pending-checkout');window.__requests=[];window.__statusReply=${JSON.stringify(reply)};
   window.fetch=async(url,options)=>{const method=options?.method||'GET';window.__requests.push({url:String(url),method});
    if(method==='POST')return new Response(${JSON.stringify(body)},{status:${status},headers:{'Content-Type':${JSON.stringify(contentType)}}});
    const reply=window.__statusReply;return new Response(reply.body??JSON.stringify(reply.result),{status:reply.status,headers:{'Content-Type':'application/json'}});};
   document.querySelector('#amountPaidInput').value='250';document.querySelector('#completeSaleBtn').click();`);
 }
 await uncertain({status:200,result:{ok:true,found:true,redirect_url:base+'/receipt-benefits.html'}});
 await wait(`location.href==='${base}/receipt-benefits.html'&&document.readyState==='complete'`);
 assert.equal(await evaluate("localStorage.getItem('pharxmaco-pending-checkout')"),null);checks.push('HTTP 502 recovers an already committed sale and opens its receipt');
 for(const reply of [{status:500,result:{ok:false}},{status:200,body:'invalid JSON'},
   {status:200,result:{}},{status:200,result:{ok:true,found:true}}]) {
  await uncertain(reply);
  await wait("document.querySelector('#posNetworkStatusTitle').textContent==='Could not confirm the sale'");
  assert.ok(await evaluate("localStorage.getItem('pharxmaco-pending-checkout')"));
  assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),true);
  assert.equal(await evaluate("document.querySelector('#checkCheckoutStatusBtn').hidden"),false);
  assert.equal(await evaluate("window.__requests.filter(r=>r.method==='POST').length"),1);
  checks.push('Unavailable or incomplete status keeps recovery state and blocks resubmission: '+JSON.stringify(reply));
  await evaluate("localStorage.removeItem('pharxmaco-pending-checkout')");
 }
 await uncertain({status:503,result:{ok:false,migration_required:true}});
 await wait("document.querySelector('#posNetworkStatusTitle').textContent==='Recovery setup is incomplete'");
 assert.ok(await evaluate("localStorage.getItem('pharxmaco-pending-checkout')"));
 assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),true);checks.push('Missing recovery schema cannot release an uncertain payment for retry');
 await evaluate("localStorage.removeItem('pharxmaco-pending-checkout')");
 for(const [status,body,type]of [[502,'Bad Gateway','text/html'],[500,'{"success":false}','application/json'],[200,'{}','application/json']]) {
  await uncertain({status:200,result:{ok:true,found:false}},status,body,type);
  await wait("document.querySelector('#posNetworkStatusTitle').textContent==='No completed sale found'");
  assert.equal(await evaluate("localStorage.getItem('pharxmaco-pending-checkout')"),null);
  assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),false);
  assert.equal(await evaluate("window.__requests.filter(r=>r.method==='GET').length"),6);
  checks.push('Authoritative repeated not-found results release the same checkout for retry after HTTP '+status);
 }
 await uncertain({status:500,result:{ok:false}});
 await wait("document.querySelector('#posNetworkStatusTitle').textContent==='Could not confirm the sale'");
 await evaluate(`window.__statusReply={status:200,result:{ok:true,found:true,redirect_url:'${base}/receipt-benefits.html'}};document.querySelector('#checkCheckoutStatusBtn').click();`);
 await wait(`location.href==='${base}/receipt-benefits.html'&&document.readyState==='complete'`);checks.push('Check Sale Status recovers after a temporary status service failure');
 await page('pos-checkout-closed-shift.html');
 assert.equal(await evaluate("document.querySelector('#completeSaleBtn')===null"),true);
 assert.match(await evaluate("document.querySelector('#startShiftSaleBtn').textContent"),/Start shift to complete sale/);checks.push('Known closed shift shows a Start Shift action');
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:false});
 assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);checks.push('Closed-shift action fits a mobile viewport');
 await evaluate("document.querySelector('.cart-qty-input').value='2';window.__sync=null;window.fetch=async(url,options)=>{window.__sync={url,fields:Object.fromEntries(options.body)};return new Response(JSON.stringify({success:false}),{status:409,headers:{'Content-Type':'application/json'}});};document.querySelector('#startShiftSaleBtn').click();");
 await wait("document.querySelector('#posNetworkStatusTitle').textContent==='Your cart could not be saved'");
 assert.equal(await evaluate('location.href'),base+'/pos-checkout-closed-shift.html');
 assert.equal(await evaluate("window.__sync.fields['items[1]']"),'2');checks.push('Starting a shift preserves edited quantities and reports a failed cart sync');
};
