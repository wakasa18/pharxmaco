/* Check vault links, reachability, source freshness and attachment integrity. Read-only except its result file. */
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const root=path.resolve(__dirname,'../..'),vault=path.join(root,'docs');
const slash=value=>value.split(path.sep).join('/');
const hash=value=>crypto.createHash('sha256').update(value).digest('hex');
const walk=dir=>fs.readdirSync(dir,{withFileTypes:true}).filter(e=>e.name!=='.obsidian').flatMap(e=>e.isDirectory()?walk(path.join(dir,e.name)):[path.join(dir,e.name)]);
const files=walk(vault),notes=files.filter(f=>f.endsWith('.md'));
const errors=[],edges=new Map(),texts=new Map();let links=0,anchorsChecked=0;
const strip=text=>text.replace(/^---\r?\n[\s\S]*?\r?\n---\r?\n/,'').replace(/^```[^\n]*\n[\s\S]*?^```[^\n]*$/gm,'');
function anchors(text){
    const values=new Set(),seen=new Map();
    for(const match of strip(text).matchAll(/^#{1,6}\s+(.+?)\s*#*\s*$/gm)){
        let slug=match[1].replace(/<[^>]*>/g,'').replace(/\[([^\]]+)\]\([^)]+\)/g,'$1').replace(/[`*_~]/g,'').toLowerCase()
            .replace(/[^\p{L}\p{N}_\-\s]/gu,'').replace(/\s/g,'-');
        const count=seen.get(slug)||0;seen.set(slug,count+1);if(count)slug+='-'+count;values.add(slug);
    }
    for(const match of text.matchAll(/\b(?:id|name)=["']([^"']+)["']/g))values.add(match[1]);
    return values;
}
function checkLink(file,target){
    if(/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(target))return;
    target=target.replace(/^<|>$/g,'');links++;
    const index=target.indexOf('#'),fragment=index<0?'':target.slice(index+1),pathname=index<0?target:target.slice(0,index);
    let decoded;try{decoded=decodeURIComponent(pathname);}catch{errors.push('Invalid encoded link in '+slash(path.relative(vault,file)));return;}
    const destination=pathname?path.resolve(path.dirname(file),decoded):file;
    const label=slash(path.relative(vault,file))+' -> '+target;
    if(!destination.startsWith(vault+path.sep)){errors.push('Link leaves vault: '+label);return;}
    if(!fs.existsSync(destination)){errors.push('Missing link: '+label);return;}
    if(destination.endsWith('.md')){
        edges.get(file).add(destination);
        if(fragment){
            const value=decodeURIComponent(fragment),text=texts.get(destination)??fs.readFileSync(destination,'utf8');
            anchorsChecked++;
            if(!anchors(text).has(value))errors.push('Missing heading anchor: '+label);
        }
    }
}
for(const file of notes){texts.set(file,fs.readFileSync(file,'utf8'));edges.set(file,new Set());}
for(const [file,text]of texts){
    const body=strip(text);
    for(const match of body.matchAll(/!?\[[^\]\n]*\]\((<[^>\n]+>|[^\s)]+)\)/g))checkLink(file,match[1]);
    if(/PhxDemo![A-Za-z0-9]+/.test(text))errors.push('Unredacted demo password: '+slash(path.relative(vault,file)));
}
const start=path.join(vault,'Home.md'),visited=new Set(),queue=[start];
while(queue.length){const file=queue.shift();if(visited.has(file))continue;visited.add(file);for(const next of edges.get(file)||[])if(!visited.has(next))queue.push(next);}
for(const file of notes)if(!visited.has(file))errors.push('Note unreachable from Home: '+slash(path.relative(vault,file)));
const manifest=JSON.parse(fs.readFileSync(path.join(vault,'reference/documentation-manifest.json'),'utf8'));
for(const entry of manifest.sources){
    const source=path.resolve(root,entry.source),target=path.resolve(vault,entry.target);
    if(!source.startsWith(root+path.sep)||!target.startsWith(vault+path.sep))errors.push('Source manifest path leaves workspace');
    else{
        if(!fs.existsSync(source)||hash(fs.readFileSync(source))!==entry.source_sha256)errors.push('Changed/missing source; run sync: '+entry.source);
        if(!fs.existsSync(target)||hash(fs.readFileSync(target))!==entry.vault_sha256)errors.push('Changed imported note; run sync: '+entry.target);
    }
}
for(const entry of manifest.assets){
    const source=path.join(root,entry.source),target=path.join(vault,entry.target);
    if(!fs.existsSync(source)||!fs.existsSync(target)||hash(fs.readFileSync(source))!==entry.sha256||hash(fs.readFileSync(target))!==entry.sha256)errors.push('Attachment/source mismatch: '+entry.target);
}
for(const entry of [...manifest.source_code,...manifest.recorded_artifacts]){
    const source=path.join(root,entry.source);
    if(!fs.existsSync(source)||hash(fs.readFileSync(source))!==entry.sha256)errors.push('Changed source/evidence inventory; run sync: '+entry.source);
}
if(notes.length!==manifest.counts.notes)errors.push('Note inventory changed; run sync.');
const result={checked_at:new Date().toISOString(),notes:notes.length,internal_links:links,heading_anchors_checked:anchorsChecked,
    reachable_from_home:visited.size,source_documents:manifest.sources.length,matching_manual_attachments:manifest.assets.length,
    errors,application_tests_rerun:false,application_database_accessed:false};
const output=path.join(root,'writable/docs-review');fs.mkdirSync(output,{recursive:true});
fs.writeFileSync(path.join(output,'checks.json'),JSON.stringify(result,null,2)+'\n');
console.log(JSON.stringify(result,null,2));
if(errors.length)process.exitCode=1;
