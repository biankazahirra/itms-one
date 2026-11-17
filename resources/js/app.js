// PR item rows: add, remove, and reindex submitted names.
(() => {
    const table = document.getElementById('items-table');
    const body = document.getElementById('items-body');
    const template = document.getElementById('item-row-template');
    const addButton = document.getElementById('add-item');

    if (!table || !body || !template || !addButton) {
        return;
    }

    const reindex = () => {
        const rows = [...body.querySelectorAll('[data-item-row]')];

        rows.forEach((row, index) => {
            row.querySelector('[data-row-number]').textContent = String(index + 1);
            row.querySelectorAll('[data-field]').forEach((input) => {
                input.name = `items[${index}][${input.dataset.field}]`;
                input.setAttribute('aria-label', `${input.dataset.field} baris ${index + 1}`);
            });
        });

        const removeButtons = body.querySelectorAll('[data-remove-item]');

        removeButtons.forEach((button) => {
            button.disabled = rows.length === 1;
            button.classList.toggle('opacity-40', rows.length === 1);
        });
    };

    addButton.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        body.appendChild(fragment);
        reindex();
        body.querySelector('[data-item-row]:last-child input')?.focus();
    });

    body.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-item]');

        if (!button || body.querySelectorAll('[data-item-row]').length === 1) {
            return;
        }

        button.closest('[data-item-row]').remove();
        reindex();
    });

    reindex();
})();
