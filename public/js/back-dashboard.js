document.addEventListener('DOMContentLoaded', () => {
    initCounters();
    setCurrentDate();
    initStrategyPerformanceChart();
    initInvestmentChart();
    initQuickActions();
    initEventButtons();
    initResourceButtons();
    initNavigation();
    initPassiveNavigationState();
});

function initPassiveNavigationState() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (!logoutBtn) {
        return;
    }

    logoutBtn.addEventListener('click', () => {
        logoutBtn.disabled = true;
        logoutBtn.classList.add('is-loading');
    }, { once: true });
}

function initCounters() {
    document.querySelectorAll('.counter').forEach((counter) => {
        const target = parseInt(counter.getAttribute('data-target') || '0', 10);
        const duration = 1500;
        const increment = target / (duration / 16);
        let current = 0;

        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counter.innerText = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.innerText = target;
            }
        };

        updateCounter();
    });
}

function setCurrentDate() {
    const dateElement = document.getElementById('currentDate');
    if (!dateElement) {
        return;
    }

    dateElement.textContent = new Date().toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function initStrategyPerformanceChart() {
    const canvas = document.getElementById('strategyPerformanceChart');
    const card = document.getElementById('strategyPerformanceCard');

    if (!canvas || !card || typeof Chart === 'undefined') {
        return;
    }

    const chartData = parseJsonDataset(card.dataset.chart, {
        labels: [],
        accepted_counts: [],
        success_rates: []
    });

    if (!Array.isArray(chartData.labels) || chartData.labels.length === 0) {
        return;
    }

    new Chart(canvas, {
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Strategies acceptees',
                    data: chartData.accepted_counts,
                    yAxisID: 'y',
                    backgroundColor: 'rgba(195, 125, 93, 0.72)',
                    borderColor: '#C37D5D',
                    borderWidth: 1,
                    borderRadius: 10,
                    maxBarThickness: 42
                },
                {
                    type: 'line',
                    label: 'Taux de reussite',
                    data: chartData.success_rates,
                    yAxisID: 'y1',
                    borderColor: '#7D8F6E',
                    backgroundColor: 'rgba(125, 143, 110, 0.16)',
                    pointBackgroundColor: '#7D8F6E',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 5,
                    tension: 0.35,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const value = context.parsed.y;

                            if (context.dataset.yAxisID === 'y1') {
                                return `${context.dataset.label}: ${Number(value).toFixed(1)}%`;
                            }

                            return `${context.dataset.label}: ${value}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        autoSkip: true,
                        maxRotation: 0,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Strategies acceptees'
                    }
                },
                y1: {
                    beginAtZero: true,
                    max: 100,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        callback(value) {
                            return `${value}%`;
                        }
                    },
                    title: {
                        display: true,
                        text: 'Taux de reussite'
                    }
                }
            }
        }
    });
}

function parseJsonDataset(rawValue, fallback) {
    try {
        const parsed = JSON.parse(rawValue || '{}');
        return { ...fallback, ...parsed };
    } catch (error) {
        return fallback;
    }
}

function initInvestmentChart() {
    const ctx = document.getElementById('investmentChart');
    if (!ctx || typeof Chart === 'undefined') {
        return;
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Actions', 'Obligations', 'Immobilier', 'Crypto'],
            datasets: [{
                data: [45, 25, 20, 10],
                backgroundColor: ['#C37D5D', '#7D8F6E', '#E3C9A0', '#A56143'],
                borderWidth: 0,
                cutout: '55%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function initQuickActions() {
    document.querySelectorAll('.action-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            showToast(`${btn.innerText.trim()} - fonctionnalite a venir`);
        });
    });
}

function initEventButtons() {
    document.querySelectorAll('.event-btn').forEach((btn) => {
        btn.addEventListener('click', () => showToast('Inscription enregistree'));
    });
}

function initResourceButtons() {
    document.querySelectorAll('.resource-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const resourceName = btn.closest('.resource-item')?.querySelector('strong')?.innerText || 'Document';
            showToast(`Telechargement de "${resourceName}" demarre`);
        });
    });
}

function setActiveLink() {
    const currentPath = window.location.pathname;
    document.querySelectorAll('.back-nav a').forEach((link) => {
        const href = link.getAttribute('href');
        const isActive = href === currentPath || (currentPath === '/back' && link.querySelector('i.fa-home'));
        link.classList.toggle('active', Boolean(isActive));
    });
}

function initNavigation() {
    setActiveLink();

    document.querySelectorAll('.view-link').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            showToast('Affichage de tous les elements');
        });
    });
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
    }, 2500);
}
