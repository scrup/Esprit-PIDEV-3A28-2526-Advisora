document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.strategy-form[data-strategy-validation]');
    if (!form) {
        return;
    }

    const fields = Array.from(form.querySelectorAll('input, select, textarea'))
        .filter((field) => field.name && field.type !== 'hidden' && field.type !== 'submit' && !field.disabled);

    fields.forEach((field) => {
        const handler = () => validateField(field);
        field.addEventListener('input', handler);
        field.addEventListener('change', handler);
        field.addEventListener('blur', handler);
    });

    form.addEventListener('submit', (event) => {
        let isValid = true;
        let firstInvalidField = null;

        fields.forEach((field) => {
            const fieldIsValid = validateField(field);
            if (!fieldIsValid) {
                isValid = false;
                firstInvalidField ??= field;
            }
        });

        if (!isValid) {
            event.preventDefault();
            firstInvalidField?.focus();
        }
    });
});

function validateField(field) {
    const messages = [];
    const value = (field.value || '').trim();
    const label = field.dataset.validationLabel || field.getAttribute('aria-label') || 'Ce champ';
    const isRequired = field.required || field.getAttribute('required') !== null;
    const minLength = parseInt(field.getAttribute('minlength') || '', 10);
    const maxLength = parseInt(field.getAttribute('maxlength') || '', 10);
    const min = field.getAttribute('min');
    const max = field.getAttribute('max');
    const isNumeric = field.type === 'number' || field.dataset.integer === 'true';

    if (isRequired && value === '') {
        messages.push(`${label} est obligatoire.`);
    }

    if (value !== '') {
        if (!Number.isNaN(minLength) && value.length < minLength) {
            messages.push(`${label} doit contenir au moins ${minLength} caracteres.`);
        }

        if (!Number.isNaN(maxLength) && value.length > maxLength) {
            messages.push(`${label} ne doit pas depasser ${maxLength} caracteres.`);
        }

        if (isNumeric) {
            const numericValue = Number(field.value);

            if (Number.isNaN(numericValue)) {
                messages.push(`${label} doit etre un nombre valide.`);
            } else {
                if (min !== null && min !== '' && numericValue < Number(min)) {
                    messages.push(`${label} doit etre superieur ou egal a ${min}.`);
                }

                if (max !== null && max !== '' && numericValue > Number(max)) {
                    messages.push(`${label} doit etre inferieur ou egal a ${max}.`);
                }

                if (field.dataset.integer === 'true' && !Number.isInteger(numericValue)) {
                    messages.push(`${label} doit etre un nombre entier.`);
                }
            }
        }
    }

    renderFieldValidation(field, messages);

    return messages.length === 0;
}

function renderFieldValidation(field, messages) {
    const group = field.closest('.form-group');
    if (!group) {
        return;
    }

    group.querySelectorAll('[data-client-error], [data-client-success]').forEach((element) => element.remove());

    const hasValue = (field.value || '').trim() !== '';
    const isValid = messages.length === 0;

    group.classList.toggle('form-group-error', !isValid);
    group.classList.toggle('form-group-success', isValid && hasValue);

    if (!isValid) {
        const error = document.createElement('div');
        error.className = 'form-error';
        error.dataset.clientError = 'true';
        error.textContent = messages[0];
        group.appendChild(error);
        return;
    }

    if (hasValue) {
        const success = document.createElement('div');
        success.className = 'success-message';
        success.dataset.clientSuccess = 'true';
        success.textContent = 'Champ valide.';
        group.appendChild(success);
    }
}
