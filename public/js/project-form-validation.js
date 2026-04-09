document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.pm-validated-form').forEach((form) => {
        const fields = Array.from(form.querySelectorAll('input, textarea, select'))
            .filter((field) => field.name && field.type !== 'hidden' && field.type !== 'submit' && !field.disabled);

        fields.forEach((field) => {
            const handler = () => validateField(field);
            field.addEventListener('input', handler);
            field.addEventListener('change', handler);
            field.addEventListener('blur', handler);
        });

        form.addEventListener('submit', (event) => {
            let isValid = true;

            fields.forEach((field) => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                event.preventDefault();
            }
        });
    });
});

function validateField(field) {
    const messages = [];
    const value = (field.value || '').trim();
    const label = field.dataset.validationLabel || field.getAttribute('aria-label') || 'Ce champ';
    const isRequired = field.required;

    if (isRequired && value === '') {
        messages.push(field.dataset.validationRequiredMessage || `${label} est obligatoire.`);
    }

    if (value !== '') {
        if (field.type === 'number') {
            const numericValue = Number(field.value);
            if (Number.isNaN(numericValue)) {
                messages.push(`${label} doit être un nombre valide.`);
            }

            const min = field.getAttribute('min');
            if (min !== null && numericValue < Number(min)) {
                messages.push(field.dataset.validationMinMessage || `${label} doit être supérieur ou égal à ${min}.`);
            }

            if (field.dataset.validationInteger === 'true' && !Number.isInteger(numericValue)) {
                messages.push(`${label} doit être un entier valide.`);
            }
        }

        const maxLength = field.getAttribute('maxlength');
        if (maxLength && value.length > Number(maxLength)) {
            messages.push(`${label} ne doit pas dépasser ${maxLength} caractères.`);
        }

        const allowedChoices = (field.dataset.validationChoices || '')
            .split(',')
            .map((item) => item.trim())
            .filter((item) => item !== '');

        if (allowedChoices.length > 0 && !allowedChoices.includes(value)) {
            messages.push(field.dataset.validationChoiceMessage || `${label} sélectionné est invalide.`);
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
