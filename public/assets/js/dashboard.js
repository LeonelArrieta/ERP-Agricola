(function () {
    'use strict';

    function syncPeriod(form) {
        var select = form.querySelector('#period');
        if (!select) {
            return;
        }

        var isCustom = select.value === 'custom';
        form.querySelectorAll('[data-period-custom]').forEach(function (field) {
            field.hidden = !isCustom;
            field.querySelectorAll('input').forEach(function (input) {
                input.required = isCustom;
                if (isCustom) {
                    input.setAttribute('aria-required', 'true');
                } else {
                    input.removeAttribute('aria-required');
                }
            });
        });
    }

    function activate() {
        document.querySelectorAll('[data-period-form]').forEach(function (form) {
            syncPeriod(form);
            form.addEventListener('change', function (event) {
                if (event.target && event.target.id === 'period') {
                    syncPeriod(form);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        activate();
    }
})();
