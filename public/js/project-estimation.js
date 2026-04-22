document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-estimation-form]');
    if (!form) {
        return;
    }

    const steps = Array.from(form.querySelectorAll('[data-estimation-step]'));
    const hiddenStep = form.querySelector('[data-estimation-current-step]');
    const progressBar = document.querySelector('[data-estimation-progress-bar]');
    const progressLabel = document.querySelector('[data-estimation-progress-label]');
    const loading = form.querySelector('[data-estimation-loading]');
    const maxStep = steps.length;
    let currentStep = Number.parseInt(form.dataset.initialStep || hiddenStep?.value || '1', 10);

    const clampStep = (value) => Math.min(Math.max(value, 1), maxStep);

    const updateProgress = () => {
        const safeStep = clampStep(currentStep);

        if (hiddenStep) {
            hiddenStep.value = String(safeStep);
        }

        if (progressBar) {
            progressBar.style.width = `${(safeStep / maxStep) * 100}%`;
            progressBar.setAttribute('aria-valuenow', String(safeStep));
        }

        if (progressLabel) {
            progressLabel.textContent = `Etape ${safeStep} sur ${maxStep}`;
        }
    };

    const showStep = (step) => {
        currentStep = clampStep(step);

        steps.forEach((panel, index) => {
            panel.classList.toggle('d-none', index + 1 !== currentStep);
        });

        updateProgress();
    };

    const validateCurrentStep = () => {
        const currentPanel = steps[currentStep - 1];
        if (!currentPanel) {
            return true;
        }

        const fields = currentPanel.querySelectorAll('input, select, textarea');
        for (const field of fields) {
            if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                continue;
            }

            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }

        return true;
    };

    form.querySelectorAll('[data-step-next]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!validateCurrentStep()) {
                return;
            }

            showStep(currentStep + 1);
        });
    });

    form.querySelectorAll('[data-step-prev]').forEach((button) => {
        button.addEventListener('click', () => {
            showStep(currentStep - 1);
        });
    });

    form.addEventListener('submit', (event) => {
        if (!validateCurrentStep()) {
            event.preventDefault();
            return;
        }

        if (hiddenStep) {
            hiddenStep.value = String(currentStep);
        }

        const submitter = event.submitter;
        if (submitter) {
            submitter.disabled = true;
        }

        if (loading) {
            loading.classList.add('is-visible');
        }
    });

    showStep(currentStep);
});
