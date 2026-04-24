document.addEventListener('DOMContentLoaded', () => {
    const buttons = Array.from(document.querySelectorAll('[data-project-tts-button]'));
    const autoNode = document.querySelector('[data-project-tts-auto]');
    const synth = window.speechSynthesis;

    if (buttons.length === 0 && !autoNode) {
        return;
    }

    if (!synth || typeof window.SpeechSynthesisUtterance === 'undefined') {
        if (buttons.length > 0) {
            disableButtons(buttons, 'Lecture vocale indisponible sur ce navigateur.');
        }

        return;
    }

    const autoConfig = readAutoConfig(autoNode);
    const defaultLabels = new WeakMap();
    let availableVoices = [];
    let activeButton = null;
    let playbackToken = 0;
    let pollingTimer = null;

    const loadVoices = () => {
        availableVoices = synth.getVoices();
    };

    loadVoices();

    if ('onvoiceschanged' in synth) {
        synth.onvoiceschanged = loadVoices;
    }

    if (buttons.length === 0) {
        initializeAutomaticAnnouncements();
    } else {
        buttons.forEach((button) => {
            defaultLabels.set(button, getButtonLabel(button));
            button.addEventListener('click', () => {
                const text = normalizeText(button.dataset.projectTtsText || '');
                if (!text) {
                    return;
                }

                if (button === activeButton && (synth.speaking || synth.pending)) {
                    stopSpeaking();
                    return;
                }

                startSpeaking(text, button, { rememberDecision: false });
            });
        });

        initializeAutomaticAnnouncements();
    }

    window.addEventListener('beforeunload', () => {
        if (pollingTimer) {
            window.clearInterval(pollingTimer);
        }

        stopSpeaking();
    });
    window.addEventListener('advisora:tts-stop', stopSpeaking);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopSpeaking();
        }
    });

    function initializeAutomaticAnnouncements() {
        if (!autoConfig) {
            return;
        }

        maybeSpeakLatestDecision(autoConfig.announcementText, autoConfig.latestDecisionId);
        pollingTimer = window.setInterval(() => {
            void pollForNewDecision();
        }, 10000);
    }

    function startSpeaking(text, button, options = {}) {
        stopSpeaking();

        playbackToken += 1;
        activeButton = button || null;

        if (button) {
            setButtonLabel(button, 'Arreter');
        }

        const token = playbackToken;
        const segments = splitText(text);
        playSegments(segments, button, token, options);
    }

    function playSegments(segments, button, token, options) {
        if (token !== playbackToken) {
            return;
        }

        const segment = segments.shift();
        if (!segment) {
            resetButton(button, token, options);
            return;
        }

        const utterance = new window.SpeechSynthesisUtterance(segment);
        const voice = pickVoice();

        utterance.lang = voice?.lang || 'fr-FR';
        utterance.voice = voice || null;
        utterance.rate = 1;
        utterance.pitch = 1;

        utterance.onend = () => {
            playSegments(segments, button, token, options);
        };

        utterance.onerror = () => {
            resetButton(button, token, options);
        };

        synth.resume();
        synth.speak(utterance);
    }

    function stopSpeaking() {
        playbackToken += 1;
        synth.cancel();

        if (activeButton) {
            setButtonLabel(activeButton, defaultLabels.get(activeButton) || 'Ecouter');
            activeButton = null;
        }
    }

    function resetButton(button, token, options = {}) {
        if (token !== playbackToken) {
            return;
        }

        if (button) {
            setButtonLabel(button, defaultLabels.get(button) || 'Ecouter');
        }

        if (options.rememberDecision && typeof options.decisionId === 'number' && options.decisionId > 0) {
            storeHeardDecision(options.decisionId);
        }

        activeButton = null;
    }

    function maybeSpeakLatestDecision(text, decisionId) {
        if (!autoConfig || decisionId <= 0 || !text || hasHeardDecision(decisionId)) {
            return;
        }

        startSpeaking(text, null, {
            rememberDecision: true,
            decisionId,
        });
    }

    async function pollForNewDecision() {
        if (!autoConfig || !autoConfig.feedUrl || document.hidden) {
            return;
        }

        try {
            const response = await fetch(autoConfig.feedUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const latestDecisionId = Number(payload.latestDecisionId || 0);
            const announcementText = normalizeText(payload.announcementMessage || '');

            if (latestDecisionId > autoConfig.latestDecisionId && announcementText !== '') {
                autoConfig.latestDecisionId = latestDecisionId;
                autoConfig.announcementText = announcementText;
                maybeSpeakLatestDecision(announcementText, latestDecisionId);
            }
        } catch (error) {
            console.error('Project TTS polling failed.', error);
        }
    }

    function readAutoConfig(node) {
        if (!node) {
            return null;
        }

        const projectId = Number(node.dataset.projectId || 0);
        const latestDecisionId = Number(node.dataset.latestDecisionId || 0);
        const announcementText = normalizeText(node.dataset.announcementText || '');
        const feedUrl = node.dataset.feedUrl || '';

        if (projectId <= 0 || latestDecisionId <= 0 || announcementText === '' || feedUrl === '') {
            return null;
        }

        return {
            projectId,
            latestDecisionId,
            announcementText,
            feedUrl,
            storageKey: `advisora_project_tts_last_heard_${projectId}`,
        };
    }

    function hasHeardDecision(decisionId) {
        if (!autoConfig || !window.localStorage) {
            return false;
        }

        return Number(window.localStorage.getItem(autoConfig.storageKey) || 0) >= decisionId;
    }

    function storeHeardDecision(decisionId) {
        if (!autoConfig || !window.localStorage) {
            return;
        }

        window.localStorage.setItem(autoConfig.storageKey, String(decisionId));
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

    function splitText(text) {
        const sentenceMatches = text.match(/[^.!?]+[.!?]?/g) || [text];
        const sentences = sentenceMatches
            .map((sentence) => sentence.trim())
            .filter((sentence) => sentence !== '');

        const chunks = [];
        let currentChunk = '';

        sentences.forEach((sentence) => {
            const nextChunk = currentChunk === '' ? sentence : `${currentChunk} ${sentence}`;
            if (nextChunk.length > 220 && currentChunk !== '') {
                chunks.push(currentChunk);
                currentChunk = sentence;
                return;
            }

            currentChunk = nextChunk;
        });

        if (currentChunk !== '') {
            chunks.push(currentChunk);
        }

        return chunks.length > 0 ? chunks : [text];
    }

    function normalizeText(text) {
        return text.replace(/\s+/g, ' ').trim();
    }

    function getButtonLabel(button) {
        return button.querySelector('[data-project-tts-label]')?.textContent?.trim() || button.textContent.trim();
    }

    function setButtonLabel(button, label) {
        const labelNode = button.querySelector('[data-project-tts-label]');
        if (labelNode) {
            labelNode.textContent = label;
            return;
        }

        button.textContent = label;
    }
});

function disableButtons(buttons, title) {
    buttons.forEach((button) => {
        button.disabled = true;
        button.title = title;
    });
}
