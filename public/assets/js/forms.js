(function () {
    'use strict';

    function wireField(field) {
        var control = field.querySelector('input:not([type="hidden"]), select, textarea');
        if (!control) {
            return;
        }

        var described = [];
        field.querySelectorAll('.hint, .field-error').forEach(function (node, index) {
            if (!node.id) {
                node.id = (control.id || 'field') + '-desc-' + index;
            }
            if (node.classList.contains('field-error')) {
                node.setAttribute('role', 'alert');
                control.setAttribute('aria-invalid', 'true');
            }
            described.push(node.id);
        });

        if (described.length > 0 && !control.getAttribute('aria-describedby')) {
            control.setAttribute('aria-describedby', described.join(' '));
        }
    }

    function focusFirstError() {
        if (document.querySelector('dialog[open]')) {
            return;
        }

        var summary = document.querySelector('.form-error-summary');
        if (summary) {
            summary.setAttribute('tabindex', '-1');
            summary.focus();
            return;
        }

        var control = document.querySelector('.field-error')
            ? document.querySelector('.field-error').closest('.field')?.querySelector('input:not([type="hidden"]), select, textarea')
            : document.querySelector('[aria-invalid="true"]');

        if (control && typeof control.focus === 'function') {
            control.focus();
        }
    }

    function restoreSubmit(form) {
        form.dataset.submitting = '0';
        form.querySelectorAll('[data-submit-disabled]').forEach(function (button) {
            button.disabled = false;
            button.removeAttribute('data-submit-disabled');
            button.removeAttribute('aria-disabled');
        });
    }

    function enhanceForm(form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented || form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }

            if (form.hasAttribute('data-confirm')) {
                return;
            }

            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (button) {
                button.dataset.submitDisabled = '1';
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });
        });
    }

    function activate() {
        document.querySelectorAll('.field').forEach(wireField);
        document.querySelectorAll('form').forEach(enhanceForm);
        focusFirstError();

        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form[data-submitting="1"]').forEach(restoreSubmit);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        activate();
    }
})();
