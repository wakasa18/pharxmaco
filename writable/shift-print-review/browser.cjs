const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
module.exports=async({send,evaluate,port,delay,checks})=>{
 const output=path.join(process.cwd(),'tmp/pdfs/shift-print-review');fs.mkdirSync(output,{recursive:true});
 const results=[];
 await send('Emulation.setDeviceMetricsOverride',{width:794,height:1123,deviceScaleFactor:1,mobile:false});
 await send('Emulation.setEmulatedMedia',{media:'print'});
 for(const state of ['open','closed','busy','busy-summary','notes']) {
  const fixture=state==='busy-summary'?'busy':state;
  await send('Page.navigate',{url:`http://127.0.0.1:${port}/shift-print-${fixture}.html`});
  for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'&&!!document.querySelector('.cw-print')"))break;await delay(40);}
  await evaluate('document.fonts.ready');
  await evaluate(`document.body.dataset.printMode='${state==='busy'?'full':'summary'}'`);
  const layout=await evaluate(`(()=>{const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height};};return{title:document.title,heading:document.querySelector('h1').textContent,
    controlsHidden:getComputedStyle(document.querySelector('.cw-print-actions')).display==='none',bodyText:document.body.innerText,
    productRows:document.querySelectorAll('[data-product-rows] tr').length,activityRows:document.querySelectorAll('[data-activity-rows] tr').length,
    width:document.documentElement.scrollWidth,viewport:innerWidth,cards:[...document.querySelectorAll('.cw-card')].map(el=>({title:el.querySelector('h2')?.textContent,overflow:getComputedStyle(el).overflow,...rect(el)})),
    signature:rect(document.querySelector('.sr-signatures')),summary:rect(document.querySelector('.sr-summary')),
    detailsVisible:getComputedStyle(document.querySelector('.sr-details')).display!=='none'};})()`);
  assert.equal(layout.controlsHidden,true);assert.equal(layout.detailsVisible,state==='busy');
  assert.ok(layout.signature.y+layout.signature.height<1000,'Signatures stay on the summary page: '+state);
  if(state==='busy'){assert.equal(layout.activityRows,120);assert.match(layout.bodyText,/PRINT|DEMO-SHIFT-000001/);}
  const pdf=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,displayHeaderFooter:false});
  fs.writeFileSync(path.join(output,`shift-${state}.pdf`),Buffer.from(pdf.data,'base64'));
  results.push({state,...layout});checks.push('Inspected A4 '+state+' shift output');
 }
 await send('Emulation.setEmulatedMedia',{media:''});
 for(const width of [320,390,768,1440]) {
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false,'Report preview overflow at '+width);
 }
 await evaluate("window.__printModes=[];window.print=()=>window.__printModes.push(document.body.dataset.printMode);document.querySelector('button[data-print-mode=full]').click();document.querySelector('button[data-print-mode=summary]').click()");
 assert.deepEqual(await evaluate('window.__printModes'),['full','summary']);
 await evaluate("document.body.dataset.printMode='full';window.dispatchEvent(new Event('afterprint'))");
 assert.equal(await evaluate('document.body.dataset.printMode'),'summary');
 fs.writeFileSync(path.join(process.cwd(),'writable/shift-print-review/browser-results.json'),JSON.stringify(results,null,2));
 console.log('Verified five A4 print samples, both print actions, and responsive previews.');
};
