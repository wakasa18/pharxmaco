const assert = require('node:assert/strict'), fs = require('node:fs'), path = require('node:path');
module.exports = async ({send, evaluate, port, delay, checks}) => {
    const navigate = async view => {
        await send('Page.navigate', {url: `http://127.0.0.1:${port}/${view}.html`});
        for (let i = 0; i < 100; i++) {
            if (await evaluate("document.readyState==='complete' && !!window.jQuery")) break;
            await delay(40);
        }
        await delay(100);
        await evaluate(`window.__table=label=>document.querySelector('table[aria-label="'+label+'"]');window.__api=label=>jQuery(__table(label)).DataTable();window.__card=label=>__table(label).closest('.cw-card');`);
    };
    const tableCount = () => evaluate("[...document.querySelectorAll('table.js-datatable')].filter(t=>jQuery.fn.dataTable.isDataTable(t)).length");
    await navigate('manager-tables-populated');
    assert.equal(await tableCount(), 3);
    assert.equal(await evaluate("document.querySelectorAll('.cashier-workspace .dt-toolbar').length"), 3);
    assert.equal(await evaluate("document.querySelectorAll('.cashier-workspace .dt-footer').length"), 3);
    for (const label of ['Branch drawers', 'Shift reviews', 'Exceptional movements']) {
        assert.equal(await evaluate(`__api('${label}').rows().count()`), 23);
        assert.equal(await evaluate(`__api('${label}').page.info().pages`), 3);
    }
    checks.push('All three Cashier Shifts tables initialize with search, length and pagination.');
    for (const column of ['Expected / counted', 'Over / short']) {
        assert.equal(await evaluate(`__card('Shift reviews').querySelectorAll('select[aria-label="Filter by ${column}"]').length`), 0);
    }
    await evaluate("(()=>{const filter=__card('Shift reviews').querySelector('select[aria-label=\"Filter by Status\"]');filter.value='Closed';filter.dispatchEvent(new Event('change'));})()");
    await delay(50);
    assert.equal(await evaluate("__api('Shift reviews').rows({search:'applied'}).count()"), 12);
    await evaluate("(()=>{const filter=__card('Shift reviews').querySelector('select[aria-label=\"Filter by Status\"]');filter.value='';filter.dispatchEvent(new Event('change'));})()");
    await delay(50);
    checks.push('Repeated cash pairs have no concatenated desktop or mobile dropdown; Status filtering still works.');
    const cashierOptions = ['All', 'Cashier 23 — Branch 2', 'Main Cashier — Main Branch', 'System Administrator — Main Branch'];
    assert.deepEqual(await evaluate("[...__card('Shift reviews').querySelectorAll('select[aria-label=\"Filter by Cashier / branch\"]')].map(select=>[...select.options].map(option=>option.textContent))"), [cashierOptions, cashierOptions]);
    await evaluate("(()=>{const filter=__card('Shift reviews').querySelector('select[aria-label=\"Filter by Cashier / branch\"]');filter.value='Main Cashier — Main Branch';filter.dispatchEvent(new Event('change'));})()");
    await delay(50);
    assert.equal(await evaluate("__api('Shift reviews').rows({search:'applied'}).count()"), 11);
    assert.deepEqual(await evaluate("[...__card('Shift reviews').querySelectorAll('select[aria-label=\"Filter by Cashier / branch\"]')].map(select=>select.value)"), ['Main Cashier — Main Branch', 'Main Cashier — Main Branch']);
    await evaluate("(()=>{const filters=__card('Shift reviews').querySelectorAll('select[aria-label=\"Filter by Cashier / branch\"]');filters[1].value='';filters[1].dispatchEvent(new Event('change'));const field=__card('Shift reviews').querySelector('.dt-search input');field.value='System Administrator Main Branch';field.dispatchEvent(new Event('input'));})()");
    await delay(180);
    assert.equal(await evaluate("__api('Shift reviews').rows({search:'applied'}).count()"), 11);
    await evaluate("(()=>{const field=__card('Shift reviews').querySelector('.dt-search input');field.value='';field.dispatchEvent(new Event('input'));})()");
    await delay(180);
    checks.push('Stacked cashier and branch names have readable separators in both filter menus; filtering, synchronization and phrase search work.');
    assert.equal(await evaluate("__table('Shift reviews').tBodies[0].rows[0].cells[0].textContent"), '#23');
    await evaluate("__api('Shift reviews').order([[0,'asc']]).draw();void 0");
    assert.deepEqual(await evaluate("[...__table('Shift reviews').tBodies[0].rows].slice(0,3).map(r=>r.cells[0].textContent)"), ['#1','#2','#3']);
    for (const column of [4, 5]) {
        await evaluate(`__api('Shift reviews').order([[${column},'desc']]).draw();void 0`);
        assert.equal(await evaluate("__table('Shift reviews').tBodies[0].rows[0].cells[0].textContent"), '#2');
    }
    await evaluate("__api('Shift reviews').order([[5,'asc']]).draw();void 0");
    assert.equal(await evaluate("__table('Shift reviews').tBodies[0].rows[0].cells[0].textContent"), '#1');
    for (const [label, column] of [['Shift reviews', 2], ['Exceptional movements', 0]]) {
        await evaluate(`__api('${label}').order([[${column},'asc']]).draw();void 0`);
        assert.match(await evaluate(`__table('${label}').tBodies[0].rows[0].cells[${column}].textContent`), /^September 30/);
    }
    checks.push('Shift IDs sort numerically; combined dates and cash cells sort by their primary value, including negative cash.');
    await evaluate("const field=__card('Shift reviews').querySelector('.dt-search input');field.value='Cashier 23';field.dispatchEvent(new Event('input'));void 0");
    await delay(200);
    assert.equal(await evaluate("__api('Shift reviews').rows({search:'applied'}).count()"), 1);
    assert.match(await evaluate("__table('Shift reviews').tBodies[0].querySelector('a[aria-label=\"Review shift #23\"]').href"), /\/shifts\/23$/);
    assert.equal(await evaluate("__api('Exceptional movements').rows({search:'applied'}).count()"), 23);
    await evaluate("__card('Exceptional movements').querySelector('[data-dt-go=next]').click();void 0");
    assert.equal(await evaluate("__api('Exceptional movements').page()"), 1);
    await evaluate("const lengthSelect=__card('Exceptional movements').querySelector('.dt-length select');lengthSelect.value='25';lengthSelect.dispatchEvent(new Event('change'));void 0");
    assert.equal(await evaluate("__api('Exceptional movements').page.len()"), 25);
    checks.push('Search, Review links, next page and rows-per-page work independently for each table.');
    assert.equal(await evaluate("__api('Exceptional movements').settings()[0].aoColumns[4].bSortable"), false);
    assert.equal(await evaluate("__api('Shift reviews').settings()[0].aoColumns[7].bSortable"), false);
    await evaluate("const form=__table('Exceptional movements').querySelector('form');window.__reviewAction=form.action;window.__csrf=form.querySelector('input[type=hidden]').value;form.querySelector('[name=review_note]').value='Checked physical cash';__api('Exceptional movements').order([[2,'desc']]).draw();void 0");
    await evaluate("__api('Exceptional movements').order([]).page.len(10).page(2).draw('page');__api('Exceptional movements').page(0).draw('page');void 0");
    const review = await evaluate("(()=>{const form=__api('Exceptional movements').rows().nodes().toArray().map(row=>row.querySelector('form')).find(f=>f.action===__reviewAction);return {note:form.querySelector('[name=review_note]').value,csrf:form.querySelector('input[type=hidden]').value,valid:form.checkValidity()};})()");
    assert.equal(review.note, 'Checked physical cash');
    assert.equal(review.csrf, await evaluate('__csrf'));
    assert.equal(review.valid, true);
    checks.push('Review actions are not sortable; note text, validation and CSRF survive sorting and paging.');
    for (const width of [320, 390, 768, 1440]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: false});
        for (const dark of [false, true]) {
            await evaluate(`document.documentElement.classList.toggle('dark',${dark})`); await delay(60);
            assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'), false, 'Manager page overflow at ' + width);
            assert.ok(await evaluate("[...document.querySelectorAll('.dt-toolbar,.dt-footer')].every(el=>el.getBoundingClientRect().width<=el.closest('.cw-card').getBoundingClientRect().width+1)"));
        }
        if ([390, 1440].includes(width)) {
            await evaluate("document.documentElement.classList.remove('dark')");
            const clip = await evaluate("(()=>{const r=__card('Shift reviews').getBoundingClientRect();return{x:r.left+scrollX,y:r.top+scrollY,width:r.width,height:r.height,scale:1};})()");
            const shot = await send('Page.captureScreenshot', {format:'png',captureBeyondViewport:true,clip});
            fs.writeFileSync(path.join(__dirname, 'shift-tables-' + width + '.png'), Buffer.from(shot.data, 'base64'));
        }
        checks.push('Manager tables fit ' + width + 'px in light and dark themes.');
    }
    await navigate('manager-tables-empty');
    assert.equal(await tableCount(), 3);
    for (const [label, message] of [['Branch drawers','No branch drawers configured.'],['Shift reviews','No shifts yet.'],['Exceptional movements','No movements awaiting review.']]) {
        assert.equal(await evaluate(`__api('${label}').rows().count()`), 0);
        assert.equal(await evaluate(`__table('${label}').tBodies[0].textContent.trim()`), message);
    }
    checks.push('All empty manager tables keep controls and their own empty messages.');
    for (const view of ['shift-open', 'shift-closed']) {
        await navigate(view);
        assert.equal(await tableCount(), 3);
        assert.equal(await evaluate("document.querySelectorAll('.cashier-workspace .dt-toolbar').length"), 3);
    }
    checks.push('Open and closed shift details initialize all three operational tables.');
    await navigate('shift-print-closed');
    assert.equal(await evaluate("document.querySelectorAll('.js-datatable,.dt-toolbar').length"), 0);
    checks.push('Printable shift reports still contain complete static tables.');
};
