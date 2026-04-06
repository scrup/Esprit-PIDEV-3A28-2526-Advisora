document.addEventListener('DOMContentLoaded', function() {
    // Animated counters
    initCounters();
    
    // Set current date
    setCurrentDate();
    
    // Initialize strategy performance chart
    initStrategyChart();
    
    // Initialize investment chart
    initInvestmentChart();
    
    // Initialize quick actions
    initQuickActions();
    
    // Initialize event buttons
    initEventButtons();
    
    // Initialize resource buttons
    initResourceButtons();
    
    // Initialize navigation
    initNavigation();
});

function initCounters() {
    const counters = document.querySelectorAll('.counter');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
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
    if (dateElement) {
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        const today = new Date().toLocaleDateString('fr-FR', options);
        dateElement.textContent = today;
    }
}

// Strategy Performance Chart (Accepted vs Refused)
let strategyChart = null;

function initStrategyChart() {
    const ctx = document.getElementById('strategyPerformanceChart').getContext('2d');
    
    strategyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [
                {
                    label: 'Stratégies acceptées',
                    data: [65, 72, 80, 110, 95, 88, 92, 100, 105, 98, 85, 90],
                    backgroundColor: '#7D8F6E',
                    borderRadius: 8,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Stratégies refusées',
                    data: [15, 18, 20, 5, 12, 10, 8, 6, 7, 9, 11, 10],
                    backgroundColor: '#C37D5D',
                    borderRadius: 8,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { size: 11, family: 'Inter' }, usePointStyle: true, boxWidth: 10 }
                },
                tooltip: {
                    backgroundColor: '#2C2418',
                    titleColor: '#E8DDD0',
                    bodyColor: '#CBBEAE',
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw} stratégies`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#F0E7DC' },
                    title: { display: true, text: 'Nombre de stratégies', font: { size: 10 } },
                    ticks: { stepSize: 20 }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
    
    // Period selector
    const periodSelect = document.getElementById('strategyPeriod');
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            updateStrategyChart(this.value);
        });
    }
}

function updateStrategyChart(months) {
    const dataMap = {
        6: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
            accepted: [65, 72, 80, 110, 95, 88],
            rejected: [15, 18, 20, 5, 12, 10]
        },
        12: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            accepted: [65, 72, 80, 110, 95, 88, 92, 100, 105, 98, 85, 90],
            rejected: [15, 18, 20, 5, 12, 10, 8, 6, 7, 9, 11, 10]
        }
    };
    
    const data = dataMap[months] || dataMap[12];
    
    if (strategyChart) {
        // Calculate acceptance rate
        const totalAccepted = data.accepted.reduce((a, b) => a + b, 0);
        const totalRejected = data.rejected.reduce((a, b) => a + b, 0);
        const acceptanceRate = Math.round((totalAccepted / (totalAccepted + totalRejected)) * 100);
        
        // Find best month
        let bestMonthIndex = 0;
        let bestMonthValue = 0;
        data.accepted.forEach((val, idx) => {
            if (val > bestMonthValue) {
                bestMonthValue = val;
                bestMonthIndex = idx;
            }
        });
        
        // Update stats display
        const acceptanceRateEl = document.getElementById('acceptanceRate');
        const bestMonthEl = document.getElementById('bestMonth');
        
        if (acceptanceRateEl) acceptanceRateEl.textContent = `${acceptanceRate}%`;
        if (bestMonthEl) bestMonthEl.textContent = `${data.labels[bestMonthIndex]} (${bestMonthValue} acceptées)`;
        
        // Update chart
        strategyChart.data.labels = data.labels;
        strategyChart.data.datasets[0].data = data.accepted;
        strategyChart.data.datasets[1].data = data.rejected;
        strategyChart.update();
    }
}

// Investment Donut Chart
function initInvestmentChart() {
    const ctx = document.getElementById('investmentChart');
    if (ctx) {
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
                    legend: { display: false },
                    tooltip: { 
                        callbacks: { 
                            label: (ctx) => `${ctx.label}: ${ctx.raw}% (${(ctx.raw * 2400000 / 100).toLocaleString()} DT)` 
                        } 
                    }
                }
            }
        });
    }
}

function initQuickActions() {
    const actions = document.querySelectorAll('.action-btn');
    actions.forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.innerHTML.trim();
            showToast(`🚀 ${action} - Fonctionnalité à venir`);
        });
    });
}

function initEventButtons() {
    const eventBtns = document.querySelectorAll('.event-btn');
    eventBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            showToast('✅ Inscription enregistrée ! Un email de confirmation vous a été envoyé.');
        });
    });
}

function initResourceButtons() {
    const resourceBtns = document.querySelectorAll('.resource-btn');
    resourceBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const resource = btn.closest('.resource-item');
            const resourceName = resource?.querySelector('strong')?.innerText || 'Document';
            showToast(`📥 Téléchargement de "${resourceName}" démarré`);
        });
    });
}

function initNavigation() {
    const navLinks = document.querySelectorAll('.back-nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            const section = link.querySelector('span')?.innerText || link.innerText;
            showToast(`📌 Navigation vers ${section}`);
        });
    });
    
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            showToast('🔐 Déconnexion en cours...');
        });
    }
    
    const viewLinks = document.querySelectorAll('.view-link');
    viewLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            showToast(`📋 Affichage de tous les éléments`);
        });
    });
}

function showToast(message) {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
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