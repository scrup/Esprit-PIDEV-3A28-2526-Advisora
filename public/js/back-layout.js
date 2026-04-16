document.addEventListener('DOMContentLoaded', () => {
    const appShell = document.querySelector('[data-back-app]');
    const toggleButton = document.querySelector('[data-sidebar-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');

    if (!appShell || !toggleButton || !overlay) {
        return;
    }

    const closeSidebar = () => appShell.classList.remove('sidebar-open');
    const openSidebar = () => appShell.classList.add('sidebar-open');

    toggleButton.addEventListener('click', () => {
        if (appShell.classList.contains('sidebar-open')) {
            closeSidebar();
            return;
        }

        openSidebar();
    });

    overlay.addEventListener('click', closeSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1100) {
            closeSidebar();
        }
    });
});
