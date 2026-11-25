const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
module.exports=async({send,evaluate,port,delay,checks})=>{
 const fixture=JSON.parse(fs.readFileSync(path.join(__dirname,'preview.json'),'utf8'));
 const base=`http://127.0.0.1:${port}`, target=`.cart-row[data-product-id="${fixture.target_id}"]`;
 const money=value=>Number(value.replace(/[^0-9.]/g,''));
 async function wait(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Promo check timed out: '+expression);}
 async function page(name){await send('Page.navigate',{url:base+'/'+name+'.html'});await wait("document.readyState==='complete'&&!!document.querySelector('#autoPromoId')");}
 async function select(value){await evaluate(`document.querySelector('#discountSelect').value=${JSON.stringify(value)};document.querySelector('#discountSelect').dispatchEvent(new Event('change'));`);}
 await page('pos-product-promo');
 assert.equal(await evaluate("document.querySelector('#discountSelect').value"),'auto');
 assert.equal(await evaluate("document.querySelector('#discountSelect').selectedOptions[0].textContent.trim()"),'No Discount');checks.push('No Discount is the default and the matching product promo still applies');
 assert.equal(await evaluate("document.querySelector('#autoPromoId').value"),String(fixture.quotes[1].id));
 assert.equal(Number(await evaluate("document.querySelector('#autoPromoAmount').value")),fixture.quotes[1].amount);
 assert.equal(money(await evaluate("document.querySelector('#coFinalTotal').textContent")),fixture.quotes[1].final_total);checks.push('Actual database promo produces the same total as server calculation');
 assert.equal(await evaluate(`document.querySelector('${target} [data-line-promo]').hidden`),false);
 assert.equal(await evaluate(`document.querySelector('.cart-row[data-product-id="${fixture.unrelated_id}"] [data-line-promo]').hidden`),true);checks.push('Only the matching product has a discount note');
 for(const qty of [2,10]){
  await evaluate(`document.querySelector('${target} .cart-qty-input').value='${qty}';document.querySelector('${target} .cart-qty-input').dispatchEvent(new Event('input'));`);
  assert.equal(Number(await evaluate("document.querySelector('#autoPromoAmount').value")),fixture.quotes[qty].amount);
  assert.equal(money(await evaluate("document.querySelector('#coFinalTotal').textContent")),fixture.quotes[qty].final_total);
  checks.push(`Quantity ${qty} recalculates automatically and respects the cap`);
 }
 await evaluate(`document.querySelector('${target} .cart-qty-input').value='1';document.querySelector('${target} .cart-qty-input').dispatchEvent(new Event('input'));`);
 await select('auto');
 assert.equal(money(await evaluate("document.querySelector('#coFinalTotal').textContent")),fixture.quotes[1].final_total);checks.push('No Discount keeps automatic product promos');
 await page('pos-product-promo');
 assert.equal(await evaluate("document.querySelector('#discountSelect').selectedOptions[0].textContent.trim()"),'No Discount');checks.push('No Discount remains selected on reload within this cart');
 await page('pos-product-promo-fresh-cart');
 assert.equal(await evaluate("document.querySelector('#discountSelect').value"),'auto');checks.push('A new checkout does not inherit the previous customer override');
 await select('senior_citizen');
 assert.equal(await evaluate("document.querySelector('#statutoryBenefitPanel').hidden"),false);
 assert.equal(await evaluate("document.querySelector('#autoPromoId').value"),String(fixture.quotes[1].id));
 assert.equal(await evaluate(`document.querySelector('${target} [data-line-promo]').hidden`),false);
 assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),true);checks.push('Senior/PWD choice retains the promo while requiring beneficiary verification');
 await select('auto');
 await evaluate(`window.__checkout=null;window.fetch=async(url,options)=>{window.__checkout={url,fields:Object.fromEntries(options.body)};return new Response(JSON.stringify({success:false,message:'Preview only. No sale submitted.',redirect_url:null}),{status:422,headers:{'Content-Type':'application/json'}});};document.querySelector('#amountPaidInput').value='9999';document.querySelector('#completeSaleBtn').click();`);
 await wait("document.querySelector('#posNetworkStatusMessage').textContent==='Preview only. No sale submitted.'");
 const post=await evaluate('window.__checkout');assert.equal(post.fields.discount_id,'auto');assert.equal(post.fields.auto_promo_id,String(fixture.quotes[1].id));assert.equal(Number(post.fields.auto_promo_amount),fixture.quotes[1].amount);checks.push('Checkout submits the actual displayed promo ID and amount');
 for(const width of [390,1440]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
  assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);checks.push(`Promo messages fit at ${width}px`);
 }
 await evaluate(`document.querySelector('${target}').remove();document.querySelector('#amountPaidInput').dispatchEvent(new Event('input'));`);
 assert.equal(await evaluate("document.querySelector('#autoPromoId').value"),'');
 assert.equal(money(await evaluate("document.querySelector('#coFinalTotal').textContent")),fixture.unrelated_price);checks.push('Removing the eligible product removes its automatic discount');
};
