 const errors=[];
 await send('Runtime.enable');
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description);});
 await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.fetch=async()=>({ok:true,json:async()=>({success:true,available:true,found:false})});"});
 const checks=[];
 for(const view of ['pos','products','catalog','catalog-selected','create','edit']) {
  await send('Page.navigate',{url:`http://127.0.0.1:${port}/${view}.html`});
  for(let i=0;i<80;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(50);}
  if(['create','edit'].includes(view))assert.equal(await evaluate("document.querySelector('#product-medicine-type').value"),view==='edit'?'branded':'');
  if(['products','catalog'].includes(view)) {
   const labels=await evaluate("[...document.querySelectorAll('.medicine-type-badge')].map(x=>x.textContent.trim())");
   assert.deepEqual(labels,['Branded','Generic','Not specified']);
  }
  for(const width of [320,390,768,1440]) {
   await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
   for(const dark of [false,true]) {
    await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);
    await delay(50);
    const overflow=await evaluate("document.documentElement.scrollWidth>innerWidth+1");
    assert.equal(overflow,false,view+' overflow '+width);
    checks.push({view,width,dark});
    if(!dark&&[390,1440].includes(width)&&['pos','products','create'].includes(view)) {
     const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
     fs.writeFileSync(path.join(dir,view+'-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }
   }
  }
  if(view==='pos') {
   const visible="[...document.querySelectorAll('.prod-card')].filter(x=>x.style.display!=='none')";
   for(const value of ['branded','generic','unspecified']) {
    await evaluate(`document.querySelector('#posMedicineType').value='${value}';document.querySelector('#posMedicineType').dispatchEvent(new Event('change'));`);
    assert.equal(await evaluate(visible+'.length'),1);
    assert.equal(await evaluate("document.querySelector('#prodCount').textContent"),'1');
   }
   await evaluate("document.querySelector('#posMedicineType').value='branded';document.querySelector('#productSearch').value='paracetamol';document.querySelector('#posMedicineType').dispatchEvent(new Event('change'));");
   assert.equal(await evaluate(visible+'[0].dataset.label'),'Biogesic 500mg','Branded medicine must be searchable by generic ingredient.');
   await evaluate("document.querySelector('#productSearch').value='no-such-medicine';document.querySelector('#posMedicineType').dispatchEvent(new Event('change'));");
   assert.equal(await evaluate(visible+'.length'),0);
   assert.equal(await evaluate("document.querySelector('#productFilterEmpty').hidden"),false);
   await evaluate("document.querySelector('#resetProductFilters').click()");
   assert.equal(await evaluate(visible+'.length'),3);
   assert.equal(await evaluate("document.querySelector('#posMedicineType').value"),'');
   assert.equal(await evaluate("document.querySelector('#productSearch').value"),'');
   checks.push({filters:'Branded/Generic/Not specified, combined ingredient search, empty state and reset'});
  }
 }
 assert.deepEqual(errors,[],'Browser JavaScript errors');
 fs.writeFileSync(path.join(dir,'checks.json'),JSON.stringify(checks,null,2));
 console.log(`Passed ${checks.length} medicine-type browser checks with no JavaScript errors.`);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{ws?.close();chrome?.kill();server?.close();});
