(function () {
    function format(value) {
        return '$ ' + value.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalc(form) {
        var insumos = 0;
        form.querySelectorAll('[data-resource-line="insumo"]').forEach(function (row) {
            var quantityInput = row.querySelector('[data-quantity]');
            var quantity = quantityInput ? parseFloat(quantityInput.value || '0') : 0;
            var unitCost = parseFloat(row.dataset.unitCost || '0');
            var subtotal = (isNaN(quantity) ? 0 : quantity) * (isNaN(unitCost) ? 0 : unitCost);
            var output = row.querySelector('[data-line-total]');
            if (output) {
                output.textContent = format(subtotal);
            }
            insumos += subtotal;
        });

        var trabajos = 0;
        form.querySelectorAll('[data-resource-line="trabajo"]').forEach(function (row) {
            var hoursInput = row.querySelector('[data-hours]');
            var costInput = row.querySelector('[data-hour-cost]');
            var hours = hoursInput ? parseFloat(hoursInput.value || '0') : 0;
            var rate = costInput ? parseFloat(costInput.value || '0') : 0;
            var subtotal = (isNaN(hours) ? 0 : hours) * (isNaN(rate) ? 0 : rate);
            var output = row.querySelector('[data-line-total]');
            if (output) {
                output.textContent = format(subtotal);
            }
            trabajos += subtotal;
        });

        var insumosOutput = form.querySelector('[data-total-insumos]');
        if (insumosOutput) {
            insumosOutput.textContent = format(insumos);
        }
        var trabajosOutput = form.querySelector('[data-total-trabajos]');
        if (trabajosOutput) {
            trabajosOutput.textContent = format(trabajos);
        }
        var totalOutput = form.querySelector('[data-total-general]');
        if (totalOutput) {
            totalOutput.textContent = format(insumos + trabajos);
        }
    }

    function syncResources(form) {
        if (form.dataset.resourcesLocked === '1') {
            return;
        }
        var start = form.querySelector('[data-start-task]');
        var enabled = !!start && start.checked;
        form.querySelectorAll('[data-resource-field]').forEach(function (element) {
            element.disabled = !enabled;
        });
        var hint = form.querySelector('[data-resources-hint]');
        if (hint) {
            hint.hidden = enabled;
        }
        recalc(form);
    }

    document.addEventListener('change', function (event) {
        var form = event.target.closest('[data-task-form]');
        if (!form) {
            return;
        }

        if (event.target.matches('[data-insumo-select]')) {
            var row = event.target.closest('[data-resource-line]');
            var option = event.target.options[event.target.selectedIndex];
            if (row && option) {
                row.dataset.unitCost = option.getAttribute('data-costo') || '0';
                var unitOutput = row.querySelector('[data-unit]');
                if (unitOutput) {
                    unitOutput.textContent = option.getAttribute('data-unidad') || '—';
                }
            }
        }

        if (event.target.matches('[data-start-task]')) {
            var complete = form.querySelector('[data-complete-task]');
            if (!event.target.checked && complete) {
                complete.checked = false;
            }
            syncResources(form);
        }

        if (event.target.matches('[data-complete-task]') && event.target.checked) {
            var start = form.querySelector('[data-start-task]');
            if (start && !start.disabled) {
                start.checked = true;
            }
            syncResources(form);
        }

        recalc(form);
    });

    document.addEventListener('input', function (event) {
        var form = event.target.closest('[data-task-form]');
        if (form) {
            recalc(form);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-task-form]').forEach(syncResources);
    });
})();
