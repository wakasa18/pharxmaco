// Dompdf CPDF QA helper, not a general-purpose PDF extractor.
const fs = require('node:fs');
const zlib = require('node:zlib');
const path = require('node:path');
const root = path.resolve(process.env.PDF_REVIEW_DIR || 'tmp/pdfs/stock-pdf-memory-fix');
const objects = new Map();
const name = process.argv[2] || 'stock-complete';
const source = fs.readFileSync(path.join(root, name+'.pdf')).toString('latin1');
for (const match of source.matchAll(/(\d+) 0 obj\s*([\s\S]*?)endobj/g)) objects.set(Number(match[1]),match[2]);
const decode = value => {
  const bytes = [];
  for(let i=0;i<value.length;i++) {
    if(value[i] !== '\\') {bytes.push(value.charCodeAt(i));continue;}
    const octal = value.slice(i+1).match(/^[0-7]{1,3}/);
    if(octal) {bytes.push(parseInt(octal[0],8)); i+=octal[0].length;continue;}
    i++; if(value[i] === '\r' || value[i] === '\n') continue;
    bytes.push(({n:10,r:13,t:9,b:8,f:12})[value[i]] ?? value.charCodeAt(i));
  }
  const b=Buffer.from(bytes); let result='';
  for(let i=0;i+1<b.length;i+=2) result+=String.fromCharCode(b.readUInt16BE(i));
  return result;
};
let page=0;const pages=[];
for(const [id,object] of objects) {
  if(!/\/Type \/Page\b/.test(object)) continue;
  const contentRef=object.match(/\/Contents (\d+) 0 R/);
  if(!contentRef) continue;
  const content=objects.get(Number(contentRef[1]));
  const streamHeader=/stream\r?\n/.exec(content);
  const length=Number(content.slice(0,streamHeader.index).match(/\/Length\s+(\d+)\b/)[1]);
  const stream=content.slice(streamHeader.index+streamHeader[0].length,streamHeader.index+streamHeader[0].length+length);
  const raw=/FlateDecode/.test(content.slice(0,content.indexOf('stream'))) ? zlib.inflateSync(Buffer.from(stream,'latin1')).toString('latin1') : stream;
  page++;
  if(process.argv.includes('--raw')) fs.writeFileSync(path.join(root,`${name}-page-${page}-raw.txt`),raw);
  const items=[];
  for(const block of raw.matchAll(/BT([\s\S]*?)ET/g)) {
    const text=[...block[1].matchAll(/\(((?:\\[\s\S]|[^\\)])*)\)/g)].map(m=>decode(m[1])).join('');
    if(text) items.push({text,position:block[1].match(/([-\d.]+) ([-\d.]+) Td/)?.[0]});
  }
  pages.push({page,items});
}
fs.writeFileSync(path.join(root,name+'-text.json'),JSON.stringify(pages,null,2));
if (!process.argv.includes('--quiet')) console.log(JSON.stringify(pages.map(p=>({page:p.page,first:p.items.slice(0,9)})),null,2));
