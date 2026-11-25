const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {records}=require('../latest-database-review/records.cjs');
const sourcePath=process.argv[2]||'C:/Users/warre/Downloads/u315645644_pharxmacotest (7).sql';
const source=fs.readFileSync(sourcePath,'utf8');
const tables=Object.fromEntries([...source.matchAll(/CREATE TABLE `([a-z_]+)`/g)].map(m=>[m[1],records(source,m[1])]));
const products=new Map(tables.products.map(p=>[p.id,p]));
const active=p=>p&&p.status==='active'&&!p.deleted_at&&!p.is_deleted&&!p.is_permanently_deleted;
const branches=new Map(tables.branches.map(b=>[b.id,b]));
const low=tables.branch_products.filter(b=>active(b)&&active(products.get(b.product_id))&&branches.get(b.branch_id)?.status==='active'&&b.stock<=b.reorder_level).map(b=>{
 const p=products.get(b.product_id),logs=tables.stock_logs.filter(l=>l.product_id===b.product_id&&l.branch_id===b.branch_id).sort((a,b)=>a.created_at.localeCompare(b.created_at)||a.id-b.id);
 const continuity=logs.slice(1).filter((l,i)=>l.previous_stock!==logs[i].new_stock);
 const items=new Set(tables.sale_items.filter(si=>si.product_id===p.id).map(si=>si.sale_id));
 const sales=tables.sales.filter(s=>s.branch_id===b.branch_id&&items.has(s.id)&&s.sale_date>='2026-09-19'&&s.sale_date<'2026-10-19');
 return {id:b.id,branch:b.branch_id,pid:p.id,name:p.product_name,stock:b.stock,level:b.reorder_level,expiry:b.expiration_date,price:b.price,cost:b.cost_price,created:b.created_at,product_created:p.created_at,updated:b.updated_at,logs:logs.length,continuity_errors:continuity.length,last_balance:logs.at(-1)?.new_stock,last_log:logs.at(-1)?.created_at,sales_in_month:sales.length};
});
const summary={source_sha256:crypto.createHash('sha256').update(source).digest('hex'),counts:Object.fromEntries(Object.entries(tables).map(([k,v])=>[k,v.length])),branches:tables.branches.map(b=>({id:b.id,name:b.branch_name,status:b.status})),sales_range:tables.sales.map(s=>s.sale_date).sort().filter((x,i,a)=>i===0||i===a.length-1),low_stock:low};
fs.writeFileSync(path.join(__dirname,'source-summary.json'),JSON.stringify(summary,null,2)+'\n');
console.log(JSON.stringify(summary,null,2));
