document.addEventListener('DOMContentLoaded', () => {
    const textButtons = Array.from(document.querySelectorAll('[data-project-tts-button]'));
    const audioButtons = Array.from(document.querySelectorAll('[data-tts-play]'));
    const autoNode = document.querySelector('[data-project-tts-auto]');
    const synth = window.speechSynthesis;
    const speechSupported = !!synth && typeof window.SpeechSynthesisUtterance !== 'undefined';

    if (textButtons.length === 0 && audioButtons.length === 0 && !autoNode) {
        return;
    }

    const defaultLabels = new WeakMap();
    let availableVoices = [];
    let activeTextButton = null;
    let activeAudioButton = null;
    let currentAudio = null;
    let currentObjectUrl = null;
    let playbackToken = 0;
    let pollingTimer = null;
    const autoConfig = readAutoConfig(autoNode);

    if (speechSupported) {
        const loadVoices = () => {
            availableVoices = synth.getVoices();
        };

        loadVoices();

        if ('onvoiceschanged' in synth) {
            synth.onvoiceschanged = loadVoices;
        }
    } else if (textButtons.length > 0) {
        disableButtons(textButtons, 'Lecture vocale indisponible sur ce navigateur.');
    }

    textButtons.forEach((button) => {
        defaultLabels.set(button, getButtonLabel(button));
        button.addEventListener('click', () => {
            if (!speechSupported) {
                return;
            }

            const text = normalizeText(button.dataset.projectTtsText || '');
            if (!text) {
                return;
            }

            if (button === activeTextButton && (synth.speaking || synth.pending)) {
                stopAllPlayback();
                return;
            }

            stopAllPlayback();
            startSpeaking(text, button, { rememberDecision: false });
        });
    });

    audioButtons.forEach((button) => {
        defaultLabels.set(button, getButtonLabel(button));
        button.addEventListener('click', () => {
            const audioUrl = button.dataset.audioUrl || '';
            if (!audioUrl) {
                return;
            }

            if (button === activeAudioButton && currentAudio) {
                stopAllPlayback();
                return;
            }

            stopAllPlayback();
            void playRemoteAudio(audioUrl, button);
        });
    });

    if (speechSupported && autoConfig) {
        maybeSpeakLatestDecision(autoConfig.announcementText, autoConfig.latestDecisionId);
        pollingTimer = window.setInterval(() => {
            void pollForNewDecision();
        }, 10000);
    }

    window.addEventListener('beforeunload', () => {
        if (pollingTimer) {
            window.clearInterval(pollingTimer);
        }

        stopAllPlayback();
    });

    window.addEventListener('advisora:tts-stop', stopAllPlayback);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopAllPlayback();
        }
    });

    function startSpeaking(text, button, options = {}) {
        if (!speechSupported) {
            return;
        }

        playbackToken += 1;
        activeTextButton = button || null;

        if (button) {
            setButtonLabel(button, 'Arreter');
        }

        const token = playbackToken;
        const segments = splitText(text);
        playSegments(segments, button, token, options);
    }

    function playSegments(segments, button, token, options) {
        if (!speechSupported || token !== playbackToken) {
            return;
        }

        const segment = segments.shift();
        if (!segment) {
            resetTextButton(button, token, options);
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
            resetTextButton(button, token, options);
        };

        synth.resume();
        synth.speak(utterance);
    }

    async function playRemoteAudio(audioUrl, button) {
        playbackToken += 1;
        const token = playbackToken;
        activeAudioButton = button || null;

        if (button) {
            setButtonLabel(button, 'Arreter');
        }

        try {
            const response = await fetch(audioUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Audio response failed.');
            }

            const blob = await response.blob();
            if (token !== playbackToken) {
                return;
            }

            currentObjectUrl = window.URL.createObjectURL(blob);
            currentAudio = new Audio(currentObjectUrl);
            currentAudio.addEventListener('ended', () => {
                resetAudioButton(button, token);
            }, { once: true });
            currentAudio.addEventListener('error', () => {
                resetAudioButton(button, token);
            }, { once: true });

            await currentAudio.play();
        } catch (error) {
            resetAudioButton(button, token);
            console.error('Project remote audio playback failed.', error);
        }
    }

    async function pollForNewDecision() {
        if (!speechSupported || !autoConfig || !autoConfig.feedUrl || document.hidden) {
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

    function maybeSpeakLatestDecision(text, decisionId) {
        if (!speechSupported || !autoConfig || decisionId <= 0 || !text || hasHeardDecision(decisionId)) {
            return;
        }

        stopAllPlayback();
        startSpeaking(text, null, {
            rememberDecision: true,
            decisionId,
        });
    }

    function stopAllPlayback() {
        stopTextPlayback();
        stopAudioPlayback();
    }

    function stopTextPlayback() {
        if (!speechSupported) {
            return;
        }

        playbackToken += 1;
        synth.cancel();

        if (activeTextButton) {
            setButtonLabel(activeTextButton, defaultLabels.get(activeTextButton) || 'Ecouter');
            activeTextButton = null;
        }
    }

    function stopAudioPlayback() {
        playbackToken += 1;

        if (currentAudio) {
            currentAudio.pause();
            currentAudio.src = '';
            currentAudio = null;
        }

        if (currentObjectUrl) {
            window.URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }

        if (activeAudioButton) {
            setButtonLabel(activeAudioButton, defaultLabels.get(activeAudioButton) || 'Lire');
            activeAudioButton = null;
        }
    }

    function resetTextButton(button, token, options = {}) {
        if (token !== playbackToken) {
            return;
        }

        if (button) {
            setButtonLabel(button, defaultLabels.get(button) || 'Ecouter');
        }

        if (options.rememberDecision && typeof options.decisionId === 'number' && options.decisionId > 0) {
            storeHeardDecision(options.decisionId);
        }

        activeTextButton = null;
    }

    function resetAudioButton(button, token) {
        if (token !== playbackToken) {
            return;
        }

        if (button) {
            setButtonLabel(button, defaultLabels.get(button) || 'Lire');
        }

        if (currentAudio) {
            currentAudio.src = '';
            currentAudio = null;
        }

        if (currentObjectUrl) {
            window.URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }

        activeAudioButton = null;
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
        return button.querySelector('[data-project-tts-label]')?.textContent?.trim()
            || button.textContent.trim();
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
