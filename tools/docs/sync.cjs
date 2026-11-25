/* Refresh the documentation vault from first-party notes. Does not bootstrap PHP or access a database. */
const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto');
const root = path.resolve(__dirname, '../..'), vault = path.join(root, 'docs');
const date = new Intl.DateTimeFormat('en-CA', {timeZone:'Asia/Singapore', year:'numeric', month:'2-digit', day:'2-digit'}).format(new Date());
const marker = 'generated_by: tools/docs/sync.cjs';
const slash = value => value.split(path.sep).join('/');
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const esc = value => String(value).replace(/\|/g, '\\|').replace(/[\r\n]+/g, ' ');
const walk = base => !fs.existsSync(base) ? [] : fs.readdirSync(base, {withFileTypes:true}).sort((a,b)=>a.name.localeCompare(b.name)).flatMap(e=>e.isDirectory()?walk(path.join(base,e.name)):[path.join(base,e.name)]);
const repo = file => slash(path.relative(root, file));
const fromVault = file => slash(path.relative(vault, file));
function safeTarget(relative) {
    const target = path.resolve(vault, relative);
    if (!target.startsWith(vault + path.sep)) throw Error('Destination leaves docs: ' + relative);
    return target;
}
function generated(relative, title, type, body, extra = '') {
    const target = safeTarget(relative);
    if (fs.existsSync(target) && !fs.readFileSync(target,'utf8').includes(marker)) throw Error('Refusing to overwrite an unmanaged note: ' + relative);
    fs.mkdirSync(path.dirname(target), {recursive:true});
    fs.writeFileSync(target, `---\ntitle: ${JSON.stringify(title)}\ntype: ${type}\nstatus: ${type === 'source-snapshot' ? 'historical' : 'generated'}\nupdated: ${date}\n${marker}\n${extra}tags: [documentation, ${type === 'source-snapshot' ? 'archive' : 'index'}]\n---\n${body.trim()}\n`);
}
function noteLink(from, to, label) {
    const target = slash(path.relative(path.dirname(safeTarget(from)), safeTarget(to)));
    return `[${esc(label)}](${target.split('/').map(encodeURIComponent).join('/')})`;
}
const sources = [];
function add(source, target, kind, existing = false) {
    source = slash(source);
    const data = fs.readFileSync(path.join(root, source));
    const text = data.toString('utf8');
    sources.push({source, target, kind, existing, source_sha256:hash(data),
        title:text.match(/^#\s+(.+)$/m)?.[1]?.trim() || path.basename(source), text});
}
// Existing authored reviews remain authoritative and are not rewritten.
for (const file of walk(path.join(vault,'reviews')).filter(f=>f.endsWith('.md'))) {
    if (path.basename(file)==='README.md' || fs.readFileSync(file,'utf8').includes(marker)) continue;
    add(repo(file), fromVault(file), 'review', true);
}
for (const file of ['README.md','HOSTINGER-DEPLOYMENT.md','tests/README.md']) {
    if (fs.existsSync(path.join(root,file))) add(file, 'archive/project/' + (file==='README.md'?'Framework-README.md':file==='tests/README.md'?'Testing-README.md':path.basename(file)), 'root-history');
}
for (const file of walk(path.join(root,'database')).filter(f=>/\.(?:md|txt)$/i.test(f))) {
    const source = repo(file), rel = source.slice('database/'.length), section = rel.split('/')[0];
    const target = section==='reviews' ? 'reviews/'+path.basename(file) : section==='upgrades' ? 'upgrades/'+path.basename(file) : 'data-guides/'+rel;
    add(source, target.endsWith('.txt')?target+'.md':target, section==='reviews'?'review':section==='upgrades'?'upgrade':'data-guide');
}
for (const entry of fs.readdirSync(path.join(root,'writable'),{withFileTypes:true}).filter(e=>e.isDirectory())) {
    const dir = path.join(root,'writable',entry.name);
    for (const file of fs.readdirSync(dir,{withFileTypes:true}).filter(f=>f.isFile() && f.name.endsWith('.md'))) {
        add(repo(path.join(dir,file.name)), `evidence/${entry.name}/${file.name}`, 'evidence');
    }
}
const byHash = new Map();
for (const entry of sources) if (!byHash.has(entry.source_sha256)) byHash.set(entry.source_sha256, entry);
for (const file of walk(path.join(root,'build')).filter(f=>f.endsWith('.md'))) {
    const data = fs.readFileSync(file), same = byHash.get(hash(data));
    add(repo(file), same ? same.target : 'archive/packages/'+slash(path.relative(path.join(root,'build'),file)), 'package-history');
    const entry = sources[sources.length-1];
    if (same) entry.duplicate_of = same.source;
    else byHash.set(entry.source_sha256, entry);
}
const sourceMap = new Map(sources.map(entry=>[path.resolve(root,entry.source),entry]));
const references = [];
let redactions = 0;
function redact(text) {
    return text.replace(/PhxDemo![A-Za-z0-9]+/g, () => { redactions++; return '[demo password omitted from vault]'; });
}
function rewriteLinks(text, entry) {
    return text.split(/(^```[^\n]*\n[\s\S]*?^```[^\n]*$)/gm).map((part,index)=>index%2?part:part.replace(/(!?)\[([^\]\n]+)\]\((<[^>\n]+>|[^\s)]+)\)/g,(whole,image,label,href)=>{
        href = href.replace(/^<|>$/g,'');
        if (/^(?:[a-z][a-z0-9+.-]*:|#|\/\/)/i.test(href)) return whole;
        const split = href.indexOf('#'), fragment = split<0?'':href.slice(split), file = split<0?href:href.slice(0,split);
        let decoded; try { decoded = decodeURIComponent(file); } catch { decoded = file; }
        const original = path.resolve(path.dirname(path.join(root,entry.source)),decoded);
        const found = sourceMap.get(original);
        if (found) return (image?'!':'')+noteLink(entry.target,found.target,label).replace(/\)$/,fragment+')');
        const relative = slash(path.relative(root,original));
        const exists = fs.existsSync(original);
        references.push({source:entry.source, target:relative+fragment, exists});
        if (original.startsWith(vault+path.sep) && exists) return (image?'!':'')+noteLink(entry.target,fromVault(original),label).replace(/\)$/,fragment+')');
        return `${label} (repository ${exists?'file':'reference unavailable'}: \`${relative+fragment}\`)`;
    })).join('');
}
for (const entry of sources) {
    if (entry.existing || entry.duplicate_of) continue;
    let content = path.extname(entry.source)==='.txt' ? '# '+path.basename(entry.source)+'\n\n```text\n'+entry.text.trim()+'\n```' : entry.text.trim();
    content = rewriteLinks(redact(content), entry);
    const banner = `> [!info] Dated source snapshot\n> Source: \`${entry.source}\`. Findings and test counts refer to the original work. Read ${noteLink(entry.target,'project/Current-State.md','Current state')} and ${noteLink(entry.target,'project/Known-Issues.md','Known issues')} for subsequent changes.\n`;
    generated(entry.target,entry.title,'source-snapshot',banner+'\n'+content,
        `source_path: ${JSON.stringify(entry.source)}\nsource_sha256: ${entry.source_sha256}\n`);
}
// Keep the already verified manual PDF available inside the vault.
const manualManifest = JSON.parse(read('writable/system-manual/manifest.json'));
const pdfSource = repo(path.resolve(manualManifest.pdf));
if (!pdfSource.startsWith('output/pdf/') || !pdfSource.endsWith('.pdf')) throw Error('Manual PDF must be an output/pdf artifact.');
const pdfTarget = 'manual/' + path.basename(pdfSource);
const assets = [];
if (fs.existsSync(path.join(root,pdfSource))) {
    fs.mkdirSync(path.dirname(safeTarget(pdfTarget)),{recursive:true});
    const data = fs.readFileSync(path.join(root,pdfSource));
    fs.writeFileSync(safeTarget(pdfTarget),data);
    assets.push({source:pdfSource,target:pdfTarget,sha256:hash(data)});
}
function listSources(relative, kind, intro) {
    const entries = sources.filter(e=>e.kind===kind && !e.duplicate_of).sort((a,b)=>b.source.localeCompare(a.source));
    const maintained = kind === 'upgrade' ? walk(path.join(vault,'upgrades')).filter(f=>f.endsWith('.md') && path.basename(f)!=='README.md' && !fs.readFileSync(f,'utf8').includes(marker)).map(f=>({target:fromVault(f), title:fs.readFileSync(f,'utf8').match(/^#\s+(.+)$/m)?.[1] || path.basename(f)})) : [];
    generated(relative,kind==='review'?'Review index':'Database and deployment guides','index',
        `# ${kind==='review'?'Review index':'Database and deployment guides'}\n\n${intro}\n\n`+
        [...maintained,...entries].map(e=>'- '+noteLink(relative,e.target,e.title)).join('\n')+
        '\n\n'+noteLink(relative,'reference/Document-Catalog.md','Complete catalog')+' · '+noteLink(relative,'Home.md','Home'));
}
listSources('reviews/README.md','review','Reviews preserve the findings and test scope from their dates. Later repair reports supersede specific findings; consult [Known issues](../project/Known-Issues.md) before treating an old list as current.');
listSources('upgrades/README.md','upgrade','These are historical guide snapshots. Executable SQL stays in `database/upgrades`. Select a patch for the actual database baseline; a dated “latest” file is not a universal installer. [Database and recovery](../system/Database-and-Recovery.md) explains the file types.');
const routesText = read('app/Config/Routes.php');
let group='Public';
const routes=[];
for (const line of routesText.split(/\r?\n/)) {
    const g=line.match(/\$routes->group\([^\n]*'filter'\s*=>\s*'([^']+)'/); if(g)group=g[1];
    const match=line.match(/^\s*\$routes->(get|post|put|patch|delete)\(\s*'([^']+)'\s*,\s*'([^']+)'/);
    if(match) routes.push({method:match[1].toUpperCase(),route:match[2],controller:match[3],group});
    if (/^\}\);/.test(line)) group='Public';
}
generated('reference/Routes.md','Route inventory','reference',
    '# Route inventory\n\nGenerated from `app/Config/Routes.php`. Filters and controller/service checks together define effective access. Public login POST also uses login throttling. This is a static inventory, not a fresh access-control test.\n\n'+
    '| Method | Route | Controller | Route group |\n| --- | --- | --- | --- |\n'+
    routes.map(r=>`| ${r.method} | \`${esc(r.route)}\` | \`${esc(r.controller)}\` | ${esc(r.group)} |`).join('\n')+
    '\n\n[Architecture](../system/Architecture.md) · [Roles and access](../system/Roles-and-Access.md) · [Home](../Home.md)');
const migrations=walk(path.join(root,'app/Database/Migrations')).filter(f=>f.endsWith('.php')).map(repo);
const sqlFiles=walk(path.join(root,'database/upgrades')).filter(f=>f.endsWith('.sql')).map(repo);
function sqlKind(file) {
    if (/clear-cashier/.test(file)) return 'Deliberate history reset';
    if (/reconcile/.test(file)) return 'Targeted data repair';
    if (/latest|latest-changes/.test(file)) return 'Baseline-specific combined upgrade';
    if (/demo-users|branded-products|product-catalog|product-invoice-data|products-vatable|user-full-names/.test(file)) return 'Scoped data update/seed';
    if (/verify-only/.test(file)) return 'Verification queries';
    return 'Schema/safeguard update; read prerequisites';
}
generated('reference/Schema-and-SQL.md','Migration and SQL inventory','reference',
    '# Migration and SQL inventory\n\nThese are source files, not proof of applied migrations. SQL bodies and raw exports remain outside the vault. Use [Database and recovery](../system/Database-and-Recovery.md) to distinguish upgrade, seed, repair, reset and restore operations.\n\n'+
    '## Source migrations\n\n'+migrations.map(f=>'- `'+f+'`').join('\n')+
    '\n\n## Existing-database SQL files\n\n| Repository file | Intent | Guide |\n| --- | --- | --- |\n'+
    sqlFiles.map(f=>{const guide=sourceMap.get(path.resolve(root,f.replace(/\.sql$/,'.md'))), maintained='upgrades/'+path.basename(f,'.sql')+'.md';const target=guide?.target || (fs.existsSync(safeTarget(maintained))?maintained:null);return `| \`${path.basename(f)}\` | ${sqlKind(f)} | ${target?noteLink('reference/Schema-and-SQL.md',target,'Guide'):'Read the SQL header and prerequisites'} |`;}).join('\n')+
    '\n\nThe files in the table are under `database/upgrades`. Full exports under `database/exports` are intentionally not copied into this vault.\n\n[Home](../Home.md)');
const artifactEntries=[];
for (const dir of fs.readdirSync(path.join(root,'writable'),{withFileTypes:true}).filter(e=>e.isDirectory()&&/review|audit|update/.test(e.name)&&e.name!=='docs-review')) {
    for(const item of fs.readdirSync(path.join(root,'writable',dir.name),{withFileTypes:true}).filter(e=>e.isFile()&&/(?:check[^/]*|[^/]*checks|[^/]*results|result)\.json$/.test(e.name))) {
        const file=`writable/${dir.name}/${item.name}`, raw=fs.readFileSync(path.join(root,file));
        let summary;try{const value=JSON.parse(raw);summary=Array.isArray(value)?`${value.length} recorded entries`:'Object keys: '+Object.keys(value).slice(0,10).join(', ');}catch{summary='Not valid JSON; inspect original';}
        artifactEntries.push({source:file,sha256:hash(raw),summary});
    }
}
generated('reference/Verification-Artifacts.md','Recorded verification artifacts','reference',
    '# Recorded verification artifacts\n\nThis inventory records available result/check files and their source hashes. It does not rerun them or interpret every object as a passing test. Observation results may reproduce a defect. Raw local-data records and credentials are not imported.\n\n'+
    '| Repository artifact | Shape of recorded result | SHA-256 |\n| --- | --- | --- |\n'+
    artifactEntries.map(e=>`| \`${e.source}\` | ${esc(e.summary)} | \`${e.sha256}\` |`).join('\n')+
    '\n\n[Testing and evidence](../development/Testing-and-Evidence.md) · [Home](../Home.md)');
generated('reference/Repository-References.md','References outside the vault','reference',
    '# References outside the vault\n\nImported notes sometimes linked directly to repository code, patches, archives or old extracted files. Their paths are retained as text to open in the IDE. Documents available in the vault use normal internal links instead. A missing historical reference is preserved explicitly.\n\n'+
    '| Source note | Repository reference | Availability at sync |\n| --- | --- | --- |\n'+
    references.map(e=>`| ${noteLink('reference/Repository-References.md',sourceMap.get(path.resolve(root,e.source)).target,path.basename(e.source))} | \`${esc(e.target)}\` | ${e.exists?'Present':'Unavailable historical reference'} |`).join('\n')+
    '\n\n[Catalog](Document-Catalog.md) · [Home](../Home.md)');
generated('reference/Document-Catalog.md','Document catalog','index','# Document catalog\n\nCatalog generation in progress.');
const allNotes=walk(vault).filter(f=>f.endsWith('.md')).map(fromVault);
const groups=new Map();
for(const file of allNotes){const section=file.includes('/')?file.split('/')[0]:'Start here';if(!groups.has(section))groups.set(section,[]);groups.get(section).push(file);}
let catalog='# Document catalog\n\nEvery Markdown note in this vault is linked below. Imported sources retain their paths/hashes in `documentation-manifest.json`. Historical package copies with identical source content share a single note; distinct versions are archived separately.\n\n';
for(const [section,files] of groups){catalog+='## '+section+'\n\n';for(const file of files){const content=fs.readFileSync(safeTarget(file),'utf8'),title=content.match(/^#\s+(.+)$/m)?.[1]||path.basename(file);catalog+='- '+noteLink('reference/Document-Catalog.md',file,title)+'\n';}catalog+='\n';}
const duplicates=sources.filter(e=>e.duplicate_of);
catalog+='## Consolidated duplicate sources\n\n'+duplicates.map(e=>'- `'+e.source+'` → '+noteLink('reference/Document-Catalog.md',e.target,'same content')).join('\n')+'\n\n[Home](../Home.md) · [Maintenance](../development/Documentation-Workflow.md)\n';
generated('reference/Document-Catalog.md','Document catalog','index',catalog);
const codePaths=['app/Config/Routes.php','app/Config/ReceiptInvoice.php','app/Models/SaleItemModel.php','app/Libraries/ExpiryStockResolution.php','app/Libraries/AccountSession.php','app/Controllers/Cashier/SalesController.php','app/Libraries/CashierShift.php','public/assets/js/datatables-init.js'];
const manifest={schema_version:1,updated:date,scope:'First-party notes; no raw SQL exports, runtime secrets or database connection.',
    sources:sources.map(({text,...e})=>({...e,vault_sha256:hash(fs.readFileSync(safeTarget(e.target)))})),
    assets,repository_references:references,recorded_artifacts:artifactEntries,
    source_code:codePaths.map(source=>({source,sha256:hash(fs.readFileSync(path.join(root,source)))})),
    counts:{notes:allNotes.length,source_documents:sources.length,imported_unique:sources.filter(e=>!e.existing&&!e.duplicate_of).length,
        retained_existing_reviews:sources.filter(e=>e.existing).length,consolidated_duplicates:duplicates.length,redacted_demo_passwords:redactions,
        routes:routes.length,migrations:migrations.length,sql_patches:sqlFiles.length,verification_artifacts:artifactEntries.length}};
fs.mkdirSync(path.join(vault,'reference'),{recursive:true});
fs.writeFileSync(path.join(vault,'reference/documentation-manifest.json'),JSON.stringify(manifest,null,2)+'\n');
console.log(JSON.stringify(manifest.counts,null,2));
