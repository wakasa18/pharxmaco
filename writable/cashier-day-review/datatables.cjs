const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
module.exports=async({send,evaluate,port,delay,checks})=>{
 const navigate=async view=>{await send('Page.navigate',{url:`http://127.0.0.1:${port}/${view}.html`});for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(40);}await delay(100);};
 const waitFor=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(40);}assert.fail('My Day table update timed out: '+expression);};
 const refresh=async()=>{await evaluate("window.dispatchEvent(new Event('focus'))");await delay(180);};
 await navigate('my-day');
 await evaluate(`window.__table=label=>document.querySelector('table[aria-label="'+label+'"]');window.__api=label=>jQuery(__table(label)).DataTable();window.__card=label=>__table(label).closest('.cw-card');`);
 assert.equal(await evaluate("[...document.querySelectorAll('table.js-datatable')].filter(t=>jQuery.fn.dataTable.isDataTable(t)).length"),4);
 assert.equal(await evaluate("document.querySelectorAll('[data-my-day] .dt-toolbar').length"),4);
 assert.equal(await evaluate("document.querySelectorAll('[data-my-day] .dt-footer').length"),4);
 checks.push({datatables:'All four My Day tables initialize with search, length and paging controls'});
 await evaluate(`window.__demoProducts=Array.from({length:23},(_,i)=>({name:'Demo product '+String(i+1).padStart(2,'0'),sold:i+1,returned:0,replacements:0,quantity_adjustments:0,amount:i===0?-12:i===1?2:i===2?95:i===3?1200:i*10}));
  window.__demoActivity=Array.from({length:23},(_,i)=>({time:i===0?'2026-09-30 23:59:00':'2026-10-'+String(i).padStart(2,'0')+' 10:00:00',type:'sale',legacy:false,sale_id:i+1,invoice:'DEMO-'+(i+1),reason:'',method:'cash',amount:i+1}));
  window.__cashierSummary.report.products=structuredClone(__demoProducts);window.__cashierSummary.report.activity=structuredClone(__demoActivity);`);
 await refresh();await waitFor("__api('Product movements').rows().count()===23");
 assert.equal(await evaluate("__api('Product movements').page.info().pages"),3);
 for(const width of [320,390,768,1440]) {
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  for(const dark of [false,true]) {
   await evaluate(`document.documentElement.classList.toggle('dark',${dark})`);await delay(100);
   assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false,'My Day paged tables overflow '+width);
   assert.ok(await evaluate("[...document.querySelectorAll('.dt-toolbar,.dt-footer')].every(el=>el.getBoundingClientRect().width<=el.closest('.cw-card').getBoundingClientRect().width+1)"));
   if(!dark&&[390,1440].includes(width)) {
    const clip=await evaluate("(()=>{const r=__card('Product movements').getBoundingClientRect();return{x:r.left+scrollX,y:r.top+scrollY,width:r.width,height:r.height,scale:1};})()");
    const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip});
    fs.writeFileSync(path.join(__dirname,'my-day-datatable-'+width+'.png'),Buffer.from(shot.data,'base64'));
   }
   checks.push({datatables:'Paged live table layout',width,dark});
  }
 }
 await evaluate("__table('Product movements').tHead.rows[0].cells[1].click()");
 assert.deepEqual(await evaluate("__api('Product movements').order().map(o=>[o[0],o[1]])"),[[1,'asc']]);
 await evaluate("__api('Product movements').order([[5,'asc']]).draw();void 0");
 assert.match(await evaluate("__table('Product movements').tBodies[0].rows[0].cells[5].textContent"),/-12\.00/);
 await evaluate("__api('Product movements').order([[5,'desc']]).draw();void 0");
 assert.match(await evaluate("__table('Product movements').tBodies[0].rows[0].cells[5].textContent"),/1,200\.00/);
 await evaluate("__api('Cashier activity').order([[0,'asc']]).draw();void 0");
 assert.equal(await evaluate("Number(__table('Cashier activity').tBodies[0].rows[0].cells[0].dataset.order)"),Date.parse('2026-09-30T23:59:00+08:00'));
 checks.push({datatables:'Header sorting, negative/large money and refreshed dates sort by their actual values'});
 await evaluate(`const search=__card('Product movements').querySelector('.dt-search input');search.value='Demo product';search.dispatchEvent(new Event('input'));
  const paymentSearch=__card('Payments').querySelector('.dt-search input');paymentSearch.value='Card';paymentSearch.dispatchEvent(new Event('input'));`);
 await delay(180);
 await evaluate("__card('Product movements').querySelector('[data-dt-go=next]').click()");
 assert.equal(await evaluate("__api('Product movements').page()"),1);
 await evaluate("window.__cashierSummary.report.products.push({name:'Demo product 24',sold:24,returned:0,replacements:0,quantity_adjustments:0,amount:240});window.__cashierSummary.report.payments.card.received=99;window.__cashierSummary.report.payments.card.net=99;");
 await refresh();await waitFor("__api('Product movements').rows().count()===24");
 assert.equal(await evaluate("__api('Product movements').page()"),1);
 assert.equal(await evaluate("__api('Product movements').search()"),'Demo product');
 assert.deepEqual(await evaluate("__api('Product movements').order().map(o=>[o[0],o[1]])"),[[5,'desc']]);
 assert.equal(await evaluate("__api('Payments').rows({search:'applied'}).count()"),1);
 assert.match(await evaluate("__table('Payments').tBodies[0].textContent"),/99\.00/);
 assert.equal(await evaluate("document.querySelectorAll('[data-my-day] .dt-toolbar').length"),4);
 checks.push({datatables:'Live refresh preserves each table search, sorting and current page without duplicate toolbars'});
 await evaluate("const length=__card('Product movements').querySelector('.dt-length select');length.value='25';length.dispatchEvent(new Event('change'));");
 assert.equal(await evaluate("__api('Product movements').page.len()"),25);
 await refresh();assert.equal(await evaluate("__api('Product movements').page.len()"),25);
 await evaluate("__api('Product movements').page.len(10).page(2).draw('page');window.__cashierSummary.report.products=__demoProducts.slice(0,3);");
 await refresh();
 assert.equal(await evaluate("__api('Product movements').page()"),0);
 assert.equal(await evaluate("__api('Product movements').rows().count()"),3);
 checks.push({datatables:'Rows-per-page survives refresh and a shrinking result moves back to an available page'});
 await evaluate("window.__cashierSummary.report.products=[];window.__cashierSummary.report.activity=[];");await refresh();
 assert.equal(await evaluate("__api('Product movements').rows().count()"),0);
 assert.match(await evaluate("__table('Product movements').tBodies[0].textContent"),/No products recorded/);
 await evaluate("window.__cashierSummary.report.products=structuredClone(__demoProducts);");await refresh();
 assert.equal(await evaluate("__api('Product movements').rows().count()"),23);
 assert.equal(await evaluate("document.querySelectorAll('[data-my-day] .dt-toolbar').length"),4);
 checks.push({datatables:'Empty/refilled results retain controls and show the correct empty message'});
 await navigate('my-day-start');
 assert.equal(await evaluate("document.querySelectorAll('[data-my-day] .dt-toolbar').length"),4);
 assert.equal(await evaluate("jQuery('table[aria-label=\"Product movements\"]').DataTable().rows().count()"),0);
 await refresh();
 assert.ok(await evaluate("jQuery('table[aria-label=\"Product movements\"]').DataTable().rows().count()>0"));
 assert.equal(await evaluate("document.querySelectorAll('[data-my-day] .dt-toolbar').length"),4);
 checks.push({datatables:'An initially empty day initializes and receives its first live rows'});
 await navigate('shift-print-closed');
 assert.equal(await evaluate("document.querySelectorAll('.js-datatable,.dt-toolbar').length"),0);
 checks.push({datatables:'Printable shift report stays a complete ordinary table'});
};
