document.addEventListener('DOMContentLoaded', () => {
    const countdownElements = document.querySelectorAll('[data-strategy-countdown]');

    function updateCountdown(el) {
        const endDate = new Date(el.dataset.endAt);
        const now = new Date();

        if (isNaN(endDate)) return;

        const totalSeconds = (endDate - now) / 1000;
        const isExpired = totalSeconds <= 0;

        if (isExpired) {
            el.classList.add('is-expired');
            const displayEl = el.querySelector('[data-countdown-display]');
            if (displayEl) displayEl.textContent = 'Expiré';
            const progressBar = el.querySelector('[data-progress-bar]');
            if (progressBar) progressBar.style.width = '100%';
            return;
        }

        el.classList.remove('is-expired');

        // Calculate time units
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = Math.floor(totalSeconds % 60);

        // Format display (always show 4 groups)
        const displayEl = el.querySelector('[data-countdown-display]');
        if (displayEl) {
            displayEl.textContent = `${days} ${hours.toString().padStart(2, '0')} ${minutes.toString().padStart(2, '0')} ${seconds.toString().padStart(2, '0')}`;
        }

        // Progress bar based on elapsed time
        const startDate = new Date(endDate);
        startDate.setMonth(startDate.getMonth() - 1); // approximate original lockedAt (adjust if you store it)
        // Better: store `data-start-at` in Twig to have exact progress. 
        // For now we compute a percentage from today to endDate over the total duration (in seconds).
        // Since we don't have the original start, we assume total duration = (endDate - startDate).
        // You can pass data-start-at="{{ strategy.lockedAt|date('c') }}" for exact progress.
        // I'll implement a fallback: if no start, we don't show progress.
        const startAttr = el.dataset.startAt;
        if (startAttr && progressBar) {
            const startDateExact = new Date(startAttr);
            const totalDuration = (endDate - startDateExact) / 1000;
            const elapsed = totalDuration - totalSeconds;
            let percent = (elapsed / totalDuration) * 100;
            percent = Math.min(100, Math.max(0, percent));
            progressBar.style.width = `${percent}%`;
        }
    }

    function refreshAllCountdowns() {
        countdownElements.forEach(el => updateCountdown(el));
    }

    // Run every second
    refreshAllCountdowns();
    setInterval(refreshAllCountdowns, 1000);
});