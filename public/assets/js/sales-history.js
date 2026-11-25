(function () {
    'use strict';
    var form = document.getElementById('sales-history-range');
    if (!form) { return; }
    var from = form.elements.namedItem('date_from');
    var to = form.elements.namedItem('date_to');
    function validateRange() {
        to.setCustomValidity(from.value && to.value && from.value > to.value
            ? 'Sale date to must be on or after sale date from.' : '');
    }
    [from, to].forEach(function (input) {
        input.addEventListener('input', validateRange);
        input.addEventListener('change', validateRange);
    });
    form.addEventListener('submit', function (event) {
        validateRange();
        if (!form.reportValidity()) { event.preventDefault(); }
    });
    validateRange();
})();
