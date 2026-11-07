document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    var message = form.getAttribute('data-confirm');
    if (!message) {
        return;
    }

    if (form.dataset.confirmed === '1') {
        form.removeAttribute('data-confirmed');
        lockSubmit(form);
        return;
    }

    event.preventDefault();

    if (typeof HTMLDialogElement === 'undefined' || typeof document.createElement('dialog').showModal !== 'function') {
        if (window.confirm(message)) {
            form.dataset.confirmed = '1';
            form.submit();
        }
        return;
    }

    var submitter = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
    var dialog = document.createElement('dialog');
    dialog.className = 'modal';
    dialog.setAttribute('role', 'alertdialog');
    dialog.setAttribute('aria-labelledby', 'confirm-title');
    dialog.setAttribute('aria-describedby', 'confirm-message');

    var body = document.createElement('div');
    body.className = 'modal-body';

    var eyebrow = document.createElement('p');
    eyebrow.className = 'modal-eyebrow';
    eyebrow.textContent = 'Confirmar';

    var title = document.createElement('h2');
    title.id = 'confirm-title';
    title.textContent = 'Confirmar operación';

    var text = document.createElement('p');
    text.className = 'modal-message';
    text.id = 'confirm-message';
    text.textContent = message;

    var actions = document.createElement('div');
    actions.className = 'modal-actions';

    var cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'button ghost';
    cancel.textContent = 'Cancelar';

    var confirm = document.createElement('button');
    confirm.type = 'button';
    confirm.className = 'danger';
    confirm.textContent = 'Continuar';

    actions.append(cancel, confirm);
    body.append(eyebrow, title, text, actions);
    dialog.append(body);
    document.body.append(dialog);

    function cleanup() {
        if (dialog.open) {
            dialog.close();
        }
        dialog.remove();
        if (submitter && typeof submitter.focus === 'function' && document.contains(submitter)) {
            submitter.focus();
        }
    }

    cancel.addEventListener('click', cleanup);
    confirm.addEventListener('click', function () {
        form.dataset.confirmed = '1';
        dialog.close();
        dialog.remove();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter);
        } else {
            form.submit();
        }
    });
    dialog.addEventListener('click', function (clickEvent) {
        if (clickEvent.target === dialog) {
            cleanup();
        }
    });
    dialog.addEventListener('cancel', cleanup);
    dialog.showModal();
    cancel.focus();
});

function lockSubmit(form) {
    form.dataset.submitting = '1';
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach(function (button) {
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
    });
}
