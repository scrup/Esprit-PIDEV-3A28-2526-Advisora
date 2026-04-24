document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-investment-recommendations]');
    if (!root) {
        return;
    }

    const selectId = root.getAttribute('data-project-select-id');
    if (!selectId) {
        return;
    }

    const projectSelect = document.getElementById(selectId);
    if (!projectSelect) {
        return;
    }

    const titleTarget = document.querySelector('[data-selected-project-title]');
    const idTarget = document.querySelector('[data-selected-project-id]');
    const budgetTarget = document.querySelector('[data-selected-project-budget]');
    const sectorTarget = document.querySelector('[data-selected-project-sector]');
    const statusTarget = document.querySelector('[data-selected-project-status]');
    const cards = Array.from(root.querySelectorAll('[data-recommendation-card]'));
    const buttons = Array.from(root.querySelectorAll('[data-recommend-project]'));

    const updateBanner = () => {
        const selectedOption = projectSelect.options[projectSelect.selectedIndex];
        if (!selectedOption || !projectSelect.value) {
            if (titleTarget) titleTarget.textContent = 'Aucun pour le moment';
            if (idTarget) idTarget.textContent = 'A selectionner';
            if (budgetTarget) budgetTarget.textContent = 'Non precise';
            if (sectorTarget) sectorTarget.textContent = 'Non renseigne';
            if (statusTarget) statusTarget.textContent = 'Non renseigne';

            return;
        }

        if (titleTarget) {
            titleTarget.textContent = selectedOption.getAttribute('data-project-title') || selectedOption.textContent.trim();
        }

        if (idTarget) {
            idTarget.textContent = `#${projectSelect.value}`;
        }

        if (budgetTarget) {
            budgetTarget.textContent = selectedOption.getAttribute('data-project-budget') || 'Non precise';
        }

        if (sectorTarget) {
            sectorTarget.textContent = selectedOption.getAttribute('data-project-sector') || 'Non renseigne';
        }

        if (statusTarget) {
            statusTarget.textContent = selectedOption.getAttribute('data-project-status') || 'Non renseigne';
        }
    };

    const updateSelectedCard = () => {
        const selectedProjectId = projectSelect.value;

        cards.forEach((card) => {
            const isSelected = card.getAttribute('data-project-id') === selectedProjectId;
            card.classList.toggle('is-selected', isSelected);
        });

        buttons.forEach((button) => {
            const isSelected = button.getAttribute('data-recommend-project') === selectedProjectId;
            button.classList.toggle('pm-btn-primary', isSelected);
            button.classList.toggle('pm-btn-secondary', !isSelected);
            button.textContent = isSelected ? 'Projet selectionne' : 'Choisir ce projet';
        });
    };

    const syncUi = () => {
        updateBanner();
        updateSelectedCard();
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const projectId = button.getAttribute('data-recommend-project');
            if (!projectId) {
                return;
            }

            projectSelect.value = projectId;
            projectSelect.dispatchEvent(new Event('change', { bubbles: true }));
            projectSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => projectSelect.focus(), 220);
        });
    });

    projectSelect.addEventListener('change', syncUi);
    syncUi();
});
