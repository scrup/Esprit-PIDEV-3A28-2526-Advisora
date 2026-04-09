document.addEventListener('DOMContentLoaded', () => {
    setCurrentDate();
    initPassiveNavigationState();
});

function setCurrentDate() {
    const dateElement = document.getElementById('currentDate');
    if (!dateElement) {
        return;
    }

    const today = new Date().toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    dateElement.textContent = today;
}

function initPassiveNavigationState() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (!logoutBtn) {
        return;
    }

    logoutBtn.addEventListener('click', () => {
        logoutBtn.disabled = true;
        logoutBtn.classList.add('is-loading');
    }, { once: true });
}
