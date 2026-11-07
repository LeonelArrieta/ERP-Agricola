(function () {
    'use strict';

    function activate() {
        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.print();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        activate();
    }
})();
