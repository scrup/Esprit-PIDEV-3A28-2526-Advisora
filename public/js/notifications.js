document.addEventListener('DOMContentLoaded', () => {
    const center = document.querySelector('[data-notification-center]');
    if (!center) {
        return;
    }

    const feedUrl = center.dataset.feedUrl || '';
    const consumeUrlBase = center.dataset.consumeUrlBase || '';
    const soundUrl = center.dataset.soundUrl || '';
    const clearUrl = center.dataset.clearUrl || '';

    const panel = center.querySelector('[data-notification-panel]');
    const toggleButton = center.querySelector('[data-notification-toggle]');
    const audioStateLabel = center.querySelector('[data-notification-audio-state]');
    const panelSubtitle = center.querySelector('[data-notification-panel-subtitle]');

    const browserToggleButton = center.querySelector('[data-notification-browser-toggle]');
    const browserToggleLabel = center.querySelector('[data-notification-browser-toggle-label]');
    const browserStateLabel = center.querySelector('[data-notification-browser-state]');
    const clearButton = center.querySelector('[data-notification-clear]');

    const countNode = center.querySelector('[data-notification-count]');
    const listNode = center.querySelector('[data-notification-list]');

    const synth = window.speechSynthesis;
    const speechSupported = !!synth && typeof window.SpeechSynthesisUtterance !== 'undefined';
    const browserNotificationSupported = 'Notification' in window;

    const notificationSound = createNotificationSound(soundUrl);
    const soundSupported = notificationSound !== null;

    const audioStorageKey = 'advisora_notifications_audio_enabled';
    const ttsStorageKey = 'advisora_notifications_tts_enabled';
    const browserStorageKey = 'advisora_notifications_browser_enabled';
    const seenStorageKey = 'advisora_notifications_seen_ids';
    const latestStorageKey = 'advisora_notifications_latest_id';

    let notifications = [];
    let audioEnabled = true;
    let ttsEnabled = loadTtsPreference();
    let browserNotificationsEnabled = loadBrowserPreference();
    let browserPermission = browserNotificationSupported ? Notification.permission : 'denied';

    let seenNotificationIds = loadSeenNotificationIds();
    let seenCacheInitialized = hasSeenNotificationCache();
    let latestNotifiedId = loadLatestNotifiedId();

    let queue = [];
    let queuedIds = new Set();
    let activeNotification = null;
    let pollingTimer = null;
    let speechToken = 0;
    let availableVoices = [];

    let soundReady = false;
    let audioUnlocked = false;

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
    updateBrowserUi();
    setPanelOpen(false);

    toggleButton?.addEventListener('click', () => {
        setPanelOpen(panel?.hidden ?? true);
    });

    browserToggleButton?.addEventListener('click', async () => {
        await toggleBrowserNotifications();
    });

    clearButton?.addEventListener('click', async () => {
        await clearAllNotifications();
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
            setTtsEnabled(true);
        }

        if (key === 'f') {
            event.preventDefault();
            setTtsEnabled(false);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            cancelCurrentSpeech(true);
            return;
        }

        if (ttsEnabled) {
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

    ['click', 'touchstart', 'keydown'].forEach((eventType) => {
        document.addEventListener(eventType, unlockAudioOnUserInteraction, { passive: true });
    });

    void refreshNotifications();
    pollingTimer = window.setInterval(() => {
        void refreshNotifications();
    }, 10000);

    async function unlockAudioOnUserInteraction() {
        if (audioUnlocked) {
            return;
        }

        if (notificationSound) {
            try {
                notificationSound.currentTime = 0;
                notificationSound.muted = true;
                await notificationSound.play();
                notificationSound.pause();
                notificationSound.currentTime = 0;
                notificationSound.muted = false;
                soundReady = true;
            } catch (error) {
                // noop
            }
        }

        const Context = window.AudioContext || window.webkitAudioContext;
        if (Context) {
            try {
                const ctx = new Context();
                const gain = ctx.createGain();
                gain.gain.value = 0.0001;
                const osc = ctx.createOscillator();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.01);
                osc.onended = () => {
                    void ctx.close();
                };
            } catch (error) {
                // noop
            }
        }

        audioUnlocked = true;

        if (panelSubtitle && (speechSupported || soundSupported)) {
            panelSubtitle.textContent = ttsEnabled
                ? 'Son actif - lecture vocale active'
                : 'Son actif - lecture vocale inactive (J/F)';
        }
    }

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

            const unseenNotifications = notifications.filter((notification) => {
                return notification
                    && typeof notification.id === 'number'
                    && !seenNotificationIds.has(notification.id);
            });
            const latestUnreadId = (notifications[0] && typeof notifications[0].id === 'number')
                ? notifications[0].id
                : null;

            const currentIds = notifications
                .map((notification) => notification?.id)
                .filter((id) => typeof id === 'number');

            if (!seenCacheInitialized) {
                seenNotificationIds = new Set(currentIds);
                saveSeenNotificationIds(seenNotificationIds);
                seenCacheInitialized = true;
            } else if (unseenNotifications.length > 0 || (latestUnreadId !== null && latestUnreadId !== latestNotifiedId)) {
                const notificationsToAlert = unseenNotifications.length > 0
                    ? unseenNotifications
                    : notifications.slice(0, 1);

                if (audioEnabled && soundSupported) {
                    void playNotificationSound();
                }

                void maybeNotifyBrowser(notificationsToAlert);
            }

            if (audioEnabled && !audioUnlocked && (speechSupported || soundSupported) && panelSubtitle) {
                panelSubtitle.textContent = 'Cliquez n importe ou pour activer le son des notifications';
            }

            currentIds.forEach((id) => seenNotificationIds.add(id));
            saveSeenNotificationIds(seenNotificationIds);
            latestNotifiedId = latestUnreadId;
            saveLatestNotifiedId(latestNotifiedId);

            if (ttsEnabled && speechSupported && !document.hidden) {
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

    function setTtsEnabled(enabled) {
        ttsEnabled = enabled;
        saveTtsPreference(enabled);
        updateAudioUi();

        if (!enabled) {
            cancelCurrentSpeech(true);
            queue = [];
            queuedIds = new Set();
            return;
        }

        if (speechSupported) {
            queueUnreadNotifications();
            drainQueue();
        }
    }

    function updateAudioUi() {
        const mediaSupported = speechSupported || soundSupported;
        const stateText = mediaSupported
            ? (audioEnabled ? 'Actif' : 'Inactif')
            : 'Indisponible';
        if (audioStateLabel) {
            audioStateLabel.textContent = stateText;
        }
        if (panelSubtitle) {
            if (!mediaSupported) {
                panelSubtitle.textContent = 'Son de notification indisponible sur ce navigateur';
            } else if (!audioEnabled) {
                panelSubtitle.textContent = 'Son de notification inactif';
            } else if (!audioUnlocked) {
                panelSubtitle.textContent = 'Cliquez n importe ou pour activer le son des notifications';
            } else if (speechSupported && soundSupported) {
                panelSubtitle.textContent = ttsEnabled
                    ? 'Son actif - lecture vocale active'
                    : 'Son actif - lecture vocale inactive (J/F)';
            } else if (soundSupported) {
                panelSubtitle.textContent = 'Son de notification actif';
            } else {
                panelSubtitle.textContent = ttsEnabled ? 'Lecture vocale active' : 'Lecture vocale inactive (J/F)';
            }
        }
    }
    async function toggleBrowserNotifications() {
        if (!browserNotificationSupported) {
            updateBrowserUi();
            return;
        }
        const hadGrantedPermission = Notification.permission === 'granted';
        browserPermission = Notification.permission;
        if (!hadGrantedPermission) {
            try {
                browserPermission = await Notification.requestPermission();
            } catch (error) {
                browserPermission = Notification.permission;
            }
        }
        if (browserPermission !== 'granted') {
            browserNotificationsEnabled = false;
            saveBrowserPreference(false);
        } else if (hadGrantedPermission) {
            browserNotificationsEnabled = !browserNotificationsEnabled;
            saveBrowserPreference(browserNotificationsEnabled);
        } else {
            browserNotificationsEnabled = true;
            saveBrowserPreference(true);
        }
        setAudioFromBrowserToggle();
        updateBrowserUi();
    }
    function updateBrowserUi() {
        if (!browserStateLabel || !browserToggleButton || !browserToggleLabel) {
            return;
        }

        if (!browserNotificationSupported) {
            browserStateLabel.textContent = 'Notifications navigateur indisponibles';
            browserToggleLabel.textContent = 'Indisponible';
            browserToggleButton.disabled = true;
            browserToggleButton.classList.remove('is-active');
            return;
        }

        if (browserPermission === 'denied') {
            browserStateLabel.textContent = 'Notifications navigateur bloquees';
            browserToggleLabel.textContent = 'Bloquees';
            browserToggleButton.disabled = false;
            browserToggleButton.classList.remove('is-active');
            return;
        }

        if (browserPermission === 'default') {
            browserStateLabel.textContent = 'Notifications navigateur non autorisees';
            browserToggleLabel.textContent = 'Autoriser';
            browserToggleButton.disabled = false;
            browserToggleButton.classList.remove('is-active');
            return;
        }

        browserStateLabel.textContent = browserNotificationsEnabled
            ? 'Notifications navigateur actives'
            : 'Notifications navigateur inactives';
        browserToggleLabel.textContent = browserNotificationsEnabled ? 'Desactiver navigateur' : 'Activer navigateur';
        browserToggleButton.disabled = false;
        browserToggleButton.classList.toggle('is-active', browserNotificationsEnabled);
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
        if (!ttsEnabled || !speechSupported || document.hidden || activeNotification || queue.length === 0) {
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
        const utterance = new window.SpeechSynthesisUtterance(
            normalizeText(notification.spokenText || notification.description || notification.title || '')
        );
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

    async function clearAllNotifications() {
        if (!clearUrl) {
            return;
        }

        if (clearButton) {
            clearButton.disabled = true;
        }

        try {
            const response = await fetch(clearUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            notifications = [];
            queue = [];
            queuedIds = new Set();
            activeNotification = null;
            renderNotifications();

            if (countNode) {
                countNode.textContent = '0';
            }

            saveSeenNotificationIds(new Set());
            saveLatestNotifiedId(null);
        } catch (error) {
            console.error('Notification clear failed.', error);
        } finally {
            if (clearButton) {
                clearButton.disabled = false;
            }
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

    async function playNotificationSound() {
        if (!notificationSound) {
            return;
        }

        try {
            if (!soundReady) {
                await primeNotificationSound();
            }

            notificationSound.currentTime = 0;
            notificationSound.muted = false;
            await notificationSound.play();
            soundReady = true;
        } catch (error) {
            playFallbackTone();
            soundReady = false;
        }
    }

    async function primeNotificationSound() {
        if (!notificationSound) {
            return;
        }

        try {
            notificationSound.currentTime = 0;
            notificationSound.muted = true;
            await notificationSound.play();
            notificationSound.pause();
            notificationSound.currentTime = 0;
            notificationSound.muted = false;
            soundReady = true;
        } catch (error) {
            soundReady = false;
        }
    }

    async function maybeNotifyBrowser(newNotifications) {
        if (!browserNotificationSupported || !browserNotificationsEnabled) {
            return;
        }

        browserPermission = Notification.permission;
        updateBrowserUi();

        if (browserPermission !== 'granted') {
            return;
        }

        const latest = [...newNotifications]
            .sort((left, right) => new Date(right.createdAt || 0).getTime() - new Date(left.createdAt || 0).getTime())
            .slice(0, 3);

        latest.forEach((notification) => {
            showBrowserNotification(notification);
        });
    }

    function showBrowserNotification(notification) {
        if (!browserNotificationSupported || Notification.permission !== 'granted') {
            return;
        }

        try {
            const instance = new Notification(notification.title || 'Nouvelle notification', {
                body: notification.description || '',
                icon: '/assets/logo.png',
                tag: `advisora-${notification.id || Date.now()}`,
                renotify: false,
            });

            instance.onclick = () => {
                window.focus();
                if (notification.targetUrl) {
                    window.location.href = notification.targetUrl;
                }
                instance.close();
            };

            window.setTimeout(() => {
                instance.close();
            }, 8000);
        } catch (error) {
            // noop
        }
    }

    function playFallbackTone() {
        const Context = window.AudioContext || window.webkitAudioContext;
        if (!Context) {
            return;
        }

        try {
            const context = new Context();
            const oscillator = context.createOscillator();
            const gainNode = context.createGain();

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, context.currentTime);

            gainNode.gain.setValueAtTime(0.0001, context.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.06, context.currentTime + 0.02);
            gainNode.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);

            oscillator.connect(gainNode);
            gainNode.connect(context.destination);

            oscillator.start();
            oscillator.stop(context.currentTime + 0.25);
            oscillator.onended = () => {
                void context.close();
            };
        } catch (error) {
            // noop
        }
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

    function createNotificationSound(url) {
        const normalized = String(url || '').trim();
        if (!normalized) {
            return null;
        }

        try {
            const audio = new Audio(normalized);
            audio.preload = 'auto';
            return audio;
        } catch (error) {
            return null;
        }
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

    function saveAudioPreference(enabled) {
        try {
            window.localStorage.setItem(audioStorageKey, enabled ? '1' : '0');
        } catch (error) {
            console.error('Unable to persist notification audio preference.', error);
        }
    }

    function loadTtsPreference() {
        try {
            const stored = window.localStorage.getItem(ttsStorageKey);
            if (stored === null) {
                return true;
            }

            return stored === '1';
        } catch (error) {
            return true;
        }
    }

    function saveTtsPreference(enabled) {
        try {
            window.localStorage.setItem(ttsStorageKey, enabled ? '1' : '0');
        } catch (error) {
            // noop
        }
    }

    function loadBrowserPreference() {
        try {
            const stored = window.localStorage.getItem(browserStorageKey);
            if (stored === null) {
                return true;
            }

            return stored === '1';
        } catch (error) {
            return true;
        }
    }

    function saveBrowserPreference(enabled) {
        try {
            window.localStorage.setItem(browserStorageKey, enabled ? '1' : '0');
        } catch (error) {
            console.error('Unable to persist browser notification preference.', error);
        }
    }

    function setAudioFromBrowserToggle() {
        audioEnabled = !!browserNotificationsEnabled;
        saveAudioPreference(audioEnabled);
        updateAudioUi();
    }

    function loadSeenNotificationIds() {
        try {
            const raw = window.localStorage.getItem(seenStorageKey);
            if (!raw) {
                return new Set();
            }

            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return new Set();
            }

            return new Set(parsed.filter((id) => Number.isInteger(id)));
        } catch (error) {
            return new Set();
        }
    }

    function saveSeenNotificationIds(ids) {
        try {
            const compact = [...ids].slice(-400);
            window.localStorage.setItem(seenStorageKey, JSON.stringify(compact));
        } catch (error) {
            // noop
        }
    }

    function hasSeenNotificationCache() {
        try {
            return window.localStorage.getItem(seenStorageKey) !== null;
        } catch (error) {
            return false;
        }
    }

    function loadLatestNotifiedId() {
        try {
            const raw = window.localStorage.getItem(latestStorageKey);
            if (!raw) {
                return null;
            }

            const parsed = Number.parseInt(raw, 10);
            return Number.isInteger(parsed) ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    function saveLatestNotifiedId(id) {
        try {
            if (typeof id !== 'number' || !Number.isInteger(id)) {
                window.localStorage.removeItem(latestStorageKey);
                return;
            }

            window.localStorage.setItem(latestStorageKey, String(id));
        } catch (error) {
            // noop
        }
    }
});

