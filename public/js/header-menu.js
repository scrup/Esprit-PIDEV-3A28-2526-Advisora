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

    const closeMenu = () => {
        header.classList.remove('is-menu-open');
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    };

    if (menuToggle && nav && actions) {
        menuToggle.addEventListener('click', () => {
            const willOpen = !header.classList.contains('is-menu-open');
            header.classList.toggle('is-menu-open', willOpen);
            menuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
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
                closeMenu();
                closeDropdowns();
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
