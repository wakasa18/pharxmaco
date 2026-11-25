/* Use the same character definitions as the management controllers. */
(() => {
    'use strict';
    const config = document.getElementById('management-input-rules');
    if (!config) return;
    const definitions = JSON.parse(config.textContent);
    const types = {
        product_name: 'catalog', generic_name: 'catalog', strength: 'catalog', unit: 'catalog', manufacturer: 'catalog',
        category_name: 'catalog', supplier_name: 'catalog', discount_name: 'catalog', branch_name: 'catalog',
        full_name: 'person', contact_person: 'person', sku: 'code', branch_code: 'code', username: 'username',
        contact_number: 'phone', address: 'address', cost_price: 'money', price: 'money',
        discount_value: 'money', minimum_purchase: 'money', max_discount_amount: 'money',
        stock: 'quantity', reorder_level: 'quantity',
    };
    let serial = 0;
    document.querySelectorAll('form[data-management-form]').forEach(form => {
        const fields = [...form.elements].filter(field => types[field.name] && !field.disabled);
        const validators = fields.map(field => {
            const type = types[field.name];
            const definition = definitions[type];
            const forbidden = definition.forbidden && new RegExp(definition.forbidden, 'u');
            const content = definition.content && new RegExp(definition.content, 'u');
            const pattern = definition.pattern && new RegExp(definition.pattern, 'u');
            const notice = document.createElement('span');
            notice.className = 'form-help';
            notice.id = 'management-field-error-' + (++serial);
            notice.style.color = 'var(--danger)';
            notice.hidden = true;
            notice.style.display = 'none';
            notice.setAttribute('aria-live', 'polite');
            field.after(notice);
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), notice.id].filter(Boolean).join(' '));
            let touched = false;
            const validate = (show = false) => {
                const value = field.value.trim();
                let error = '';
                if (!value) {
                    if (field.required && field.value) error = 'Enter a value; spaces alone are not allowed.';
                } else if ((pattern && !pattern.test(value)) || (forbidden && forbidden.test(value)) || (content && !content.test(value))
                    || (definition.max !== undefined && Number(value) > definition.max)) {
                    error = definition.message;
                } else if (definition.phone) {
                    const digits = value.replace(/\D/g, '');
                    if (digits.length < 7 || digits.length > 15 || (value.match(/\+/g) || []).length > 1 || (value.includes('+') && !value.startsWith('+'))) error = definition.message;
                }
                field.setCustomValidity(error);
                if (show) touched = true;
                notice.textContent = touched && !field.validity.valid ? field.validationMessage : '';
                notice.hidden = !notice.textContent;
                notice.style.display = notice.hidden ? 'none' : 'block';
                if (touched) field.setAttribute('aria-invalid', String(!field.validity.valid));
                return field.validity.valid;
            };
            field.addEventListener('input', () => validate());
            field.addEventListener('change', () => validate());
            field.addEventListener('blur', () => validate(true));
            field.addEventListener('invalid', () => validate(true));
            validate();
            return {field, validate};
        });
        form.addEventListener('submit', event => {
            const invalid = validators.filter(({validate}) => !validate(true));
            if (!invalid.length) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            invalid[0].field.reportValidity();
        }, true);
        window.addEventListener('pageshow', () => validators.forEach(({validate}) => validate()));
    });
})();
