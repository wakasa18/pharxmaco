const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
module.exports=async({send,evaluate,port,delay,checks})=>{
    await send('Page.navigate',{url:`http://127.0.0.1:${port}/manual.html`});
    for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'"))break;await delay(40);}
    assert.equal(await evaluate("document.querySelectorAll('.manual-page').length"),37);
    assert.equal(await evaluate("document.querySelectorAll('.toc-part').length"),6);
    assert.equal(await evaluate("document.querySelectorAll('.toc-table a').length"),68);
    assert.equal(await evaluate("[...document.querySelectorAll('.toc-table a')].every(link=>document.getElementById(link.hash.slice(1)))"),true);
    checks.push('All 34 chapter entries and 68 contents links resolve within six parts.');
    assert.match(await evaluate('getComputedStyle(document.body).fontFamily'),/Calibri/);
    assert.ok(await evaluate('parseFloat(getComputedStyle(document.body).fontSize)>=14.6'));
    assert.equal(await evaluate("getComputedStyle(document.querySelector('#overview h1')).fontWeight"),'400');
    checks.push('HTML uses 11-point body text and normal-weight Calibri Light chapter headings.');
    assert.equal(await evaluate("[...document.body.querySelectorAll('*')].filter(el=>[...el.childNodes].some(node=>node.nodeType===3&&node.textContent.trim())).every(el=>getComputedStyle(el).color==='rgb(0, 0, 0)')"),true,'All manual text must be black');
    assert.equal(await evaluate("[...document.querySelectorAll('.manual-table th,.manual-table td')].every(cell=>{const s=getComputedStyle(cell);return s.backgroundColor==='rgb(255, 255, 255)'&&parseFloat(s.borderLeftWidth)>0&&s.borderLeftStyle==='solid'})"),true,'Tables must be unshaded grids');
    checks.push('All text is black and tables use an unshaded grid without alternating fills.');
    for(const width of [390,820,1440]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(70);
        assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false,'Page overflow at '+width);
        assert.equal(await evaluate("[...document.querySelectorAll('.manual-table')].every(table=>table.getBoundingClientRect().right<=table.closest('.manual-page').getBoundingClientRect().right+1)"),true,'Table overflow at '+width);
        if([390,1440].includes(width))for(const selector of ['.cover','#document-control','.toc','.benefit-chapter']){
            const clip=await evaluate(`(()=>{const r=document.querySelector('${selector}').getBoundingClientRect();return{x:r.left+scrollX,y:r.top+scrollY,width:r.width,height:r.height,scale:1};})()`);
            const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip});
            fs.writeFileSync(path.join(__dirname,'html-'+selector.slice(1)+'-'+width+'.png'),Buffer.from(shot.data,'base64'));
        }
        checks.push('Cover, contents and all manual tables fit '+width+'px without page overflow.');
    }
    await evaluate("document.querySelector('.toc-table a[href=\"#senior-pwd\"]').click()");
    assert.equal(await evaluate('location.hash'),'#senior-pwd');
    checks.push('A contents link navigates to the selected procedure.');
    await send('Emulation.setEmulatedMedia',{media:'print'});
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.web-guide')).display"),'none');
    assert.equal(await evaluate("[...document.querySelectorAll('.manual-page *')].every(el=>getComputedStyle(el).color==='rgb(0, 0, 0)')"),true,'Printed text must be black');
    assert.equal(await evaluate("[...document.querySelectorAll('.manual-table .numeric')].every(cell=>getComputedStyle(cell).textAlign==='right')"),true);
    checks.push('Print styling hides browser navigation and right-aligns monetary columns.');
};
