const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const context = {window:{}};
vm.createContext(context);
vm.runInContext(fs.readFileSync('public/assets/js/discount-math.js','utf8'), context);
const math = context.window.PharxmacoDiscount;
const cases = JSON.parse(fs.readFileSync('tests/fixtures/discount-cases.json','utf8'));
for (const {subtotal,rule,expected} of cases) assert.equal(math.calculate(subtotal,rule),expected);
// Execute the actual checkout eligibility function, not a copy of its logic.
const source = fs.readFileSync('app/Views/cashier/sales/index.php','utf8');
const calc = source.slice(source.indexOf('    function syncPromotionPreview('),source.indexOf('    function refreshAll()'));
const attrs = {'data-type':'percentage','data-value':'10','data-minimum':'59.70','data-max':'','data-applies-to':'all'};
Object.assign(context, {
 discountSelect:{selectedIndex:0,options:[{value:'1',getAttribute:n=>attrs[n]||''}]},
 discountWarning:{textContent:'',classList:{remove(){},add(){}}},fmt:n=>n.toFixed(2),
 automaticPromoInfo:null,autoPromoId:null,autoPromoAmount:null,autoPromoSnapshot:null,productPromotions:[]
});
vm.runInContext('let currentDiscountValid = true;'+calc,context);
assert.equal(vm.runInContext('calcDiscount([{subtotal:19.90*3,productId:1,categoryId:1}],59.70)',context),5.97);
assert.equal(vm.runInContext('currentDiscountValid',context),true);
assert.equal(context.discountWarning.textContent,'');
attrs['data-applies-to']='category';attrs['data-category-id']='2';
assert.equal(vm.runInContext('calcDiscount([{subtotal:100,productId:1,categoryId:1}],100)',context),0);
assert.equal(vm.runInContext('currentDiscountValid',context),false);
attrs['data-applies-to']='all';attrs['data-value']='50';attrs['data-minimum']='0';
const half = vm.runInContext('calcDiscount([{subtotal:1.01,productId:1,categoryId:1}],1.01)',context);
assert.equal(half,0.51);
assert.equal((math.cents(1.01)-math.cents(half))/100,0.50);
assert.equal(vm.runInContext('currentDiscountValid',context),true);
// Product-only promotion in a mixed cart: unrelated products cannot qualify or receive it.
Object.assign(attrs, {'data-applies-to':'product','data-product-id':'1','data-type':'percentage','data-value':'10','data-minimum':'0'});
assert.equal(vm.runInContext('calcDiscount([{subtotal:200,productId:1,categoryId:1},{subtotal:100,productId:2,categoryId:1}],300)',context),20);
attrs['data-minimum']='20';
assert.equal(vm.runInContext('calcDiscount([{subtotal:10,productId:1,categoryId:1},{subtotal:100,productId:2,categoryId:1}],110)',context),0);
assert.equal(vm.runInContext('currentDiscountValid',context),false);
assert.ok(context.discountWarning.textContent.includes('matching items'));
attrs['data-type']='fixed';attrs['data-value']='3';
assert.equal(vm.runInContext('calcDiscount([{subtotal:20,productId:1,categoryId:1},{subtotal:100,productId:2,categoryId:1}],120)',context),3);
assert.equal(vm.runInContext('currentDiscountValid',context),true);
attrs['data-applies-to']='category';attrs['data-category-id']='1';attrs['data-type']='percentage';attrs['data-value']='50';attrs['data-minimum']='0';attrs['data-max']='0.75';
assert.equal(vm.runInContext('calcDiscount([{subtotal:1.01,productId:1,categoryId:1},{subtotal:1.01,productId:2,categoryId:1},{subtotal:100,productId:3,categoryId:2}],102.02)',context),0.75);
console.log('Passed shared centavo cases and actual POS eligibility/rounding regression checks.');
for (const {status,lines,discount,vatExemption} of JSON.parse(fs.readFileSync('tests/fixtures/statutory-discount-cases.json','utf8'))) {
 const quote = math.statutory(lines,status,12);
 assert.equal(quote.discount,discount);assert.equal(quote.vatExemption,vatExemption);
}
assert.throws(()=>math.statutory([{subtotal:112,taxTreatment:''}],'vat',12));
assert.throws(()=>math.statutory([{subtotal:112,taxTreatment:'taxable'}],'',12));
console.log('Passed shared Senior Citizen/PWD VAT, Non-VAT, mixed tax and rounding cases.');
for (const item of JSON.parse(fs.readFileSync('tests/fixtures/product-promotion-cases.json','utf8'))) {
 const promos=item.promos.map(p=>({id:p.id,productId:p.product_id,type:p.discount_type,value:p.discount_value,minimum:p.minimum_purchase||0,maximum:p.max_discount_amount??null}));
 const lines=item.items.map(line=>({productId:line.product_id,subtotal:line.subtotal}));
 const result=math.bestProductPromotion(lines,promos);
 assert.equal(result?.id??null,item.id,item.label);assert.equal(result?.amount??0,item.amount,item.label);
}
const automaticOption={value:'auto',getAttribute:()=>''};
context.discountSelect={selectedIndex:0,options:[automaticOption]};
context.productPromotions=[{id:3,productId:44,type:'percentage',value:20,minimum:0,maximum:100,
 name:'Bandage promo',option:{value:'3',getAttribute:key=>({'data-type':'percentage','data-value':'20','data-minimum':'0','data-max':'100','data-applies-to':'product','data-product-id':'44','data-promo-name':'Bandage promo'}[key]||'')}}];
context.autoPromoId={value:''};context.autoPromoAmount={value:''};context.automaticPromoInfo={hidden:true,textContent:''};
assert.equal(vm.runInContext('calcDiscount([{subtotal:55,productId:44,categoryId:1},{subtotal:100,productId:45,categoryId:1}],155)',context),11);
assert.equal(context.autoPromoId.value,'3');assert.equal(context.autoPromoAmount.value,'11.00');
context.autoPromoSnapshot={value:''};
context.productPromotions.push({id:4,productId:45,type:'fixed',value:15,minimum:100,name:'Second promo'});
assert.equal(vm.runInContext('calcDiscount([{subtotal:55,productId:44,categoryId:1},{subtotal:100,productId:45,categoryId:1}],155)',context),26);
assert.deepEqual(JSON.parse(context.autoPromoSnapshot.value),[{id:3,product_id:44,amount_cents:1100},{id:4,product_id:45,amount_cents:1500}]);
assert.equal(context.autoPromoId.value,'');assert.equal(context.autoPromoAmount.value,'26.00');
context.productPromotions.pop();
assert.equal(vm.runInContext('calcDiscount([{subtotal:100,productId:45,categoryId:1}],100)',context),0);
assert.equal(context.autoPromoId.value,'');assert.equal(vm.runInContext('currentDiscountValid',context),true);
console.log('Passed shared automatic product promo selection and actual POS auto-mode checks.');
for(const test of JSON.parse(fs.readFileSync('tests/fixtures/category-promotion-cases.json','utf8'))){
 const lines=test.items.map(line=>({productId:line.product_id,categoryId:line.category_id,subtotal:line.subtotal}));
 const promos=test.promos.filter(p=>(p.status||'active')==='active'&&(!p.end_date||p.end_date>='2026-10-16')).map(p=>({id:p.id,productId:p.product_id,categoryId:p.category_id,scope:p.applies_to,
  type:p.discount_type,value:p.discount_value,minimum:p.minimum_purchase||0,maximum:p.max_discount_amount??null}));
 const offers=math.productPromotions(lines,promos);
 assert.deepEqual(Array.from(offers,p=>({id:p.id,product_id:p.productId,amount:p.amount})),test.offers,test.label);
 assert.equal(offers.reduce((sum,p)=>sum+math.cents(p.amount),0),math.cents(test.total),test.label);
}
console.log('Passed shared automatic category minimum, fixed/capped allocation, overlap and rounding cases.');
