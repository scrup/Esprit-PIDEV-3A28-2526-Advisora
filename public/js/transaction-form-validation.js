document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validation-form="transaction"]').forEach((form) => {
        const fields = Array.from(form.querySelectorAll('input, textarea, select'))
            .filter((field) => field.name && field.type !== 'hidden' && field.type !== 'submit' && !field.disabled);

        const amountField = findField(form, ['MontantTransac']);
        const typeField = findField(form, ['type']);
        const dateField = findField(form, ['DateTransac']);

        fields.forEach((field) => {
            const handler = () => validateTransactionForm(form, { amountField, typeField, dateField }, field);
            field.addEventListener('input', handler);
            field.addEventListener('change', handler);
            field.addEventListener('blur', handler);
        });

        form.addEventListener('submit', (event) => {
            const isValid = validateTransactionForm(form, { amountField, typeField, dateField });

            if (!isValid) {
                event.preventDefault();
                const firstInvalid = form.querySelector('.pm-input-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });
});

function validateTransactionForm(form, refs, changedField = null) {
    const fieldsToValidate = changedField ? [changedField] : Array.from(form.querySelectorAll('input, textarea, select'))
        .filter((field) => field.name && field.type !== 'hidden' && field.type !== 'submit' && !field.disabled);

    const validationState = new Map();
    let isValid = true;

    fieldsToValidate.forEach((field) => {
        validationState.set(field, getBaseMessages(field));
    });

    if (refs.dateField && (!changedField || changedField === refs.dateField)) {
        mergeMessages(validationState, refs.dateField, getDateMessages(refs.dateField));
    }

    if (refs.amountField && (!changedField || changedField === refs.amountField)) {
        mergeMessages(validationState, refs.amountField, getAmountMessages(form, refs.amountField));
    }

    if (refs.typeField && (!changedField || changedField === refs.typeField)) {
        mergeMessages(validationState, refs.typeField, getTypeMessages(refs.typeField));
    }

    validationState.forEach((messages, field) => {
        renderFieldState(field, messages);
        if (messages.length > 0) {
            isValid = false;
        }
    });

    return isValid;
}

function getBaseMessages(field) {
    const messages = [];
    const value = (field.value || '').trim();
    const label = field.dataset.validationLabel || field.getAttribute('aria-label') || 'Ce champ';

    if (field.required && value === '') {
        messages.push(`${label} est obligatoire.`);
    }

    if (value !== '' && field.type === 'number') {
        const numericValue = Number(field.value);
        if (Number.isNaN(numericValue)) {
            messages.push(`${label} doit etre un nombre valide.`);
        }

        const min = field.getAttribute('min');
        if (min !== null && numericValue < Number(min)) {
            messages.push(`${label} doit etre superieur ou egal a ${min}.`);
        }
    }

    const maxLength = field.getAttribute('maxlength');
    if (value !== '' && maxLength && value.length > Number(maxLength)) {
        messages.push(`${label} ne doit pas depasser ${maxLength} caracteres.`);
    }

    return messages;
}

function getDateMessages(field) {
    const value = (field.value || '').trim();
    if (value === '') {
        return [];
    }

    const selectedDate = new Date(`${value}T00:00:00`);
    if (Number.isNaN(selectedDate.getTime())) {
        return ['La date de transaction doit etre valide.'];
    }

    return [];
}

function getAmountMessages(form, field) {
    const value = (field.value || '').trim();
    if (value === '') {
        return [];
    }

    const amount = Number(value);
    if (Number.isNaN(amount)) {
        return [];
    }

    const min = Number(form.dataset.investmentMin || '');
    const max = Number(form.dataset.investmentMax || '');
    const currency = (form.dataset.investmentCurrency || 'TND').trim() || 'TND';

    if (!Number.isNaN(min) && !Number.isNaN(max) && (amount < min || amount > max)) {
        return [`Le montant doit etre compris entre ${formatAmount(min)} et ${formatAmount(max)} ${currency}.`];
    }

    return [];
}

function getTypeMessages(field) {
    const value = (field.value || '').trim();
    if (value === '') {
        return [];
    }

    if (!/^[A-Za-z0-9_]+$/.test(value)) {
        return ['Le type doit contenir uniquement des lettres, des chiffres ou des underscores.'];
    }

    return [];
}

function renderFieldState(field, messages) {
    const fieldWrapper = field.closest('.pm-field');
    if (!fieldWrapper) {
        return;
    }

    const errorContainer = fieldWrapper.querySelector('[data-field-errors]');
    if (errorContainer) {
        errorContainer.innerHTML = messages.length > 0
            ? messages.map((message) => `<div>${escapeHtml(message)}</div>`).join('')
            : '';
    }

    field.classList.toggle('pm-input-invalid', messages.length > 0);
    field.classList.toggle('pm-input-valid', messages.length === 0 && field.value.trim() !== '');
    fieldWrapper.classList.toggle('pm-field-has-error', messages.length > 0);
}

function mergeMessages(validationState, field, extraMessages) {
    const currentMessages = validationState.get(field) || [];
    extraMessages.forEach((message) => {
        if (!currentMessages.includes(message)) {
            currentMessages.push(message);
        }
    });
    validationState.set(field, currentMessages);
}

function findField(form, names) {
    return names
        .map((name) => form.querySelector(`[name$="[${name}]"]`))
        .find((field) => field !== null) || null;
}

function formatAmount(value) {
    return value.toFixed(2);
}

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
