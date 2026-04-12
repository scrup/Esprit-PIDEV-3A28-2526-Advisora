// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Charts
    initCharts();
    
    // Initialize navigation
    initNavigation();
    
    // Initialize buttons
    initButtons();
    
    // Initialize currency ticker
    initTNDTicker();
});

// Initialize Charts
function initCharts() {
    // ROI Chart
    const roiCanvas = document.getElementById('roiChart');
    if (roiCanvas) {
        const roiCtx = roiCanvas.getContext('2d');
        new Chart(roiCtx, {
            type: 'line',
            data: {
                labels: ['Année 1', 'Année 2', 'Année 3', 'Année 4', 'Année 5'],
                datasets: [
                    {
                        label: 'Avec Advisora',
                        data: [12, 28, 45, 68, 92],
                        borderColor: '#C37D5D',
                        backgroundColor: 'rgba(195, 125, 93, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#C37D5D',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    },
                    {
                        label: 'Stratégie traditionnelle',
                        data: [5, 11, 18, 26, 35],
                        borderColor: '#7D8F6E',
                        backgroundColor: 'rgba(125, 143, 110, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#7D8F6E',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { 
                        position: 'top', 
                        labels: { font: { size: 12, family: 'Inter' } } 
                    },
                    tooltip: { 
                        backgroundColor: '#2C2418', 
                        titleColor: '#E8DDD0', 
                        bodyColor: '#CBBEAE' 
                    }
                },
                scales: {
                    y: { 
                        title: { display: true, text: 'ROI (%)', font: { size: 12 } }, 
                        beginAtZero: true, 
                        grid: { color: '#F0E7DC' } 
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
//highlight active nav link on scroll
    window.addEventListener('scroll', () => {
    const sections = document.querySelectorAll('section');
    const navLinks = document.querySelectorAll('.nav-link');

    let current = '';
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        if (window.scrollY >= sectionTop - 100) {
            current = section.getAttribute('id');
        }
    });

    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
            link.classList.add('active');
        }
    });
});

    // Risk Reduction Chart
    const riskCanvas = document.getElementById('riskChart');
    if (riskCanvas) {
        const riskCtx = riskCanvas.getContext('2d');
        new Chart(riskCtx, {
            type: 'bar',
            data: {
                labels: ['Avant Advisora', 'Après 6 mois', 'Après 12 mois', 'Après 24 mois'],
                datasets: [{
                    label: 'Indice de volatilité',
                    data: [78, 52, 34, 21],
                    backgroundColor: '#C37D5D',
                    borderRadius: 10,
                    barPercentage: 0.6,
                    hoverBackgroundColor: '#A56143'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { backgroundColor: '#2C2418' }
                },
                scales: {
                    y: { 
                        title: { display: true, text: 'Niveau de risque (%)', font: { size: 12 } }, 
                        beginAtZero: true, 
                        max: 100, 
                        grid: { color: '#F0E7DC' } 
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
}

// Smooth scroll function
function smoothScroll(targetElement, duration = 800) {
    const targetPosition = targetElement.getBoundingClientRect().top;
    const startPosition = window.pageYOffset;
    const distance = targetPosition - 80;
    let startTime = null;

    function animation(currentTime) {
        if (startTime === null) startTime = currentTime;
        const timeElapsed = currentTime - startTime;
        const run = easeInOutCubic(timeElapsed, startPosition, distance, duration);
        window.scrollTo(0, run);
        if (timeElapsed < duration) requestAnimationFrame(animation);
    }

    function easeInOutCubic(t, b, c, d) {
        t /= d / 2;
        if (t < 1) return c / 2 * t * t * t + b;
        t -= 2;
        return c / 2 * (t * t * t + 2) + b;
    }

    requestAnimationFrame(animation);
}

function scrollToSection(sectionId) {
    const section = document.querySelector(sectionId);
    if (section) {
        smoothScroll(section, 600);
    }
}

// Initialize navigation : this is important for the nav links to work properly, especially with the new hash-based navigation for sections on the home page. It also ensures that real routes like / and /boutique navigate normally without interference. The hero CTA and Get Started buttons will also scroll to the solutions section as intended.
function initNavigation() {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href');
            // Only prevent default and scroll for hash anchors (#), allow real routes
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const section = document.querySelector(href);
                // If section exists on current page, scroll to it
                if (section) {
                    scrollToSection(href);
                } else {
                    // If section doesn't exist, navigate to home with the hash
                    window.location.href = '/' + href;
                }
            }
            // Real routes like / and /boutique will navigate normally
        });
    });

    // Hero CTA button
    const heroCtaBtn = document.getElementById('heroCta');
    if (heroCtaBtn) {
        heroCtaBtn.addEventListener('click', () => {
            scrollToSection('#solutions');
        });
    }

    // Get Started button
    const getStartedBtn = document.getElementById('getStartedBtn');
    if (getStartedBtn) {
        getStartedBtn.addEventListener('click', () => {
            scrollToSection('#solutions');
        });
    }
}

// Toast notification
function showToastMessage(msg) {
    alert(msg + ' ✨');
}


// Cart functionality
document.addEventListener('DOMContentLoaded', function() {
    // Cart elements
    const cartBtn = document.getElementById('cartBtn');
    const cartSidebar = document.getElementById('cartSidebar');
    const cartOverlay = document.getElementById('cartOverlay');
    const closeCartBtn = document.getElementById('closeCartBtn');
    
    // Open cart
    if (cartBtn) {
        cartBtn.addEventListener('click', function() {
            if (cartSidebar) cartSidebar.classList.add('open');
            if (cartOverlay) cartOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    }
    
    // Close cart
    function closeCart() {
        if (cartSidebar) cartSidebar.classList.remove('open');
        if (cartOverlay) cartOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }
    
    if (closeCartBtn) {
        closeCartBtn.addEventListener('click', closeCart);
    }
    
    if (cartOverlay) {
        cartOverlay.addEventListener('click', closeCart);
    }
    
    // Close cart with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && cartSidebar && cartSidebar.classList.contains('open')) {
            closeCart();
        }
    });
});

// Initialize buttons
function initButtons() {
    // Primary buttons handlers
    const btnsPrimary = document.querySelectorAll('.btn-primary');
    btnsPrimary.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if(btn.id === 'newsSignupBtn') {
                const emailInput = document.getElementById('newsEmail');
                if(emailInput && emailInput.value && emailInput.value.includes('@')) {
                    showToastMessage(`📬 Merci ! ${emailInput.value} est maintenant abonné.`);
                    emailInput.value = '';
                } else {
                    showToastMessage('📧 Veuillez entrer une adresse email valide.');
                }
            } else if(btn.id === 'ctaBannerBtn') {
                showToastMessage('🎯 Demande de démo reçue ! Notre équipe vous contactera sous 24h.');
            } else if(btn.id === 'heroCta' || btn.id === 'getStartedBtn') {
                return;
            } else {
                showToastMessage('Fonctionnalité à venir — version améliorée en route.');
            }
        });
    });

    // Login button: keep default link navigation to /login

    // Card links
    const cardLinks = document.querySelectorAll('.card-link');
    cardLinks.forEach(link => {
        link.addEventListener('click', () => {
            showToastMessage('📈 Plongez plus profond : livres blancs et études de cas disponibles.');
        });
    });
}

// TND Currency ticker simulation
let tickerInterval = null;

function initTNDTicker() {
    updateTNDTicker();
    tickerInterval = setInterval(updateTNDTicker, 4500);
}

function updateTNDTicker() {
    const tndusd = document.getElementById('tndusd');
    const tndeur = document.getElementById('tndeur');
    const tndgbp = document.getElementById('tndgbp');
    const tndjpy = document.getElementById('tndjpy');
    const tndchf = document.getElementById('tndchf');
    const tndcad = document.getElementById('tndcad');
    const tndcny = document.getElementById('tndcny');
    
    if (!tndusd) return;
    
    let tndusdVal = parseFloat(tndusd.innerText);
    let tndeurVal = parseFloat(tndeur.innerText);
    let tndgbpVal = parseFloat(tndgbp.innerText);
    let tndjpyVal = parseFloat(tndjpy.innerText);
    let tndchfVal = parseFloat(tndchf.innerText);
    let tndcadVal = parseFloat(tndcad.innerText);
    let tndcnyVal = parseFloat(tndcny.innerText);

    const randomMove = () => (Math.random() - 0.5) * 0.0015;
    const randomMoveJpy = () => (Math.random() - 0.5) * 0.25;
    const randomMoveCny = () => (Math.random() - 0.5) * 0.008;

    let newTNDUSD = +(tndusdVal + randomMove()).toFixed(4);
    let newTNDEUR = +(tndeurVal + randomMove()).toFixed(4);
    let newTNDGBP = +(tndgbpVal + randomMove()).toFixed(4);
    let newTNDJPY = +(tndjpyVal + randomMoveJpy()).toFixed(2);
    let newTNDCHF = +(tndchfVal + randomMove()).toFixed(4);
    let newTNDCAD = +(tndcadVal + randomMove()).toFixed(4);
    let newTNDCNY = +(tndcnyVal + randomMoveCny()).toFixed(4);

    function setChange(elementId, newVal, oldVal) {
        let span = document.getElementById(elementId);
        if(span) {
            let change = ((newVal - oldVal) / oldVal * 100).toFixed(2);
            let isPositive = parseFloat(change) >= 0;
            span.innerText = (isPositive ? `▲ +${Math.abs(change)}%` : `▼ -${Math.abs(change)}%`);
            span.className = isPositive ? 'positive' : 'negative';
        }
    }

    tndusd.innerText = newTNDUSD;
    tndeur.innerText = newTNDEUR;
    tndgbp.innerText = newTNDGBP;
    tndjpy.innerText = newTNDJPY;
    tndchf.innerText = newTNDCHF;
    tndcad.innerText = newTNDCAD;
    tndcny.innerText = newTNDCNY;

    setChange('tndusdChange', newTNDUSD, tndusdVal);
    setChange('tndeurChange', newTNDEUR, tndeurVal);
    setChange('tndgbpChange', newTNDGBP, tndgbpVal);
    setChange('tndjpyChange', newTNDJPY, tndjpyVal);
    setChange('tndchfChange', newTNDCHF, tndchfVal);
    setChange('tndcadChange', newTNDCAD, tndcadVal);
    setChange('tndcnyChange', newTNDCNY, tndcnyVal);
}

// Cleanup interval on page unload
window.addEventListener('beforeunload', () => {
    if(tickerInterval) clearInterval(tickerInterval);
});