document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('#header.site-header');
    if (!header) {
        return;
    }

    const menuToggle = header.querySelector('[data-header-toggle]');
    const nav = header.querySelector('[data-header-nav]');
    const actions = header.querySelector('[data-header-actions]');
    const navLinks = nav ? nav.querySelectorAll('.nav-link') : [];
    const mobileBreakpoint = 900;
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    const isElementVisible = (element) => {
        if (!element) {
            return false;
        }
        if (element.closest('[hidden]')) {
            return false;
        }
        const style = window.getComputedStyle(element);
        return style.display !== 'none' && style.visibility !== 'hidden';
    };

    const getMenuFocusableElements = () => {
        if (!nav || !actions) {
            return [];
        }
        return [...nav.querySelectorAll(focusableSelector), ...actions.querySelectorAll(focusableSelector)]
            .filter(isElementVisible);
    };

    const trapFocusInsideMenu = (event) => {
        if (window.innerWidth > mobileBreakpoint || !header.classList.contains('is-menu-open')) {
            return;
        }

        const focusables = getMenuFocusableElements();
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
    };

    const closeMenu = (restoreFocus = false) => {
        header.classList.remove('is-menu-open');
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
            if (restoreFocus) {
                menuToggle.focus();
            }
        }
    };

    if (menuToggle && nav && actions) {
        const openMenuAndFocusFirstItem = () => {
            const willOpen = !header.classList.contains('is-menu-open');
            header.classList.toggle('is-menu-open', willOpen);
            menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

            if (willOpen) {
                const focusables = getMenuFocusableElements();
                if (focusables.length) {
                    focusables[0].focus();
                }
            }
        };

        menuToggle.addEventListener('click', () => {
            openMenuAndFocusFirstItem();
        });

        menuToggle.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!header.classList.contains('is-menu-open')) {
                    openMenuAndFocusFirstItem();
                } else {
                    const focusables = getMenuFocusableElements();
                    if (focusables.length) {
                        focusables[0].focus();
                    }
                }
            }
        });

        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= mobileBreakpoint) {
                    closeMenu();
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!header.contains(event.target)) {
                closeMenu();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > mobileBreakpoint) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu(true);
                closeDropdowns();
                return;
            }

            if (event.key === 'Tab') {
                trapFocusInsideMenu(event);
            }
        });
    }

    const dropdowns = header.querySelectorAll('[data-user-dropdown]');

    function closeDropdowns() {
        dropdowns.forEach((dropdown) => {
            const toggle = dropdown.querySelector('[data-user-dropdown-toggle]');
            const menu = dropdown.querySelector('[data-user-dropdown-menu]');
            if (!toggle || !menu) {
                return;
            }

            toggle.setAttribute('aria-expanded', 'false');
            menu.hidden = true;
            dropdown.classList.remove('is-open');
        });
    }

    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-user-dropdown-toggle]');
        const menu = dropdown.querySelector('[data-user-dropdown-menu]');
        if (!toggle || !menu) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = menu.hidden;
            closeDropdowns();
            menu.hidden = !willOpen;
            dropdown.classList.toggle('is-open', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

            if (willOpen) {
                const firstMenuAction = menu.querySelector(focusableSelector);
                if (firstMenuAction) {
                    firstMenuAction.focus();
                }
            }
        });

        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (menu.hidden) {
                    toggle.click();
                } else {
                    const firstMenuAction = menu.querySelector(focusableSelector);
                    if (firstMenuAction) {
                        firstMenuAction.focus();
                    }
                }
            }
        });

        menu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                menu.hidden = true;
                dropdown.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!header.contains(event.target)) {
            closeDropdowns();
            return;
        }

        if (!(event.target instanceof Element) || !event.target.closest('[data-user-dropdown]')) {
            closeDropdowns();
        }
    });
});
