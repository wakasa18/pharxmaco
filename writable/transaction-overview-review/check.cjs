const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const root = process.cwd(), dir = __dirname, profile = path.join(dir, 'chrome-' + Date.now());
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const checks = [], errors = [], posts = []; let server, chrome, ws;
(async () => {
    server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if(req.method==='POST') {let body='';req.on('data',chunk=>body+=chunk);req.on('end',()=>{posts.push({path:url.pathname,body:new URLSearchParams(body)});res.end('<html><body>Fixture saved</body></html>');});return;}
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
    const open=async name=>{await send('Page.navigate',{url:origin+'/'+name+'.html'});await wait("document.readyState==='complete' && !!window.PharxmacoTransactionOverview");if(name!=='exchange')await wait("document.querySelector('form[data-transaction-overview]')?.dataset.overviewInitialized==='1'");};
    await send('Page.enable');await send('Runtime.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    const clickReview=async id=>{await read(`document.querySelector('#${id} button[type=submit]').click()`);await wait(`document.getElementById('${id}-overview').classList.contains('open')`);};
    const acknowledge=async id=>{await read(`{const a=document.querySelector('#${id}-overview [data-overview-ack]');a.checked=true;a.dispatchEvent(new Event('change',{bubbles:true}));}`);};
    const expectPost=async count=>{for(let i=0;i<100;i++){if(posts.length===count)return;await delay(30);}throw Error('Expected '+count+' fixture POSTs; saw '+posts.length);};
    const checkLayout=async (page,formId)=>{
        for(const width of [320,390,768,1440]) for(const dark of [false,true]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
            await read(`document.documentElement.classList.toggle('dark',${dark})`);await delay(70);
            assert.equal(await read('document.documentElement.scrollWidth>innerWidth+1'),false,page+'/'+width+'/'+dark);
            if(formId) {
                const geometry=await read(`(()=>{const r=document.querySelector('#${formId}-overview [role=dialog]').getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:innerWidth,height:innerHeight};})()`);
                assert.ok(geometry.left>=0&&geometry.right<=geometry.width+1&&geometry.top>=0&&geometry.bottom<=geometry.height+1,JSON.stringify({page,width,dark,geometry}));
                assert.equal(await read(`document.querySelector('#${formId}-overview .transaction-overview-body').clientHeight>80`),true);
            }
            checks.push('responsive '+page+'/'+width+'/'+dark);
            if([390,1440].includes(width)&&!dark) {
                await read(formId ? `document.querySelector('#${formId}-overview .transaction-overview-body').scrollTop=0` : 'window.scrollTo(0,0)');
                const screenshot=await send('Page.captureScreenshot',{format:'png'});
                fs.writeFileSync(path.join(dir,`${page}-${width}.png`),Buffer.from(screenshot.data,'base64'));
            }
        }
    };
    await open('refund');
    const refundToken=await read("document.querySelector('[name=_refund_token]').value");
    await read(`{const q=document.querySelector('.refund-qty-input');q.value='2';q.dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('refund-method').value='cash';document.getElementsByName('return_condition[1]')[0].value='damaged';document.getElementById('refund-reason').value='<img src=x onerror="window.overviewInjected=1"> Damaged package';}`);
    await clickReview('refundForm');
    assert.equal(posts.length,0);
    assert.equal(await read("document.querySelector('#refundForm-overview [data-overview-content]').textContent.includes('₱20.00')"),true);
    assert.equal(await read("document.querySelectorAll('#refundForm-overview [data-overview-content] img').length"),0);
    assert.equal(await read('window.overviewInjected===undefined'),true);
    assert.equal(await read("document.querySelector('#refundForm-overview [data-overview-confirm]').disabled"),true);
    assert.equal(await read("document.querySelector('#refundForm').closest('[inert]')!==null"),true);
    checks.push('refund submit opens accurate safe overview without posting; confirmation required and background isolated');
    await checkLayout('refundOverview','refundForm');
    await read("document.querySelector('#refundForm-overview [data-overview-back]').click()");
    assert.equal(await read("document.querySelector('.refund-qty-input').value"),'2');
    assert.equal(await read("document.activeElement.id"),'submit-refund-btn');
    assert.equal(posts.length,0);
    checks.push('Back to edit preserves refund entries and returns focus without posting');
    await clickReview('refundForm');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape'});
    assert.equal(await read("document.getElementById('refundForm-overview').classList.contains('open')"),false);
    checks.push('Escape closes the refund overview');
    await clickReview('refundForm');await acknowledge('refundForm');
    await read("document.querySelector('#refundForm-overview [data-overview-confirm]').focus()");
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab'});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab'});
    assert.equal(await read("document.querySelector('#refundForm-overview').contains(document.activeElement)"),true);
    checks.push('keyboard focus stays inside the open confirmation dialog');
    await read("{const q=document.querySelector('.refund-qty-input');q.value='3';q.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('#refundForm-overview [data-overview-confirm]').click();}");
    assert.equal(posts.length,0);
    assert.equal(await read("document.querySelector('#refundForm-overview [data-overview-status]').textContent.includes('form changed')"),true);
    assert.equal(await read("document.querySelector('#refundForm-overview [data-overview-ack]').checked"),false);
    assert.equal(await read("document.querySelector('#refundForm-overview [data-overview-content]').textContent.includes('₱30.00')"),true);
    checks.push('changed refund payload refreshes overview and requires acknowledgement again');
    await acknowledge('refundForm');
    await read("{const b=document.querySelector('#refundForm-overview [data-overview-confirm]');b.click();b.click();}");await expectPost(1);
    assert.equal(posts[0].body.get('refund[1]'),'3');assert.equal(posts[0].body.get('return_condition[1]'),'damaged');
    assert.equal(posts[0].body.get('_refund_token'),refundToken);assert.equal(posts[0].path,'/cashier/sales/refund-partial/1');
    checks.push('one confirmed refund POST retains current quantities, condition and original refund token');

    await open('roundedRefund');
    const examples=JSON.parse(fs.readFileSync(path.join(dir,'refund-expectations.json'),'utf8'));
    for(const e of examples)assert.equal(await read(`PharxmacoTransactionOverview.refundCents(${e.quantity},${e.remainingQuantity},${e.remainingCents})`),e.savedCents);
    checks.push('seven preview amounts match actual controller-recorded payouts, including half-cent and full-remainder cases');
    await read("{const q=document.querySelector('.refund-qty-input');q.value='3';q.dispatchEvent(new Event('input',{bubbles:true}));document.getElementById('refund-method').value='cash';document.getElementById('refund-reason').value='Return full remaining amount';}");
    assert.equal(await read("document.getElementById('total-refund-amount').textContent.trim()"),'₱1.00');
    await clickReview('refundForm');
    assert.equal(await read("document.querySelector('#refundForm-overview [data-overview-content]').textContent.includes('₱1.00')"),true);
    checks.push('full refund overview uses exact remaining centavos rather than a rounded unit-price product');

    await open('correction');
    const revision=await read("document.querySelector('[name=sale_revision]').value");
    await read("document.querySelector('[name=correction_reason]').value='Correct item count'");
    await clickReview('correctionForm');
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-status]').textContent.includes('No changes')"),true);
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-ack]').disabled"),true);
    checks.push('unchanged correction cannot be confirmed');
    await read("document.querySelector('#correctionForm-overview [data-overview-back]').click()");
    await read("{const q=document.querySelector('.qty-input');q.value='4';q.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('[name=notes]').value='Corrected purchase notes';const c=document.getElementById('settlementConfirmed');c.checked=true;c.dispatchEvent(new Event('change',{bubbles:true}));}");
    await clickReview('correctionForm');
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-content]').textContent.includes('₱40.00')"),true);
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-content]').textContent.includes('Return 1 to available stock')"),true);
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-content]').textContent.includes('Corrected purchase notes')"),true);
    checks.push('correction overview shows before/after quantity, planned stock effect, notes and payout');
    await checkLayout('correctionOverview','correctionForm');
    await acknowledge('correctionForm');
    await read("document.querySelector('[name=notes]').value='Updated after review';document.querySelector('#correctionForm-overview [data-overview-confirm]').click()");
    assert.equal(posts.length,1);
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-content]').textContent.includes('Updated after review')"),true);
    checks.push('changed correction notes cannot reuse an earlier confirmation');
    await acknowledge('correctionForm');
    await read("{const b=document.querySelector('#correctionForm-overview [data-overview-confirm]');b.click();b.click();}");await expectPost(2);
    assert.equal(posts[1].body.get('items[1]'),'4');assert.equal(posts[1].body.get('sale_revision'),revision);
    assert.equal(posts[1].body.get('settlement_difference'),'-1000');assert.equal(posts[1].body.get('settlement_confirmed'),'1');
    assert.equal(posts[1].body.get('notes'),'Updated after review');
    checks.push('one correction POST retains revision, updated notes and exact confirmed settlement');

    await open('discountedCorrection');
    const expected=JSON.parse(fs.readFileSync(path.join(dir,'correction-expectations.json'),'utf8'));
    await read("{document.querySelector('[name=correction_reason]').value='Correct discounted quantities';const q=document.querySelector('.qty-input');q.value='4';q.dispatchEvent(new Event('input',{bubbles:true}));const c=document.getElementById('settlementConfirmed');c.checked=true;c.dispatchEvent(new Event('change',{bubbles:true}));}");
    await clickReview('correctionForm');
    assert.equal(await read("Number(document.getElementById('correctionForm').dataset.overviewFinalCents)"),Math.round(expected.final*100));
    assert.equal(await read("Number(document.getElementById('correctionForm').dataset.overviewDiscountCents)"),Math.round(expected.discount*100));
    assert.equal(await read("Number(document.getElementById('correctionForm').dataset.overviewDifference)"),expected.difference);
    checks.push('discounted overview final, discount and settlement match the server pricing calculation');
    await read("document.querySelector('#correctionForm-overview [data-overview-back]').click()");
    await read("{const q=document.querySelector('.qty-input');q.value='7';q.dispatchEvent(new Event('input',{bubbles:true}));const m=document.getElementById('paymentMethod');m.value='gcash';m.dispatchEvent(new Event('change',{bubbles:true}));const c=document.getElementById('settlementConfirmed');c.checked=true;c.dispatchEvent(new Event('change',{bubbles:true}));}");
    assert.equal(await read("document.getElementById('correctionForm').checkValidity()"),false);
    await read("{const r=document.getElementById('referenceNo');r.value='GCASH-001';r.dispatchEvent(new Event('input',{bubbles:true}));const c=document.getElementById('settlementConfirmed');c.checked=true;c.dispatchEvent(new Event('change',{bubbles:true}));}");
    await clickReview('correctionForm');
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-content]').textContent.includes('Collect from customer')"),true);
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-content]').textContent.includes('₱16.00')"),true);
    assert.equal(await read("document.querySelector('#correctionForm-overview [data-overview-content]').textContent.includes('GCASH-001')"),true);
    checks.push('additional digital settlement retains reference validation and shows exact collection/reference');

    await open('exchange');
    assert.equal(await read("document.querySelector('.exchange-page h1').textContent"),'Exchange overview');
    assert.equal(await read("document.querySelector('.exchange-page').textContent.includes('keep outside available stock')"),true);
    assert.equal(await read("document.getElementById('exchangeCompleteForm').checkValidity()"),false);
    checks.push('server exchange overview displays source/stock effects and requires acknowledgement');
    await checkLayout('exchangeOverview');
    await read("{document.querySelector('#exchangeCompleteForm input[type=checkbox]').checked=true;const b=document.querySelector('#exchangeCompleteForm button[type=submit]');b.click();b.click();}");await expectPost(3);
    assert.equal(posts[2].path,'/cashier/sales/exchange/1/complete');
    assert.equal(posts[2].body.get('exchange_token').length,48);
    checks.push('exchange posts one existing reviewed token without an extra review dialog');
    await delay(150);assert.equal(posts.length,3);assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(dir,'browser-results.json'),JSON.stringify({checks,javascript_errors:errors,fixture_posts:posts.map(p=>({path:p.path})),application_records_changed:false},null,2));
    console.log(`Passed ${checks.length} transaction-overview browser scenarios; ${posts.length} fixture-only POSTs.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
