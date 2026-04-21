let typeDonutChart = null;

let typeLegend;
let typeDonutTotal;

const STATUS = {
    PENDING: 'pending',
    APPROVED: 'approved',
    REJECTED: 'rejected',
    IN_PROGRESS: 'in-progress',
    UNASSIGNED: 'unassigned'
};

const TYPE_COLORS = [
    '#C37D5D',
    '#7D8F6E',
    '#E3C9A0',
    '#4E6FAE',
    '#A56E5F',
    '#7C9A92',
    '#B69B5B',
    '#9A7AA0',
    '#5F8C5A'
];

document.addEventListener('DOMContentLoaded', () => {
    setActiveLink();
    initElements();
    initCharts();
    initEventListeners();
    updateTypeDistribution();
    initObjectiveModal();
});

function setActiveLink() {
    const currentPath = window.location.pathname;
    document.querySelectorAll('.back-nav a').forEach((link) => {
        link.classList.toggle('active', link.getAttribute('href') === currentPath);
    });
}

function initElements() {
    typeLegend = document.getElementById('typeLegend');
    typeDonutTotal = document.getElementById('typeDonutTotal');
}

function initCharts() {
    const donutCtx = document.getElementById('strategyTypeDonutChart')?.getContext('2d');

    if (!donutCtx) {
        return;
    }

    typeDonutChart = new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [],
                borderWidth: 0,
                cutout: '65%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = typeDonutChart?.data.datasets[0].data.reduce((sum, item) => sum + item, 0) || 1;
                            const percentage = Math.round((value / total) * 100);

                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function updateTypeDistribution() {
    const cards = getStrategyCards();
    const counts = new Map();

    cards.forEach((card) => {
        const type = card.dataset.type || 'non-defini';
        counts.set(type, (counts.get(type) || 0) + 1);
    });

    const entries = [...counts.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));

    if (typeDonutTotal) {
        typeDonutTotal.textContent = String(cards.length);
    }

    renderTypeLegend(entries);
    updateTypeDonutChart(entries);
}

function renderTypeLegend(entries) {
    if (!typeLegend) {
        return;
    }

    typeLegend.innerHTML = '';

    if (entries.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'legend-label';
        empty.textContent = 'Aucune strategie visible';
        typeLegend.appendChild(empty);
        return;
    }

    entries.forEach(([type, count], index) => {
        const item = document.createElement('div');
        item.className = 'legend-item';

        const color = document.createElement('span');
        color.className = 'legend-color';
        color.style.backgroundColor = TYPE_COLORS[index % TYPE_COLORS.length];

        const label = document.createElement('span');
        label.className = 'legend-label';
        label.textContent = formatTypeLabel(type);

        const value = document.createElement('span');
        value.className = 'legend-value';
        value.textContent = String(count);

        item.appendChild(color);
        item.appendChild(label);
        item.appendChild(value);
        typeLegend.appendChild(item);
    });
}

function updateTypeDonutChart(entries) {
    if (!typeDonutChart) {
        return;
    }

    typeDonutChart.data.labels = entries.map(([type]) => formatTypeLabel(type));
    typeDonutChart.data.datasets[0].data = entries.map(([, count]) => count);
    typeDonutChart.data.datasets[0].backgroundColor = entries.map((_, index) => TYPE_COLORS[index % TYPE_COLORS.length]);
    typeDonutChart.update();
}

function getStrategyCards() {
    return Array.from(document.querySelectorAll('.strategy-card'));
}

function formatTypeLabel(type) {
    if (!type || type === 'non-defini') {
        return 'Non defini';
    }

    return type
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function initEventListeners() {
    document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const confirmMessage = form.dataset.confirmMessage || 'Confirmer cette action ?';

            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    });
}

function initObjectiveModal() {
    const modal = document.getElementById('objectifModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const objectifForm = document.getElementById('objectifForm');
    const objectifModalTitle = document.getElementById('objectifModalTitle');
    const objectifSubmitText = document.getElementById('objectifSubmitText');
    const selectedStrategyName = document.getElementById('selectedStrategyName');
    const strategyIdInput = document.getElementById('strategyId');
    const objectifNameInput = document.getElementById('objectifName');
    const objectifDescriptionInput = document.getElementById('objectifDescription');
    const objectifTokenInput = document.getElementById('objectifToken');
    const priorityBtns = document.querySelectorAll('.priority-btn');
    const priorityInput = document.getElementById('objectifPriority');

    const setPriority = (priority) => {
        const normalizedPriority = priority || 'medium';

        priorityBtns.forEach((button) => {
            button.classList.toggle('active', button.dataset.priority === normalizedPriority);
        });

        if (priorityInput) {
            priorityInput.value = normalizedPriority;
        }
    };

    const resetModalToCreate = () => {
        if (objectifForm) {
            objectifForm.action = objectifForm.dataset.createAction || objectifForm.action;
            objectifForm.reset();
        }

        if (objectifTokenInput && objectifForm?.dataset.createToken) {
            objectifTokenInput.value = objectifForm.dataset.createToken;
        }

        if (objectifModalTitle) {
            objectifModalTitle.textContent = 'Attribuer un objectif';
        }

        if (objectifSubmitText) {
            objectifSubmitText.textContent = "Attribuer l'objectif";
        }

        setPriority('medium');
    };

    const closeModal = () => {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    document.querySelectorAll('.btn-attribuer-objectif').forEach((btn) => {
        btn.addEventListener('click', () => {
            resetModalToCreate();

            if (selectedStrategyName) {
                selectedStrategyName.textContent = btn.dataset.name || '-';
            }

            if (strategyIdInput) {
                strategyIdInput.value = btn.dataset.id || '';
            }

            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    document.querySelectorAll('.btn-edit-objective').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (selectedStrategyName) {
                selectedStrategyName.textContent = btn.dataset.strategyName || '-';
            }

            if (strategyIdInput) {
                strategyIdInput.value = btn.dataset.strategyId || '';
            }

            if (objectifForm) {
                objectifForm.action = btn.dataset.action || objectifForm.action;
            }

            if (objectifTokenInput) {
                objectifTokenInput.value = btn.dataset.token || '';
            }

            if (objectifModalTitle) {
                objectifModalTitle.textContent = 'Modifier un objectif';
            }

            if (objectifSubmitText) {
                objectifSubmitText.textContent = "Enregistrer l'objectif";
            }

            if (objectifNameInput) {
                objectifNameInput.value = btn.dataset.name || '';
            }

            if (objectifDescriptionInput) {
                objectifDescriptionInput.value = btn.dataset.description || '';
            }

            setPriority(btn.dataset.priority || 'medium');

            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    closeModalBtn?.addEventListener('click', closeModal);
    cancelModalBtn?.addEventListener('click', closeModal);

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    priorityBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            setPriority(btn.dataset.priority || 'medium');
        });
    });

    objectifForm?.addEventListener('submit', (e) => {
        const objectifName = objectifNameInput?.value.trim() || '';

        if (!objectifName) {
            e.preventDefault();
            showToast('Veuillez entrer un nom pour l objectif');
            return;
        }

        if (!strategyIdInput?.value) {
            e.preventDefault();
            showToast('Aucune strategie selectionnee');
        }
    });
}

function calculateROI(budget, gainEstime) {
    if (!budget || budget === 0) {
        return gainEstime || 0;
    }

    return ((gainEstime || 0) / budget) * 100;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-TN', {
        style: 'currency',
        currency: 'TND',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function formatPercentage(value) {
    const sign = value >= 0 ? '+' : '';
    return `${sign}${value}%`;
}

function getStatusCssClass(status) {
    switch (status) {
        case STATUS.PENDING:
            return 'pending';
        case STATUS.APPROVED:
            return 'approved';
        case STATUS.REJECTED:
            return 'rejected';
        case STATUS.IN_PROGRESS:
            return 'in-progress';
        case STATUS.UNASSIGNED:
            return 'unassigned';
        default:
            return 'unknown';
    }
}

function showToast(message) {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

window.showToast = showToast;
window.calculateROI = calculateROI;
window.formatCurrency = formatCurrency;
window.formatPercentage = formatPercentage;
window.getStatusCssClass = getStatusCssClass;
