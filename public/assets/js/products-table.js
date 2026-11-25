/* Product filters update the DataTable without navigating the workspace. */
(function () {
'use strict';
function boot() {
    const form = document.getElementById('products-filter-form');
    const table = document.querySelector('table[aria-label="Products"]');
    if (!form || !table || !window.PharxmacoTables || !window.jQuery?.fn.dataTable.isDataTable(table)) return;
    const api = window.jQuery(table).DataTable();
    const card = form.closest('.card');
    const feedback = card.querySelector('.product-filter-feedback');
    const message = feedback.querySelector('span');
    const retry = feedback.querySelector('button');
    const drafts = new Map(), bound = new WeakSet(), updates = new WeakMap();
    let pendingRequest = null, sequence = 0, retryUrl = null, retryReset = false;
    const rows = () => api.rows().nodes().toArray();
    const key = stockForm => stockForm.elements.product_id.value + ':' + stockForm.elements.branch_id.value;
    function rememberDrafts() {
        rows().forEach(row => row.querySelectorAll('.stock-update-form').forEach(stockForm => {
            if (!stockForm.elements.stock.value && !stockForm.elements.remarks.value) { drafts.delete(key(stockForm)); return; }
            drafts.set(key(stockForm), { quantity: stockForm.elements.stock.value, reason: stockForm.elements.remarks.value,
                mode: stockForm.elements.stock_mode.value, baseline: stockForm.dataset.draftStock || stockForm.elements.old_stock.value });
        }));
    }
    function restoreDrafts(next) {
        next.forEach(row => row.querySelectorAll('.stock-update-form').forEach(stockForm => {
            const draft = drafts.get(key(stockForm));
            if (!draft) return;
            stockForm.elements.stock.value = draft.quantity;
            stockForm.elements.remarks.value = draft.reason;
            stockForm.elements.stock_mode.value = draft.mode;
            if (Number(draft.baseline) !== Number(stockForm.elements.old_stock.value)) stockForm.dataset.draftStock = draft.baseline;
        }));
    }
    function show(text, failed) {
        feedback.hidden = false;
        feedback.setAttribute('role', failed ? 'alert' : 'status');
        message.textContent = text;
        retry.hidden = !failed;
    }
    function syncFilters(filters, manufacturers) {
        ['keyword', 'branch_id', 'category_id', 'status'].forEach(name => {
            let input = form.querySelector('input[name="' + name + '"]');
            if (!filters[name]) { input?.remove(); return; }
            if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = name; form.appendChild(input); }
            input.value = filters[name];
        });
        const makerNames = manufacturers.slice();
        if (filters.manufacturer && !makerNames.includes(filters.manufacturer)) makerNames.push(filters.manufacturer);
        ['products-manufacturer', 'products-manufacturer-mobile'].forEach(id => {
            const select = document.getElementById(id);
            if (!select) return;
            select.replaceChildren(new Option('All manufacturers', ''));
            makerNames.forEach(name => select.add(new Option(name, name)));
            select.value = filters.manufacturer;
        });
        ['products-stock-status', 'products-stock-status-mobile'].forEach(id => {
            const select = document.getElementById(id);
            if (select) select.value = filters.stock_filter;
        });
        document.getElementById('products-medicine-type').value = filters.medicine_type;
    }
    async function load(url, resetQuick) {
        const current = ++sequence;
        pendingRequest?.abort();
        const controller = new AbortController();
        pendingRequest = controller;
        retryUrl = new URL(url); retryReset = resetQuick;
        table.setAttribute('aria-busy', 'true');
        show('Updating products…', false);
        const timeout = window.setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (response.status === 401 || response.status === 403) throw new Error('Your session or access has changed. Sign in again to update products.');
            if (!response.ok || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error('Products could not be updated. Showing the previous results.');
            const data = await response.json();
            if (current !== sequence) return;
            if (data.ok !== true || typeof data.rows_html !== 'string' || !Array.isArray(data.manufacturers) || !data.filters) throw new Error('The product response was incomplete. Showing the previous results.');
            const fragment = document.createElement('tbody');
            fragment.innerHTML = data.rows_html;
            const next = [...fragment.rows].filter(row => !row.querySelector('td[colspan]'));
            if (next.some(row => row.cells.length !== table.tHead.rows[0].cells.length)) throw new Error('The product response was incomplete. Showing the previous results.');
            rememberDrafts(); restoreDrafts(next);
            const headings = [...table.tHead.rows[0].cells];
            next.forEach(row => {
                row.setAttribute('role', 'row');
                [...row.cells].forEach((cell, index) => {
                    cell.setAttribute('role', 'cell'); cell.dataset.label = headings[index].textContent.trim();
                    if (headings[index].classList.contains('table-number')) cell.classList.add('table-number');
                    if (index === Number(table.dataset.recordTitle || 0)) cell.classList.add('record-title');
                    if (/^actions?$/i.test(cell.dataset.label)) cell.classList.add('record-actions');
                });
            });
            bindStockForms(next);
            window.PharxmacoTables.replaceRows(table, next);
            if (resetQuick) card.querySelector('.dt-clear')?.click();
            syncFilters(data.filters, data.manufacturers);
            let cap = card.querySelector('.dt-cap-note');
            if (data.truncated && !cap) {
                cap = document.createElement('p'); cap.className = 'dt-cap-note'; cap.setAttribute('role', 'status');
                cap.textContent = 'This list is very long, so only the first 3,000 records are loaded here.';
                feedback.after(cap);
            } else if (!data.truncated) cap?.remove();
            const applied = new URL(form.action);
            Object.entries(data.filters).forEach(([name, value]) => { if (value !== '') applied.searchParams.set(name, value); });
            history.replaceState(history.state, '', applied);
            show('Products updated.', false);
        } catch (error) {
            if (current !== sequence) return;
            show(error.name === 'AbortError' ? 'Updating products timed out. Showing the previous results.' : error.message, true);
        } finally {
            window.clearTimeout(timeout);
            if (current === sequence) { pendingRequest = null; table.removeAttribute('aria-busy'); }
        }
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        const url = new URL(form.action);
        new FormData(form).forEach((value, name) => { if (value !== '') url.searchParams.set(name, value); });
        load(url, false);
    });
    document.getElementById('products-medicine-type').addEventListener('change', () => form.requestSubmit());
    form.querySelector('a').addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button) return;
        event.preventDefault(); load(new URL(event.currentTarget.href), true);
    });
    retry.addEventListener('click', () => { if (retryUrl) load(retryUrl, retryReset); });
function bindStockForms(rows) {
rows.forEach(function(row) { row.querySelectorAll('.stock-update-form').forEach(function(form){
    if (bound.has(form)) return;
    bound.add(form);
    const mode = form.querySelector('[name="stock_mode"]');
    const input = form.querySelector('[name="stock"]');
    const current = Number(form.querySelector('[name="old_stock"]').value);
    const preview = form.querySelector('.stock-preview');
    const button = form.querySelector('button[type="submit"]');
    const reason = form.querySelector('[name="remarks"]');
    const lot = form.querySelector('[name="batch_no"]');
    const expiry = form.querySelector('[name="expiration_date"]');
    const batchNote = form.querySelector('.stock-batch-note');
    const chips = form.querySelectorAll('.stock-chip');
    const presets = Array.from(chips, chip => chip.dataset.reason).sort((a, b) => b.length - a.length);
    const unit = quantity => quantity === 1 ? form.dataset.unitSingular : form.dataset.unitPlural;
    function updatePreview() {
        const removing = mode.value === 'remove';
        // Adding stock records a new batch (lot and expiry); removing can name the batch to take from.
        form.querySelectorAll('.stock-add-only').forEach(field => {
            field.hidden = removing;
            field.querySelectorAll('input, select').forEach(control => { control.disabled = removing; });
        });
        form.querySelectorAll('.stock-remove-only').forEach(field => {
            field.hidden = !removing;
            field.querySelectorAll('input, select').forEach(control => { control.disabled = !removing; });
        });
        if (batchNote && !removing) {
            // Unchanged lot and expiry top up the batch they came from; any change starts a new batch.
            const same = lot.value.trim() === lot.dataset.default && expiry.value === expiry.dataset.default;
            batchNote.textContent = same
                ? 'Adds to the current batch' + (lot.dataset.default ? ' (' + lot.dataset.default + ')' : '') + '. Change the lot or expiry to create a new batch.'
                : 'A new batch will be created.';
        }
        chips.forEach(chip => { chip.hidden = chip.dataset.mode !== (removing ? 'remove' : 'add'); });
        input.max = String(removing ? Math.min(current, 1000000) : 1000000);
        button.textContent = removing ? 'Remove stock' : 'Add stock';
        input.setCustomValidity('');
        if (form.dataset.draftStock && Number(form.dataset.draftStock) !== current) {
            preview.textContent = 'Stock changed from ' + form.dataset.draftStock + ' to ' + current + '. Check and re-enter the quantity before saving.';
            input.setCustomValidity('Stock changed. Check and re-enter the quantity before saving.');
            button.disabled = true;
            return;
        }
        const trimmedReason = reason.value.trim();
        reason.setCustomValidity(trimmedReason.length < 3 || trimmedReason.length > 200
            ? 'Enter a reason or reference of 3 to 200 characters.' : '');
        button.disabled = true;
        const units = Number(input.value);
        if (!input.value) {
            preview.textContent = 'Enter a quantity to see the new balance.';
            return;
        }
        if (!/^[0-9]+$/.test(input.value) || !Number.isInteger(units) || units < 1 || units > 1000000) {
            preview.textContent = 'Enter a whole quantity from 1 to 1,000,000.';
            input.setCustomValidity('Enter a whole quantity from 1 to 1,000,000.');
            return;
        }
        if (removing && units > current) {
            preview.textContent = 'Cannot remove more than the current stock.';
            input.setCustomValidity('Removal quantity exceeds current stock.');
            return;
        }
        const balance = removing ? current - units : current + units;
        preview.textContent = current + (removing ? ' − ' : ' + ') + units + ' = ' + balance + ' ' + unit(balance) + ' after saving';
        button.disabled = !input.validity.valid || !reason.validity.valid;
    }
    mode.addEventListener('change', updatePreview);
    // Quick reasons fill the reason field; anything typed after the preset (e.g. a DR number) is kept.
    chips.forEach(chip => chip.addEventListener('click', function() {
        const lead = presets.find(text => reason.value.trim().toLowerCase().startsWith(text.toLowerCase()));
        const rest = (lead ? reason.value.trim().slice(lead.length) : reason.value).trim();
        reason.value = chip.dataset.reason + ' ' + rest;
        reason.focus();
        reason.setSelectionRange(reason.value.length, reason.value.length);
        updatePreview();
    }));
    input.addEventListener('input', function() { delete form.dataset.draftStock; updatePreview(); });
    reason.addEventListener('input', updatePreview);
    lot.addEventListener('input', updatePreview);
    expiry.addEventListener('input', updatePreview);
    expiry.addEventListener('change', updatePreview);
    reason.addEventListener('change', updatePreview);
    form.addEventListener('submit', function(event){
        if (pendingRequest) { event.preventDefault(); return; }
        reason.value = reason.value.trim();
        updatePreview();
        if (!form.reportValidity()) {
            event.preventDefault();
        }
    });
    updates.set(form, updatePreview);
    updatePreview();
}); });
}
    bindStockForms(rows());
    window.addEventListener('pageshow', () => rows().forEach(row => row.querySelectorAll('.stock-update-form').forEach(stockForm => updates.get(stockForm)?.())));
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
}());
