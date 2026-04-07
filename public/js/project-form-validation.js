document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.pm-validated-form').forEach((form) => {
        const fields = Array.from(form.querySelectorAll('input, textarea, select'))
            .filter((field) => field.name && field.type !== 'hidden' && field.type !== 'submit' && !field.disabled);

        fields.forEach((field) => {
            const handler = () => validateField(field, form);
            field.addEventListener('input', handler);
            field.addEventListener('change', handler);
            field.addEventListener('blur', handler);
        });

        form.addEventListener('submit', (event) => {
            let isValid = true;

            fields.forEach((field) => {
                if (!validateField(field, form)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                event.preventDefault();
            }
        });
    });
});

function validateField(field, form) {
    const messages = [];
    const value = (field.value || '').trim();
    const label = field.dataset.validationLabel || field.getAttribute('aria-label') || 'Ce champ';
    const isRequired = field.required;

    if (isRequired && value === '') {
        messages.push(`${label} est obligatoire.`);
    }

    if (value !== '') {
        if (field.type === 'number') {
            const numericValue = Number(field.value);
            if (Number.isNaN(numericValue)) {
                messages.push(`${label} doit etre un nombre valide.`);
            }

            const min = field.getAttribute('min');
            if (min !== null && numericValue < Number(min)) {
                messages.push(`${label} doit etre strictement superieur a 0.`);
            }
        }

        const maxLength = field.getAttribute('maxlength');
        if (maxLength && value.length > Number(maxLength)) {
            messages.push(`${label} ne doit pas depasser ${maxLength} caracteres.`);
        }
    }

    if (field.dataset.role === 'project-end-date') {
        const startField = form.querySelector('[data-role="project-start-date"]');
        if (startField && startField.value && field.value) {
            const startDate = new Date(startField.value);
            const endDate = new Date(field.value);
            if (endDate < startDate) {
                messages.push('La date de fin doit etre posterieure ou egale a la date de creation.');
            }
        }
    }

    renderFieldState(field, messages);

    return messages.length === 0;
}

function renderFieldState(field, messages) {
    const fieldWrapper = field.closest('.pm-field');
    if (!fieldWrapper) {
        return;
    }

    const errorContainer = fieldWrapper.querySelector('[data-field-errors]');
    if (errorContainer) {
        if (messages.length > 0) {
            errorContainer.innerHTML = messages.map((message) => `<div>${escapeHtml(message)}</div>`).join('');
        } else {
            errorContainer.innerHTML = '';
        }
    }

    field.classList.toggle('pm-input-invalid', messages.length > 0);
    field.classList.toggle('pm-input-valid', messages.length === 0 && field.value.trim() !== '');
    fieldWrapper.classList.toggle('pm-field-has-error', messages.length > 0);
}

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
