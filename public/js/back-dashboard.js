document.addEventListener('DOMContentLoaded', () => {
    setCurrentDate();
    initStrategyPerformanceChart();
    initInvestmentChart();
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

    const context = canvas.getContext('2d');
    const barGradient = context.createLinearGradient(0, 0, 0, 320);
    barGradient.addColorStop(0, 'rgba(185, 145, 105, 0.5)');
    barGradient.addColorStop(1, 'rgba(185, 145, 105, 0.08)');

    const lineGradient = context.createLinearGradient(0, 0, 0, 320);
    lineGradient.addColorStop(0, 'rgba(126, 139, 99, 0.26)');
    lineGradient.addColorStop(1, 'rgba(126, 139, 99, 0.02)');

    new Chart(canvas, {
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Strategies acceptees',
                    data: chartData.accepted_counts,
                    yAxisID: 'y',
                    backgroundColor: barGradient,
                    borderColor: '#b99169',
                    borderWidth: 1,
                    borderRadius: 10,
                    maxBarThickness: 36
                },
                {
                    type: 'line',
                    label: 'Taux de reussite',
                    data: chartData.success_rates,
                    yAxisID: 'y1',
                    borderColor: '#7e8b63',
                    backgroundColor: lineGradient,
                    pointBackgroundColor: '#7e8b63',
                    pointBorderColor: '#11161a',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 5,
                    tension: 0.35,
                    fill: true
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
                    position: 'bottom',
                    labels: {
                        color: '#d7dde3',
                        usePointStyle: true,
                        boxWidth: 10,
                        padding: 16
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(19, 24, 29, 0.96)',
                    titleColor: '#ffffff',
                    bodyColor: '#e5ebf0',
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                    borderWidth: 1,
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
                        color: 'rgba(255, 255, 255, 0.05)',
                        drawTicks: false
                    },
                    ticks: {
                        color: '#97a5b2',
                        maxRotation: 0,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#97a5b2',
                        precision: 0
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
                        color: '#97a5b2',
                        callback(value) {
                            return `${value}%`;
                        }
                    }
                }
            }
        }
    });
}

function initInvestmentChart() {
    const canvas = document.getElementById('investmentChart');
    const shell = document.querySelector('[data-donut]');
    if (!canvas || !shell || typeof Chart === 'undefined') {
        return;
    }

    const distribution = parseJsonDataset(shell.dataset.donut, []);
    if (!Array.isArray(distribution) || distribution.length === 0) {
        return;
    }

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: distribution.map((item) => item.label),
            datasets: [{
                data: distribution.map((item) => item.value),
                backgroundColor: ['#b99169', '#7e8b63', '#d7b98c', '#8f785e'],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(19, 24, 29, 0.96)',
                    titleColor: '#ffffff',
                    bodyColor: '#e5ebf0',
                    borderColor: 'rgba(255, 255, 255, 0.08)',
                    borderWidth: 1
                }
            }
        }
    });
}

function parseJsonDataset(rawValue, fallback) {
    try {
        return JSON.parse(rawValue || 'null') ?? fallback;
    } catch (error) {
        return fallback;
    }
}
