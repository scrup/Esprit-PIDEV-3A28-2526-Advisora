// Strategy Data
const strategiesData = [
    {
        id: 1,
        name: "Expansion Marché Euro",
        duration: "2 ans",
        roi: 24,
        roiPositive: true,
        tags: ["Croissance", "Efficacité"],
        impact: 85,
        status: "pending",
        sector: "tech",
        risk: "moderate",
        type: "expansion"
    },
    {
        id: 2,
        name: "Optimisation Crypto-actifs",
        duration: "6 mois",
        roi: 18,
        roiPositive: true,
        tags: ["Risque Élevé", "Innovation"],
        impact: 62,
        status: "approved",
        sector: "crypto",
        risk: "high",
        type: "optimisation"
    },
    {
        id: 3,
        name: "Diversification ESG Vert",
        duration: "5 ans",
        roi: 4,
        roiPositive: false,
        tags: ["Durabilité", "Risque Faible"],
        impact: 42,
        status: "rejected",
        sector: "energie",
        risk: "low",
        type: "diversification"
    },
    {
        id: 4,
        name: "Fintech Innovation Hub",
        duration: "18 mois",
        roi: 32,
        roiPositive: true,
        tags: ["Innovation", "Croissance"],
        impact: 78,
        status: "approved",
        sector: "fintech",
        risk: "moderate",
        type: "expansion"
    },
    {
        id: 5,
        name: "Cloud Infrastructure Scale",
        duration: "3 ans",
        roi: 28,
        roiPositive: true,
        tags: ["Technologie", "Scalabilité"],
        impact: 91,
        status: "pending",
        sector: "tech",
        risk: "moderate",
        type: "expansion"
    }
];

// DOM Elements
let strategiesList, donutChart, goalChart, totalSpan, pendingSpan, approvedSpan;
let currentFilter = { sector: 'all', risk: 'moderate', minPerformance: 0 };

function setActiveLink() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.back-nav a');
    navLinks.forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if (href === currentPath || (currentPath === '/back' && link.querySelector('i.fa-home'))) {
            link.classList.add('active');
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    setActiveLink();
    
    strategiesList = document.getElementById('strategiesList');
    totalSpan = document.getElementById('totalStrategies');
    pendingSpan = document.getElementById('pendingStrategies');
    approvedSpan = document.getElementById('approvedStrategies');
    
    renderStrategies();
    initCharts();
    initFilters();
    initEventListeners();
});

// Render Strategies
function renderStrategies() {
    let filtered = strategiesData;
    
    // Apply sector filter
    if (currentFilter.sector !== 'all') {
        filtered = filtered.filter(s => s.sector === currentFilter.sector);
    }
    
    // Apply risk filter
    filtered = filtered.filter(s => s.risk === currentFilter.risk);
    
    // Apply performance filter
    filtered = filtered.filter(s => s.impact >= currentFilter.minPerformance);
    
    // Update stats
    const total = filtered.length;
    const pending = filtered.filter(s => s.status === 'pending').length;
    const approved = filtered.filter(s => s.status === 'approved').length;
    
    if (totalSpan) totalSpan.textContent = total;
    if (pendingSpan) pendingSpan.textContent = pending;
    if (approvedSpan) approvedSpan.textContent = approved;
    
    // Update donut chart counts
    const expansionCount = filtered.filter(s => s.type === 'expansion').length;
    const optimisationCount = filtered.filter(s => s.type === 'optimisation').length;
    const diversificationCount = filtered.filter(s => s.type === 'diversification').length;
    
    const donutTotal = document.getElementById('donutTotal');
    const expansionCountEl = document.getElementById('expansionCount');
    const optimisationCountEl = document.getElementById('optimisationCount');
    const diversificationCountEl = document.getElementById('diversificationCount');
    
    if (donutTotal) donutTotal.textContent = total;
    if (expansionCountEl) expansionCountEl.textContent = expansionCount;
    if (optimisationCountEl) optimisationCountEl.textContent = optimisationCount;
    if (diversificationCountEl) diversificationCountEl.textContent = diversificationCount;
    
    // Update donut chart
    if (donutChart) {
        donutChart.data.datasets[0].data = [expansionCount, optimisationCount, diversificationCount];
        donutChart.update();
    }
    
    // Render cards
    if (strategiesList) {
        strategiesList.innerHTML = filtered.map(strategy => `
            <div class="strategy-card" data-id="${strategy.id}">
                <div class="strategy-checkbox">
                    <input type="checkbox" class="strategy-select">
                </div>
                <div class="strategy-icon">
                    <span class="material-symbols-outlined">${getStrategyIcon(strategy.type)}</span>
                </div>
                <div class="strategy-info">
                    <div class="strategy-title">
                        <h3>${escapeHtml(strategy.name)}</h3>
                        <span class="strategy-badge">${strategy.duration}</span>
                    </div>
                    <div class="strategy-tags">
                        <div class="roi-badge ${!strategy.roiPositive ? 'negative' : ''}">
                            <span class="material-symbols-outlined">${strategy.roiPositive ? 'trending_up' : 'trending_down'}</span>
                            ROI: ${strategy.roiPositive ? '+' : ''}${strategy.roi}%
                        </div>
                        ${strategy.tags.map(tag => `<span class="tag">${escapeHtml(tag)}</span>`).join('')}
                    </div>
                </div>
                <div class="strategy-metrics">
                    <div class="metric-impact">
                        <div class="impact-circle">
                            <svg width="44" height="44" viewBox="0 0 36 36">
                                <path class="impact-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#F0E7DC" stroke-width="3"></path>
                                <path class="impact-fill" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#C37D5D" stroke-dasharray="${strategy.impact}, 100" stroke-linecap="round" stroke-width="3"></path>
                            </svg>
                            <span class="impact-value">${strategy.impact}</span>
                        </div>
                        <p class="metric-label">Impact</p>
                    </div>
                    <div class="strategy-status">
                        <span class="status-badge ${strategy.status}">${getStatusText(strategy.status)}</span>
                    </div>
                    <button class="btn-swot" data-id="${strategy.id}">
                        <span class="material-symbols-outlined">analytics</span>
                        Voir SWOT
                    </button>
                </div>
                <div class="strategy-actions">
                    <button class="action-icon view-btn" data-id="${strategy.id}">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                    <button class="action-icon edit-btn" data-id="${strategy.id}">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="action-icon delete-btn" data-id="${strategy.id}">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </div>
        `).join('');
        
        // Attach event listeners
        document.querySelectorAll('.btn-swot').forEach(btn => {
            btn.addEventListener('click', () => showToast('📊 Analyse SWOT disponible dans les détails de la stratégie'));
        });
        
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => showToast('👁️ Affichage des détails de la stratégie'));
        });
        
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => showToast('✏️ Fonctionnalité d\'édition à venir'));
        });
        
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', () => showToast('🗑️ Suppression de stratégie - Fonctionnalité à venir'));
        });
    }
}

function getStrategyIcon(type) {
    const icons = {
        expansion: 'public',
        optimisation: 'currency_bitcoin',
        diversification: 'energy_savings_leaf'
    };
    return icons[type] || 'insights';
}

function getStatusText(status) {
    const texts = {
        pending: 'En attente',
        approved: 'Acceptée',
        rejected: 'Refusée'
    };
    return texts[status] || status;
}

// Initialize Charts
function initCharts() {
    // Donut Chart
    const donutCtx = document.getElementById('strategyDonutChart')?.getContext('2d');
    if (donutCtx) {
        donutChart = new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Expansion', 'Optimisation', 'Diversification'],
                datasets: [{
                    data: [21, 13, 8],
                    backgroundColor: ['#C37D5D', '#7D8F6E', '#E3C9A0'],
                    borderWidth: 0,
                    cutout: '65%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } }
            }
        });
    }
    
    // Goal Chart
    const goalCtx = document.getElementById('goalChart')?.getContext('2d');
    if (goalCtx) {
        goalChart = new Chart(goalCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [72, 28],
                    backgroundColor: ['#C37D5D', '#F0E7DC'],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });
    }
}

// Initialize Filters
function initFilters() {
    const sectorFilter = document.getElementById('sectorFilter');
    const riskBtns = document.querySelectorAll('.risk-btn');
    const performanceRange = document.getElementById('performanceRange');
    const resetBtn = document.getElementById('resetFiltersBtn');
    
    if (sectorFilter) {
        sectorFilter.addEventListener('change', (e) => {
            currentFilter.sector = e.target.value;
            renderStrategies();
        });
    }
    
    riskBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            riskBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter.risk = btn.dataset.risk;
            renderStrategies();
        });
    });
    
    if (performanceRange) {
        performanceRange.addEventListener('input', (e) => {
            currentFilter.minPerformance = parseInt(e.target.value);
            renderStrategies();
        });
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            currentFilter = { sector: 'all', risk: 'moderate', minPerformance: 0 };
            if (sectorFilter) sectorFilter.value = 'all';
            if (performanceRange) performanceRange.value = 0;
            riskBtns.forEach(b => {
                b.classList.remove('active');
                if (b.dataset.risk === 'moderate') b.classList.add('active');
            });
            renderStrategies();
            showToast('🔄 Filtres réinitialisés');
        });
    }
}

// Event Listeners
function initEventListeners() {
    const newStrategyBtn = document.getElementById('newStrategyBtn');
    if (newStrategyBtn) {
        newStrategyBtn.addEventListener('click', () => {
            showToast('✨ Création d\'une nouvelle stratégie - Fonctionnalité à venir');
        });
    }
    
    const compareToggle = document.getElementById('compare-toggle');
    if (compareToggle) {
        compareToggle.addEventListener('change', (e) => {
            if (e.target.checked) {
                showToast('📊 Mode comparaison activé - Sélectionnez plusieurs stratégies');
            } else {
                showToast('📊 Mode comparaison désactivé');
            }
        });
    }
}

// Helper Functions
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
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




