const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const root = process.cwd(), dir = __dirname, limits = JSON.parse(fs.readFileSync(path.join(dir,'limits.json')));
const profile = path.join(dir, 'chrome-' + Date.now()), delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const checks = [], exceptions = [], posts = [];
let server, chrome, ws, origin;
(async () => {
    server = http.createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (req.method === 'POST') { posts.push(url.pathname); res.end('{}'); return; }
        if (url.pathname === '/session/activity') { res.setHeader('Content-Type','application/json'); res.end('{"ok":true}'); return; }
        const base = url.pathname.startsWith('/assets/') ? path.join(root,'public') : dir;
        const file = path.resolve(base, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(base + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'text/html; charset=utf-8');
        res.end(fs.readFileSync(file));
    });
    await new Promise(resolve => server.listen(0,'127.0.0.1',resolve)); origin = 'http://127.0.0.1:' + server.address().port;
    chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port=0','--user-data-dir=' + profile,'about:blank'], {stdio:'ignore',windowsHide:true});
    let port;
    for (let i=0;i<100;i++) { const file=path.join(profile,'DevToolsActivePort'); if(fs.existsSync(file)){port=Number(fs.readFileSync(file,'utf8').split('\n')[0]);break;} await delay(100); }
    if (!port) throw Error('Chrome did not start');
    const tabs = await (await fetch('http://127.0.0.1:' + port + '/json')).json();
    ws = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve,reject) => {ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
    let serial=0; const pending=new Map();
    ws.addEventListener('message',event => {const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params.exceptionDetails.exception?.description);});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const wait=async expression=>{for(let i=0;i<120;i++){if(await evaluate(expression))return;await delay(50);}throw Error('Timed out: '+expression);};
    const test=async(label,expression)=>{assert.equal(await evaluate(expression),true,label);checks.push(label);};
    const go=async name=>{
        await evaluate('window.__oldExpiryDocument=true');
        await send('Page.navigate',{url:origin+'/'+name+'.html'});
        await wait("!window.__oldExpiryDocument && document.readyState==='complete' && !!document.querySelector('[data-expiry-ready]')");
        await evaluate("window.date=document.querySelector('[data-expiry-input]');window.review=document.querySelector('[data-expiry-review]');window.reason=document.querySelector('[data-expiry-reason]');window.wrap=document.querySelector('[data-expiry-reason-wrap]');window.setDate=value=>{date.value=value;date.dispatchEvent(new Event('input',{bubbles:true}));};");
    };
    await send('Page.enable');await send('Runtime.enable');
    const afterMaximum = String(Number(limits.maximum.slice(0,4)) + 1) + '-01-01';
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        const check=(label,expression)=>test(width+': '+label,expression);
        for(const name of ['admin-create','cashier-create','catalog','admin-replace','cashier-replace']) {
            await go(name);
            await check(name+' browser bounds match server',`date.min===${JSON.stringify(limits.minimum)} && date.max===${JSON.stringify(limits.maximum)}`);
            await check(name+' review controls and instructions removed',`!document.querySelector('[name="expiry_range_reviewed"],[name="expiry_range_reason"],[data-expiry-review],[data-expiry-reason]') && !document.querySelector('[data-expiry-date-control]').textContent.includes('administrator')`);
            await check(name+' blank matches field requirement',`date.value==='' && date.checkValidity()===!${name.includes('replace')}`);
            await evaluate(`setDate(${JSON.stringify(limits.maximum)})`);
            await check(name+' maximum is valid','date.checkValidity()');
            await evaluate(`setDate(${JSON.stringify(afterMaximum)})`);
            await check(name+' following day blocked','!date.checkValidity() && date.validity.rangeOverflow');
            await evaluate(`setDate(${JSON.stringify(limits.minimum)})`);
            await check(name+' tomorrow is valid','date.checkValidity()');
            await evaluate(`setDate(${JSON.stringify(limits.today)})`);
            await check(name+' today is expired','!date.checkValidity() && date.validity.rangeUnderflow');
            await evaluate('date.form.reset()');await delay(30);
            await check(name+' reset preserves date cap',`date.max===${JSON.stringify(limits.maximum)} && date.min===${JSON.stringify(limits.minimum)}`);
            await check(name+' no horizontal page overflow','document.documentElement.scrollWidth<=innerWidth');
            if (name==='admin-create'||name==='cashier-create') {
                await evaluate(`setDate(${JSON.stringify(limits.maximum)});date.scrollIntoView({block:'center'})`);await delay(250);
                fs.writeFileSync(path.join(dir,name+'-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
            }
        }
        for (const name of ['admin-edit-legacy','admin-edit-expired']) {
            await go(name);
            await check(name+' unchanged is valid','date.checkValidity() && !date.hasAttribute(\'min\') && !date.hasAttribute(\'max\')');
            await evaluate(`setDate(${JSON.stringify(afterMaximum)})`);
            await check(name+' changed date gets maximum','!date.checkValidity() && date.validity.rangeOverflow');
            await evaluate(`setDate(${JSON.stringify(limits.today)})`);
            await check(name+' changed date gets minimum','!date.checkValidity() && date.validity.rangeUnderflow');
            await evaluate(`setDate(${JSON.stringify(limits.maximum)})`);
            await check(name+' in-range change valid','date.checkValidity()');
            await evaluate('date.form.reset()');await delay(30);
            await check(name+' reset restores unchanged legacy date','date.checkValidity() && date.value===document.querySelector(\'[data-expiry-date-control]\').dataset.original && !date.hasAttribute(\'max\')');
        }
        await go('admin-redisplay');
        await check('Rejected later date is retained and invalid',"date.value==='2099-01-01' && !date.checkValidity() && date.validity.rangeOverflow");
        await check('Old posted approval fields cannot restore removed UI',"!document.querySelector('[name=expiry_range_reviewed],[name=expiry_range_reason],[data-expiry-review]')");
    }
    // The native date input enforces the same cap without JavaScript.
    await send('Emulation.setScriptExecutionDisabled',{value:true});
    await send('Page.navigate',{url:origin+'/admin-create.html'});await delay(350);
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    await test('No JavaScript: same native min and max',`document.querySelector('[data-expiry-input]').max===${JSON.stringify(limits.maximum)} && document.querySelector('[data-expiry-input]').min===${JSON.stringify(limits.minimum)}`);
    await test('No JavaScript: no override controls',"!document.querySelector('[name=expiry_range_reviewed],[name=expiry_range_reason]')");
    assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);
    fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,application_posts:0,synthetic_posts:posts.length,widths:[1440,390]},null,2));
    console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
