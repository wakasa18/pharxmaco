(() => {
    'use strict';
    const config = document.getElementById('payment-reference-rules');
    if (!config) return;
    const rules = JSON.parse(config.textContent);
    const error = (method, value, required = true) => {
        const rule = rules[method];
        if (!rule) return 'Select a supported payment method.';
        if (method === 'cash') return '';
        const text = String(value ?? '').trim();
        if (!text && !required) return '';
        return new RegExp('^(?:' + rule.pattern + ')$').test(text) ? '' : rule.error;
    };
    const apply = (input, method, required = true, keepOriginal = false) => {
        if (!input) return false;
        const rule = rules[method] || rules.cash;
        input.required = method !== 'cash' && required && !keepOriginal;
        input.maxLength = keepOriginal ? 100 : rule.maximum;
        input.minLength = keepOriginal ? 0 : rule.minimum;
        input.inputMode = rule.inputmode;
        input.placeholder = rule.placeholder;
        if (rule.pattern && !keepOriginal) input.pattern = rule.pattern;
        else input.removeAttribute('pattern');
        const message = keepOriginal ? '' : error(method, input.value, required);
        input.setCustomValidity(message);
        const help = document.getElementById(input.dataset.referenceHelp || '');
        if (help) help.textContent = rule.help;
        const notice = document.getElementById(input.dataset.referenceError || '');
        if (notice) {
            notice.textContent = message;
            notice.hidden = !message || !input.value.trim();
        }
        return !message;
    };
    window.PharxmacoPaymentReference = {rules, error, apply};
    const start = () => document.querySelectorAll('[data-payment-reference-method]').forEach(input => {
        const method = document.getElementById(input.dataset.paymentReferenceMethod);
        if (!method) return;
        const update = () => {
            const original = input.hasAttribute('data-reference-original-method')
                && method.value === input.dataset.referenceOriginalMethod
                && input.value.trim() === input.dataset.referenceOriginalValue;
            apply(input, method.value, input.dataset.referenceOptional !== '1', original);
        };
        input.addEventListener('input', update);
        input.addEventListener('blur', () => { input.value = input.value.trim(); update(); });
        method.addEventListener('change', update);
        input.form?.addEventListener('reset', () => setTimeout(update, 0));
        update();
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
