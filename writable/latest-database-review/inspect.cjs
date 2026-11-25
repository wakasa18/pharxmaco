const fs=require('node:fs'),crypto=require('node:crypto');
const sourcePath=process.argv[2];if(!sourcePath)throw Error('Provide SQL export path.');
const source=fs.readFileSync(sourcePath,'utf8');
const schemas={};
for(const match of source.matchAll(/CREATE TABLE `([a-z_]+)` \(([\s\S]*?)\) ENGINE=[^;]+;/g)) {
 const columns=[...match[2].matchAll(/^\s*`([^`]+)`\s+([^\r\n]+)/gm)].map(m=>({name:m[1],definition:m[2].replace(/,$/,'')}));
 schemas[match[1]]=columns;
}
const report={source_path:sourcePath,sha256:crypto.createHash('sha256').update(source).digest('hex'),tables:schemas};
fs.writeFileSync(__dirname+'/inspection.json',JSON.stringify(report,null,2));
console.log(JSON.stringify({sha256:report.sha256,tables:Object.fromEntries(Object.entries(schemas).map(([name,columns])=>[name,columns.map(column=>column.name)]))},null,2));
