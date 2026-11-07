(function () {
    function sync(form) {
        var select = form.querySelector('[data-harvest-product]');
        var output = form.querySelector('[data-harvest-unit]');
        if (!select || !output) {
            return;
        }
        var option = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
        output.value = option && option.value !== '' ? (option.getAttribute('data-unidad') || '') : '';
    }

    document.addEventListener('change', function (event) {
        var select = event.target.closest('[data-harvest-product]');
        if (select) {
            sync(select.closest('form'));
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-harvest-product]').forEach(function (select) {
            sync(select.closest('form'));
        });
    });
})();
