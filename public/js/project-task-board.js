document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-task-board="enabled"]').forEach((board) => {
        initializeBoard(board);
    });
});

function initializeBoard(board) {
    let draggedCard = null;

    board.querySelectorAll('[data-task-card]').forEach((card) => {
        card.addEventListener('dragstart', () => {
            draggedCard = card;
            card.classList.add('pm-task-card-dragging');
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('pm-task-card-dragging');
            draggedCard = null;
            board.querySelectorAll('.pm-task-dropzone-active').forEach((zone) => {
                zone.classList.remove('pm-task-dropzone-active');
            });
        });
    });

    board.querySelectorAll('[data-task-dropzone]').forEach((dropzone) => {
        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropzone.classList.add('pm-task-dropzone-active');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('pm-task-dropzone-active');
        });

        dropzone.addEventListener('drop', async (event) => {
            event.preventDefault();
            dropzone.classList.remove('pm-task-dropzone-active');

            if (!draggedCard) {
                return;
            }

            const nextStatus = dropzone.dataset.taskDropzone;
            const currentStatus = draggedCard.dataset.taskStatus;

            if (!nextStatus || currentStatus === nextStatus) {
                return;
            }

            const moveUrl = draggedCard.dataset.moveUrl;
            const moveToken = draggedCard.dataset.moveToken;

            try {
                draggedCard.classList.add('pm-task-card-loading');

                const response = await fetch(moveUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({
                        status: nextStatus,
                        _token: moveToken,
                    }),
                });

                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Le deplacement de la tache a echoue.');
                }

                draggedCard.dataset.taskStatus = payload.status;
                const statusBadge = draggedCard.querySelector('.pm-task-status');
                if (statusBadge) {
                    statusBadge.textContent = payload.statusLabel;
                    statusBadge.className = `pm-task-status pm-task-status-${payload.statusCssClass}`;
                }
                const accent = draggedCard.querySelector('.pm-task-card-accent');
                if (accent) {
                    accent.className = `pm-task-card-accent pm-task-card-accent-${payload.statusCssClass}`;
                }

                dropzone.appendChild(draggedCard);
                draggedCard.classList.add('pm-task-card-success');
                refreshBoard(board, payload.progress);
                window.setTimeout(() => {
                    draggedCard.classList.remove('pm-task-card-success');
                }, 500);
            } catch (error) {
                window.alert(error.message || 'Le deplacement de la tache a echoue.');
            } finally {
                draggedCard.classList.remove('pm-task-card-loading');
            }
        });
    });
}

function refreshBoard(board, progress) {
    board.querySelectorAll('[data-task-column]').forEach((column) => {
        const dropzone = column.querySelector('[data-task-dropzone]');
        const cards = dropzone ? dropzone.querySelectorAll('[data-task-card]') : [];
        const countLabel = column.querySelector('[data-task-count]');
        const emptyState = dropzone ? dropzone.querySelector('[data-task-empty]') : null;

        if (countLabel) {
            countLabel.textContent = `${cards.length} tache(s)`;
        }

        if (emptyState) {
            emptyState.style.display = cards.length > 0 ? 'none' : '';
        } else if (cards.length === 0 && dropzone) {
            const paragraph = document.createElement('p');
            paragraph.className = 'pm-empty-text';
            paragraph.dataset.taskEmpty = 'true';
            paragraph.textContent = 'Aucune tache dans cette colonne.';
            dropzone.appendChild(paragraph);
        }
    });

    const statCards = board.querySelectorAll('.pm-task-stat-value');
    if (statCards[0]) {
        const total = board.querySelectorAll('[data-task-card]').length;
        statCards[0].textContent = String(total);
    }
    if (statCards[1]) {
        statCards[1].textContent = `${Math.round(progress)}%`;
    }

    const progressLabels = board.querySelectorAll('.pm-task-progress-meta strong');
    progressLabels.forEach((label) => {
        label.textContent = `${Number(progress).toFixed(2)}%`;
    });

    board.querySelectorAll('.pm-progress-bar').forEach((bar) => {
        bar.style.width = `${progress}%`;
    });

    const counters = board.querySelectorAll('.pm-task-counter-row span');
    const todo = board.querySelector('[data-task-column="TODO"] [data-task-count]');
    const progressCount = board.querySelector('[data-task-column="IN_PROGRESS"] [data-task-count]');
    const done = board.querySelector('[data-task-column="DONE"] [data-task-count]');
    if (counters[0] && todo) counters[0].textContent = `To Do : ${extractCount(todo.textContent)}`;
    if (counters[1] && progressCount) counters[1].textContent = `In Progress : ${extractCount(progressCount.textContent)}`;
    if (counters[2] && done) counters[2].textContent = `Done : ${extractCount(done.textContent)}`;
}

function extractCount(text) {
    const match = String(text).match(/\d+/);
    return match ? match[0] : '0';
}
