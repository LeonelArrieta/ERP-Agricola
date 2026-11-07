document.addEventListener('change', function (event) {
    var select = event.target.closest('[data-worker-select]');
    if (!select) {
        return;
    }
    var scope = select.closest('tr') || select.closest('form');
    var target = scope ? scope.querySelector('[data-hour-cost]') : null;
    if (!target) {
        return;
    }
    var option = select.options[select.selectedIndex];
    var cost = option ? option.getAttribute('data-costo') : null;
    if (cost !== null && cost !== '') {
        target.value = cost;
    }
});
