document.addEventListener('DOMContentLoaded', () => {
    const switchContainer = document.querySelector('[data-interface-switch]');
    const modeToggle = switchContainer?.querySelector('[data-interface-toggle-input], .js-interface-toggle');
    const modeBadge = document.querySelector('[data-mode-badge]');

    if (!switchContainer || !modeToggle) {
        return;
    }

    const currentInterface = switchContainer.dataset.currentInterface === 'front' ? 'front' : 'back';
    const frontUrl = switchContainer.dataset.frontUrl || '/';
    const backUrl = switchContainer.dataset.backUrl || '/back';

    applyInterfaceState(currentInterface);
    modeToggle.checked = currentInterface === 'back';

    modeToggle.addEventListener('change', () => {
        const targetInterface = modeToggle.checked ? 'back' : 'front';
        const targetUrl = targetInterface === 'back' ? backUrl : frontUrl;
        const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;

        if (!targetUrl || targetUrl === currentUrl || targetUrl === window.location.pathname) {
            applyInterfaceState(targetInterface);
            return;
        }

        localStorage.setItem('advisora_mode', targetInterface);
        window.location.assign(targetUrl);
    });

    function applyInterfaceState(interfaceMode) {
        document.body.classList.toggle('front-mode', interfaceMode === 'front');
        document.body.classList.toggle('back-mode', interfaceMode === 'back');
        localStorage.setItem('advisora_mode', interfaceMode);

        if (!modeBadge) {
            return;
        }

        modeBadge.textContent = interfaceMode === 'front' ? 'Front Office' : 'Back Office';
        modeBadge.classList.toggle('front-badge', interfaceMode === 'front');
    }
});
