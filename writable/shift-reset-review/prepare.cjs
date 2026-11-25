const fs = require('node:fs'), path = require('node:path'), crypto = require('node:crypto'), assert = require('node:assert/strict');
const sourcePath = process.argv[2];
if (!sourcePath) throw Error('Provide the source export path.');
const source = fs.readFileSync(sourcePath, 'utf8');
// Only recognize complete INSERT statements whose semicolons are outside quoted data.
function insertRanges(sql) {
    const ranges = []; let quote = null, comment = null, start = null;
    for (let i = 0; i < sql.length; i++) {
        const ch = sql[i], next = sql[i + 1];
        if (comment === 'line') { if (ch === '\n') comment = null; continue; }
        if (comment === 'block') { if (ch === '*' && next === '/') { comment = null; i++; } continue; }
        if (quote) {
            if (ch === '\\' && quote !== '`') i++;
            else if (ch === quote) { if (next === quote) i++; else quote = null; }
            continue;
        }
        if (ch === "'" || ch === '"' || ch === '`') { quote = ch; continue; }
        if (ch === '#' || (ch === '-' && next === '-' && /\s/.test(sql[i + 2] || ' '))) { comment = 'line'; continue; }
        if (ch === '/' && next === '*') { comment = 'block'; i++; continue; }
        if (start === null && sql.slice(i, i + 12).toUpperCase() === 'INSERT INTO ') start = i;
        if (ch === ';' && start !== null) {
            const text = sql.slice(start, i + 1);
            const match = text.match(/^INSERT INTO `([a-z_]+)` \(([^)]+)\) VALUES\s*([\s\S]*);$/);
            if (!match) throw Error('Unsupported INSERT statement.');
            ranges.push({start, end: i + 1, text, table: match[1], columns: [...match[2].matchAll(/`([^`]+)`/g)].map(m => m[1]), values: match[3]}); start = null;
        }
    }
    if (quote || comment === 'block' || start !== null) throw Error('Incomplete export syntax.');
    return ranges;
}
function rawTuples(text) {
    const rows = []; let quote = false, row = null, token = '';
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (quote) {
            token += ch;
            if (ch === '\\') token += text[++i];
            else if (ch === "'") { if (text[i + 1] === "'") token += text[++i]; else quote = false; }
        } else if (ch === "'") { quote = true; token += ch; }
        else if (ch === '(') { if (row) throw Error('Nested data expression.'); row = []; token = ''; }
        else if (ch === ',' && row) { row.push(token.trim()); token = ''; }
        else if (ch === ')') { if (!row) throw Error('Unexpected tuple end.'); row.push(token.trim()); rows.push(row); row = null; token = ''; }
        else if (row) token += ch;
        else if (!/[\s,]/.test(ch)) throw Error('Unexpected character outside data tuple.');
    }
    if (quote || row) throw Error('Incomplete tuple.');
    for (const row of rows) for (const value of row) if (!/^(?:NULL|-?\d+(?:\.\d+)?|'[\s\S]*')$/i.test(value)) throw Error('Unsupported data literal.');
    return rows;
}
const ranges = insertRanges(source), removed = new Set(['cashier_shifts', 'cashier_shift_actions', 'cashier_shift_reviews']);
const reset = {cashier_drawers: ['active_shift_id', 'last_shift_id'], cashier_events: ['shift_id']};
const counts = {}, edits = [];
for (const item of ranges) {
    const rows = rawTuples(item.values);
    assert.ok(rows.every(row => row.length === item.columns.length), 'Field count mismatch: ' + item.table);
    counts[item.table] = (counts[item.table] || 0) + rows.length;
    if (removed.has(item.table)) edits.push({...item, replacement: '-- Shift-history rows removed at the owner\'s request.'});
    else if (reset[item.table]) {
        for (const field of reset[item.table]) {
            const index = item.columns.indexOf(field); assert.ok(index >= 0, 'Missing reset field: ' + field);
            for (const row of rows) row[index] = 'NULL';
        }
        edits.push({...item, replacement: item.text.slice(0, item.text.indexOf('VALUES') + 6) + '\r\n' + rows.map(row => '(' + row.join(', ') + ')').join(',\r\n') + ';'});
    }
}
assert.equal(counts.cashier_shifts, 2, 'Review source changes before regenerating.');
assert.equal(counts.cashier_shift_actions, 3, 'Review source changes before regenerating.');
let output = source;
for (const edit of edits.reverse()) output = output.slice(0, edit.start) + edit.replacement + output.slice(edit.end);
const hash = data => crypto.createHash('sha256').update(data).digest('hex');
output = '-- Pharxmaco full backup with cashier shift history removed, October 16, 2026.\r\n'
    + '-- Restore into an EMPTY database; use the clear-cashier-shifts patch for an existing database.\r\n'
    + '-- Source SHA-256: ' + hash(source) + '\r\n'
    + '-- Sales/payment events, held carts, general audit logs, inventory and accounts are preserved.\r\n'
    + '-- Drawer shift references and payment-event shift links are NULL; ID sequences are preserved.\r\n' + output;
const target = 'database/exports/u315645644_pharxmacotest-without-cashier-shifts-2026-10-16.sql';
fs.mkdirSync(path.dirname(target), {recursive: true}); fs.writeFileSync(target, output);
const manifest = {source_path: path.resolve(sourcePath), source_sha256: hash(source), cleaned_export: path.resolve(target), cleaned_sha256: hash(output), patch: path.resolve('database/upgrades/2026-10-16-clear-cashier-shifts.sql'), original_row_counts: counts};
fs.writeFileSync(path.join(__dirname, 'manifest.json'), JSON.stringify(manifest, null, 2));
console.log(JSON.stringify({file: target, shifts_removed: counts.cashier_shifts, shift_actions_removed: counts.cashier_shift_actions, preserved_payment_events: counts.cashier_events, source_unchanged: hash(fs.readFileSync(sourcePath)) === manifest.source_sha256}, null, 2));
