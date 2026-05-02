document.addEventListener('DOMContentLoaded', () => {
    const center = document.querySelector('[data-notification-center]');
    if (!center) {
        return;
    }

    const feedUrl = center.dataset.feedUrl || '';
    const consumeUrlBase = center.dataset.consumeUrlBase || '';
    const panel = center.querySelector('[data-notification-panel]');
    const toggleButton = center.querySelector('[data-notification-toggle]');
    const audioToggleButton = center.querySelector('[data-notification-audio-toggle]');
    const audioToggleLabel = center.querySelector('[data-notification-audio-toggle-label]');
    const audioStateLabel = center.querySelector('[data-notification-audio-state]');
    const panelSubtitle = center.querySelector('[data-notification-panel-subtitle]');
    const countNode = center.querySelector('[data-notification-count]');
    const listNode = center.querySelector('[data-notification-list]');
    const synth = window.speechSynthesis;
    const speechSupported = !!synth && typeof window.SpeechSynthesisUtterance !== 'undefined';
    const storageKey = 'advisora_notifications_audio_enabled';

    let notifications = [];
    let audioEnabled = loadAudioPreference();
    let queue = [];
    let queuedIds = new Set();
    let activeNotification = null;
    let pollingTimer = null;
    let speechToken = 0;
    let availableVoices = [];

    const loadVoices = () => {
        availableVoices = speechSupported ? synth.getVoices() : [];
    };

    if (speechSupported) {
        loadVoices();

        if ('onvoiceschanged' in synth) {
            synth.onvoiceschanged = loadVoices;
        }
    }

    updateAudioUi();
    setPanelOpen(false);

    toggleButton?.addEventListener('click', () => {
        setPanelOpen(panel?.hidden ?? true);
    });

    audioToggleButton?.addEventListener('click', () => {
        setAudioEnabled(!audioEnabled);
    });

    document.addEventListener('click', (event) => {
        if (!center.contains(event.target)) {
            setPanelOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (shouldIgnoreKeyboardShortcut(event.target)) {
            return;
        }

        const key = (event.key || '').toLowerCase();
        if (key === 'j') {
            event.preventDefault();
            setAudioEnabled(true);
        }

        if (key === 'f') {
            event.preventDefault();
            setAudioEnabled(false);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            cancelCurrentSpeech(true);
            return;
        }

        if (audioEnabled) {
            queueUnreadNotifications();
            void refreshNotifications();
        }
    });

    window.addEventListener('beforeunload', () => {
        if (pollingTimer) {
            window.clearInterval(pollingTimer);
        }

        cancelCurrentSpeech(false);
    });

    void refreshNotifications();
    pollingTimer = window.setInterval(() => {
        void refreshNotifications();
    }, 10000);

    async function refreshNotifications() {
        if (!feedUrl) {
            return;
        }

        try {
            const response = await fetch(feedUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
            renderNotifications();

            if (audioEnabled && speechSupported && !document.hidden) {
                queueUnreadNotifications();
                drainQueue();
            }
        } catch (error) {
            console.error('Notification feed refresh failed.', error);
        }
    }

    function renderNotifications() {
        const count = notifications.length;
        if (countNode) {
            countNode.textContent = String(count);
        }

        if (!listNode) {
            return;
        }

        listNode.innerHTML = '';

        if (count === 0) {
            const empty = document.createElement('p');
            empty.className = 'notification-empty';
            empty.textContent = 'Aucune notification active.';
            listNode.appendChild(empty);
            return;
        }

        notifications.forEach((notification) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'notification-item';
            item.addEventListener('click', () => {
                if (notification.targetUrl) {
                    window.location.href = notification.targetUrl;
                }
            });

            const title = document.createElement('strong');
            title.textContent = notification.title || 'Notification';

            const description = document.createElement('p');
            description.textContent = notification.description || '';

            const createdAt = document.createElement('time');
            createdAt.dateTime = notification.createdAt || '';
            createdAt.textContent = formatDate(notification.createdAt);

            item.appendChild(title);
            item.appendChild(description);
            item.appendChild(createdAt);
            listNode.appendChild(item);
        });
    }

    function setAudioEnabled(enabled) {
        if (!speechSupported) {
            audioEnabled = false;
            updateAudioUi();
            return;
        }

        audioEnabled = enabled;
        saveAudioPreference(enabled);
        updateAudioUi();

        if (!enabled) {
            cancelCurrentSpeech(true);
            queue = [];
            queuedIds = new Set();
            return;
        }

        queueUnreadNotifications();
        drainQueue();
    }

    function updateAudioUi() {
        const stateText = speechSupported
            ? (audioEnabled ? 'Actif' : 'Desactive')
            : 'Indisponible';

        if (audioStateLabel) {
            audioStateLabel.textContent = stateText;
        }

        if (panelSubtitle) {
            panelSubtitle.textContent = speechSupported
                ? (audioEnabled ? 'Lecture vocale active' : 'Lecture vocale inactive')
                : 'Lecture vocale indisponible sur ce navigateur';
        }

        if (audioToggleLabel) {
            audioToggleLabel.textContent = speechSupported
                ? (audioEnabled ? 'Desactiver' : 'Activer')
                : 'Indisponible';
        }

        if (audioToggleButton) {
            audioToggleButton.disabled = !speechSupported;
            audioToggleButton.classList.toggle('is-active', audioEnabled && speechSupported);
        }
    }

    function setPanelOpen(open) {
        if (!panel || !toggleButton) {
            return;
        }

        panel.hidden = !open;
        toggleButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function queueUnreadNotifications() {
        const ordered = [...notifications].sort((left, right) => {
            return new Date(left.createdAt).getTime() - new Date(right.createdAt).getTime();
        });

        ordered.forEach((notification) => {
            if (!notification || typeof notification.id !== 'number') {
                return;
            }

            if (activeNotification && activeNotification.id === notification.id) {
                return;
            }

            if (queuedIds.has(notification.id)) {
                return;
            }

            queue.push(notification);
            queuedIds.add(notification.id);
        });
    }

    function drainQueue() {
        if (!audioEnabled || !speechSupported || document.hidden || activeNotification || queue.length === 0) {
            return;
        }

        const next = queue.shift();
        if (!next) {
            return;
        }

        queuedIds.delete(next.id);
        activeNotification = next;
        speakNotification(next);
    }

    function speakNotification(notification) {
        speechToken += 1;
        const currentToken = speechToken;
        const utterance = new window.SpeechSynthesisUtterance(normalizeText(notification.spokenText || notification.description || notification.title || ''));
        const voice = pickVoice();

        window.dispatchEvent(new CustomEvent('advisora:tts-stop'));

        utterance.lang = voice?.lang || 'fr-FR';
        utterance.voice = voice || null;
        utterance.rate = 1;
        utterance.pitch = 1;

        utterance.onend = async () => {
            if (currentToken !== speechToken || !activeNotification || activeNotification.id !== notification.id) {
                return;
            }

            await consumeNotification(notification.id);
        };

        utterance.onerror = () => {
            if (currentToken !== speechToken) {
                return;
            }

            activeNotification = null;
            drainQueue();
        };

        synth.cancel();
        synth.speak(utterance);
    }

    async function consumeNotification(notificationId) {
        const consumeUrl = buildConsumeUrl(notificationId);
        if (!consumeUrl) {
            activeNotification = null;
            return;
        }

        try {
            const response = await fetch(consumeUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                activeNotification = null;
                drainQueue();
                return;
            }

            notifications = notifications.filter((notification) => notification.id !== notificationId);
            activeNotification = null;
            renderNotifications();

            const payload = await response.json();
            if (countNode && typeof payload.count === 'number') {
                countNode.textContent = String(payload.count);
            }

            queueUnreadNotifications();
            drainQueue();
        } catch (error) {
            console.error('Notification consume failed.', error);
            activeNotification = null;
            drainQueue();
        }
    }

    function cancelCurrentSpeech(shouldRequeue) {
        if (!speechSupported) {
            return;
        }

        speechToken += 1;

        if (shouldRequeue && activeNotification && !queuedIds.has(activeNotification.id)) {
            queue.unshift(activeNotification);
            queuedIds.add(activeNotification.id);
        }

        activeNotification = null;
        synth.cancel();
    }

    function buildConsumeUrl(notificationId) {
        if (!consumeUrlBase) {
            return '';
        }

        return `${consumeUrlBase}/${notificationId}/consume`;
    }

    function pickVoice() {
        const frenchVoices = availableVoices.filter((voice) => (voice.lang || '').toLowerCase().startsWith('fr'));
        if (frenchVoices.length === 0) {
            return availableVoices[0] || null;
        }

        return frenchVoices.find((voice) => voice.lang === 'fr-TN')
            || frenchVoices.find((voice) => voice.lang === 'fr-FR')
            || frenchVoices[0];
    }

    function normalizeText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function formatDate(value) {
        if (!value) {
            return 'Maintenant';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return 'Maintenant';
        }

        return new Intl.DateTimeFormat('fr-TN', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(date);
    }

    function shouldIgnoreKeyboardShortcut(target) {
        if (!(target instanceof HTMLElement)) {
            return false;
        }

        if (target.isContentEditable) {
            return true;
        }

        return ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
    }

    function loadAudioPreference() {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            return false;
        }
    }

    function saveAudioPreference(enabled) {
        try {
            window.localStorage.setItem(storageKey, enabled ? '1' : '0');
        } catch (error) {
            console.error('Unable to persist notification audio preference.', error);
        }
    }
});
