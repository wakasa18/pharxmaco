(() => {
    'use strict';
    const money = value => (Number(value) < 0 ? '−' : '') + '₱' + Math.abs(Number(value || 0)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const cents = value => value === '' || !Number.isFinite(Number(value)) ? null : Math.round(Number(value) * 100);
    const opening = document.querySelector('[data-opening-form]');
    if (opening) {
        const drawer = opening.elements.drawer_id, cash = opening.elements.opening_cash, note = opening.elements.opening_note;
        const update = () => {
            const expected = drawer.selectedOptions[0]?.dataset.expected;
            const prior = expected === undefined || expected === '' ? null : cents(expected);
            opening.querySelector('[data-opening-expected]').textContent = !drawer.value ? 'Choose an available drawer to see the previous handover count.' : prior === null ? 'No previous closing count for this drawer.' : 'Previous cashier left ' + money(prior / 100) + ' in the drawer.';
            const required = prior !== null && cents(cash.value) !== null && cents(cash.value) !== prior;
            note.required = required; note.minLength = required ? 3 : 0;
        };
        drawer.addEventListener('change', update); cash.addEventListener('input', update); update();
    }
    const closing = document.querySelector('[data-closing-form]');
    if (closing) {
        const cash = closing.elements.counted_cash, left = closing.elements.cash_left, note = closing.elements.closing_note;
        const update = () => {
            const count = cents(cash.value), remaining = cents(left.value), difference = count === null ? null : count - cents(closing.dataset.expected);
            const result = closing.querySelector('[data-count-difference]');
            result.textContent = difference === null ? 'Enter your physical count to see the over / short amount.' : difference === 0 ? 'Your count matches the expected cash.' : (difference > 0 ? 'Over: ' : 'Short: ') + money(Math.abs(difference) / 100);
            result.dataset.state = difference === null ? '' : difference === 0 ? 'match' : difference > 0 ? 'over' : 'short';
            closing.querySelector('[data-closing-removal]').textContent = count === null || remaining === null ? 'The rest will be recorded as removed at closing.' : 'Removed at closing: ' + money((count - remaining) / 100);
            left.setCustomValidity(count !== null && remaining !== null && remaining > count ? 'Cash left cannot exceed cash counted.' : '');
            note.required = (difference !== null && difference !== 0) || closing.dataset.onBehalf === '1'; note.minLength = note.required ? 3 : 0;
        };
        cash.addEventListener('input', update); left.addEventListener('input', update); update();
    }
    const manila = value => new Date(String(value).replace(' ', 'T') + '+08:00');
    const elapsed = document.querySelector('[data-elapsed]');
    if (elapsed) {
        const tick = () => {
            const minutes = Math.max(0, Math.floor((Date.now() - manila(elapsed.dataset.since).getTime()) / 60000));
            elapsed.textContent = minutes < 1 ? 'just now' : minutes < 60 ? minutes + ' min' : Math.floor(minutes / 60) + ' h ' + String(minutes % 60).padStart(2, '0') + ' min';
        };
        tick(); setInterval(tick, 30000);
    }
    const movement = document.querySelector('[data-movement-form]');
    if (movement) {
        const amount = movement.elements.amount, reason = movement.elements.reason, preview = movement.querySelector('[data-movement-preview]');
        const kind = () => movement.querySelector('input[name="movement_type"]:checked').value;
        const expected = () => { const el = document.querySelector('[data-expected-cash]'); return el ? Number(el.dataset.value) : NaN; };
        const update = () => {
            const value = cents(amount.value), base = expected(); let state = '', text = 'Enter an amount to see the expected cash afterwards.', problem = '';
            if (value !== null && value > 0 && Number.isFinite(base)) {
                const after = Math.round(base * 100) + (kind() === 'cash_in' ? value : -value);
                if (after < 0) { problem = 'Cash removed cannot exceed the expected cash in the drawer.'; state = 'bad'; text = 'This is more than the ' + money(base) + ' expected in the drawer.'; }
                else text = 'Expected cash afterwards: ' + money(after / 100);
            }
            amount.setCustomValidity(problem); preview.textContent = text; preview.dataset.state = state;
        };
        movement.addEventListener('change', update); amount.addEventListener('input', update);
        for (const chip of movement.querySelectorAll('[data-reason]')) chip.addEventListener('click', () => {
            reason.value = chip.dataset.reason; movement.querySelector('input[name="movement_type"][value="' + chip.dataset.type + '"]').checked = true; update(); reason.focus();
        });
        document.addEventListener('cw:expected', update); update();
    }
    const root = document.querySelector('[data-my-day]');
    if (!root) return;
    const date = value => {
        const parsed = new Date(value.replace(' ', 'T') + '+08:00');
        return Number.isNaN(parsed.getTime()) ? value : new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(parsed);
    };
    const cell = (row, text, className) => {
        const td = document.createElement('td'); td.textContent = String(text ?? ''); if (className) td.className = className; row.append(td); return td;
    };
    const empty = (body, count, message) => { if (!body.children.length) { const td = cell(body.appendChild(document.createElement('tr')), message, 'cw-empty'); td.colSpan = count; } };
    const badge = (parent, text, tone) => { const el = document.createElement('span'); el.className = 'cw-badge is-' + tone; el.textContent = text; parent.append(el); return el; };
    const moneyCell = (row, value) => cell(row, money(value), 'money' + (Number(value) < 0 ? ' is-neg' : ''));
    const tones = { sale: 'ok', refund: 'bad', exchange: 'info', adjustment: 'warn', cash_in: 'ok', cash_out: 'warn' };
    const small = (parent, text) => { const el = document.createElement('small'); el.textContent = text; parent.append(el); };
    const replaceRows = (body, rows, count, message) => {
        if (window.PharxmacoTables) window.PharxmacoTables.replaceRows(body.closest('table'), rows);
        else { body.replaceChildren(...rows); empty(body, count, message); }
    };
    function render(report) {
        for (const el of root.querySelectorAll('[data-report-metric]')) el.textContent = el.dataset.format === 'money' ? money(report[el.dataset.reportMetric]) : Number(report[el.dataset.reportMetric]).toLocaleString('en-PH');
        const vat = root.querySelector('[data-vat-help]'); if (vat) vat.textContent = 'VAT relief: ' + money(report.vat_relief);
        const payments = root.querySelector('[data-payment-rows]'), paymentRows = [];
        for (const [method, entry] of Object.entries(report.payments)) {
            if (method === 'unknown' && !entry.paid_out) continue;
            const row = document.createElement('tr'); paymentRows.push(row);
            const label = cell(row, '');
            const dot = document.createElement('span'); dot.className = 'cw-dot cw-dot-' + method; dot.setAttribute('aria-hidden', 'true');
            label.append(dot, { cash: 'Cash', gcash: 'GCash', card: 'Card', unknown: 'Unknown (older refund)' }[method] || method);
            for (const key of ['received', 'paid_out', 'net']) key === 'net' ? moneyCell(row, entry[key]) : cell(row, money(entry[key]), 'money');
        }
        replaceRows(payments, paymentRows, 4, 'No payments recorded in this period.');
        const products = root.querySelector('[data-product-rows]'), productRows = [];
        for (const entry of report.products) {
            const row = document.createElement('tr'); productRows.push(row); cell(row, entry.name);
            for (const key of ['sold', 'returned', 'replacements', 'quantity_adjustments']) cell(row, entry[key], 'number' + (key === 'returned' && Number(entry[key]) > 0 ? ' is-neg' : ''));
            moneyCell(row, entry.amount);
        }
        replaceRows(products, productRows, 6, 'No products recorded in this period. Completed sales will appear here.');
        const activity = root.querySelector('[data-activity-rows]'), activityRows = [];
        for (const entry of report.activity) {
            const row = document.createElement('tr'); activityRows.push(row);
            cell(row, date(entry.time), 'cw-date').dataset.order = String(new Date(entry.time.replace(' ', 'T') + '+08:00').getTime());
            const type = cell(row, ''); badge(type, entry.type.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()), tones[entry.type] || 'muted');
            if (entry.legacy) small(type, 'Before shift tracking');
            const info = cell(row, '');
            if (Number(entry.sale_id) > 0) { const link = document.createElement('a'); link.href = root.dataset.saleUrl.replace(/\/$/, '') + '/' + Number(entry.sale_id); link.textContent = entry.invoice || 'View sale'; info.append(link); }
            if (entry.reason) small(info, entry.reason);
            cell(row, entry.method.toUpperCase()); moneyCell(row, entry.amount);
        }
        replaceRows(activity, activityRows, 5, 'No activity recorded in this period.');
        const caption = root.querySelector('[data-activity-caption]'); if (caption) caption.textContent = `Latest 100 movements in this period, including ${report.refund_count} refund event(s) and ${report.exchange_count} exchange(s).`;
        root.querySelector('[data-last-updated]').textContent = 'Updated ' + date(report.generated_at);
    }
    let busy = false;
    const updated = root.querySelector('.cw-updated'), refreshButton = root.querySelector('[data-refresh]');
    async function refresh(force) {
        if (busy || (force !== true && document.hidden) || navigator.onLine === false) return;
        busy = true; refreshButton?.classList.add('is-busy');
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetch(root.dataset.summaryUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
            if (!response.ok) throw new Error('Summary unavailable');
            const data = await response.json(); render(data.report); if (updated) updated.dataset.state = 'live';
            const changed = Number(data.active_shift_id || 0) !== Number(root.dataset.activeShift);
            root.querySelector('[data-shift-changed]').hidden = !changed;
            for (const form of root.querySelectorAll('form[action*="cashier/shifts/"]')) for (const control of form.elements) control.disabled = changed;
            const expected = root.querySelector('[data-expected-cash]'); if (expected && data.expected_cash !== null) { expected.textContent = money(data.expected_cash); expected.dataset.value = String(data.expected_cash); document.dispatchEvent(new Event('cw:expected')); }
            root.querySelector('[data-hero-stale]')?.toggleAttribute('data-shift-stale', changed);
        } catch (_) { root.querySelector('[data-last-updated]').textContent = 'Live update unavailable. Refresh to check the latest totals.'; if (updated) updated.dataset.state = 'error'; }
        finally { clearTimeout(timeout); busy = false; refreshButton?.classList.remove('is-busy'); }
    }
    setInterval(refresh, 30000); window.addEventListener('focus', () => refresh()); window.addEventListener('online', () => refresh());
    refreshButton?.addEventListener('click', () => refresh(true));
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
