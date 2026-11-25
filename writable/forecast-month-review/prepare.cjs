const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),assert=require('node:assert/strict');
const {records}=require('../latest-database-review/records.cjs');
const root=path.resolve(__dirname,'../..');
const sourcePath=process.argv[2]||'C:/Users/warre/Downloads/u315645644_pharxmacotest (7).sql';
const source=fs.readFileSync(sourcePath,'utf8'),hash=s=>crypto.createHash('sha256').update(s).digest('hex');
const data=Object.fromEntries([...source.matchAll(/CREATE TABLE `([a-z_]+)`/g)].map(m=>[m[1],records(source,m[1])]));
const marker='PHX-DEMO-MONTH-20261019',from='2026-09-19',to='2026-10-18';
assert.ok(!data.sales.some(s=>s.notes?.startsWith(marker)),'Source already contains this fixture.');
const active=p=>p&&p.status==='active'&&!p.deleted_at&&!p.is_deleted&&!p.is_permanently_deleted;
const products=new Map(data.products.map(p=>[p.id,p])),branches=new Map(data.branches.map(b=>[b.id,b]));
const refunded=new Map();for(const r of data.refund_items)refunded.set(r.sale_item_id,(refunded.get(r.sale_item_id)||0)+r.quantity_refunded);
const salesById=new Map(data.sales.map(s=>[s.id,s])),observedUtc=new Set(),observedManila=new Set();
for(const si of data.sale_items){const s=salesById.get(si.sale_id);if(s&&['completed','partially_refunded'].includes(s.status)&&si.quantity-(refunded.get(si.id)||0)>0){
 const key=`${s.branch_id}:${si.product_id}:`,localDay=new Date(Date.parse(s.sale_date.replace(' ','T')+'Z')+8*3600000).toISOString().slice(0,10);
 observedUtc.add(key+s.sale_date.slice(0,10));observedManila.add(key+localDay);
}}
const pairs=data.branch_products.filter(bp=>active(bp)&&active(products.get(bp.product_id))&&branches.get(bp.branch_id)?.status==='active'&&bp.stock<=bp.reorder_level&&bp.expiration_date>= '2026-10-19'&&bp.created_at<from&&products.get(bp.product_id).created_at<from&&bp.price>0&&bp.price>=bp.cost_price).sort((a,b)=>a.branch_id-b.branch_id||a.product_id-b.product_id);
assert.equal(pairs.length,19,'Review the changed source selection.');
const cents=n=>Math.round(n*100),money=n=>(n/100).toFixed(2);
function automaticOffer(p,subtotal,day){
 let best=null;
 for(const d of data.discounts){if(!active(d)||!['product','category'].includes(d.applies_to)||(d.start_date&&d.start_date>day)||(d.end_date&&d.end_date<day)||d[d.applies_to+'_id']!==p[d.applies_to==='product'?'id':'category_id']||subtotal<cents(d.minimum_purchase||0))continue;
  let amount=d.discount_type==='fixed'?cents(d.discount_value):Math.round(subtotal*cents(d.discount_value)/10000);
  amount=Math.min(subtotal,amount,d.max_discount_amount===null?Infinity:cents(d.max_discount_amount));
  if(amount>0&&(!best||amount>best.amount||amount===best.amount&&d.id<best.id))best={id:d.id,name:d.discount_name,product_id:p.id,scope:d.applies_to,category_id:d.applies_to==='category'?d.category_id:null,amount};
 }return best;
}
const plan=[],pairDetails=[];
for(const [pairIndex,bp]of pairs.entries()){
 const p=products.get(bp.product_id),logs=data.stock_logs.filter(l=>l.product_id===p.id&&l.branch_id===bp.branch_id).sort((a,b)=>a.created_at.localeCompare(b.created_at)||a.id-b.id);
 assert.ok(logs.length&&logs.at(-1).new_stock===bp.stock,'Source ledger does not close at current stock.');
 for(let i=1;i<logs.length;i++)assert.equal(logs[i].previous_stock,logs[i-1].new_stock,'Source ledger is discontinuous.');
 let existingDays=0;
 for(let index=0;index<30;index++){
  const day=new Date(Date.parse(from+'T00:00:00Z')+index*86400000).toISOString().slice(0,10);
  const observationKey=`${bp.branch_id}:${p.id}:${day}`;
  if(observedUtc.has(observationKey)&&observedManila.has(observationKey)){existingDays++;continue;}
  let tick=Date.parse(day+'T02:00:00Z')+pairIndex*120000;
  const stamp=t=>new Date(t).toISOString().slice(0,19).replace('T',' ');
  while(logs.some(l=>l.created_at>=stamp(tick)&&l.created_at<=stamp(tick+1000)))tick+=10000;
  const delivery=stamp(tick),sale=stamp(tick+1000),previous=logs.filter(l=>l.created_at<delivery).at(-1),next=logs.find(l=>l.created_at>sale);
  const opening=previous?.new_stock??next?.previous_stock??bp.stock;
  assert.ok(Number.isInteger(opening)&&opening>=0);
  const quantity=2+(p.id+bp.branch_id)%3+Math.floor(index/10)+[0,1,0,0,1,0,-1][index%7];
  const subtotal=quantity*cents(bp.price),cost=quantity*cents(bp.cost_price),offer=automaticOffer(p,subtotal,day),discount=offer?.amount||0;
  const invoice=`DEMO-M30-20261019-${day.replaceAll('-','')}-B${bp.branch_id}-P${p.id}`;
  const deliveryId=`DM30-${day.replaceAll('-','')}-B${bp.branch_id}-P${p.id}`;
  assert.ok(invoice.length<=50&&deliveryId.length<=32);
  const metadata=JSON.stringify({version:2,tin:'',tax_status:'',vat_rate:12,serial:null,discount_name:'',statutory_discount:null,product_promotions:offer?[{...offer,amount:offer.amount/100}]:[],demo_data:marker});
  plan.push({inventory_id:bp.id,branch_id:bp.branch_id,product_id:p.id,day,quantity,opening,delivery_date:delivery,sale_date:sale,invoice_no:invoice,delivery_id:deliveryId,product_name:p.product_name,generic_name:p.generic_name,strength:p.strength,tax_treatment:p.tax_treatment,expiry:bp.expiration_date,manufacturer:p.manufacturer,supplier_id:p.supplier_id,price:money(cents(bp.price)),cost:money(cents(bp.cost_price)),subtotal:money(subtotal),discount:money(discount),total:money(subtotal-discount),profit:money(subtotal-discount-cost),receipt_metadata:metadata});
 }
 pairDetails.push({inventory_id:bp.id,branch_id:bp.branch_id,branch:branches.get(bp.branch_id).branch_name,product_id:p.id,product:p.product_name,stock:bp.stock,reorder_level:bp.reorder_level,expiry:bp.expiration_date,existing_days:existingDays,added_days:30-existingDays});
}
const q=value=>value===null||value===undefined?'NULL':typeof value==='number'?String(value):String(value).includes('\\')?`CONVERT(0x${Buffer.from(String(value)).toString('hex')} USING utf8mb4)`:`'${String(value).replaceAll("'","''")}'`;
const sqlFields=(table)=>[...source.match(new RegExp('CREATE TABLE `'+table+'` \\(([\\s\\S]*?)\\) ENGINE='))[1].matchAll(/^\s*`([^`]+)`\s+([^\r\n]+)/gm)].map(m=>({name:m[1],definition:m[2],scale:Number(m[2].match(/^decimal\(\d+,(\d+)\)/i)?.[1]??-1)}));
const fieldExpr=(table,alias)=>sqlFields(table).map(f=>`COALESCE(CAST(${alias}.\`${f.name}\` AS CHAR),'~NULL~')`).join(',');
const tableHashSql=(table,where='')=>`(SELECT COALESCE(SHA2(GROUP_CONCAT(SHA2(CONCAT_WS(CHAR(31),${fieldExpr(table,'g')}),256) ORDER BY g.id SEPARATOR ''),256),SHA2('',256)) FROM \`${table}\` g ${where})`;
const tableHash=(table)=>hash([...data[table]].sort((a,b)=>a.id-b.id).map(row=>hash(sqlFields(table).map(f=>row[f.name]===null?'~NULL~':f.scale>=0?Number(row[f.name]).toFixed(f.scale):String(row[f.name])).join('\x1f'))).join(''));
const guarded=['products','branch_products','branches','discounts','sales','sale_items','stock_logs'];
const guardConditions=guarded.map(table=>`${tableHashSql(table)}=${q(tableHash(table))}`);
const note=marker+': SYNTHETIC forecast training sale; no actual customer payment or cashier shift.';
const salesFields=['invoice_no','user_id','branch_id','discount_id','total_amount','discount_amount','final_total','payment_method','reference_no','amount_paid','change_amount','status','notes','sale_date','created_at','updated_at','receipt_metadata'];
const itemFields=['product_id','product_name_snapshot','cost_price_at_sale','quantity','price','subtotal','discount_applied','profit','created_at','stock_delivery_id','expiration_date_at_sale','generic_name_snapshot','strength_snapshot','tax_treatment_snapshot','vat_exemption_amount','statutory_discount_kind'];
const logFields=['product_id','branch_id','user_id','action_type','quantity','previous_stock','new_stock','remarks','created_at','supplier_id','manufacturer'];
const saleValues=r=>[r.invoice_no,null,r.branch_id,null,r.subtotal,r.discount,r.total,'cash',null,r.total,'0.00','completed',note,r.sale_date,r.sale_date,r.sale_date,r.receipt_metadata];
const itemValues=r=>[r.product_id,r.product_name,r.cost,r.quantity,r.price,r.subtotal,r.discount,r.profit,r.sale_date,r.delivery_id,r.expiry,r.generic_name,r.strength,r.tax_treatment,'0.00',null];
const logValues=(r,delivery)=>[r.product_id,r.branch_id,null,delivery?'stock_in':'stock_out',r.quantity,delivery?r.opening:r.opening+r.quantity,delivery?r.opening+r.quantity:r.opening,marker+(delivery?': SYNTHETIC resupply ':': SYNTHETIC sale ')+r.invoice_no,delivery?r.delivery_date:r.sale_date,delivery?r.supplier_id:null,r.manufacturer];
function temp(name,table,fields,rows,extraTypes={}){const ddl=sqlFields(table),defs=fields.map(field=>{const f=ddl.find(f=>f.name===field);let type=extraTypes[field]||f.definition.replace(/,\s*$/,'').replace(/\s+(?:NOT NULL|DEFAULT|COMMENT|ON UPDATE|COLLATE)[\s\S]*$/i,'');return `  \`${field}\` ${type} NULL`;});return `DROP TEMPORARY TABLE IF EXISTS ${name};\nCREATE TEMPORARY TABLE ${name} (\n${defs.join(',\n')}\n) ENGINE=InnoDB;\nINSERT INTO ${name} (${fields.map(f=>'`'+f+'`').join(',')}) VALUES\n${rows.map(row=>'('+row.map(q).join(',')+')').join(',\n')};\n`;}
const expectedSales=temp('phx_m30_sales','sales',salesFields,plan.map(saleValues));
const expectedItems=temp('phx_m30_items','sale_items',['invoice_no',...itemFields],plan.map(r=>[r.invoice_no,...itemValues(r)]),{invoice_no:'varchar(50)'});
const expectedLogs=temp('phx_m30_logs','stock_logs',logFields,plan.flatMap(r=>[logValues(r,true),logValues(r,false)]));
const compare=(alias,other,fields)=>fields.map(f=>`BINARY ${alias}.\`${f}\` <=> BINARY ${other}.\`${f}\``).join(' AND ');
const saleMatch=`(SELECT COUNT(*) FROM sales s JOIN phx_m30_sales e ON BINARY s.invoice_no=BINARY e.invoice_no WHERE ${compare('s','e',salesFields)})=${plan.length}`;
const itemMatch=`(SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN phx_m30_items i ON BINARY i.invoice_no=BINARY s.invoice_no WHERE s.notes=@phx_m30_note AND ${compare('si','i',itemFields)})=${plan.length}`;
const logMatch=`(SELECT COUNT(*) FROM stock_logs sl JOIN phx_m30_logs e ON BINARY e.remarks=BINARY sl.remarks WHERE ${compare('sl','e',logFields)})=${plan.length*2}`;
const counts=`(SELECT COUNT(*) FROM sales WHERE notes=@phx_m30_note)=${plan.length} AND (SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.notes=@phx_m30_note)=${plan.length} AND (SELECT COUNT(*) FROM stock_logs WHERE remarks LIKE CONCAT(@phx_m30_marker,':%'))=${plan.length*2}`;
const complete=`(${counts} AND ${saleMatch} AND ${itemMatch} AND ${logMatch})`;
const sql=`-- PHARXMACO: 30 COMPLETE DAYS OF FORECAST DEMO HISTORY, September 19 to October 18, 2026.
-- Additive phpMyAdmin import for the EXISTING TEST database from u315645644_pharxmacotest (7).sql.
-- Source SHA-256: ${hash(source)}
-- Fills missing product/day observations; retains existing observations and all original records.
-- ${plan.length} synthetic sales/items; ${plan.length*2} matching stock movements; 19 product/branch pairs.
-- No balances, prices, expiry dates, users, shifts, invoice counters or forecast snapshots are changed.
-- A complete repeat is a no-op. Changed source or partial/edited demo data causes a rollback.
-- Import the ENTIRE file on one connection. Stop on SQL errors; do not use this as a full backup.
SELECT id,receipt_metadata FROM sales LIMIT 0;
SELECT id,stock_delivery_id,expiration_date_at_sale,generic_name_snapshot,strength_snapshot,tax_treatment_snapshot FROM sale_items LIMIT 0;
SELECT id,previous_stock,new_stock FROM stock_logs LIMIT 0;
SET @phx_m30_old_tz=@@SESSION.time_zone,@phx_m30_old_group=@@SESSION.group_concat_max_len,
 @phx_m30_old_client=@@character_set_client,@phx_m30_old_results=@@character_set_results,@phx_m30_old_collation=@@collation_connection;
SET time_zone='+00:00';
SET SESSION group_concat_max_len=1048576;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @phx_m30_marker=${q(marker)},@phx_m30_note=${q(note)};
${expectedSales}
${expectedItems}
${expectedLogs}
START TRANSACTION;
SELECT id FROM branch_products WHERE id IN (${pairs.map(p=>p.id).join(',')}) ORDER BY id FOR UPDATE;
SET @phx_m30_transactional=(SELECT COUNT(*)=3 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('sales','sale_items','stock_logs') AND ENGINE='InnoDB');
SET @phx_m30_complete=${complete};
SET @phx_m30_source_ok=(${guardConditions.join('\n AND ')});
SET @phx_m30_empty=(SELECT COUNT(*)=0 FROM sales WHERE invoice_no LIKE 'DEMO-M30-20261019-%' OR notes LIKE CONCAT(@phx_m30_marker,'%'))
 AND (SELECT COUNT(*)=0 FROM stock_logs WHERE remarks LIKE CONCAT(@phx_m30_marker,'%'));
SET @phx_m30_insert=(@phx_m30_transactional AND @phx_m30_source_ok AND @phx_m30_empty AND NOT @phx_m30_complete);
INSERT INTO sales (${salesFields.map(f=>'`'+f+'`').join(',')}) SELECT ${salesFields.map(f=>'e.`'+f+'`').join(',')} FROM phx_m30_sales e WHERE @phx_m30_insert ORDER BY e.sale_date,e.branch_id,e.invoice_no;
INSERT INTO sale_items (sale_id,${itemFields.map(f=>'`'+f+'`').join(',')}) SELECT s.id,${itemFields.map(f=>'i.`'+f+'`').join(',')} FROM phx_m30_items i JOIN sales s ON BINARY s.invoice_no=BINARY i.invoice_no AND s.notes=@phx_m30_note WHERE @phx_m30_insert ORDER BY i.created_at,i.product_id;
INSERT INTO stock_logs (${logFields.map(f=>'`'+f+'`').join(',')}) SELECT ${logFields.map(f=>'e.`'+f+'`').join(',')} FROM phx_m30_logs e WHERE @phx_m30_insert ORDER BY e.created_at,e.branch_id,e.product_id;
SET @phx_m30_ok=(@phx_m30_transactional AND (@phx_m30_complete OR @phx_m30_insert) AND ${complete});
SET @phx_m30_finish=IF(@phx_m30_ok,'COMMIT','ROLLBACK');
PREPARE phx_m30_statement FROM @phx_m30_finish;
EXECUTE phx_m30_statement;
DEALLOCATE PREPARE phx_m30_statement;
DROP TEMPORARY TABLE phx_m30_sales;
DROP TEMPORARY TABLE phx_m30_items;
DROP TEMPORARY TABLE phx_m30_logs;
SET time_zone=@phx_m30_old_tz;
SET SESSION group_concat_max_len=@phx_m30_old_group;
SET character_set_client=@phx_m30_old_client,character_set_results=@phx_m30_old_results,collation_connection=@phx_m30_old_collation;
SELECT CASE WHEN @phx_m30_ok AND @phx_m30_complete THEN 'Already complete: no changes'
 WHEN @phx_m30_ok THEN '30-day forecast demo import complete'
 ELSE 'ROLLED BACK: source differs or demo fixture is partial/edited; no data added' END AS result,
 DATABASE() AS selected_database,${q(from)} AS forecast_from,${q(to)} AS forecast_to,
 IF(@phx_m30_ok AND NOT @phx_m30_complete,${plan.length},0) AS added_sales,
 IF(@phx_m30_ok AND NOT @phx_m30_complete,${plan.length*2},0) AS added_stock_movements;
`;
const output='database/samples/2026-10-19-month-forecast-demo.sql';
fs.writeFileSync(path.join(root,output),sql);
const report={source_path:sourcePath,source_sha256:hash(source),output,output_sha256:hash(sql),marker,from,to,days:30,pairs:pairDetails,added_sales:plan.length,added_items:plan.length,added_logs:plan.length*2,added_units:plan.reduce((n,r)=>n+r.quantity,0),added_revenue:money(plan.reduce((n,r)=>n+cents(Number(r.total)),0)),promo_sales:plan.filter(r=>Number(r.discount)>0).length,original_row_counts:Object.fromEntries(Object.entries(data).map(([k,v])=>[k,v.length])),guards:guarded.map(table=>({table,sha256:tableHash(table)})),plan};
fs.writeFileSync(path.join(__dirname,'manifest.json'),JSON.stringify(report,null,2)+'\n');
console.log(JSON.stringify({output,from,to,pairs:pairs.length,added_sales:plan.length,added_logs:plan.length*2,added_units:report.added_units,added_revenue:report.added_revenue,promo_sales:report.promo_sales},null,2));
