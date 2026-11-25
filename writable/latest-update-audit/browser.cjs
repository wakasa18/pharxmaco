const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
module.exports=async({send,evaluate,port,delay,checks})=>{
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"document.addEventListener('DOMContentLoaded',()=>{const style=document.createElement('style');style.textContent='@view-transition { navigation: none; }';document.head.appendChild(style);});"});
 await send('Page.navigate',{url:`http://127.0.0.1:${port}/pos-benefits.html`});
 for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'&&!!document.querySelector('#checkoutForm')"))break;await delay(40);}
 const rounding=await evaluate("(()=>{const math=window.PharxmacoDiscount,lines=[1,2].map(id=>({productId:id,subtotal:112.02,taxTreatment:'taxable'})),promos=[1,2].map(id=>({id:id+10,productId:id,type:'fixed',value:32.01,minimum:0,maximum:null})),quote=math.combinedStatutory(lines,lines,promos,'vat',12);return{chosenPayable:(22404-math.cents(quote.discount)-math.cents(quote.vatExemption))/100,bothPromosPayable:(22404-math.productPromotions(lines,promos).reduce((sum,offer)=>sum+math.cents(offer.amount),0))/100};})()");
 assert.equal(rounding.chosenPayable,160.02);assert.equal(rounding.bothPromosPayable,160.02);
 await evaluate("window.__auditRequests=[];window.fetch=async(url,options)=>{window.__auditRequests.push({url:String(url),method:options?.method||'GET'});return new Response('Bad Gateway',{status:502,headers:{'Content-Type':'text/html'}});};document.querySelector('#amountPaidInput').value='250';document.querySelector('#completeSaleBtn').click();");
 for(let i=0;i<100;i++){if(await evaluate("document.querySelector('#posNetworkStatusTitle').textContent==='Could not confirm the sale'"))break;await delay(40);}
 const observation=await evaluate("({pending:localStorage.getItem('pharxmaco-pending-checkout'),buttonDisabled:document.querySelector('#completeSaleBtn').disabled,status:document.querySelector('#posNetworkStatusTitle').textContent,requests:window.__auditRequests})");
 assert.ok(observation.pending);assert.equal(observation.buttonDisabled,true);
 assert.equal(observation.requests.length,2);assert.equal(observation.requests[0].method,'POST');
 assert.equal(observation.requests.some(request=>request.url.includes('checkout-status')),true);
 fs.writeFileSync(path.join(__dirname,'fixed-browser-results.json'),JSON.stringify({simulated_http_502:observation,vat_rounding:rounding},null,2));
 checks.push('VAT benefit comparison selects the lower 160.02 payable');
 checks.push('HTTP 502 checks saved-sale status and keeps recovery state when status is unavailable');
};
