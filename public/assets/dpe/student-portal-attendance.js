(function () {
    'use strict';
    var form = document.querySelector('[data-portal-attendance-months]');
    if (!form) return;
    var inputs = Array.from(form.querySelectorAll('[data-attendance-month]'));

    function validateMonths() {
        inputs.forEach(function (input) { input.setCustomValidity(''); });
        inputs.forEach(function (input, index) {
            if (input.value && inputs.some(function (other, otherIndex) {
                return otherIndex !== index && other.value === input.value;
            })) input.setCustomValidity('Pilih tiga bulan yang berbeda.');
        });
        return form.reportValidity();
    }

    form.addEventListener('change', function (event) {
        if (inputs.includes(event.target) && validateMonths()) form.requestSubmit();
    });
    form.addEventListener('submit', function (event) {
        if (!validateMonths()) event.preventDefault();
    });
}());
