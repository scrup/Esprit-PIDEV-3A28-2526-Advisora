(function () {
    const root = document.querySelector('[data-a11y-widget]');
    if (!root) {
        return;
    }

    const STORAGE_KEY = 'advisora_a11y_settings_v1';
    const FONT_BASE_PX = 16;
    const FONT_STEP = 0.05;
    const FONT_MIN = 0.85;
    const FONT_MAX = 1.4;
    const DEFAULTS = {
        fontScale: 1,
        spaciousText: false,
        dyslexiaFont: false,
        focusMode: false,
        readingRuler: false,
        pauseAnimations: false,
        linkEmphasis: false,
        colorProfile: 'default'
    };

    const panel = root.querySelector('[data-a11y-panel]');
    const toggleBtn = root.querySelector('[data-a11y-toggle]');
    const closeBtn = root.querySelector('[data-a11y-close]');
    const fontValue = root.querySelector('[data-a11y-font-value]');
    const ruler = root.querySelector('[data-a11y-ruler]');
    const focusOverlay = root.querySelector('[data-a11y-focus]');
    const settingInputs = root.querySelectorAll('[data-a11y-setting]');
    const colorButtons = root.querySelectorAll('[data-a11y-color]');
    const actionButtons = root.querySelectorAll('[data-a11y-action]');

    let settings = loadSettings();
    let rulerHandler = null;

    applySettings();
    syncControls();

    toggleBtn.addEventListener('click', () => {
        const isOpen = !panel.hidden;
        panel.hidden = isOpen;
        toggleBtn.setAttribute('aria-expanded', String(!isOpen));
    });

    closeBtn.addEventListener('click', () => {
        panel.hidden = true;
        toggleBtn.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('click', (event) => {
        if (panel.hidden) {
            return;
        }
        if (!root.contains(event.target)) {
            panel.hidden = true;
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            panel.hidden = true;
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    });

    actionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.getAttribute('data-a11y-action');
            if (action === 'font-up') {
                settings.fontScale = clamp(settings.fontScale + FONT_STEP, FONT_MIN, FONT_MAX);
            } else if (action === 'font-down') {
                settings.fontScale = clamp(settings.fontScale - FONT_STEP, FONT_MIN, FONT_MAX);
            } else if (action === 'font-reset') {
                settings.fontScale = 1;
            }
            persistAndApply();
        });
    });

    settingInputs.forEach((input) => {
        input.addEventListener('change', () => {
            const key = input.getAttribute('data-a11y-setting');
            if (!Object.prototype.hasOwnProperty.call(DEFAULTS, key)) {
                return;
            }
            settings[key] = Boolean(input.checked);
            persistAndApply();
        });
    });

    colorButtons.forEach((button) => {
        button.addEventListener('click', () => {
            settings.colorProfile = button.getAttribute('data-a11y-color') || 'default';
            persistAndApply();
        });
    });

    function persistAndApply() {
        saveSettings(settings);
        applySettings();
        syncControls();
    }

    function applySettings() {
        const htmlFontSize = Math.round(FONT_BASE_PX * settings.fontScale * 100) / 100;
        document.documentElement.style.fontSize = `${htmlFontSize}px`;

        document.body.classList.toggle('a11y-spacious-text', settings.spaciousText);
        document.body.classList.toggle('a11y-dyslexia-font', settings.dyslexiaFont);
        document.body.classList.toggle('a11y-pause-animations', settings.pauseAnimations);
        document.body.classList.toggle('a11y-link-emphasis', settings.linkEmphasis);

        document.body.setAttribute('data-a11y-color', settings.colorProfile);

        focusOverlay.hidden = !settings.focusMode;
        toggleReadingRuler(settings.readingRuler);
    }

    function syncControls() {
        if (fontValue) {
            fontValue.textContent = `${Math.round(settings.fontScale * 100)}%`;
        }

        settingInputs.forEach((input) => {
            const key = input.getAttribute('data-a11y-setting');
            if (Object.prototype.hasOwnProperty.call(settings, key)) {
                input.checked = Boolean(settings[key]);
            }
        });

        colorButtons.forEach((button) => {
            const isActive = (button.getAttribute('data-a11y-color') || 'default') === settings.colorProfile;
            button.setAttribute('aria-pressed', String(isActive));
        });
    }

    function toggleReadingRuler(isEnabled) {
        if (!ruler) {
            return;
        }

        ruler.hidden = !isEnabled;

        if (!isEnabled) {
            if (rulerHandler) {
                document.removeEventListener('mousemove', rulerHandler);
                rulerHandler = null;
            }
            return;
        }

        if (!rulerHandler) {
            rulerHandler = (event) => {
                ruler.style.top = `${event.clientY}px`;
            };
            document.addEventListener('mousemove', rulerHandler, { passive: true });
        }
    }

    function loadSettings() {
        try {
            const raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return { ...DEFAULTS };
            }
            const parsed = JSON.parse(raw);
            return {
                ...DEFAULTS,
                ...parsed,
                fontScale: sanitizeNumber(parsed.fontScale, DEFAULTS.fontScale),
                colorProfile: sanitizeColor(parsed.colorProfile)
            };
        } catch (error) {
            return { ...DEFAULTS };
        }
    }

    function saveSettings(nextSettings) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(nextSettings));
        } catch (error) {
            // Ignore localStorage write errors.
        }
    }

    function sanitizeNumber(value, fallback) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return fallback;
        }
        return clamp(number, FONT_MIN, FONT_MAX);
    }

    function sanitizeColor(value) {
        const allowed = ['default', 'high-contrast', 'deuter-friendly', 'tritan-friendly'];
        return allowed.includes(value) ? value : 'default';
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }
})();
