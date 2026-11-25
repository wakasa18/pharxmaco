/* Overview dialog shown before Products actions are submitted.
 * Forms opt in with data-action-review="stock|catalog|trash|restore|delete|legacy-restore|legacy-delete|batch".
 * The dialog only describes what the form will send; on Confirm the original form is re-submitted
 * with requestSubmit(), so native validation, CSRF and every server-side rule stay authoritative. */
(() => {
    'use strict';
    const start = () => {
        const overlay = document.getElementById('product-action-review');
        if (!overlay || overlay.dataset.bound) return;
        overlay.dataset.bound = '1';
        // Keep fixed positioning independent of page layout and animation.
        document.body.appendChild(overlay);

        const pick = selector => overlay.querySelector(selector);
        const titleEl = pick('[data-par-title]'), subtitleEl = pick('[data-par-subtitle]');
        const nameEl = pick('[data-par-name]'), metaEl = pick('[data-par-meta]');
        const sectionsEl = pick('[data-par-sections]');
        const notesWrap = pick('[data-par-notes-wrap]'), notesEl = pick('[data-par-notes]');
        const confirmButton = pick('[data-par-confirm]'), back = pick('[data-par-back]');
        const body = pick('.product-review-body'), dialog = pick('.product-review-dialog');
        const money = new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'});
        const confirmed = new WeakSet();
        let pending = null, busy = false;

        const text = (form, name) => String(form.elements.namedItem(name)?.value ?? '').trim();
        const count = (value, unit) => Number(value).toLocaleString('en-PH') + (unit ? ' ' + unit : '');
        const peso = value => value === '' ? 'Not provided' : money.format(Number(value));
        const day = value => {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
            if (!match) return value || 'No expiry date';
            return new Date(+match[1], +match[2] - 1, +match[3])
                .toLocaleDateString('en-PH', {year: 'numeric', month: 'long', day: 'numeric'});
        };
        const optionLabel = select => select?.selectedOptions[0]?.textContent.replace(/\s+/g, ' ').trim() || '';
        const meta = form => [form.dataset.reviewBranch && 'Branch: ' + form.dataset.reviewBranch,
            form.dataset.reviewSku && 'SKU: ' + form.dataset.reviewSku].filter(Boolean).join(' · ');
        const retained = form => count(form.dataset.reviewStock || 0, form.dataset.reviewUnit || '');

        const describers = {
            stock(form) {
                const removing = text(form, 'stock_mode') === 'remove';
                const current = Number(text(form, 'old_stock')), amount = Number(text(form, 'stock'));
                const balance = removing ? current - amount : current + amount;
                const unit = n => n === 1 ? form.dataset.unitSingular : form.dataset.unitPlural;
                const rows = [
                    ['Action', removing ? 'Remove stock' : 'Add stock'],
                    ['Quantity', count(amount, unit(amount))],
                    ['Current stock', count(current, unit(current))],
                    ['Stock after saving', count(balance, unit(balance))],
                ];
                const notes = [];
                if (removing) {
                    rows.push(['Taken from batch', optionLabel(form.elements.namedItem('batch_id')) || 'Earliest expiry first']);
                } else {
                    rows.push(['Batch / lot no.', text(form, 'batch_no') || 'Not provided'],
                        ['Expiry date', day(text(form, 'expiration_date'))]);
                    const batchNote = form.querySelector('.stock-batch-note');
                    if (batchNote && batchNote.textContent.trim()) notes.push(batchNote.textContent.trim());
                }
                rows.push(['Reason / reference', text(form, 'remarks')]);
                return {
                    tone: removing ? 'danger' : 'primary',
                    title: removing ? 'Review stock removal' : 'Review stock addition',
                    subtitle: 'Check the stock change below before saving it.',
                    confirm: removing ? 'Confirm & remove stock' : 'Confirm & add stock',
                    name: form.dataset.reviewProduct, meta: meta(form),
                    sections: [{heading: 'Stock change', rows}], notes,
                };
            },
            catalog(form) {
                const unit = form.dataset.reviewUnit || '';
                return {
                    tone: 'primary', title: 'Review branch setup',
                    subtitle: 'Check the details below before adding this product to your branch.',
                    confirm: 'Confirm & add to branch',
                    name: form.dataset.reviewProduct, meta: meta(form),
                    sections: [{heading: 'Pricing and stock', rows: [
                        ['Initial stock', count(text(form, 'stock') || 0, unit)],
                        ['Reorder level', count(text(form, 'reorder_level') || 0, unit)],
                        ['Cost price per unit', peso(text(form, 'cost_price'))],
                        ['Selling price per unit', peso(text(form, 'price'))],
                        ['Expiration date', day(text(form, 'expiration_date'))],
                        ['Batch / lot number', text(form, 'batch_no') || 'Not provided'],
                    ]}],
                    notes: ['Shared product details are kept as they are.'],
                };
            },
            trash: form => ({
                tone: 'danger', title: 'Move product to Trash',
                subtitle: 'Check what will happen before moving this product.',
                confirm: 'Move to Trash', name: form.dataset.reviewProduct, meta: meta(form),
                sections: [{heading: 'Product in this branch', rows: [
                    ['Branch', form.dataset.reviewBranch || 'This branch'],
                    ['Stock kept', retained(form)],
                    ['Status', form.dataset.reviewStatus || 'Not recorded'],
                ]}],
                notes: ['Only ' + (form.dataset.reviewBranch || 'this branch') + ' is affected. Other branches keep this product.',
                    'Its stock is retained and you can restore it from Product Trash.'],
            }),
            restore: form => ({
                tone: 'primary', title: 'Restore product',
                subtitle: 'Check the product below before restoring it.',
                confirm: 'Restore product', name: form.dataset.reviewProduct, meta: meta(form),
                sections: [{heading: 'Product in this branch', rows: [
                    ['Branch', form.dataset.reviewBranch || 'This branch'],
                    ['Stock retained', retained(form)],
                ]}],
                notes: ['Restored in ' + (form.dataset.reviewBranch || 'this branch') + ' only. Other branches are not changed.'],
            }),
            delete: form => ({
                tone: 'danger', title: 'Delete product permanently',
                subtitle: 'This cannot be undone from Trash.',
                confirm: 'Delete permanently', name: form.dataset.reviewProduct, meta: meta(form),
                sections: [{heading: 'Product in this branch', rows: [
                    ['Branch', form.dataset.reviewBranch || 'This branch'],
                    ['Stock retained in records', retained(form)],
                ]}],
                notes: ['Removed from Trash for ' + (form.dataset.reviewBranch || 'this branch') + ' only. Other branches are unaffected.',
                    'Its database record and stock history are retained.', 'You cannot restore it here afterward.'],
            }),
            'legacy-restore': form => ({
                tone: 'primary', title: 'Restore shared product',
                subtitle: 'This older deletion applied to every branch.',
                confirm: 'Restore shared product', name: form.dataset.reviewProduct, meta: meta(form),
                sections: [],
                notes: ['Restores the shared catalog record for all of its branches.', 'Review each branch\u2019s availability afterward.'],
            }),
            'legacy-delete': form => ({
                tone: 'danger', title: 'Delete older shared deletion permanently',
                subtitle: 'This cannot be undone from Trash.',
                confirm: 'Delete permanently', name: form.dataset.reviewProduct, meta: meta(form),
                sections: [],
                notes: ['Hides this older shared deletion from Trash.',
                    'Existing branch records and transaction history are retained.',
                    'You cannot restore the shared product here afterward.'],
            }),
            batch(form) {
                const was = {lot: form.dataset.originalLot || '', expiry: form.dataset.originalExpiry || ''};
                const now = {lot: text(form, 'batch_no'), expiry: text(form, 'expiration_date')};
                const change = (before, after, show) => before === after
                    ? show(after) + ' (unchanged)' : show(before) + ' \u2192 ' + show(after);
                return {
                    tone: 'primary', title: 'Review batch correction',
                    subtitle: 'Check the current and corrected details before saving.',
                    confirm: 'Confirm & save correction',
                    name: form.dataset.reviewProduct, meta: meta(form),
                    sections: [{heading: form.dataset.reviewBatch || 'Batch', rows: [
                        ['Batch / lot no.', change(was.lot, now.lot, v => v || 'Not provided')],
                        ['Expiration date', change(was.expiry, now.expiry, day)],
                        ['Reason for correction', text(form, 'reason')],
                    ]}],
                    notes: [],
                };
            },
        };

        function render(details) {
            titleEl.textContent = details.title;
            subtitleEl.textContent = details.subtitle;
            nameEl.textContent = details.name || 'Product';
            metaEl.textContent = details.meta || '';
            metaEl.hidden = !details.meta;
            dialog.dataset.tone = details.tone;
            confirmButton.textContent = details.confirm;
            confirmButton.className = 'btn ' + (details.tone === 'danger' ? 'btn-danger' : 'btn-primary');
            sectionsEl.replaceChildren();
            for (const part of details.sections) {
                const section = document.createElement('section');
                section.className = 'product-review-section';
                const heading = document.createElement('h3');
                heading.textContent = part.heading;
                const list = document.createElement('dl');
                list.className = 'product-review-fields';
                for (const [label, value] of part.rows) {
                    const pair = document.createElement('div'), term = document.createElement('dt'), def = document.createElement('dd');
                    term.textContent = label;
                    def.textContent = value;
                    pair.append(term, def);
                    list.append(pair);
                }
                section.append(heading, list);
                sectionsEl.append(section);
            }
            notesEl.replaceChildren(...details.notes.map(note => {
                const item = document.createElement('li');
                item.textContent = note;
                return item;
            }));
            notesWrap.hidden = details.notes.length === 0;
            body.scrollTop = 0;
        }

        const close = () => window.PharxmacoModal?.close(overlay);
        overlay.querySelectorAll('[data-par-close]').forEach(button => button.addEventListener('click', close));
        overlay.addEventListener('click', event => { if (event.target === overlay) close(); });

        confirmButton.addEventListener('click', () => {
            if (!pending || busy) return;
            const {form} = pending;
            pending = null;
            close();
            // The table can refresh its rows while the dialog is open; a detached form has nothing to submit.
            if (!form.isConnected) return;
            busy = true;
            confirmButton.disabled = true;
            confirmed.add(form);
            form.requestSubmit();
        });

        // Delegated so it also covers rows that the Products table replaces after filtering.
        document.addEventListener('submit', event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.matches('form[data-action-review]')) return;
            if (confirmed.has(form)) {
                confirmed.delete(form);
                // Another handler rejected the confirmed submit (for example validation): allow another try.
                if (event.defaultPrevented) { busy = false; confirmButton.disabled = false; }
                return;
            }
            if (event.defaultPrevented) return; // Validation or another guard already stopped it.
            if (busy) { event.preventDefault(); return; }
            const describe = describers[form.dataset.actionReview];
            if (!describe) return;
            if (!window.PharxmacoModal) {
                // Dialog helper unavailable: keep the old browser confirmation for destructive actions.
                if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) event.preventDefault();
                return;
            }
            event.preventDefault();
            render(describe(form));
            const trigger = event.submitter || form.querySelector('button[type="submit"]');
            pending = {form};
            window.PharxmacoModal.open(overlay, {className: 'open', trigger, initialFocus: back});
        });

        window.addEventListener('pageshow', () => {
            busy = false; pending = null; confirmButton.disabled = false;
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
