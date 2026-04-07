document.addEventListener('DOMContentLoaded', function () {
    function createErrorEl(message) {
        const el = document.createElement('div');
        el.className = 'fv-error';
        el.style.color = '#b00020';
        el.style.fontSize = '0.9em';
        el.style.marginTop = '4px';
        el.textContent = message;
        return el;
    }

    function showError(field, message) {
        removeError(field);
        const err = createErrorEl(message);
        field.classList.add('fv-invalid');
        if (field.nextElementSibling) {
            field.parentNode.insertBefore(err, field.nextElementSibling);
        } else {
            field.parentNode.appendChild(err);
        }
    }

    function removeError(field) {
        field.classList.remove('fv-invalid');
        const next = field.parentNode.querySelector('.fv-error');
        if (next) next.remove();
    }

    function validateField(field) {
        const label = field.getAttribute('data-validation-label') || field.name || 'Ce champ';
        const isRequired = field.required || field.getAttribute('required') !== null;
        const val = (field.value || '').toString().trim();

        if (isRequired && (val === '' || val === null)) {
            showError(field, `${label} est requis.`);
            return false;
        }

        if (field.type === 'number' || field.matches('input[type="number"]')) {
            const min = parseFloat(field.getAttribute('min'));
            if (!isNaN(min) && val !== '') {
                const num = parseFloat(val);
                if (isNaN(num) || num <= min) {
                    showError(field, `${label} doit être supérieur à ${min}.`);
                    return false;
                }
            }
        }

        removeError(field);
        return true;
    }

    document.querySelectorAll('form').forEach(function (form) {
        const fields = Array.from(form.querySelectorAll('input, textarea, select'))
            .filter(f => f.type !== 'hidden' && !f.disabled);

        fields.forEach(function (f) {
            f.addEventListener('input', function () { validateField(f); });
            f.addEventListener('blur', function () { validateField(f); });
        });

        form.addEventListener('submit', function (e) {
            let valid = true;
            for (const f of fields) {
                if (!validateField(f)) {
                    valid = false;
                }
            }
            if (!valid) {
                e.preventDefault();
                const firstInvalid = form.querySelector('.fv-invalid');
                if (firstInvalid) firstInvalid.focus();
            }
        });
    });
});
