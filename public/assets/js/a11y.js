(function () {
    'use strict';

    function markScrollableRegions() {
        document.querySelectorAll('.table-wrap').forEach(function (region) {
            if (region.scrollWidth > region.clientWidth) {
                region.setAttribute('tabindex', '0');
                region.setAttribute('role', 'region');
                region.classList.add('is-scrollable');
                if (!region.getAttribute('aria-label')) {
                    region.setAttribute('aria-label', 'Tabla con desplazamiento horizontal');
                }
            } else {
                region.removeAttribute('tabindex');
                region.removeAttribute('role');
                region.classList.remove('is-scrollable');
            }
        });
    }

    function activate() {
        markScrollableRegions();
        window.addEventListener('resize', markScrollableRegions);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        activate();
    }
})();
