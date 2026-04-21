document.addEventListener('DOMContentLoaded', () => {
    initializeProjectTts();
});

const TTS_ENABLED_KEY = 'advisora_tts_enabled';
const BACK_PROJECT_KEY = 'advisora_tts_back_project_id';

let ttsQueue = [];
let currentAudio = null;
let currentObjectUrl = null;
let projectPollingTimer = null;
let backPollingTimer = null;
let playbackToken = 0;

function initializeProjectTts() {
    bindTtsToggles();
    bindManualSpeechButtons();
    refreshAutomaticTts();
}

function bindTtsToggles() {
    document.querySelectorAll('[data-tts-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextState = !isTtsEnabled();
            setTtsEnabled(nextState);
            refreshAutomaticTts();
        });
    });

    syncTtsToggleLabels();
}

function bindManualSpeechButtons() {
    document.querySelectorAll('[data-tts-play]').forEach((button) => {
        button.addEventListener('click', () => {
            const audioUrl = button.dataset.audioUrl;
            if (!audioUrl) {
                return;
            }

            enqueueAudio(audioUrl, true);
        });
    });
}

function refreshAutomaticTts() {
    syncTtsToggleLabels();
    stopProjectPolling();
    stopBackPolling();

    if (!isTtsEnabled()) {
        return;
    }

    playPendingSubmissionConfirmation();
    startProjectPolling();
    startBackOfficePolling();
}

function isTtsEnabled() {
    return window.localStorage.getItem(TTS_ENABLED_KEY) === '1';
}

function setTtsEnabled(enabled) {
    window.localStorage.setItem(TTS_ENABLED_KEY, enabled ? '1' : '0');
}

function syncTtsToggleLabels() {
    const enabled = isTtsEnabled();

    document.querySelectorAll('[data-tts-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        const label = button.querySelector('[data-tts-toggle-label]');
        if (label) {
            label.textContent = enabled ? 'Annonces vocales actives' : 'Activer les annonces vocales';
        }
    });
}

function playPendingSubmissionConfirmation() {
    const event = document.querySelector('[data-project-tts-submission]');
    if (!event || event.dataset.played === '1') {
        return;
    }

    const audioUrl = event.dataset.audioUrl;
    if (!audioUrl) {
        return;
    }

    event.dataset.played = '1';
    enqueueAudio(audioUrl, false);
}

function startProjectPolling() {
    const context = document.querySelector('[data-project-tts-manage]');
    if (!context) {
        return;
    }

    const projectId = Number.parseInt(context.dataset.projectId || '0', 10);
    if (!projectId) {
        return;
    }

    const storageKey = `advisora_tts_project_decision_${projectId}`;
    const initialDecisionId = Number.parseInt(context.dataset.latestDecisionId || '0', 10);
    const storedDecisionId = Number.parseInt(window.localStorage.getItem(storageKey) || '0', 10);
    const baselineDecisionId = Math.max(initialDecisionId, storedDecisionId);

    window.localStorage.setItem(storageKey, String(baselineDecisionId));

    const poll = async () => {
        const latestStoredId = Number.parseInt(window.localStorage.getItem(storageKey) || '0', 10);
        const url = new URL(context.dataset.feedUrl, window.location.origin);
        url.searchParams.set('afterDecisionId', String(latestStoredId));

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (typeof payload.latestDecisionId === 'number') {
                window.localStorage.setItem(storageKey, String(payload.latestDecisionId));
            }

            if (payload.hasNewAnnouncement && payload.audioUrl) {
                enqueueAudio(payload.audioUrl, false);
            }
        } catch (error) {
            console.error('Project TTS polling failed.', error);
        }
    };

    projectPollingTimer = window.setInterval(poll, 10000);
}

function startBackOfficePolling() {
    const context = document.querySelector('[data-back-project-tts-feed]');
    if (!context) {
        return;
    }

    const storedProjectId = window.localStorage.getItem(BACK_PROJECT_KEY);
    let hasBaseline = storedProjectId !== null;

    const poll = async () => {
        const afterProjectId = Number.parseInt(window.localStorage.getItem(BACK_PROJECT_KEY) || '0', 10);
        const url = new URL(context.dataset.backProjectTtsFeed, window.location.origin);
        url.searchParams.set('afterProjectId', String(afterProjectId));

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const maxProjectId = Number.parseInt(String(payload.maxProjectId || afterProjectId), 10);
            if (!hasBaseline) {
                window.localStorage.setItem(BACK_PROJECT_KEY, String(maxProjectId));
                hasBaseline = true;
                return;
            }

            if (Array.isArray(payload.newProjects)) {
                payload.newProjects.forEach((project) => {
                    if (project && project.audioUrl) {
                        enqueueAudio(project.audioUrl, false);
                    }
                });
            }

            window.localStorage.setItem(BACK_PROJECT_KEY, String(maxProjectId));
        } catch (error) {
            console.error('Back office TTS polling failed.', error);
        }
    };

    poll();
    backPollingTimer = window.setInterval(poll, 15000);
}

function stopProjectPolling() {
    if (projectPollingTimer) {
        window.clearInterval(projectPollingTimer);
        projectPollingTimer = null;
    }
}

function stopBackPolling() {
    if (backPollingTimer) {
        window.clearInterval(backPollingTimer);
        backPollingTimer = null;
    }
}

function enqueueAudio(audioUrl, replaceCurrent) {
    if (!audioUrl) {
        return;
    }

    if (replaceCurrent) {
        playbackToken += 1;
        ttsQueue = [audioUrl];
        stopCurrentAudio();
    } else {
        ttsQueue.push(audioUrl);
    }

    if (!currentAudio) {
        void playNextAudio();
    }
}

async function playNextAudio() {
    if (currentAudio || ttsQueue.length === 0) {
        return;
    }

    const nextAudioUrl = ttsQueue.shift();
    const activeToken = playbackToken;
    if (!nextAudioUrl) {
        return;
    }

    try {
        const response = await fetch(nextAudioUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(await response.text());
        }

        const blob = await response.blob();
        if (activeToken !== playbackToken) {
            return;
        }

        currentObjectUrl = window.URL.createObjectURL(blob);
        currentAudio = new Audio(currentObjectUrl);

        currentAudio.addEventListener('ended', handleAudioFinished, { once: true });
        currentAudio.addEventListener('error', handleAudioError, { once: true });

        await currentAudio.play();
    } catch (error) {
        const message = error instanceof Error && error.message
            ? error.message
            : 'Lecture vocale indisponible pour le moment.';
        showTtsNotice(message);
        console.error('Unable to play audio.', error);
        cleanupAudioState();
        void playNextAudio();
    }
}

function handleAudioFinished() {
    cleanupAudioState();
    void playNextAudio();
}

function handleAudioError() {
    showTtsNotice('Le navigateur n a pas pu lire l annonce vocale.');
    cleanupAudioState();
    void playNextAudio();
}

function stopCurrentAudio() {
    playbackToken += 1;

    if (currentAudio) {
        currentAudio.pause();
    }

    cleanupAudioState();
}

function cleanupAudioState() {
    if (currentAudio) {
        currentAudio.src = '';
        currentAudio = null;
    }

    if (currentObjectUrl) {
        window.URL.revokeObjectURL(currentObjectUrl);
        currentObjectUrl = null;
    }
}

function showTtsNotice(message) {
    const existing = document.querySelector('[data-tts-notice]');
    if (existing) {
        existing.remove();
    }

    const notice = document.createElement('div');
    notice.className = 'flash-message flash-error';
    notice.dataset.ttsNotice = 'true';
    notice.textContent = message;
    document.body.prepend(notice);

    window.setTimeout(() => {
        notice.remove();
    }, 4000);
}
