(function () {
    function format(value) {
        return '$ ' + value.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function number(value) {
        var parsed = parseFloat(value);
        return isNaN(parsed) ? 0 : parsed;
    }

    function lotContext(row) {
        var select = row.querySelector('[data-lot-select]');
        var option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
        var hasLot = !!(option && option.value !== '');
        return {
            hasLot: hasLot,
            cost: hasLot ? number(option.getAttribute('data-costo')) : 0,
            rate: hasLot ? number(option.getAttribute('data-alicuota')) : 0,
            unit: hasLot ? option.getAttribute('data-unidad') || '' : ''
        };
    }

    function price(row) {
        var input = row.querySelector('[data-price]');
        return input ? number(input.value) : 0;
    }

    function quantity(row) {
        var input = row.querySelector('[data-quantity]');
        return input ? number(input.value) : 0;
    }

    function recalcLine(row) {
        var context = lotContext(row);
        var unitPrice = price(row);
        var qty = quantity(row);
        var finalUnit = unitPrice * (1 + context.rate / 100);
        var subtotal = qty * unitPrice;
        var total = qty * finalUnit;

        var unitOutput = row.querySelector('[data-unit]');
        if (unitOutput) {
            unitOutput.textContent = context.hasLot ? context.unit : '—';
        }
        var finalOutput = row.querySelector('[data-final-price]');
        if (finalOutput) {
            finalOutput.textContent = format(finalUnit);
        }
        var subtotalOutput = row.querySelector('[data-line-subtotal]');
        if (subtotalOutput) {
            subtotalOutput.textContent = format(subtotal);
        }
        var totalOutput = row.querySelector('[data-line-total]');
        if (totalOutput) {
            totalOutput.textContent = format(total);
        }
    }

    function recalcTotals(form) {
        var subtotal = 0;
        var total = 0;
        form.querySelectorAll('#sale-lines tr[data-line]').forEach(function (row) {
            var unitPrice = price(row);
            var qty = quantity(row);
            var rate = lotContext(row).rate;
            subtotal += qty * unitPrice;
            total += qty * unitPrice * (1 + rate / 100);
        });
        var subtotalOutput = form.querySelector('[data-sale-subtotal]');
        if (subtotalOutput) {
            subtotalOutput.textContent = format(subtotal);
        }
        var taxOutput = form.querySelector('[data-sale-tax]');
        if (taxOutput) {
            taxOutput.textContent = format(total - subtotal);
        }
        var totalOutput = form.querySelector('[data-sale-total]');
        if (totalOutput) {
            totalOutput.textContent = format(total);
        }
    }

    function priceFromMargin(row) {
        var context = lotContext(row);
        var marginInput = row.querySelector('[data-margin]');
        var priceInput = row.querySelector('[data-price]');
        var margin = marginInput ? number(marginInput.value) / 100 : 0;
        if (context.hasLot && priceInput && margin >= 0 && margin < 1) {
            priceInput.value = (context.cost / (1 - margin)).toFixed(2);
        }
        recalcLine(row);
    }

    function marginFromPrice(row) {
        var context = lotContext(row);
        var priceInput = row.querySelector('[data-price]');
        var marginInput = row.querySelector('[data-margin]');
        var unitPrice = priceInput ? number(priceInput.value) : 0;
        if (context.hasLot && marginInput && unitPrice > 0) {
            var margin = 1 - (context.cost / unitPrice);
            if (margin >= 0 && margin < 1) {
                marginInput.value = (margin * 100).toFixed(2);
            }
        }
        recalcLine(row);
    }

    function syncRow(row) {
        var priceInput = row.querySelector('[data-price]');
        if (priceInput && priceInput.value === '') {
            priceFromMargin(row);
        } else {
            recalcLine(row);
        }
    }

    function syncProvisional(form) {
        var hasProvisional = false;
        form.querySelectorAll('#sale-lines [data-lot-select]').forEach(function (select) {
            var option = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
            if (option && option.value !== '' && option.getAttribute('data-provisional') === '1') {
                hasProvisional = true;
            }
        });
        var acceptance = form.querySelector('[data-provisional-acceptance]');
        if (acceptance) {
            acceptance.hidden = !hasProvisional;
            if (!hasProvisional) {
                var checkbox = acceptance.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
        }
        var note = form.querySelector('[data-provisional-note]');
        if (note) {
            note.hidden = !hasProvisional;
        }
    }

    function rowFrom(event) {
        return event.target.closest('#sale-lines tr[data-line]');
    }

    function refresh(form) {
        form.querySelectorAll('#sale-lines tr[data-line]').forEach(recalcLine);
        recalcTotals(form);
    }

    document.addEventListener('change', function (event) {
        var row = rowFrom(event);
        if (!row) {
            return;
        }
        var form = row.closest('form');
        if (event.target.matches('[data-lot-select]')) {
            priceFromMargin(row);
            syncProvisional(form);
        } else if (event.target.matches('[data-margin]')) {
            priceFromMargin(row);
        } else if (event.target.matches('[data-price]')) {
            marginFromPrice(row);
        }
        recalcTotals(form);
    });

    document.addEventListener('input', function (event) {
        var row = rowFrom(event);
        if (!row) {
            return;
        }
        var form = row.closest('form');
        if (event.target.matches('[data-margin]')) {
            priceFromMargin(row);
        } else if (event.target.matches('[data-price]')) {
            marginFromPrice(row);
        } else if (event.target.matches('[data-quantity]')) {
            recalcLine(row);
        }
        recalcTotals(form);
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#sale-lines tr[data-line]').forEach(syncRow);
        document.querySelectorAll('#sale-lines').forEach(function (list) {
            var form = list.closest('form');
            if (form) {
                syncProvisional(form);
                recalcTotals(form);
            }
        });
    });
})();
