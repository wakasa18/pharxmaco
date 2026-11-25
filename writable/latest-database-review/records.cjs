const fs=require('node:fs');
function decode(token) {
 if(token.toUpperCase()==='NULL')return null;
 if(token.startsWith("'"))return token.slice(1,-1).replace(/\\([\\'"nrt0Z])|''/g,(all,escaped)=>escaped?({n:'\n',r:'\r',t:'\t','0':'\0',Z:'\x1a'}[escaped]??escaped):"'");
 if(!/^-?\d+(?:\.\d+)?$/.test(token))throw Error('Unsupported data literal.');
 return Number(token);
}
function tuples(text) {
 const rows=[];let row=null,token='',quoted=false;
 for(let i=0;i<text.length;i++){
  const ch=text[i];
  if(quoted){token+=ch;if(ch==='\\')token+=text[++i];else if(ch==="'"){if(text[i+1]==="'")token+=text[++i];else quoted=false;}}
  else if(ch==="'"){quoted=true;token+=ch;}
  else if(ch==='('){if(row)throw Error('Unsupported nested data expression.');row=[];token='';}
  else if(ch===','&&row){row.push(decode(token.trim()));token='';}
  else if(ch===')'){row.push(decode(token.trim()));rows.push(row);row=null;token='';}
  else if(row)token+=ch;
 }
 if(quoted||row)throw Error('Incomplete data tuple.');return rows;
}
function records(source,table){
 const rows=[];if(!/^[a-z_]+$/.test(table))throw Error('Unsupported table identifier.');
 for(const match of source.matchAll(new RegExp('INSERT\\s+INTO\\s+`'+table+'`\\s*\\(([^)]+)\\)\\s+VALUES\\s*([\\s\\S]*?);\\s*\\r?\\n','gi'))){
  const columns=[...match[1].matchAll(/`([^`]+)`/g)].map(x=>x[1]);
  for(const row of tuples(match[2])){if(row.length!==columns.length)throw Error('Malformed data tuple: '+table);rows.push(Object.fromEntries(columns.map((column,i)=>[column,row[i]])));}
 }return rows;
}
module.exports={records};
if(require.main===module){
 const source=fs.readFileSync(process.argv[2],'utf8');
 const tables=[...source.matchAll(/CREATE TABLE `([a-z_]+)`/g)].map(m=>m[1]);
 const products=records(source,'products');
 console.log(JSON.stringify({counts:Object.fromEntries(tables.map(table=>[table,records(source,table).length])),
  migrations:records(source,'migrations').map(m=>({version:m.version,class:m.class})),
  product_details:{generic_names:products.filter(p=>p.generic_name?.trim()).length,strengths:products.filter(p=>p.strength?.trim()).length,
   tax_treatments:products.reduce((counts,p)=>(counts[p.tax_treatment??'unspecified']=(counts[p.tax_treatment??'unspecified']||0)+1,counts),{}),
   medicine_types:products.reduce((counts,p)=>(counts[p.medicine_type??'unspecified']=(counts[p.medicine_type??'unspecified']||0)+1,counts),{})},
  demo_accounts:records(source,'users').filter(u=>['demo_admin','demo_cashier1','demo_cashier2'].includes(u.username)).map(u=>({username:u.username,role:u.role,status:u.status,branch_id:u.branch_id}))},null,2));
}
