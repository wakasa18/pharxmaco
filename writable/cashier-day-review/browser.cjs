const fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict');
module.exports=async({send,evaluate,port,dir,delay,checks,errors})=>{
 const report=JSON.parse(fs.readFileSync(path.join(dir,'report.json'),'utf8'));
 await send('Page.addScriptToEvaluateOnNewDocument',{source:`window.__cashierSummary={report:${JSON.stringify(report)},active_shift_id:1,expected_cash:130};window.fetch=async()=>({ok:true,json:async()=>structuredClone(window.__cashierSummary)});`});
 const navigate=async view=>{await send('Page.navigate',{url:`http://127.0.0.1:${port}/${view}.html`});for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(40);}await delay(100);};
 for(const view of ['start-shift','start-shift-busy','my-day','my-day-start','shift-open','shift-closed','manager','shift-print-closed','pos-shift-open','pos-shift-required']) {
  await navigate(view);
  for(const width of [320,390,768,1440]) {
   await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
   for(const dark of view.startsWith('shift-print')?[false]:[false,true]) {
    await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);await delay(80);
    assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false,`${view} overflow ${width} ${dark}`);
    if(view==='pos-shift-open'||view==='pos-shift-required') {
     const notice=await evaluate(`(()=>{const notice=document.querySelector('.pos-shift-notice'),button=notice.querySelector('.pos-shift-action'),style=getComputedStyle(notice),icon=getComputedStyle(notice,'::before'),r=notice.getBoundingClientRect(),b=button.getBoundingClientRect();return{role:notice.getAttribute('role'),classes:notice.className,text:notice.textContent,button:button.textContent.trim(),href:button.getAttribute('href'),mask:icon.maskImage||icon.webkitMaskImage,background:style.backgroundColor,height:b.height,inside:b.left>=r.left&&b.right<=r.right&&b.top>=r.top&&b.bottom<=r.bottom};})()`);
     assert.equal(notice.role,'status');assert.match(notice.mask,/svg/);assert.notEqual(notice.background,'rgba(0, 0, 0, 0)');assert.ok(notice.height>=44&&notice.inside,'Shift action touch target fits the banner');
     if(view==='pos-shift-required'){assert.match(notice.classes,/warning/);assert.equal(notice.button,'Start shift');assert.match(notice.href,/cashier\/start-shift$/);assert.match(notice.text,/Count your opening cash first/);}
     else{assert.match(notice.classes,/success/);assert.match(notice.href,/cashier\/shifts\/1$/);}
    }
    checks.push({view,width,dark});
    if([390,1440].includes(width)) {await delay(450);const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(dir,`${view}-${width}-${dark?'dark':'light'}.png`),Buffer.from(shot.data,'base64'));}
   }
  }
 }
 await require('./datatables.cjs')({send,evaluate,port,delay,checks});
 await navigate('start-shift-busy');
 assert.equal(await evaluate("document.querySelector('[data-opening-form] button').disabled"),true);
 assert.match(await evaluate("document.querySelector('.cw-warning').textContent"),/No drawer is available/);
 checks.push({opening:'Unavailable drawer cannot be opened and explains the handover wait'});
 await navigate('start-shift');
 assert.equal(await evaluate("document.querySelector('#cwDrawer').value"),'1');
 await evaluate("document.querySelector('#cwDrawer').value='1';document.querySelector('#cwOpeningCash').value='90';document.querySelector('#cwDrawer').dispatchEvent(new Event('change'));");
 assert.equal(await evaluate("document.querySelector('#cwOpeningNote').required"),true);
 await evaluate("document.querySelector('#cwOpeningCash').value='100';document.querySelector('#cwOpeningCash').dispatchEvent(new Event('input'))");
 assert.equal(await evaluate("document.querySelector('#cwOpeningNote').required"),false);
 checks.push({opening:'Handover difference requires a reason'});
 await navigate('shift-open');
 await evaluate("document.querySelector('#cwCounted').value='129';document.querySelector('#cwLeft').value='130';document.querySelector('#cwCounted').dispatchEvent(new Event('input'))");
 assert.equal(await evaluate("document.querySelector('#cwClosingNote').required"),true);
 assert.equal(await evaluate("document.querySelector('#cwLeft').checkValidity()"),false);
 assert.match(await evaluate("document.querySelector('[data-count-difference]').textContent"),/Short:.*1.00/);
 await evaluate("document.querySelector('#cwCounted').value='130';document.querySelector('#cwLeft').value='100';document.querySelector('#cwCounted').dispatchEvent(new Event('input'))");
 assert.equal(await evaluate("document.querySelector('#cwClosingNote').required"),false);
 assert.match(await evaluate("document.querySelector('[data-closing-removal]').textContent"),/30.00/);
 checks.push({closing:'Count differences and cash removal previews; cannot leave more than counted'});
 await navigate('my-day');
 await evaluate("window.__cashierSummary.report.sales=99;window.__cashierSummary.report.products[0].name='<img src=x onerror=alert(1)>';window.dispatchEvent(new Event('focus'))");await delay(150);
 assert.match(await evaluate("document.querySelector('[data-report-metric=sales]').textContent"),/99.00/);
 assert.equal(await evaluate("document.querySelector('[data-product-rows] img')===null"),true);
 assert.match(await evaluate("document.querySelector('[data-product-rows]').textContent"),/<img/);
 await evaluate("window.__cashierSummary.active_shift_id=null;window.dispatchEvent(new Event('focus'))");await delay(150);
 assert.equal(await evaluate("document.querySelector('[data-shift-changed]').hidden"),false);
 assert.equal(await evaluate("document.querySelector('form[action*=movement] button').disabled"),true);
 checks.push({live:'Totals refresh safely; drawer controls stop when another window closes the shift'});
 await navigate('shift-print-closed');await send('Emulation.setEmulatedMedia',{media:'print'});
 assert.equal(await evaluate("getComputedStyle(document.querySelector('.cw-print-actions')).display"),'none');
 await send('Emulation.setEmulatedMedia',{media:''});checks.push({print:'Final closing report hides controls for printing'});
 assert.deepEqual(errors,[],'Browser JavaScript errors');
 fs.writeFileSync(path.join(dir,'browser-checks.json'),JSON.stringify(checks,null,2));
 console.log(`Passed ${checks.length} cashier workspace browser checks.`);
};
