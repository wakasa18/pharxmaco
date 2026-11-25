const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const context={window:{}};vm.createContext(context);vm.runInContext(fs.readFileSync('public/assets/js/discount-math.js','utf8'),context);
const math=context.window.PharxmacoDiscount;
let count=0;
function check(test){
 const eligible=test.items.filter(item=>test.selected.includes(item.productId));
 const promos=test.promos.map(p=>({id:p.id,productId:p.product_id,categoryId:p.category_id,scope:p.applies_to||'product',type:p.discount_type,value:p.discount_value,
  minimum:p.minimum_purchase||0,maximum:p.max_discount_amount??null}));
 const quote=math.combinedStatutory(test.items,eligible,promos,test.status,12);
 assert.equal(math.cents(quote.discount),math.cents(test.discount),test.label);
 assert.equal(math.cents(quote.vatExemption),math.cents(test.vat),test.label);
 assert.equal(math.cents(quote.promotionAmount),math.cents(test.promo),test.label);
 assert.equal(quote.promotion?.id??null,test.promo_id,test.label);
 if(test.promo_ids)assert.deepEqual(Array.from(quote.promotions,p=>p.id),test.promo_ids,test.label);
 if(test.plans)for(const [id,plan]of Object.entries(test.plans)){
  assert.equal(math.cents(quote.items[id].discount),math.cents(plan.discount));
  assert.equal(math.cents(quote.items[id].vatExemption),math.cents(plan.vat_exemption));
  assert.equal(quote.items[id].statutory,plan.kind!==null);
 }
 count++;
}
for(const test of JSON.parse(fs.readFileSync('tests/fixtures/benefit-product-promo-cases.json','utf8')))check(test);
const generated='writable/benefit-promo-review/math-comparison.json';
if(fs.existsSync(generated))for(const test of JSON.parse(fs.readFileSync(generated,'utf8')))check(test);
console.log(`Passed ${count} combined Senior/PWD/product-promo cases, including per-line PHP/JS centavo comparison.`);
