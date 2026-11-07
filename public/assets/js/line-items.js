document.addEventListener('click', function (event) {
    var addButton = event.target.closest('[data-add-line]');
    if (addButton) {
        event.preventDefault();
        var container = document.querySelector(addButton.getAttribute('data-target'));
        var template = document.querySelector(addButton.getAttribute('data-template'));
        if (!container || !template) {
            return;
        }
        var index = nextLineIndex(container);
        var html = template.innerHTML.replace(/__INDEX__/g, String(index));
        var holder = document.createElement('tbody');
        holder.innerHTML = html;
        var newLine = holder.firstElementChild;
        if (newLine) {
            container.appendChild(newLine);
            markLockedRemovals(newLine);
            updateLineActions(container);
            var focusTarget = newLine.querySelector('select, input');
            if (focusTarget) {
                focusTarget.focus();
            }
        }
        return;
    }

    var removeButton = event.target.closest('[data-remove-line]');
    if (removeButton) {
        event.preventDefault();
        var line = removeButton.closest('[data-line]');
        var container = line ? line.parentElement : null;
        if (!line || !container) {
            return;
        }
        if (container.querySelectorAll('[data-line]').length <= 1) {
            return;
        }
        line.remove();
        updateLineActions(container);
        var add = addButtonFor(container);
        if (add) {
            add.focus();
        }
    }
});

function nextLineIndex(container) {
    if (!container.dataset.nextLineIndex) {
        container.dataset.nextLineIndex = String(maxLineIndex(container) + 1);
    }
    var index = parseInt(container.dataset.nextLineIndex, 10);
    if (isNaN(index) || index < 0) {
        index = maxLineIndex(container) + 1;
    }
    container.dataset.nextLineIndex = String(index + 1);

    return index;
}

function maxLineIndex(container) {
    var max = -1;
    container.querySelectorAll('[data-line] [name]').forEach(function (control) {
        var match = /\[(\d+)\]/.exec(control.getAttribute('name') || '');
        if (match) {
            max = Math.max(max, parseInt(match[1], 10));
        }
    });

    return max;
}

function markLockedRemovals(root) {
    root.querySelectorAll('[data-remove-line][data-resource-field][disabled]').forEach(function (remove) {
        remove.dataset.lineLocked = '1';
    });
}

function addButtonFor(container) {
    var form = container.closest('form');
    var selector = '[data-add-line][data-target="#' + container.id + '"]';
    var add = form ? form.querySelector(selector) : null;

    return add || (form ? form.querySelector('[data-add-line]') : null) || document.querySelector('[data-add-line]');
}

function updateLineActions(container) {
    var lines = container.querySelectorAll('[data-line]');
    lines.forEach(function (line, index) {
        var remove = line.querySelector('[data-remove-line]');
        if (remove && remove.dataset.lineLocked !== '1') {
            remove.setAttribute('aria-label', 'Quitar línea ' + (index + 1));
            remove.disabled = lines.length <= 1;
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-line]').forEach(function (line) {
        var container = line.parentElement;
        if (!container || container.dataset.linesReady === '1') {
            return;
        }
        container.dataset.linesReady = '1';
        container.dataset.nextLineIndex = String(maxLineIndex(container) + 1);
        markLockedRemovals(container);
        updateLineActions(container);
    });
});
