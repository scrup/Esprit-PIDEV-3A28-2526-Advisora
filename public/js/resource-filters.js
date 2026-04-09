document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-filter-form]').forEach((form) => {
        let debounceTimer = null;

        const submitForm = () => {
            if (debounceTimer !== null) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        };

        form.querySelectorAll('input, select').forEach((field) => {
            if (!field.name || field.type === 'hidden' || field.type === 'submit') {
                return;
            }

            field.addEventListener('change', () => {
                if (field.tagName === 'SELECT') {
                    submitForm();
                    return;
                }

                debounceTimer = setTimeout(submitForm, 350);
            });

            if (field.tagName === 'INPUT') {
                field.addEventListener('input', () => {
                    debounceTimer = setTimeout(submitForm, 350);
                });
            }
        });
    });
});
