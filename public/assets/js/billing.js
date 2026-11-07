(function () {
    'use strict';

    function selectedText(select, fallback) {
        if (!select || select.selectedIndex < 0) {
            return fallback;
        }

        return select.options[select.selectedIndex].textContent.trim() || fallback;
    }

    function formatDate(value) {
        var parts = (value || '').split('-');
        return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : value;
    }

    function syncSummary(form) {
        var type = form.querySelector('#id_tipo_comprobante');
        var point = form.querySelector('#id_punto_venta');
        var date = form.querySelector('#fecha');

        var typeLabel = selectedText(type, 'Comprobante');
        var pointLabel = selectedText(point, 'Punto de venta');
        var dateLabel = formatDate(date ? date.value : '');

        var typeOutput = form.closest('.panel').querySelector('[data-billing-summary-type]');
        var pointOutput = form.closest('.panel').querySelector('[data-billing-summary-point]');
        var dateOutput = form.closest('.panel').querySelector('[data-billing-summary-date]');
        if (typeOutput) {
            typeOutput.textContent = typeLabel;
        }
        if (pointOutput) {
            pointOutput.textContent = pointLabel;
        }
        if (dateOutput) {
            dateOutput.textContent = dateLabel;
        }

        form.setAttribute(
            'data-confirm',
            '¿Emitir ' + typeLabel + ' por ' + (form.dataset.total || '') + ' a ' + (form.dataset.clientName || 'el cliente') + '? Punto de venta ' + pointLabel + ', fecha ' + dateLabel + '. La operación fiscal es irreversible.'
        );
    }

    function activate() {
        document.querySelectorAll('[data-billing-form]').forEach(function (form) {
            syncSummary(form);
            form.addEventListener('change', function () {
                syncSummary(form);
            });
            form.addEventListener('submit', function () {
                syncSummary(form);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        activate();
    }
})();
