// Product Database
const products = [
    { id: 1, name: "Analyse financière premium", price: 299, category: "analytics", icon: "fas fa-chart-line", description: "Rapport détaillé de votre situation financière avec recommandations personnalisées.", popular: true },
    { id: 2, name: "Tableau de bord interactif", price: 149, category: "analytics", icon: "fas fa-chart-pie", description: "Visualisation en temps réel de vos KPI et indicateurs de performance.", popular: false },
    { id: 3, name: "Stratégie d'investissement sur mesure", price: 499, category: "strategy", icon: "fas fa-brain", description: "Plan d'investissement personnalisé basé sur vos objectifs et votre profil de risque.", popular: true },
    { id: 4, name: "Optimisation fiscale", price: 349, category: "strategy", icon: "fas fa-file-invoice-dollar", description: "Solutions pour réduire votre charge fiscale légalement.", popular: false },
    { id: 5, name: "Formation trading débutant", price: 199, category: "training", icon: "fas fa-graduation-cap", description: "Apprenez les bases du trading et de l'analyse technique.", popular: true },
    { id: 6, name: "Masterclass investissement", price: 399, category: "training", icon: "fas fa-chalkboard-user", description: "Formation avancée sur les stratégies d'investissement.", popular: false },
    { id: 7, name: "Rapport ESG personnalisé", price: 249, category: "analytics", icon: "fas fa-leaf", description: "Analyse de l'impact environnemental et social de vos investissements.", popular: false },
    { id: 8, name: "Coaching financier 1:1", price: 599, category: "strategy", icon: "fas fa-user-tie", description: "Sessions individuelles avec un expert financier certifié.", popular: true }
];

// Cart state
let cart = [];

// DOM Elements
let productsGrid, cartSidebar, cartOverlay, cartCountEl, cartItemsEl, cartTotalEl, cartBtn, closeCartBtn;

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DOM elements
    productsGrid = document.getElementById('productsGrid');
    cartSidebar = document.getElementById('cartSidebar');
    cartOverlay = document.getElementById('cartOverlay');
    cartCountEl = document.getElementById('cartCount');
    cartItemsEl = document.getElementById('cartItems');
    cartTotalEl = document.getElementById('cartTotal');
    cartBtn = document.getElementById('cartBtn');
    closeCartBtn = document.getElementById('closeCartBtn');

    // Initialize shop
    if (productsGrid) {
        renderProducts();
        initFilters();
    }

    // Initialize cart events
    if (cartBtn) {
        cartBtn.addEventListener('click', openCart);
    }
    
    if (closeCartBtn) {
        closeCartBtn.addEventListener('click', closeCart);
    }
    
    if (cartOverlay) {
        cartOverlay.addEventListener('click', closeCart);
    }

    // Checkout button
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', () => {
            if (cart.length === 0) {
                showToast('Votre panier est vide !');
            } else {
                showToast(`Merci pour votre commande ! Total: ${cartTotalEl.textContent}`);
                cart = [];
                updateCartUI();
                closeCart();
            }
        });
    }

    // Button handlers
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', () => {
            showToast('🔐 Fonctionnalité à venir');
        });
    }

    const contactBtn = document.getElementById('contactBtn');
    if (contactBtn) {
        contactBtn.addEventListener('click', () => {
            scrollToSection('#contact');
        });
    }

    const newsSignupBtn = document.getElementById('newsSignupBtn');
    if (newsSignupBtn) {
        newsSignupBtn.addEventListener('click', () => {
            const emailInput = document.getElementById('newsEmail');
            if (emailInput && emailInput.value && emailInput.value.includes('@')) {
                showToast(`📬 Merci ! ${emailInput.value} est maintenant abonné.`);
                emailInput.value = '';
            } else {
                showToast('📧 Veuillez entrer une adresse email valide.');
            }
        });
    }
});

// Render Products
function renderProducts(filter = 'all') {
    if (!productsGrid) return;
    
    const filteredProducts = filter === 'all' ? products : products.filter(p => p.category === filter);
    
    productsGrid.innerHTML = filteredProducts.map(product => `
        <div class="product-card" data-id="${product.id}" data-category="${product.category}">
            <div class="product-icon"><i class="${product.icon}"></i></div>
            <h3>${escapeHtml(product.name)}</h3>
            <p>${escapeHtml(product.description)}</p>
            <div class="product-price">${product.price.toFixed(2)} €</div>
            ${product.popular ? '<span class="popular-badge"><i class="fas fa-fire"></i> Populaire</span>' : ''}
            <button class="btn-add-to-cart" data-id="${product.id}">
                <i class="fas fa-cart-plus"></i> Ajouter au panier
            </button>
        </div>
    `).join('');

    // Attach event listeners to add-to-cart buttons
    document.querySelectorAll('.btn-add-to-cart').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const productId = parseInt(btn.dataset.id);
            addToCart(productId);
        });
    });
}

// Add to Cart
function addToCart(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;

    const existingItem = cart.find(item => item.id === productId);
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({ ...product, quantity: 1 });
    }

    updateCartUI();
    showToast(`${product.name} ajouté au panier !`);
    
    // Animate cart button
    const cartBtnEl = document.getElementById('cartBtn');
    if (cartBtnEl) {
        cartBtnEl.classList.add('cart-bump');
        setTimeout(() => cartBtnEl.classList.remove('cart-bump'), 300);
    }
}

// Remove from Cart
function removeFromCart(productId) {
    const itemIndex = cart.findIndex(item => item.id === productId);
    if (itemIndex !== -1) {
        const removedItem = cart[itemIndex];
        if (removedItem.quantity > 1) {
            removedItem.quantity -= 1;
        } else {
            cart.splice(itemIndex, 1);
        }
        updateCartUI();
        showToast(`${removedItem.name} retiré du panier`);
    }
}

// Update Cart UI (count, items, total)
function updateCartUI() {
    // Update cart count
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    if (cartCountEl) cartCountEl.textContent = totalItems;
    
    // Update cart items display
    if (!cartItemsEl) return;
    
    if (cart.length === 0) {
        cartItemsEl.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-bag"></i>
                <p>Votre panier est vide</p>
            </div>
        `;
    } else {
        cartItemsEl.innerHTML = cart.map(item => `
            <div class="cart-item" data-id="${item.id}">
                <div class="cart-item-info">
                    <div class="cart-item-icon"><i class="${item.icon}"></i></div>
                    <div class="cart-item-details">
                        <h4>${escapeHtml(item.name)}</h4>
                        <p>${item.price.toFixed(2)} €</p>
                    </div>
                </div>
                <div class="cart-item-actions">
                    <button class="qty-btn minus" data-id="${item.id}">-</button>
                    <span class="qty">${item.quantity}</span>
                    <button class="qty-btn plus" data-id="${item.id}">+</button>
                    <button class="remove-btn" data-id="${item.id}"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>
        `).join('');
        
        // Attach event listeners for cart item buttons
        document.querySelectorAll('.qty-btn.minus').forEach(btn => {
            btn.addEventListener('click', () => removeFromCart(parseInt(btn.dataset.id)));
        });
        document.querySelectorAll('.qty-btn.plus').forEach(btn => {
            btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.id)));
        });
        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id);
                cart = cart.filter(item => item.id !== id);
                updateCartUI();
                showToast('Article retiré du panier');
            });
        });
    }
    
    // Update total
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    if (cartTotalEl) cartTotalEl.textContent = `${total.toFixed(2)} €`;
}

// Toast notification
function showToast(message) {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${escapeHtml(message)}`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

// Toggle Cart Sidebar
function openCart() {
    if (cartSidebar) cartSidebar.classList.add('open');
    if (cartOverlay) cartOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    if (cartSidebar) cartSidebar.classList.remove('open');
    if (cartOverlay) cartOverlay.classList.remove('open');
    document.body.style.overflow = '';
}

// Filter functionality
function initFilters() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = btn.dataset.filter;
            renderProducts(filter);
        });
    });
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

// Helper function to escape HTML and prevent XSS
function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// TND Currency ticker simulation
function updateTNDTicker() {
    const ids = ['tndusd', 'tndeur', 'tndgbp', 'tndjpy', 'tndchf', 'tndcad', 'tndcny'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            let val = parseFloat(el.innerText);
            const randomMove = (Math.random() - 0.5) * 0.0015;
            let newVal = +(val + randomMove).toFixed(id === 'tndjpy' ? 2 : 4);
            el.innerText = newVal;
            
            const changeSpan = document.getElementById(id + 'Change');
            if (changeSpan) {
                const change = ((newVal - val) / val * 100).toFixed(2);
                const isPositive = parseFloat(change) >= 0;
                changeSpan.innerText = (isPositive ? `▲ +${Math.abs(change)}%` : `▼ -${Math.abs(change)}%`);
                changeSpan.className = isPositive ? 'positive' : 'negative';
            }
        }
    });
}

// Start currency ticker
setInterval(updateTNDTicker, 4500);


