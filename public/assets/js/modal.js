(function () {
    'use strict';

    var openers = new WeakMap();

    function focusFirst(dialog) {
        var target = dialog.querySelector('[autofocus], button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (target) {
            target.focus();
        } else {
            dialog.focus();
        }
    }

    function open(dialog) {
        if (dialog.open) {
            return;
        }

        openers.set(dialog, document.activeElement);

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
            dialog.setAttribute('aria-modal', 'true');
        }

        window.requestAnimationFrame(function () {
            focusFirst(dialog);
        });
    }

    function close(dialog) {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
            dialog.removeAttribute('aria-modal');
        }

        var opener = openers.get(dialog);
        if (opener && typeof opener.focus === 'function' && document.contains(opener)) {
            opener.focus();
        }
    }

    function closeOnBackdrop(dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                close(dialog);
            }
        });
    }

    function activate() {
        document.querySelectorAll('dialog[data-modal]').forEach(function (dialog) {
            if (dialog.open) {
                window.requestAnimationFrame(function () {
                    focusFirst(dialog);
                });
            } else {
                open(dialog);
            }

            dialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    close(dialog);
                });
            });

            dialog.addEventListener('cancel', function () {
                close(dialog);
            });

            closeOnBackdrop(dialog);
        });

        document.querySelectorAll('[data-history-back]').forEach(function (element) {
            element.addEventListener('click', function (event) {
                event.preventDefault();
                window.history.back();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        activate();
    }
})();
