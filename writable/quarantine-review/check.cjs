const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const root = process.cwd(), dir = __dirname, profile = path.join(dir, 'chrome-' + Date.now());
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const checks = [], errors = []; let server, chrome, ws;
(async () => {
    server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/server-time' || url.pathname === '/session/activity') {
            res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify({success:true, server_time_ms:Date.now()})); return;
        }
        const base = url.pathname.startsWith('/assets/') ? path.join(root, 'public') : dir;
        const file = path.resolve(base, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(base + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'text/html; charset=utf-8');
        let data = fs.readFileSync(file);
        if (file.endsWith('.html')) data = data.toString().replaceAll('http://localhost/pharxmaco/public/', '/').replaceAll('http&#x3A;&#x2F;&#x2F;localhost&#x2F;pharxmaco&#x2F;public&#x2F;', '/');
        res.end(data);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir=' + profile,'about:blank'], {stdio:'ignore', windowsHide:true});
    let port;
    for (let i=0; i<100; i++) { const f=path.join(profile,'DevToolsActivePort'); if(fs.existsSync(f)) {port=Number(fs.readFileSync(f,'utf8').split('\n')[0]); break;} await delay(100); }
    if (!port) throw Error('Chrome did not start');
    const tabs = await (await fetch('http://127.0.0.1:' + port + '/json')).json();
    ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve,reject) => {ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
    let serial=0; const pending=new Map();
    ws.addEventListener('message',event=>{
        const m=JSON.parse(event.data), p=pending.get(m.id);
        if(p) { pending.delete(m.id); m.error ? p.reject(Error(m.error.message)) : p.resolve(m.result); }
        if(m.method==='Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.exception?.description);
    });
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
    const read=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const wait=async expression=>{for(let i=0;i<100;i++){if(await read(expression))return;await delay(50);}throw Error('UI timed out: '+expression);};
    const open=async name=>{await send('Page.navigate',{url:origin+'/'+name+'.html'});await wait("document.readyState==='complete' && !!document.querySelector('.quarantine-page')");if(['list','empty'].includes(name))await wait("!!document.querySelector('.dt-toolbar')");};
    await send('Page.enable');await send('Runtime.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await open('list');
    await read("void (window.__api = jQuery('table[aria-label=\"Quarantined returns\"]').DataTable())");
    assert.equal(await read('__api.rows().count()'),32);
    assert.equal(await read('__api.page.len()'),25);
    assert.equal(await read('__api.page.info().pages'),2);
    checks.push('DataTables initializes 32 rendered returns with pagination');
    assert.deepEqual(await read('JSON.parse(JSON.stringify(__api.order()))'),[[0,'desc']]);
    checks.push('returns use descending return-date ordering');
    await read("{const s=document.querySelector('.dt-search input');s.value='Distinctive quarantine item';s.dispatchEvent(new Event('input',{bubbles:true}));}");
    await delay(250);
    assert.equal(await read("__api.rows({search:'applied'}).count()"),1);
    checks.push('product quick search filters returned lines');
    await read("document.querySelector('.dt-clear').click()");
    assert.equal(await read("__api.rows({search:'applied'}).count()"),32);
    await read("void __api.page.len(10).page(1).draw(false)");
    assert.equal(await read('__api.page.info().page'),1);
    checks.push('clear, rows per page and pagination work');
    assert.equal(await read("document.querySelector('.q-filters').dataset.autoFilter"),'off');
    assert.equal(await read("document.querySelectorAll('.dt-toolbar input[type=date]').length"),0);
    checks.push('branch/status filters require Apply and have no duplicate date controls');
    assert.equal(await read("!!document.querySelector('#sidebarNavigation a[href=\"/admin/quarantine\"]')"),true);
    checks.push('admin navigation includes Quarantine');
    for(const name of ['list','detail','blocked','resolved','empty','setup']) {
        await open(name);
        for(const width of [320,390,768,1440]) for(const dark of [false,true]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
            await read(`document.documentElement.classList.toggle('dark',${dark})`);await delay(70);
            assert.equal(await read('document.documentElement.scrollWidth>innerWidth+1'),false,name+'/'+width+'/'+dark);
            checks.push('no horizontal page overflow: '+name+'/'+width+'/'+dark);
            if(['list','detail','blocked','resolved'].includes(name) && [390,1440].includes(width) && !dark) {
                await read('window.scrollTo(0,0)');const screenshot=await send('Page.captureScreenshot',{format:'png'});
                fs.writeFileSync(path.join(dir,`${name}-${width}.png`),Buffer.from(screenshot.data,'base64'));
            }
        }
    }
    await open('detail');
    assert.equal(await read("document.querySelector('.q-body[method=post]').checkValidity()"),false);
    await read("document.querySelector('input[value=released]').checked=true;document.querySelector('#q-note').value='Sealed units checked';document.querySelector('input[name=confirmed]').checked=true");
    assert.equal(await read("document.querySelector('.q-body[method=post]').checkValidity()"),true);
    assert.equal(await read("document.querySelector('.q-body[method=post]').getAttribute('action')"),'/admin/quarantine/1');
    checks.push('inspection form requires outcome, note and physical confirmation');
    assert.equal(await read("document.querySelector('input[name=inspection_token]').value.length"),48);
    checks.push('inspection form carries a server-issued review token');
    await open('blocked');
    assert.equal(await read("document.querySelector('input[value=released]').disabled"),true);
    assert.equal(await read("document.querySelector('input[value=damaged]').disabled"),false);
    checks.push('unknown delivery disables release while retaining non-stock outcomes');
    await open('resolved');
    assert.equal(await read("document.querySelectorAll('input[name=outcome]').length"),0);
    assert.equal(await read("document.querySelector('.quarantine-page').textContent.includes('Stock log #1')"),true);
    checks.push('resolved inspection is read-only with stock-log reference');
    await open('empty');
    assert.equal(await read("jQuery('table').DataTable().rows().count()"),0);
    checks.push('empty filtered result remains a functional DataTable');
    assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,javascript_errors:errors,application_records_changed:false},null,2));
    console.log(`Passed ${checks.length} quarantine browser scenarios.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
