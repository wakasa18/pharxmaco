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
        await evaluate("window.date=document.querySelector('[data-expiry-input]');window.review=document.querySelector('[data-expiry-review]');window.reason=document.querySelector('[data-expiry-reason]');window.wrap=document.querySelector('[data-expiry-reason-wrap]');window.setDate=value=>{date.value=value;date.dispatchEvent(new Event('input',{bubbles:true}));};window.setReview=value=>{review.checked=value;review.dispatchEvent(new Event('change',{bubbles:true}));};window.setReason=value=>{reason.value=value;reason.dispatchEvent(new Event('input',{bubbles:true}));};");
    };
    await send('Page.enable');await send('Runtime.enable');
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        const check=(label,expression)=>test(width+': '+label,expression);
        await go('admin-create');
        await check('Browser bounds match server',`date.min===${JSON.stringify(limits.minimum)} && date.max===${JSON.stringify(limits.maximum)}`);
        await check('Optional blank date and hidden review reason','date.checkValidity() && wrap.hidden && reason.disabled');
        await evaluate("setDate('2099-01-01')");
        await check('Later date requires review','!date.checkValidity() && date.validity.rangeOverflow');
        await evaluate('setReview(true)');
        await check('Review allows date and requires reason','date.checkValidity() && reason.required && !reason.checkValidity() && !wrap.hidden');
        await evaluate("setReason('Verified date printed on sealed package')");
        await check('Complete administrator review is valid','date.checkValidity() && reason.checkValidity()');
        await evaluate("setReason('界'.repeat(201))");
        await check('Reason capped at 200 characters','!reason.checkValidity()');
        await evaluate("setReason('😀'.repeat(200))");
        await check('Unicode character count matches server','reason.checkValidity()');
        await evaluate("setReason('Verified date printed on sealed package');date.scrollIntoView({block:'center'})");await delay(350);
        fs.writeFileSync(path.join(dir,'admin-review-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate(`setDate(${JSON.stringify(limits.maximum)});setReason('')`);
        await check('In-range date needs no exception reason','date.checkValidity() && !reason.required && reason.checkValidity()');
        await evaluate("setDate('2099-01-01');setReview(false)");
        await check('Removing review reinstates maximum','!date.checkValidity() && reason.disabled && wrap.hidden');
        await evaluate(`setDate(${JSON.stringify(limits.today)})`);
        await check('Today is expired','!date.checkValidity() && date.validity.rangeUnderflow');
        await evaluate('setDate(\'\');date.form.reset()');await delay(30);
        await check('Reset restores ordinary bounds',`!review.checked && date.max===${JSON.stringify(limits.maximum)} && reason.disabled`);
        for (const name of ['admin-edit-legacy','admin-edit-expired']) {
            await go(name);
            await check(name+' unchanged is valid','date.checkValidity() && !date.hasAttribute(\'min\') && !date.hasAttribute(\'max\')');
            await evaluate("setDate('2099-02-01')");
            await check(name+' changed date gets bounds','!date.checkValidity() && date.validity.rangeOverflow');
            await evaluate("setDate(document.querySelector('[data-expiry-date-control]').dataset.original)");
            await check(name+' restoring original is valid','date.checkValidity() && !date.hasAttribute(\'max\')');
        }
        for (const name of ['cashier-create','catalog','cashier-replace']) {
            await go(name);
            await check(name+' no override control','review===null && reason===null');
            await evaluate("setDate('2099-01-01')");
            await check(name+' later date is invalid','!date.checkValidity() && date.validity.rangeOverflow');
            await evaluate(`setDate(${JSON.stringify(limits.maximum)})`);
            await check(name+' boundary date is valid','date.checkValidity()');
        }
        await go('admin-replace');
        await check('Replacement date is required','date.required && !date.checkValidity()');
        await evaluate("setDate('2099-01-01');setReview(true);setReason('Verified new supplier delivery')");
        await check('Administrator replacement can review later date','date.checkValidity() && reason.checkValidity() && reason.required');
        await go('admin-redisplay');
        await check('Rejected input preserves date and checked review',"date.value==='2099-01-01' && review.checked && !wrap.hidden");
        await check('Reason is escaped once and inert',"reason.value==='Verified \"sealed\" package <script>not executable</script>' && !document.querySelector('[data-expiry-date-control] script:not([src])')");
        await check('No horizontal page overflow','document.documentElement.scrollWidth<=innerWidth');
        await evaluate("date.scrollIntoView({block:'center'})");await delay(350);
        fs.writeFileSync(path.join(dir,'redisplay-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    // Native controls remain conservative without JavaScript.
    await send('Emulation.setScriptExecutionDisabled',{value:true});
    await send('Page.navigate',{url:origin+'/admin-create.html'});await delay(350);
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    await test('No JavaScript: normal maximum and explanation retained',`document.querySelector('[data-expiry-input]').max===${JSON.stringify(limits.maximum)} && document.body.textContent.includes('Enable JavaScript')`);
    assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);
    fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,application_posts:0,synthetic_posts:posts.length,widths:[1440,390]},null,2));
    console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
