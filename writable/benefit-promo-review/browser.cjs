const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
module.exports=async({send,evaluate,port,delay,checks})=>{
 const base=`http://127.0.0.1:${port}`,money=value=>Number(value.replace(/[^0-9.]/g,''));
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"document.addEventListener('DOMContentLoaded',()=>{const style=document.createElement('style');style.textContent='@view-transition { navigation: none; }';document.head.appendChild(style);});"});
 async function wait(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Combined promo check timed out: '+expression);}
 async function page(name,selector='#discountSelect'){await send('Page.navigate',{url:base+'/'+name+'.html'});await wait(`document.readyState==='complete'&&!!document.querySelector('${selector}')`);}
 async function total(expected){assert.equal(money(await evaluate("document.querySelector('#coFinalTotal').textContent")),expected);}
 async function benefit(kind){await evaluate(`document.querySelector('#discountSelect').value='${kind}';document.querySelector('#discountSelect').dispatchEvent(new Event('change'));document.querySelector('#beneficiaryName').value='Test Beneficiary';document.querySelector('#beneficiaryId').value='TEST-ID';document.querySelector('#benefitVerified').checked=true;document.querySelector('.benefit-product[data-product-id="1"]').checked=true;document.querySelector('#statutoryBenefitPanel').dispatchEvent(new Event('input'));`);}
 async function qty(id,value){await evaluate(`document.querySelector('.cart-row[data-product-id="${id}"] .cart-qty-input').value='${value}';document.querySelector('.cart-row[data-product-id="${id}"] .cart-qty-input').dispatchEvent(new Event('input'));`);}
 await page('combined-pos-25');await total(128);
 const choices=await evaluate("[...document.querySelector('#discountSelect').options].map(option=>({value:option.value,label:option.textContent.trim(),scope:option.dataset.appliesTo}))");
 assert.equal(choices[0].label,'No Discount');
 assert.equal(await evaluate("document.querySelector('#discountSelect').selectedOptions[0].textContent.trim()"),'No Discount');
 assert.equal(choices.some(option=>/automatic product promos/i.test(option.label)),false);
 assert.equal(choices.some(option=>['product','category'].includes(option.scope)||['3','4','6'].includes(option.value)),false);
 assert.equal(choices.find(option=>option.value==='senior_citizen').label,'Senior Citizen');
 assert.equal(choices.find(option=>option.value==='pwd').label,'PWD');
 checks.push('No Discount is the default, with no automatic/product promo options and simple benefit labels');
 assert.deepEqual(JSON.parse(await evaluate("document.querySelector('#autoPromoSnapshot').value")),[{id:4,product_id:1,amount_cents:2800},{id:3,product_id:2,amount_cents:1100}]);
 checks.push('Every qualifying product gets its own automatic promo before selecting a beneficiary');
 for(const [choice,expected]of [['5',150.3],['auto',128]]){
  await evaluate(`document.querySelector('#discountSelect').value='${choice}';document.querySelector('#discountSelect').dispatchEvent(new Event('change'));`);await total(expected);
 }
 checks.push('All-product offers remain selectable, and No Discount restores automatic product/category promos');
 await evaluate("window.__defaultPost=null;window.__originalFetch=window.fetch;window.fetch=async(url,options)=>{window.__defaultPost=Object.fromEntries(options.body);return new Response(JSON.stringify({success:false,message:'Default preview only.',redirect_url:null}),{status:422,headers:{'Content-Type':'application/json'}});};document.querySelector('#amountPaidInput').value='200';document.querySelector('#completeSaleBtn').click();");
 await wait("document.querySelector('#posNetworkStatusMessage').textContent==='Default preview only.'");
 const defaultPost=await evaluate('window.__defaultPost');assert.equal(defaultPost.discount_id,'auto');assert.equal(defaultPost.auto_promo_amount,'39.00');assert.equal(JSON.parse(defaultPost.auto_promo_snapshot).length,2);
 checks.push('No Discount submits both displayed product promos using automatic checkout pricing');
 await evaluate('window.fetch=window.__originalFetch');await page('combined-pos-25');await total(128);
 await evaluate("sessionStorage.setItem('pharxmaco-pos-discount',JSON.stringify({scope:document.querySelector('#discountSelect').dataset.selectionScope,choice:''}));");
 await page('combined-pos-25');await total(128);
 assert.equal(await evaluate("document.querySelector('#discountSelect').selectedOptions[0].textContent.trim()"),'No Discount');
 checks.push('A previously saved empty No Discount choice migrates without disabling product promos');
 await evaluate("document.querySelector('#discountSelect').value='senior_citizen';document.querySelector('#discountSelect').dispatchEvent(new Event('change'));");await total(128);
 assert.equal(await evaluate("document.querySelector('#completeSaleBtn').disabled"),true);checks.push('Promos stay visible while beneficiary details are incomplete, with checkout blocked');
 await benefit('senior_citizen');await total(124);
 assert.equal(await evaluate("document.querySelector('#autoPromoId').value"),'3');
 assert.equal(await evaluate("document.querySelector('#autoPromoAmount').value"),'11.00');checks.push('Senior benefit and promo on another product coexist');
 assert.equal(money(await evaluate("document.querySelector('#coStatutoryAmt').textContent")),20);
 assert.equal(money(await evaluate("document.querySelector('#coProductPromoAmt').textContent")),11);
 assert.equal(money(await evaluate("document.querySelector('#coVatExemptionAmt').textContent")),12);
 assert.equal(await evaluate("getComputedStyle(document.querySelector('#coDiscountRow')).display"),'none');checks.push('Payment summary separates promo, statutory discount and VAT relief without double counting');
 assert.match(await evaluate("document.querySelector('.cart-row[data-product-id=\"1\"] [data-line-promo]').textContent"),/Senior Citizen/);
 assert.match(await evaluate("document.querySelector('.cart-row[data-product-id=\"2\"] [data-line-promo]').textContent"),/Product promo/);checks.push('Each cart line shows its applied treatment');
 await qty(2,2);await total(168);checks.push('Promo quantity changes retain Senior/PWD amounts on the other product');
 await qty(2,1);await qty(1,2);await total(204);checks.push('Beneficiary quantity changes retain the product promo');
 await qty(1,1);await benefit('pwd');await total(124);
 assert.equal(await evaluate("document.querySelector('#coStatutoryLabel').textContent"),'PWD discount');checks.push('PWD follows the same combined rules');
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:900,deviceScaleFactor:1,mobile:false});
 await evaluate("document.querySelector('[data-pos-target=payment]').click()");await delay(150);
 assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);
 const screenshot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(__dirname,'combined-pos-390.png'),Buffer.from(screenshot.data,'base64'));checks.push('Combined payment summary fits on mobile');
 await evaluate("window.__post=null;window.fetch=async(url,options)=>{window.__post=Object.fromEntries(options.body);return new Response(JSON.stringify({success:false,message:'Preview only; no real sale.',redirect_url:null}),{status:422,headers:{'Content-Type':'application/json'}});};document.querySelector('#amountPaidInput').value='200';document.querySelector('#completeSaleBtn').click();");
 await wait("document.querySelector('#posNetworkStatusMessage').textContent==='Preview only; no real sale.'");
 const post=await evaluate('window.__post');assert.equal(post.discount_id,'pwd');assert.equal(post.include_product_promos,'1');assert.equal(post.auto_promo_id,'3');assert.equal(post.auto_promo_amount,'11.00');assert.equal(post['benefit_products[1]'],'1');assert.equal(post['benefit_products[2]'],undefined);checks.push('Checkout sends both modes with only the selected beneficiary products');
 await evaluate("document.querySelector('#discountSelect').value='auto';document.querySelector('#discountSelect').dispatchEvent(new Event('change'));");await total(128);
 assert.equal(await evaluate("document.querySelector('#includeProductPromos').disabled"),true);checks.push('No Discount removes beneficiary benefits while keeping every product promo');
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await page('combined-pos-40');await benefit('senior_citizen');await total(111.2);
 assert.deepEqual(JSON.parse(await evaluate("document.querySelector('#autoPromoSnapshot').value")),[{id:4,product_id:1,amount_cents:4480},{id:3,product_id:2,amount_cents:1100}]);
 assert.equal(await evaluate("document.querySelector('#autoPromoAmount').value"),'55.80');
 assert.equal(await evaluate("document.querySelector('#coStatutoryRow').hidden"),true);
 assert.equal(await evaluate("document.querySelector('#coVatExemptionRow').hidden"),true);checks.push('A better overlapping promo replaces statutory treatment while the other product keeps its promo');
 await benefit('pwd');await total(111.2);checks.push('PWD also keeps every qualifying product promo');
 await evaluate("window.__post=null;window.fetch=async(url,options)=>{window.__post=Object.fromEntries(options.body);return new Response(JSON.stringify({success:false,message:'Multi preview only.',redirect_url:null}),{status:422,headers:{'Content-Type':'application/json'}});};document.querySelector('#amountPaidInput').value='200';document.querySelector('#completeSaleBtn').click();");
 await wait("document.querySelector('#posNetworkStatusMessage').textContent==='Multi preview only.'");
 const multi=await evaluate('window.__post');assert.equal(multi.discount_id,'pwd');assert.equal(multi.auto_promo_amount,'55.80');assert.equal(JSON.parse(multi.auto_promo_snapshot).length,2);checks.push('Checkout submits every per-product promo alongside PWD');
 for(const kind of ['senior_citizen','pwd'])for(const type of ['receipt','details']){
  await page(`combined-${type}-${kind}`,type==='receipt'?'.receipt-page':'.sale-details-page');
  const text=await evaluate('document.body.textContent');assert.match(text,/Promotions/);assert.match(text,kind==='pwd'?/PWD discount/:/Senior Citizen discount/);assert.match(text,/124\.00/);assert.match(text,/Test Beneficiary/);
  for(const width of [390,1440]){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);}
  checks.push(`${kind} ${type} retains both deductions, beneficiary and total at mobile/desktop widths`);
 }
 for(const kind of ['auto','senior_citizen','pwd'])for(const type of ['receipt','details']){
  await page(`multi-${type}-${kind}`,type==='receipt'?'.receipt-page':'.sale-details-page');
  const text=await evaluate('document.body.textContent');assert.match(text,/Promo 1/);assert.match(text,/Promo 2/);assert.match(text,kind==='auto'?/213\.00/:/209\.00/);
  for(const width of [390,1440]){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);}
  checks.push(`${kind} ${type} displays multiple saved promos and correct total on mobile/desktop`);
 }
 await page('category-pos');await total(225.25);
 assert.equal(await evaluate("document.querySelector('#discountSelect').selectedOptions[0].textContent.trim()"),'No Discount');
 assert.equal(await evaluate("document.querySelectorAll('#discountSelect option[data-applies-to=category]').length"),0);
 assert.deepEqual(JSON.parse(await evaluate("document.querySelector('#autoPromoSnapshot').value")),[{id:4,product_id:1,amount_cents:2800},{id:8,product_id:2,amount_cents:1375}]);
 assert.equal(await evaluate("document.querySelector('.cart-row[data-product-id=\"3\"] [data-line-promo]').hidden"),true);
 checks.push('Category promos apply by default without dropdown options and unrelated categories receive no discount');
 await benefit('senior_citizen');await total(221.25);
 assert.equal(await evaluate("document.querySelector('#autoPromoAmount').value"),'13.75');
 assert.equal(money(await evaluate("document.querySelector('#coStatutoryAmt').textContent")),20);
 checks.push('Senior Citizen keeps the better statutory treatment while another item keeps its category promo');
 await qty(2,0);await total(180);
 assert.equal(await evaluate("document.querySelector('#autoPromoAmount').value"),'0.00');
 checks.push('Unrelated category spend cannot satisfy the category promo minimum');
 await qty(2,2);await total(265.23);
 assert.equal(await evaluate("document.querySelector('#autoPromoAmount').value"),'24.77');
 checks.push('Quantity changes share the category cap once and retain exact centavos alongside statutory benefits');
 await qty(2,1);await benefit('pwd');await total(221.25);checks.push('PWD retains the best category promo on other matching products');
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:900,deviceScaleFactor:1,mobile:false});
 await evaluate("document.querySelector('[data-pos-target=payment]').click()");await delay(150);
 assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);
 const categoryShot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(__dirname,'category-pos-390.png'),Buffer.from(categoryShot.data,'base64'));
 checks.push('Category and PWD payment totals fit on mobile');
 await evaluate("window.__categoryPost=null;window.fetch=async(url,options)=>{window.__categoryPost=Object.fromEntries(options.body);return new Response(JSON.stringify({success:false,message:'Category preview only.',redirect_url:null}),{status:422,headers:{'Content-Type':'application/json'}});};document.querySelector('#amountPaidInput').value='500';document.querySelector('#completeSaleBtn').click();");
 await wait("document.querySelector('#posNetworkStatusMessage').textContent==='Category preview only.'");
 const categoryPost=await evaluate('window.__categoryPost');assert.equal(categoryPost.discount_id,'pwd');assert.equal(categoryPost.auto_promo_amount,'13.75');
 assert.deepEqual(JSON.parse(categoryPost.auto_promo_snapshot),[{id:8,product_id:2,amount_cents:1375}]);
 checks.push('Checkout submits the displayed per-item category promo and selected PWD benefit');
 await evaluate("document.querySelector('#discountSelect').value='auto';document.querySelector('#discountSelect').dispatchEvent(new Event('change'));");await total(225.25);
 checks.push('No Discount restores the best product/category promos after leaving PWD');
 for(const kind of ['auto','senior_citizen','pwd'])for(const type of ['receipt','details']){
  await page(`category-${type}-${kind}`,type==='receipt'?'.receipt-page':'.sale-details-page');
  const text=await evaluate('document.body.textContent');assert.match(text,/Category offer/);assert.match(text,/Promotions/);assert.match(text,kind==='auto'?/225\.25/:/221\.25/);
  for(const width of [390,1440]){await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);}
  checks.push(`${kind} ${type} displays saved category discounts and exact totals on mobile/desktop`);
 }
 };
