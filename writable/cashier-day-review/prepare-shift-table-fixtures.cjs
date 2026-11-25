const fs = require('node:fs'), path = require('node:path');
const dir = __dirname, source = fs.readFileSync(path.join(dir, 'manager.html'), 'utf8');
const table = /(<table\b[^>]*aria-label="([^"]+)"[^>]*>[\s\S]*?<tbody>)([\s\S]*?)(<\/tbody>)/g;
const replaceCell = (cell, body, order) => cell.replace(/>[^]*<\/td>$/, '>' + body + '</td>').replace(/ data-order="[^"]*"/, order === undefined ? '' : ' data-order="' + order + '"');
const populated = source.replace(table, (_, head, label, body, tail) => {
    const row = body.match(/<tr>[\s\S]*?<\/tr>/)?.[0];
    if (!row) throw Error('Missing fixture row for ' + label);
    const cells = row.match(/<td\b[^>]*>[\s\S]*?<\/td>/g);
    const rows = Array.from({length: 23}, (_, index) => {
        const id = index + 1, copy = [...cells];
        const stamp = index === 0 ? 'September 30, 2026 at 11:59 PM' : 'October ' + id + ', 2026 at 10:00 AM';
        if (label === 'Branch drawers') {
            copy[0] = replaceCell(copy[0], 'Branch ' + id);
        } else if (label === 'Shift reviews') {
            copy[0] = replaceCell(copy[0], '#' + id, id);
            const cashier = id === 23 ? 'Cashier 23' : index % 2 ? 'System Administrator' : 'Main Cashier';
            const branch = id === 23 ? 'Branch 2' : 'Main Branch';
            copy[1] = replaceCell(copy[1], '<strong>' + cashier + '</strong><small>' + branch + '</small>');
            copy[2] = replaceCell(copy[2], stamp + '<small>October 24, 2026 at 9:00 PM</small>');
            copy[3] = replaceCell(copy[3], index % 2 ? 'Open' : 'Closed');
            const expected = index === 1 ? 1200 : 8.57;
            copy[4] = replaceCell(copy[4], '₱' + expected.toFixed(2) + '<small>₱' + expected.toFixed(2) + '</small>', expected);
            const difference = index === 0 ? -12 : index === 1 ? 1200 : 0;
            copy[5] = replaceCell(copy[5], '₱' + difference.toFixed(2) + '<small>Opening: ₱95.00</small>', difference);
            copy[7] = copy[7].replaceAll('/shifts/1', '/shifts/' + id).replaceAll('#1', '#' + id);
        } else if (label === 'Exceptional movements') {
            copy[0] = replaceCell(copy[0], stamp + '<small>Cashier ' + id + ' · Branch 1</small>');
            copy[2] = replaceCell(copy[2], '₱' + (index === 0 ? -12 : index === 1 ? 1200 : id).toFixed(2));
            copy[3] = replaceCell(copy[3], 'Movement reason ' + id);
            copy[4] = copy[4].replaceAll('/event/1', '/event/' + id).replaceAll('movement 1', 'movement ' + id);
        }
        return '<tr>' + copy.join('') + '</tr>';
    });
    return head + rows.join('\n') + tail;
});
fs.writeFileSync(path.join(dir, 'manager-tables-populated.html'), populated);
fs.writeFileSync(path.join(dir, 'manager-tables-empty.html'), source.replace(table, (_, head, label, body, tail) => head + tail));
console.log('Prepared populated and empty manager fixtures from rendered application views.');
