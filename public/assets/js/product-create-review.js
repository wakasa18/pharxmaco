/* Review the current form values before the normal product POST. */
(() => {
    'use strict';
    const form = document.getElementById('product-create-form');
    const overlay = document.getElementById('product-create-review');
    const modal = window.PharxmacoModal;
    if (!form || !overlay || !modal) return;

    // Keep fixed positioning independent of the editor's layout and animation.
    document.body.appendChild(overlay);
    const save = document.getElementById('product-review-save');
    const edit = document.getElementById('product-review-edit');
    const trigger = form.querySelector('button[type="submit"]');
    const originalTrigger = trigger.innerHTML;
    const currency = new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'});
    let reviewed = null;
    let saving = false;
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-controls', overlay.id);

    const signature = () => JSON.stringify([...new FormData(form).entries()]);
    const value = name => {
        const field = form.elements.namedItem(name);
        const raw = (field?.value || '').trim();
        if (name === 'branch_id' && form.dataset.reviewBranch) return form.dataset.reviewBranch;
        if (field?.tagName === 'SELECT') return raw ? field.selectedOptions[0].textContent.trim() : ({
            medicine_type: 'Not specified', senior_pwd_eligible: 'Not confirmed',
            tax_treatment: 'Not specified', supplier_id: 'Not provided',
        }[name] || 'Not provided');
        if (name === 'sku' && !raw) return 'Not assigned';
        if (!raw) return 'Not provided';
        if (name === 'cost_price' || name === 'price') return currency.format(Number(raw));
        if (name === 'stock' || name === 'reorder_level') {
            const unit = form.elements.namedItem('unit').value.trim();
            return Number(raw).toLocaleString('en-PH') + (unit ? ' ' + unit : '');
        }
        return raw;
    };
    const close = () => modal.close(overlay);
    overlay.querySelectorAll('[data-product-review-close]').forEach(button => button.addEventListener('click', close));
    overlay.addEventListener('click', event => { if (event.target === overlay) close(); });

    form.addEventListener('submit', event => {
        if (saving) { event.preventDefault(); return; }
        if (event.submitter === save && overlay.classList.contains('open') && reviewed === signature()) {
            saving = true;
            close();
            // Keep native validation, CSRF and the original form payload intact.
            window.setTimeout(() => { save.disabled = true; }, 0);
            return;
        }
        event.preventDefault();
        if (!form.reportValidity()) return;
        overlay.querySelectorAll('[data-product-review]').forEach(element => {
            element.textContent = value(element.dataset.productReview);
        });
        reviewed = signature();
        overlay.querySelector('.product-review-body').scrollTop = 0;
        modal.open(overlay, {className: 'open', trigger, initialFocus: edit});
    });
    window.addEventListener('pageshow', () => {
        saving = false;
        reviewed = null;
        save.disabled = false;
        trigger.disabled = false;
        trigger.innerHTML = originalTrigger;
    });
})();
