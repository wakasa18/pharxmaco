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

    const minYear=Number(limits.minimum.slice(0,4)),maxYear=Number(limits.maximum.slice(0,4));
    const afterMaximum=String(maxYear+1)+'-01-01';
    const click=selector=>evaluate('document.querySelector('+JSON.stringify(selector)+').click()');
    const open=async(mode='icon')=>{
        await evaluate("date.scrollIntoView({block:'center'});date.focus()");await delay(50);
        if(mode==='icon') {
            const r=await evaluate("(()=>{const r=date.getBoundingClientRect();return{x:r.right-18,y:r.top+r.height/2};})()");
            await send('Input.dispatchMouseEvent',{type:'mousePressed',x:r.x,y:r.y,button:'left',clickCount:1});
            await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:r.x,y:r.y,button:'left',clickCount:1});
        } else {
            await evaluate("date.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',altKey:true,bubbles:true,cancelable:true}))");
        }
        await wait("!!document.querySelector('.dp')");
    };
    const years=async()=>{await click('.dp [data-act=title]');await click('.dp [data-act=title]');await wait("!!document.querySelector('.dp [data-year]')");};
    const edge=async direction=>{
        let moves=0;
        while(await evaluate("!document.querySelector('.dp [data-act="+direction+"]').disabled")) {
            if(++moves>30)throw Error('Unbounded '+direction+' year navigation');await click('.dp [data-act='+direction+']');
        }
        return moves;
    };
    const shot=async name=>{await delay(250);fs.writeFileSync(path.join(dir,name+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));};
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        const check=(label,expression)=>test(width+': '+label,expression);
        for(const name of ['admin-create','cashier-create','catalog','admin-replace','cashier-replace','admin-edit-normal']) {
            await go(name);
            await check(name+' has bounds before selecting date','date.min==='+JSON.stringify(limits.minimum)+' && date.max==='+JSON.stringify(limits.maximum));
            const original=await evaluate('date.value');
            await open();
            await check(name+' icon opens themed calendar before selection',"!!document.querySelector('.dp') && document.activeElement.closest('.dp')!==null");
            await check(name+' initial disabled days match range',"[...document.querySelectorAll('.dp [data-date]')].every(b=>b.disabled===(b.dataset.date<"+JSON.stringify(limits.minimum)+"||b.dataset.date>"+JSON.stringify(limits.maximum)+"))");
            await years();await edge('next');
            await check(name+' year view disables all out-of-range years',"[...document.querySelectorAll('.dp [data-year]')].every(b=>b.disabled===(+b.dataset.year<"+minYear+"||+b.dataset.year>"+maxYear+"))");
            await check(name+' forward navigation stops at maximum year',"document.querySelector('.dp [data-act=next]').disabled && !document.querySelector('.dp [data-year=\""+maxYear+"\"]').disabled");
            await evaluate("document.querySelector('.dp [data-year=\""+(maxYear+1)+"\"]').click()");
            await check(name+' disabled future year cannot change view or field',"!!document.querySelector('.dp [data-year]') && date.value==="+JSON.stringify(original));
            if(name==='admin-edit-normal')await shot('initial-normal-years-'+width);
            await click('.dp [data-year="'+maxYear+'"]');
            await check(name+' maximum-year month view cannot advance',"document.querySelector('.dp [data-act=next]').disabled && !!document.querySelector('.dp [data-month]')");
            await click('.dp [data-month="11"]');
            await check(name+' maximum-month next arrow disabled',"document.querySelector('.dp [data-act=next]').disabled");
            await check(name+' next-year days are disabled',"[...document.querySelectorAll('.dp [data-date]')].filter(b=>b.dataset.date>"+JSON.stringify(limits.maximum)+").every(b=>b.disabled)");
            await evaluate("document.querySelector('.dp [data-date=\""+limits.maximum+"\"]').focus();document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true,cancelable:true}))");
            await check(name+' keyboard cannot cross maximum',"document.activeElement.dataset.date==="+JSON.stringify(limits.maximum));
            await evaluate("window.selectionEvents={input:0,change:0};date.addEventListener('input',()=>selectionEvents.input++);date.addEventListener('change',()=>selectionEvents.change++)");
            await click('.dp [data-date="'+limits.maximum+'"]');
            await check(name+' committing date emits one input and change',"selectionEvents.input===1 && selectionEvents.change===1");
            await check(name+' maximum day commits valid date',"date.value==="+JSON.stringify(limits.maximum)+" && date.checkValidity() && !document.querySelector('.dp')");
            await open('keyboard');await years();await edge('prev');
            await check(name+' previous year navigation stops',"document.querySelector('.dp [data-act=prev]').disabled && [...document.querySelectorAll('.dp [data-year]')].filter(b=>+b.dataset.year<"+minYear+").every(b=>b.disabled)");
            await click('.dp [data-year="'+minYear+'"]');
            const minMonth=Number(limits.minimum.slice(5,7))-1;
            await check(name+' early months disabled',"[...document.querySelectorAll('.dp [data-month]')].filter(b=>+b.dataset.month<"+minMonth+").every(b=>b.disabled)");
            await click('.dp [data-month="'+minMonth+'"]');
            await check(name+' first month cannot go backward',"document.querySelector('.dp [data-act=prev]').disabled");
            await check(name+' Today is disabled before selecting a day',"document.querySelector('.dp [data-act=today]').disabled");
            await evaluate("document.querySelector('.dp [data-act=today]').click()");
            await check(name+' disabled Today does not change date',"date.value==="+JSON.stringify(limits.maximum));
            await evaluate("document.querySelector('.dp [data-date=\""+limits.minimum+"\"]').focus();document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true,cancelable:true}))");
            await check(name+' keyboard cannot cross minimum',"document.activeElement.dataset.date==="+JSON.stringify(limits.minimum));
            await check(name+' Clear matches requiredness',"!!document.querySelector('.dp [data-act=clear]')===!date.required");
            if(name==='admin-edit-normal')await shot('first-date-'+width);
            await evaluate("document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}))");
            await check(name+' Escape closes and returns focus',"!document.querySelector('.dp') && document.activeElement===date");
        }
        for(const name of ['admin-edit-legacy','admin-edit-expired','admin-redisplay']) {
            await go(name);const original=await evaluate('date.value');
            const unchanged=name!=='admin-redisplay';
            await check(name+' stored date validity retained','date.checkValidity()==='+unchanged);
            await open();await years();
            await check(name+' opens only within server year range',"[...document.querySelectorAll('.dp [data-year]:not(:disabled)')].every(b=>+b.dataset.year>="+minYear+"&&+b.dataset.year<="+maxYear+")");
            await check(name+' opening picker does not overwrite original',"date.value==="+JSON.stringify(original));
            await edge('next');
            await check(name+' legacy date cannot unlock later years',"document.querySelector('.dp [data-act=next]').disabled && [...document.querySelectorAll('.dp [data-year]:not(:disabled)')].every(b=>+b.dataset.year<="+maxYear+")");
            if(name==='admin-edit-legacy')await shot('legacy-years-'+width);
            await evaluate("document.querySelector('.dp').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}))");
            await evaluate('date.form.reset()');await delay(30);
            await check(name+' reset preserves original value','date.value==='+JSON.stringify(original));
        }
        await go('admin-edit-normal');await open('keyboard');
        await evaluate("setDate('2099-01-01')");
        await check('Typing later year while open does not escape view bounds',"!!document.querySelector('.dp [data-date=\""+limits.maximum+"\"]') && [...document.querySelectorAll('.dp [data-date]:not(:disabled)')].every(b=>b.dataset.date<="+JSON.stringify(limits.maximum)+")");
        await check('Typed later year remains invalid',"!date.checkValidity() && date.validity.rangeOverflow");
        await evaluate("document.querySelector('.dp').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));setDate('2028-06-12')");
        await open('keyboard');await years();await edge('next');await click('.dp [data-year=\"2035\"]');
        await click('.dp [data-act=title]');await click('.dp [data-act=title]');
        await check('View cycling keeps selected browsing year',"document.querySelector('.dp .dp-title').textContent.includes('2035')");
        await click('.dp [data-act=next]');
        await check('Month navigation follows newly browsed year',"document.querySelector('.dp .dp-title').textContent.includes('2035')");
        await evaluate("date.form.reset()");await delay(30);
        await check('Reset while open restores stored date',"date.value==='2028-06-12' && date.max==="+JSON.stringify(limits.maximum));
        await evaluate("document.querySelector('.dp').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}))");
        // Ordinary report dates keep the existing unbounded calendar behavior.
        await evaluate("const f=document.createElement('form');f.innerHTML='<label for=report-date>Report date</label><input id=report-date type=date value=2028-06-12>';document.body.appendChild(f);window.date=f.querySelector('input')");
        await open('keyboard');await years();
        await check('Unbounded report date year options stay available',"[...document.querySelectorAll('.dp [data-year]')].every(b=>!b.disabled) && !document.querySelector('.dp [data-act=next]').disabled && !document.querySelector('.dp [data-act=prev]').disabled");
        await click('.dp [data-act=next]');
        await check('Unbounded report date can browse later years',"[...document.querySelectorAll('.dp [data-year]')].some(b=>+b.dataset.year>"+maxYear+")");
        await evaluate("document.querySelector('.dp').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));setDate('2028-12-12')");
        await open('keyboard');await click('.dp [data-act=next]');
        await check('Unbounded report calendar crosses December to January',"document.querySelector('.dp .dp-title').textContent.includes('January 2029')");
        await evaluate("document.querySelector('.dp').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}))");
    }
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:900,deviceScaleFactor:1,mobile:true});
    await send('Emulation.setTouchEmulationEnabled',{enabled:true,maxTouchPoints:1});
    await go('admin-edit-normal');
    await test('Coarse-pointer device keeps initial native expiry bounds',"date.min==="+JSON.stringify(limits.minimum)+" && date.max==="+JSON.stringify(limits.maximum));
    await test('Coarse-pointer fallback uses native input',"matchMedia('(hover:none) and (pointer:coarse)').matches && !document.documentElement.classList.contains('has-date-picker')");
    await evaluate("setDate('2099-01-01')");
    await test('Coarse-pointer typed date still validates against cap',"!date.checkValidity() && date.validity.rangeOverflow");
    assert.deepEqual(exceptions,[]);assert.deepEqual(posts,[]);
    fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,passed:checks.length,exceptions,application_posts:0,synthetic_posts:posts.length,widths:[1440,390]},null,2));
    console.log(JSON.stringify({passed:checks.length,exceptions,posts:posts.length}));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(ws)ws.close();if(chrome)chrome.kill();if(server)await new Promise(resolve=>server.close(resolve));});
