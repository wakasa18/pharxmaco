const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { records } = require('../latest-database-review/records.cjs');
const sourcePath = 'C:/Users/warre/Downloads/u315645644_pharxmacotest (7).sql';
const source = fs.readFileSync(sourcePath, 'utf8');
const sales = records(source, 'sales');
const items = records(source, 'sale_items');
const refunds = records(source, 'refund_items');
const cents = n => Math.round(Number(n || 0) * 100);
const selected = sales.filter(s => ['completed', 'partially_refunded'].includes(s.status)
    && s.sale_date >= '2026-09-21 00:00:00' && s.sale_date <= '2026-10-20 23:59:59');
const ids = new Set(selected.map(s => s.id));
const sum = (rows, key) => rows.reduce((a, row) => a + cents(row[key]), 0);
const original = sum(selected, 'total_amount');
const discount = sum(selected, 'discount_amount');
const vat = sum(items.filter(i => ids.has(i.sale_id)), 'vat_exemption_amount');
const returned = sum(refunds.filter(r => ids.has(r.sale_id)), 'refund_subtotal');
const net = sum(selected, 'final_total') - returned;
const result = {
    source_sha256: crypto.createHash('sha256').update(source).digest('hex'),
    source_only: true, hosted_database_accessed: false,
    range: ['2026-09-21', '2026-10-20'], transactions: selected.length,
    original_sales: original / 100, discounts: discount / 100, vat_exemption: vat / 100,
    refunds: returned / 100, legacy_gross_after_refunds: (original - returned) / 100,
    net_sales: net / 100, other_adjustments: (original - discount - vat - returned - net) / 100,
    screenshot: { gross: 238422, discount: 779.02, net: 237637.09, unexplained: (23842200 - 77902 - 23763709) / 100 },
};
const manifest = JSON.parse(fs.readFileSync(path.join(__dirname, '../forecast-month-review/manifest.json'), 'utf8'));
assert.equal(manifest.source_sha256, result.source_sha256);
const added = manifest.plan.filter(s => s.sale_date >= '2026-09-21 00:00:00' && s.sale_date <= '2026-10-20 23:59:59');
result.with_prepared_demo = {
    transactions: selected.length + added.length,
    original_sales: (original + sum(added, 'subtotal')) / 100,
    gross_after_refunds: (original - returned + sum(added, 'subtotal')) / 100,
    discounts: (discount + sum(added, 'discount')) / 100,
    vat_exemption: vat / 100, refunds: returned / 100,
    net_sales: (net + sum(added, 'total')) / 100,
};
assert.equal(result.with_prepared_demo.gross_after_refunds, result.screenshot.gross);
assert.equal(result.with_prepared_demo.discounts, result.screenshot.discount);
assert.equal(result.with_prepared_demo.net_sales, result.screenshot.net);
assert.equal(result.with_prepared_demo.vat_exemption, result.screenshot.unexplained);
result.screenshot_matches_prepared_data = true;
fs.writeFileSync(path.join(__dirname, 'source-audit.json'), JSON.stringify(result, null, 2) + '\n');
console.log(JSON.stringify(result, null, 2));
