/* Review saved values versus the proposed payload; native validation and POST remain authoritative. */
(() => {
    const start = () => document.querySelectorAll('form[data-submit-review]').forEach(form => {
        const overlay = document.getElementById(form.id + '-review');
        const originalNode = document.getElementById(form.id + '-review-original');
        const modal = window.PharxmacoModal;
        if (!overlay || !originalNode || !modal) return;
        const original = JSON.parse(originalNode.textContent);
        const editMode = form.dataset.reviewMode === 'edit';
        const product = form.dataset.submitReview === 'product';
        const trigger = form.querySelector('button[type="submit"]');
        const confirm = overlay.querySelector('[data-review-confirm]');
        const back = overlay.querySelector('[data-review-back]');
        const rows = overlay.querySelector('[data-review-rows]');
        const note = overlay.querySelector('[data-review-note]');
        const originalLabel = trigger.innerHTML;
        let reviewed = null, saving = false;
        const money = new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP'});
        const productFields = [
            ['product_name','Product name'],['sku','SKU'],['category_id','Category'],['unit','Selling unit'],
            ['medicine_type','Medicine type'],['generic_name','Generic medicine name'],['strength','Strength'],
            ['tax_treatment','Tax classification'],['senior_pwd_eligible','Senior Citizen/PWD eligibility'],
            ['cost_price','Cost price per unit','money'],['price','Selling price per unit','money'],
            ['stock','Stock quantity','quantity'],['reorder_level','Reorder level','quantity'],
            ['expiration_date','Expiration date'],['status','Branch availability'],['manufacturer','Manufacturer'],['supplier_id','Supplier'],
        ];
        const discountFields = [
            ['discount_name','Discount name'],['description','Description'],['status','Cashier availability'],
            ['discount_type','Discount type'],['discount_value','Discount value','discount'],['applies_to','Products included'],
            ['category_id','Category','category'],['product_id','Product','product'],['minimum_purchase','Minimum matching purchase','minimum'],
            ['max_discount_amount','Maximum peso discount','maximum'],['start_date','Start date'],['end_date','End date'],
        ];
        const fields = product ? productFields : discountFields;
        const input = name => form.elements.namedItem(name);
        const current = () => Object.fromEntries(fields.map(([name]) => [name, String(input(name)?.value ?? '').trim()]));
        const applicable = (type, state) => type === 'category' ? state.applies_to === 'category'
            : type === 'product' ? state.applies_to === 'product' : type === 'maximum' ? state.discount_type === 'percentage' : true;
        const normalized = ([name, label, type], state) => {
            if (!applicable(type, state)) return 'not-applicable';
            const raw = String(state[name] ?? '').trim();
            if (['money','quantity','minimum','maximum','discount'].includes(type)) {
                if (!raw && type === 'maximum') return '';
                return (type === 'discount' ? state.discount_type + ':' : '') + Number(raw || 0).toFixed(type === 'quantity' ? 0 : 2);
            }
            return raw;
        };
        const display = ([name, label, type], state) => {
            if (!applicable(type, state)) return 'Not applicable';
            const raw = String(state[name] ?? '').trim();
            if (type === 'money' || type === 'minimum') return money.format(Number(raw || 0));
            if (type === 'maximum') return raw ? money.format(Number(raw)) : 'No maximum';
            if (type === 'quantity') return Number(raw || 0).toLocaleString('en-PH');
            if (type === 'discount') return state.discount_type === 'percentage' ? Number(raw || 0).toLocaleString('en-PH') + '%' : money.format(Number(raw || 0));
            const field = input(name);
            if (field?.tagName === 'SELECT') {
                const option = [...field.options].find(option => option.value === raw);
                return option?.textContent.replace(/\s+/g, ' ').trim() || (raw ? 'Unavailable (ID ' + raw + ')' : 'Not specified');
            }
            if (name === 'start_date' && !raw) return 'Immediately';
            if (name === 'end_date' && !raw) return 'No end date';
            return raw || 'Not provided';
        };
        const signature = () => JSON.stringify([...new FormData(form).entries()]);
        const close = () => modal.close(overlay);
        const updateStockReason = () => {
            if (!product) return;
            const reason = input('stock_reason');
            const changed = Number(input('stock').value) !== Number(original.stock || 0);
            reason.required = changed;
            reason.setCustomValidity(changed && reason.value.trim().length < 3 ? 'Enter a short reason for changing the stock quantity.' : '');
        };
        if (product) {
            input('stock').addEventListener('input', updateStockReason);
            input('stock_reason').addEventListener('input', updateStockReason);
            input('stock').addEventListener('change', updateStockReason);
            input('stock_reason').addEventListener('change', updateStockReason);
            updateStockReason();
        }
        function render() {
            const state = current();
            const changed = fields.filter(field => !editMode || normalized(field, original) !== normalized(field, state));
            rows.replaceChildren();
            for (const field of changed) {
                if (!editMode && !applicable(field[2], state)) continue;
                const row = document.createElement('section');
                row.className = 'submit-review-change';
                row.dataset.reviewField = field[0];
                if (!editMode) row.dataset.reviewNew = '1';
                const heading = document.createElement('h3');
                heading.textContent = field[1];
                const values = document.createElement('dl');
                for (const [title, source] of editMode ? [['Current value', original], ['New value', state]] : [['Value to save', state]]) {
                    const pair = document.createElement('div'), label = document.createElement('dt'), value = document.createElement('dd');
                    label.textContent = title;
                    value.textContent = display(field, source);
                    pair.append(label, value);
                    values.append(pair);
                }
                row.append(heading, values);
                rows.append(row);
            }
            overlay.querySelector('[data-review-count]').textContent = editMode
                ? changed.length + (changed.length === 1 ? ' change to review' : ' changes to review') : 'Discount details';
            overlay.querySelector('[data-review-empty]').hidden = !editMode || changed.length > 0;
            confirm.disabled = editMode && changed.length === 0;
            note.textContent = product
                ? (changed.some(field => field[0] === 'stock') ? 'Reason for stock change: ' + input('stock_reason').value.trim() : '')
                : (state.applies_to === 'all' ? 'Cashiers select this offer from the Discount dropdown.'
                    : 'This offer applies automatically to matching ' + (state.applies_to === 'product' ? 'product' : 'category') + ' lines when its dates and purchase requirements are met.');
            if (!product && state.status === 'inactive') note.textContent += '\nSaved as unavailable to cashiers until activated.';
            note.hidden = !note.textContent;
            reviewed = signature();
        }
        document.body.appendChild(overlay);
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-controls', overlay.id);
        overlay.querySelectorAll('[data-submit-review-close]').forEach(button => button.addEventListener('click', close));
        overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
        form.addEventListener('submit', event => {
            if (saving) { event.preventDefault(); return; }
            updateStockReason();
            if (event.submitter === confirm && overlay.classList.contains('open') && !confirm.disabled && reviewed === signature() && form.checkValidity()) {
                saving = true;
                close();
                window.setTimeout(() => { confirm.disabled = true; }, 0);
                return;
            }
            event.preventDefault();
            if (!form.reportValidity()) return;
            render();
            overlay.querySelector('.product-review-body').scrollTop = 0;
            modal.open(overlay, {className:'open', trigger, initialFocus:back});
        });
        window.addEventListener('pageshow', () => {
            saving = false; reviewed = null; confirm.disabled = false; trigger.disabled = false; trigger.innerHTML = originalLabel;
        });
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
