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

// RAB item rows: add, remove, reindex, line totals, create-mode toggle, quick unit add.
(() => {
    const table = document.getElementById('rab-items-table');
    const body = document.getElementById('rab-items-body');
    const template = document.getElementById('rab-item-row-template');
    const addButton = document.getElementById('add-rab-item');

    if (table && body && template && addButton) {
        const grandTotalEl = document.getElementById('rab-grand-total');

        const numberFromValue = (value) => {
            const parsed = Number(value);

            return Number.isFinite(parsed) ? parsed : 0;
        };

        const formatRupiah = (value) => `Rp ${value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        const recalc = () => {
            let total = 0;

            body.querySelectorAll('[data-item-row]').forEach((row) => {
                const quantity = numberFromValue(row.querySelector('[data-field="quantity"]')?.value);
                const price = numberFromValue(row.querySelector('[data-field="unit_price"]')?.value);
                const lineTotal = quantity * price;
                const cell = row.querySelector('[data-line-total]');

                total += lineTotal;

                if (cell) {
                    cell.textContent = lineTotal === 0 && (quantity === 0 || price === 0) ? '—' : formatRupiah(lineTotal);
                }
            });

            if (grandTotalEl) {
                grandTotalEl.textContent = formatRupiah(total);
            }
        };

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

            recalc();
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

        body.addEventListener('input', (event) => {
            if (event.target.matches('[data-field="quantity"], [data-field="unit_price"]')) {
                recalc();
            }
        });

        reindex();
    }

    const modeRadios = document.querySelectorAll('input[name="creation_mode"]');

    if (modeRadios.length > 0) {
        const standaloneFields = document.getElementById('standalone-fields');
        const importFields = document.getElementById('import-fields');
        const itemsSection = document.getElementById('items-section');
        const prSelect = document.getElementById('purchase_request_id');

        const setMode = (mode) => {
            const isImport = mode === 'import';

            standaloneFields?.classList.toggle('hidden', isImport);
            importFields?.classList.toggle('hidden', !isImport);
            itemsSection?.classList.toggle('hidden', isImport);

            if (prSelect) {
                prSelect.disabled = !isImport;
            }

            itemsSection?.querySelectorAll('input, select').forEach((el) => {
                el.disabled = isImport;
            });

            standaloneFields?.querySelectorAll('input, select').forEach((el) => {
                if (el.id === 'location') {
                    return;
                }

                el.disabled = isImport;
            });

            document.querySelectorAll('[data-mode-option]').forEach((label) => {
                const active = label.dataset.modeOption === mode;

                label.classList.toggle('border-primary', active);
                label.classList.toggle('bg-primary/5', active);
                label.classList.toggle('text-primary', active);
            });
        };

        modeRadios.forEach((radio) => radio.addEventListener('change', () => setMode(radio.value)));
        setMode(document.querySelector('input[name="creation_mode"]:checked')?.value ?? 'standalone');
    }

    const quickAddButton = document.getElementById('quick-add-unit-button');

    if (quickAddButton) {
        const input = document.getElementById('quick_add_unit_name');
        const csrf = document.getElementById('quick_add_csrf')?.value ?? '';

        quickAddButton.addEventListener('click', async () => {
            const unitName = input.value.trim();

            if (unitName === '') {
                return;
            }

            try {
                const response = await fetch('/master/units/quick-add', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ unit_name: unitName }).toString(),
                });

                const payload = await response.json();

                document.querySelectorAll('#rab-items-table select[data-field="unit_id"], #rab-item-row-template select[data-field="unit_id"]').forEach((select) => {
                    if ([...select.options].some((option) => String(option.value) === String(payload.id))) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = String(payload.id);
                    option.textContent = payload.unit_name;
                    select.appendChild(option);
                });

                input.value = '';
            } catch {
                // leave input intact so the user can retry
            }
        });
    }
})();

// Contract form: payment method blocks, termin rows, percentage total, nominal preview.
(() => {
    const form = document.getElementById('contract-form');

    if (!form) {
        return;
    }

    const methodSelect = document.getElementById('payment_method');
    const blocks = [...form.querySelectorAll('[data-payment-block]')];
    const valueInput = document.getElementById('contract_value');
    const body = document.getElementById('termin-body');
    const template = document.getElementById('termin-row-template');
    const addButton = document.getElementById('add-termin');
    const totalEl = document.getElementById('termin-total');

    const formatRupiah = (value) => `Rp ${value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const recalc = () => {
        if (!body) {
            return;
        }

        const contractValue = Number(valueInput?.value || 0);
        const rows = [...body.querySelectorAll('[data-termin-row]')];
        let total = 0;

        rows.forEach((row, index) => {
            row.querySelector('[data-termin-number]').textContent = String(index + 1);

            row.querySelectorAll('[data-termin-field]').forEach((input) => {
                input.name = `termins[${index}][${input.dataset.terminField}]`;
            });

            const percentage = Number(row.querySelector('[data-termin-field="percentage"]')?.value || 0);
            total += percentage;

            const nominal = row.querySelector('[data-termin-nominal]');

            if (nominal) {
                nominal.textContent = formatRupiah(contractValue * percentage / 100);
            }

            const removeButton = row.querySelector('[data-remove-termin]');

            if (removeButton) {
                removeButton.disabled = rows.length === 1;
                removeButton.classList.toggle('opacity-40', rows.length === 1);
            }
        });

        if (totalEl) {
            totalEl.textContent = total.toLocaleString('id-ID', { maximumFractionDigits: 4 });
            totalEl.classList.toggle('text-danger', Math.abs(total - 100) > 0.00005);
        }
    };

    const toggleBlocks = () => {
        const method = methodSelect?.value;

        blocks.forEach((block) => {
            const active = block.dataset.paymentBlock === method;

            block.classList.toggle('hidden', !active);
            block.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !active;
            });
        });

        recalc();
    };

    methodSelect?.addEventListener('change', toggleBlocks);

    addButton?.addEventListener('click', () => {
        if (!body || !template) {
            return;
        }

        body.appendChild(template.content.cloneNode(true));
        recalc();
        body.querySelector('[data-termin-row]:last-child input')?.focus();
    });

    body?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-termin]');

        if (!button || body.querySelectorAll('[data-termin-row]').length === 1) {
            return;
        }

        button.closest('[data-termin-row]').remove();
        recalc();
    });

    form.addEventListener('input', (event) => {
        if (event.target.matches('[data-termin-field="percentage"], #contract_value')) {
            recalc();
        }
    });

    toggleBlocks();
})();
