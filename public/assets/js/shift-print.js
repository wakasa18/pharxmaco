(() => {
    'use strict';
    document.querySelectorAll('button[data-print-mode]').forEach(button => {
        button.addEventListener('click', () => {
            document.body.dataset.printMode = button.dataset.printMode;
            window.print();
        });
    });
    window.addEventListener('afterprint', () => { document.body.dataset.printMode = 'summary'; });
})();
