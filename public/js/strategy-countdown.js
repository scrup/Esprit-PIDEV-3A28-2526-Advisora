(function () {
    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function formatRemainingTime(milliseconds) {
        const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }

    function updateCountdownNode(node, nowTimestamp) {
        const valueNode = node.querySelector('[data-countdown-value]');
        if (!valueNode) {
            return;
        }

        const endAtRaw = node.dataset.endAt || '';
        const endTimestamp = Date.parse(endAtRaw);
        if (Number.isNaN(endTimestamp)) {
            valueNode.textContent = '--:--:--';
            node.classList.add('is-expired');
            return;
        }

        const remaining = endTimestamp - nowTimestamp;
        if (remaining <= 0) {
            valueNode.textContent = '00:00:00';
            node.classList.add('is-expired');
            return;
        }

        valueNode.textContent = formatRemainingTime(remaining);
        node.classList.remove('is-expired');
    }

    function initStrategyCountdowns() {
        const countdownNodes = Array.from(document.querySelectorAll('[data-strategy-countdown]'));
        if (countdownNodes.length === 0) {
            return;
        }

        const tick = function () {
            const now = Date.now();
            countdownNodes.forEach(function (node) {
                updateCountdownNode(node, now);
            });
        };

        tick();
        window.setInterval(tick, 1000);
    }

    document.addEventListener('DOMContentLoaded', initStrategyCountdowns);
})();

