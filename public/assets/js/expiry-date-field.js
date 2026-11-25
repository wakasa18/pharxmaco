(() => {
    'use strict';
    document.querySelectorAll('[data-expiry-date-control]').forEach(control => {
        if (control.dataset.expiryReady) return;
        control.dataset.expiryReady = '1';
        const date = control.querySelector('[data-expiry-input]');
        const update = () => {
            const unchangedLegacy = Boolean(control.dataset.original) && date.value === control.dataset.original
                && (date.value < control.dataset.minimum || date.value > control.dataset.maximum);
            if (unchangedLegacy) {
                date.removeAttribute('min');
                date.removeAttribute('max');
            } else {
                date.min = control.dataset.minimum;
                date.max = control.dataset.maximum;
            }
        };
        date.addEventListener('input', update);
        date.addEventListener('change', update);
        date.form?.addEventListener('reset', () => setTimeout(update, 0));
        update();
    });
})();
