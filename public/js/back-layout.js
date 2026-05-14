document.addEventListener('DOMContentLoaded', () => {
    const appShell = document.querySelector('[data-back-app]');
    const toggleButton = document.querySelector('[data-sidebar-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const sidebar = document.querySelector('[data-back-sidebar]');

    if (!appShell || !toggleButton || !overlay || !sidebar) {
        return;
    }

    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const closeSidebar = (restoreFocus = false) => {
        appShell.classList.remove('sidebar-open');
        if (restoreFocus) {
            toggleButton.focus();
        }
    };

    const openSidebar = () => {
        appShell.classList.add('sidebar-open');
        const firstFocusable = sidebar.querySelector(focusableSelector);
        if (firstFocusable) {
            firstFocusable.focus();
        }
    };

    toggleButton.addEventListener('click', () => {
        if (appShell.classList.contains('sidebar-open')) {
            closeSidebar(true);
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

    document.addEventListener('keydown', (event) => {
        if (!appShell.classList.contains('sidebar-open')) {
            return;
        }

        if (event.key === 'Escape') {
            closeSidebar(true);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusables = [...sidebar.querySelectorAll(focusableSelector)].filter((element) => {
            if (element.closest('[hidden]')) {
                return false;
            }
            const style = window.getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden';
        });

        if (!focusables.length) {
            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const current = document.activeElement;

        if (event.shiftKey && current === first) {
            event.preventDefault();
            last.focus();
            return;
        }

        if (!event.shiftKey && current === last) {
            event.preventDefault();
            first.focus();
        }
    });
});
