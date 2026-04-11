document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validation-form="investment"]').forEach((form) => {
        const fields = Array.from(form.querySelectorAll('input, textarea, select'))
            .filter((field) => field.name && field.type !== 'hidden' && field.type !== 'submit' && !field.disabled);

        const budMinField = findField(form, ['bud_minInv']);
        const budMaxField = findField(form, ['bud_maxInv']);
        const durationField = findField(form, ['durationEstimateLabel']);
        const currencyField = findField(form, ['CurrencyInv']);

        fields.forEach((field) => {
            const handler = () => validateInvestmentForm(form, { budMinField, budMaxField, durationField, currencyField }, field);
            field.addEventListener('input', handler);
            field.addEventListener('change', handler);
            field.addEventListener('blur', handler);
        });

        form.addEventListener('submit', (event) => {
            const isValid = validateInvestmentForm(
                form,
                { budMinField, budMaxField, durationField, currencyField }
            );

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

function validateInvestmentForm(form, refs, changedField = null) {
    const fieldsToValidate = changedField ? [changedField] : Array.from(form.querySelectorAll('input, textarea, select'))
        .filter((field) => field.name && field.type !== 'hidden' && field.type !== 'submit' && !field.disabled);

    const validationState = new Map();
    let isValid = true;

    fieldsToValidate.forEach((field) => {
        const messages = getBaseMessages(field);
        validationState.set(field, messages);
    });

    if (refs.durationField && (!changedField || changedField === refs.durationField)) {
        mergeMessages(validationState, refs.durationField, getDurationMessages(refs.durationField));
    }

    if (refs.currencyField && (!changedField || changedField === refs.currencyField)) {
        mergeMessages(validationState, refs.currencyField, getCurrencyMessages(refs.currencyField));
    }

    if (refs.budMinField && refs.budMaxField) {
        const shouldValidateBudgetPair = !changedField
            || changedField === refs.budMinField
            || changedField === refs.budMaxField;

        if (shouldValidateBudgetPair) {
            const pairMessages = getBudgetPairMessages(refs.budMinField, refs.budMaxField);
            mergeMessages(validationState, refs.budMinField, pairMessages.min);
            mergeMessages(validationState, refs.budMaxField, pairMessages.max);
        }
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

function getDurationMessages(field) {
    const value = (field.value || '').trim();
    if (value === '') {
        return [];
    }

    const pattern = /^\d+\s*(jour|jours|semaine|semaines|mois|an|ans|annee|annees)$/i;
    if (!pattern.test(value)) {
        return ['La duree estimee doit etre au format "4 mois", "2 ans" ou "10 jours".'];
    }

    return [];
}

function getCurrencyMessages(field) {
    const value = (field.value || '').trim();
    if (value === '') {
        return [];
    }

    if (!/^[A-Za-z]{2,10}$/.test(value)) {
        return ['La devise doit contenir uniquement des lettres, par exemple TND ou EUR.'];
    }

    return [];
}

function getBudgetPairMessages(budMinField, budMaxField) {
    const minValue = (budMinField.value || '').trim();
    const maxValue = (budMaxField.value || '').trim();

    const result = { min: [], max: [] };

    if (minValue === '' || maxValue === '') {
        return result;
    }

    const min = Number(minValue);
    const max = Number(maxValue);

    if (Number.isNaN(min) || Number.isNaN(max)) {
        return result;
    }

    if (max < min) {
        result.max.push('Le montant maximum doit etre superieur ou egal au montant minimum.');
    }

    return result;
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

function findField(form, names) {
    return names
        .map((name) => form.querySelector(`[name$="[${name}]"]`))
        .find((field) => field !== null) || null;
}

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
