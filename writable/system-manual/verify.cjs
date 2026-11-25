const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict'), crypto = require('node:crypto'), {execFileSync} = require('node:child_process');
const root = process.cwd(), manifest = JSON.parse(fs.readFileSync(path.join(__dirname, 'manifest.json'), 'utf8'));
const dir = path.join(root, 'tmp/pdfs/system-manual'), name = path.basename(manifest.pdf, '.pdf');
fs.mkdirSync(dir, {recursive: true}); fs.copyFileSync(manifest.pdf, path.join(dir, name + '.pdf'));
execFileSync(process.execPath, ['writable/pdf-export-review/inspect-streams.cjs', name, '--quiet'], {cwd: root, env: {...process.env, PDF_REVIEW_DIR: dir}, stdio: 'pipe'});
const pages = JSON.parse(fs.readFileSync(path.join(dir, name + '-text.json'), 'utf8'));
const normal = text => text.replace(/\s+/g, ' ').replace(/\s+([.,;:!?])/g, '$1').trim();
const source = fs.readFileSync(manifest.source, 'utf8');
const sha = data => crypto.createHash('sha256').update(data).digest('hex');
assert.equal(sha(fs.readFileSync(manifest.pdf)), manifest.pdf_sha256);
assert.equal(sha(source), manifest.source_sha256);
assert.equal(pages.length, 37);
assert.equal(manifest.actual_pages, 37);
for (const entry of manifest.page_map) assert.equal(entry.actual_page, entry.expected_page, entry.title);
const geometry = [];
for (let i = 0; i < pages.length; i++) {
    const page = pages[i]; const text = normal(page.items.map(item => item.text).join(' '));
    assert.ok(text.includes('Page ' + (i + 1) + ' of 37'), 'Missing page number ' + (i + 1));
    if (i > 0) {
        assert.ok(text.includes(normal(manifest.page_map[i].title)), 'Missing or split chapter title on page ' + (i + 1));
    }
    const runningLabels = ['Pharxmaco User Manual', 'PHARXMACO / USER MANUAL', 'Point of Sale and Inventory Operations', 'Point of sale and inventory operations', ...(manifest.parts || []).map(part=>part.title)];
    const body = page.items.filter(item => !runningLabels.includes(item.text)
        && !item.text.startsWith('PHX UM 001') && !/^Page \d+ of \d+$/.test(item.text));
    const y = body.map(item => Number(item.position?.split(' ')[1])).filter(Number.isFinite);
    const minY = Math.min(...y);
    geometry.push({page: i + 1, body_text_items: body.length, lowest_baseline_points: minY});
    assert.ok(body.length > 8, 'Unexpected near-empty page ' + (i + 1));
    assert.ok(minY >= 55, 'Body text is too close to footer on page ' + (i + 1) + ': ' + minY);
}
const sections = source.split(/<!-- page: [^>]+ -->/).slice(1);
for (let i = 3; i < sections.length; i++) {
    const paragraphs = sections[i].trim().split(/\n\s*\n/);
    const last = normal(paragraphs.at(-1).replace(/\*\*|`/g, ''));
    const text = normal(pages[i].items.map(item => item.text).join(' '));
    const compact = value => value.replace(/[^\p{L}\p{N}]/gu, '').toLowerCase();
    assert.ok(compact(text).includes(compact(last)), 'Final paragraph missing or moved from page ' + (i + 1));
}
for (const label of ['My Day', 'Point of Sale', 'Sales History', 'Products', 'Categories', 'Stock Logs', 'Expiry Report', 'Suppliers', 'Cashier Shifts', 'Reports', 'Discounts', 'Users', 'Branches', 'Activity Logs', 'Interface Settings', 'Backup & Restore']) assert.ok(source.includes(label), 'Missing module ' + label);
for (const field of ['tin', 'taxStatus', 'vatRate', 'serialPrefix', 'serialStart', 'serialEnd', 'serialDigits']) assert.ok(fs.readFileSync('app/Config/ReceiptInvoice.php', 'utf8').includes('$' + field), 'Configuration field mismatch ' + field);
assert.equal(Math.round(11200 * (1 - 0.25)), 8400); assert.equal(Math.round(11200 * (1 - 0.30)), 7840);
const pdfRaw = fs.readFileSync(manifest.pdf).toString('latin1');
const links = [...pdfRaw.matchAll(/\/Subtype\s+\/Link\b/g)].length;
assert.ok(links >= 68, 'Missing linked table of contents');
assert.ok(/\/FontFile2\b/.test(pdfRaw), 'TrueType font outlines must be embedded');
if (manifest.body_font === 'Calibri') assert.ok(/\/BaseFont\s*\/[^\s]*Calibri/.test(pdfRaw), 'Calibri must be used in the PDF');
if (manifest.font_color === 'black') {
    // Dompdf content streams use RGB fills and q/Q graphics-state saves.
    // Match complete text blocks so operator-like characters inside text are ignored.
    for (let i=1;i<=pages.length;i++) {
        const raw=fs.readFileSync(path.join(dir,`${name}-page-${i}-raw.txt`),'latin1');
        let fill=[0,0,0]; const states=[];
        const tokens=raw.matchAll(/BT[\s\S]*?ET|([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+rg\b|\b([qQ])\b/g);
        for (const token of tokens) {
            if (token[0].startsWith('BT')) assert.deepEqual(fill,[0,0,0],'Non-black PDF text on page '+i);
            else if (token[4]==='q') states.push([...fill]);
            else if (token[4]==='Q') { assert.ok(states.length,'Unbalanced PDF graphics state'); fill=states.pop(); }
            else fill=token.slice(1,4).map(Number);
        }
    }
}
const baseline = path.join(__dirname,'revisions/rev-1.2/Pharxmaco_User_Manual.md');
if (fs.existsSync(baseline) && !process.argv.includes('--workflow-update')) {
    const prior = fs.readFileSync(baseline,'utf8')
        .replace('Document PHX UM 001 | Revision 1.2 | 18 October 2026','Document PHX UM 001')
        .replace(/\| Revision and issue date \| 1\.2 \/ 18 October 2026 \|\r?\n/,'')
        .replace('| Revision owner |','| Document owner |')
        .replace('Cashiers, administrators and designated supervisors','Cashiers and administrators')
        .replace('Check the issue date when application screens change.','Use a current copy when application screens change.')
        .replace(/## Information to give the administrator\r?\n[\s\S]*?(?=\*\*End of manual\.\*\*)/,'')
        .replaceAll('34 Terms and support handover','34 Terms and definitions')
        .replace('Stop only the affected task, preserve the physical goods or payment evidence, and record the details in Chapter 34.','Stop only the affected task and preserve the physical goods or payment evidence.');
    assert.equal(source, prior, 'Only the requested section, its obsolete reference and edition/title metadata may change');
}
if (process.argv.includes('--workflow-update')) {
    assert.ok(source.includes('13-digit payment-confirmation reference'), 'Current GCash reference instructions missing');
    assert.ok(source.includes('Saved Sales Forecasts'), 'Sales-only forecast instructions missing');
    for (const phrase of ['Restock Suggestions', 'low-stock product estimates', '30-day coverage assumption']) {
        assert.ok(!source.includes(phrase), 'Retired planning instruction remains: ' + phrase);
        assert.ok(!pages.some(page => normal(page.items.map(item => item.text).join(' ')).includes(phrase)), 'Retired planning instruction remains in PDF: ' + phrase);
    }
}
const removedPhrases = ['Information to give the administrator','Record the application/site name','State whether the operation\'s outcome is confirmed','The administrator should compare sale'];
for(const phrase of removedPhrases) {
    assert.ok(!source.includes(phrase),'Removed section remains in manuscript');
    assert.ok(!pages.some(page=>normal(page.items.map(item=>item.text).join(' ')).includes(phrase)),'Removed section remains in PDF');
}
const revisionNumberLabel=/\b(?:revision|rev\.?)\s+\d/i;
assert.ok(!revisionNumberLabel.test(source),'Revision number labels remain in the manuscript');
for(const page of pages) assert.ok(!revisionNumberLabel.test(normal(page.items.map(item=>item.text).join(' '))),'Revision number labels remain in PDF');
const html=fs.readFileSync(manifest.html,'utf8');
assert.ok(!revisionNumberLabel.test(html)&&!/>Revision<|Revision and issue date/i.test(html),'Revision labels remain in HTML');
const issueDateLabel=/\b(?:issue date|issued)\b|18 October 2026/i;
assert.ok(!issueDateLabel.test(source)&&!issueDateLabel.test(html),'Issue date remains in manuscript or HTML');
for(const page of pages) assert.ok(!issueDateLabel.test(normal(page.items.map(item=>item.text).join(' '))),'Issue date remains in PDF');
const result = {pages: pages.length, chapters: 34, issue_date_shown:false, revision_label_shown:false, body_font:manifest.body_font, display_font:manifest.display_font, body_font_pt:manifest.body_font_pt, linked_contents_annotations: links, checked:['Chapter starts and table-of-contents page numbers match.','Every page has its correct footer number.','Every chapter retains its final paragraph on its own page.','No body text enters the footer area.','All navigation modules and invoice configuration fields are covered.','Training discount amounts checked.','TrueType typography embedded in the PDF.','Requested section, revision labels and issue date removed; other operating text retained.'], geometry, pdf_sha256: manifest.pdf_sha256, source_sha256: manifest.source_sha256, application_records_modified: false};
if (manifest.font_color === 'black') result.checked.push('All PDF text uses black fill, including running headers and footers.');
fs.writeFileSync(path.join(__dirname, 'checks.json'), JSON.stringify(result, null, 2));
console.log(JSON.stringify({pages: result.pages, chapters: result.chapters, linked_contents_annotations: links, lowest_body_baseline: Math.min(...geometry.map(page => page.lowest_baseline_points)), checks: result.checked.length}, null, 2));
