# 🗺️ DIAGRAMMES ARCHITECTURAUX - ADVISORA

---

## 1️⃣ DIAGRAMME FLUX UTILISATEUR - MARKETPLACE C2C

```
┌─────────────────────────────────────────────────────────────────┐
│                    UTILISATEUR CLIENT (LOGIN)                    │
└─────────────────────┬───────────────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┬──────────────────────┐
        │                           │                      │
        ▼                           ▼                      ▼
  ┌──────────────┐          ┌──────────────┐     ┌────────────────┐
  │   ACHETEUR   │          │  VENDEUR     │     │  WALLET OWNER  │
  └──────┬───────┘          └──────┬───────┘     └────────┬────────┘
         │                         │                      │
    Voir annonces            Publier annonce         Recharger coins
         │                         │                      │
         ▼                         ▼                      ▼
  ┌──────────────────┐   ┌────────────────────┐  ┌──────────────────┐
  │ Boutique/Shop    │   │ Publish Form       │  │ Wallet Form      │
  │ (Annonces)       │   │ - Select Réserva   │  │ - Montant        │
  │ - Images grid    │   │ - Qty + Price      │  │ - Provider       │
  │ - Price/Dispo    │   │ - Image (opt)      │  │ - Confirm button │
  └────┬─────────────┘   │ - Note             │  └────┬─────────────┘
       │                 └──────┬─────────────┘       │
       │ Clic image            │                       │
       ▼                       ▼                       ▼
  ┌──────────────────┐  ┌────────────────────┐  ┌──────────────────┐
  │ Product Detail   │  │ ClientMarketplace  │  │ Create Topup     │
  │ - Full images    │  │ Service            │  │ - resource_wallet│
  │ - Spec/stock     │  │ ::publishListing() │  │   _topup (PEND)  │
  │ - Add/Buy BTN    │  │                    │  │                  │
  └────┬─────────────┘  └──────┬─────────────┘  └────────┬─────────┘
       │                       │                        │
       ├─"Add to cart"─────┐   │                   Wait for payment
       │                   │   │ Redirect shop    │
       │ "Buy now"─────┐   │   ▼                  ▼
       │               │   └─→[Boutique page]   [Recharge page]
       │               │      (listing visible)  (PENDING)
       ▼               ▼
  ┌─────────────────────────────────┐
  │     Checkout Form               │
  │ - Qty + Project target          │
  │ - Delivery info (opt)           │
  │ - Wallet balance check          │
  │ - Final price                   │
  └──────────┬──────────────────────┘
             │
             ▼
  ┌─────────────────────────────────┐
  │ ShopController::checkout()      │
  │ Validate qty/wallet             │
  │ Debit wallet coins              │
  │ Create market_order (PENDING)   │
  │ Create delivery (PENDING)       │
  │ Send notification               │
  └──────────┬──────────────────────┘
             │
             ▼
  ┌─────────────────────────────────┐
  │  Confirmation page              │
  │  Order #XX created              │
  │  Seller notified                │
  │  Delivery in progress           │
  └─────────────────────────────────┘
```

---

## 2️⃣ DIAGRAMME BASE DE DONNÉES - ENTITÉS MARKETPLACE

```
┌──────────────────────────────┐
│          USER                │
├──────────────────────────────┤
│ idUser (PK)                  │
│ email                        │
│ roles (JSON)                 │
│ wallet_account_id (FK)       │
└────┬──────────────┬──────────┘
     │              │
     │ (1:1)        │ (1:N) SELLER
     │              │
     ▼              ▼
┌──────────────────────────────┐  ┌──────────────────────────────────┐
│ ResourceWalletAccount        │  │  ResourceMarketListing           │
├──────────────────────────────┤  ├──────────────────────────────────┤
│ idUser (PK,FK)               │  │ idListing (PK)                   │
│ balanceCoins                 │  │ sellerUserId (FK→User)           │
│ updatedAt                    │  │ idProj (FK→Project)              │
└──────────────────────────────┘  │ idRs (FK→Resource)               │
                                   │ qtyInitial                       │
                                   │ qtyRemaining                     │
                                   │ unitPrice                        │
                                   │ status (LISTED/SOLD_OUT/CANC)    │
                                   │ note                             │
                                   │ image_url                        │
                                   │ createdAt / updatedAt            │
                                   └───────┬──────┬──────┬────────────┘
                                           │      │      │
                                    (1:N)  │      │      │ (1:N)
                                           │      │      │
                  ┌─────────────────────────┘      │      │
                  │                ┌────────────────┘      │
                  │                │           ┌──────────┘
                  ▼                ▼           ▼
            ┌──────────────┐  ┌───────────┐ ┌──────────────────┐
            │ ResourceWallet   │ Project   │ │    Resource      │
            │ Topup        │  ├───────────┤ ├──────────────────┤
            ├──────────────┤  │ idProj    │ │ idRs             │
            │ idTopup (PK) │  │ name      │ │ name             │
            │ userId (FK)  │  │ owner_id  │ │ status           │
            │ amount       │  │ (FK→User) │ │ supplier_id      │
            │ coins        │  └───────────┘ │ (FK→Supplier)    │
            │ provider     │                │ description      │
            │ (STRIPE...)  │                │ image_url        │
            │ status       │                └──────────────────┘
            │ (PENDING...)    │
            └──────────────────┘

                                   ┌──────────────────────────────────┐
                                   │ ResourceMarketOrder              │
                                   ├──────────────────────────────────┤
                                   │ idOrder (PK)                     │
                                   │ listingId (FK→Listing)           │
                                   │ buyerId (FK→User)                │
                                   │ qty                              │
                                   │ unitPrice (snapshot)             │
                                   │ status (PENDING/CONFIRMED/...)   │
                                   │ createdAt                        │
                                   └──────────────────────────────────┘
                                           │
                                           │ (1:1)
                                           ▼
                                   ┌──────────────────────────────────┐
                                   │ ResourceMarketDelivery           │
                                   ├──────────────────────────────────┤
                                   │ idDelivery (PK)                  │
                                   │ orderId (FK)                     │
                                   │ status (PENDING/SHIPPED/DEL)     │
                                   │ trackingNo                       │
                                   │ shippedAt / deliveredAt          │
                                   └──────────────────────────────────┘
```

---

## 3️⃣ DIAGRAMME ARCHITECTURE SYMFONY - LAYERS

```
┌───────────────────────────────────────────────────────────────────┐
│                    FRONTEND LAYER (Twig)                          │
│                                                                   │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐             │
│  │shop.html │ │checkout  │ │my_listings   │wallet   │             │
│  │.twig     │ │.html.twig│ │.html.twig    │.html    │             │
│  │          │ │          │ │              │.twig    │             │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘             │
│                                                                   │
│  CSS: shop.css (2263 lignes) | front.css | ...                   │
│  JS: Vanilla JS + optional Stimulus (minimal)                    │
└─────────────────────────────┬─────────────────────────────────────┘
                              │ HTTP Request/Response
                              ▼
┌───────────────────────────────────────────────────────────────────┐
│           CONTROLLER LAYER (Route + Request Handling)             │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  ShopController (ResourceShopBundle)                       │  │
│  │  - index() → GET /boutique (Shop view)                     │  │
│  │  - publishForm() → GET /boutique/publier                   │  │
│  │  - publishListing() → POST /boutique/publier               │  │
│  │  - myListings() → GET /boutique/mes-annonces               │  │
│  │  - cancelListing() → POST /boutique/cancel                 │  │
│  │  - wallet() → GET /boutique/wallet                         │  │
│  │  - topups() → GET /boutique/recharges                      │  │
│  │  - productDetail() → GET /boutique/produit/{id}            │  │
│  │  - checkout() → POST /boutique/checkout                    │  │
│  │  - directCheckout() → POST /checkout/direct                │  │
│  │  - addToCart() → POST /panier/add                          │  │
│  │  - removeFromCart() → POST /panier/remove                  │  │
│  │  ... 15+ actions                                           │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Other Controllers                                         │  │
│  │  - ProjectController                                       │  │
│  │  - ResourceController                                      │  │
│  │  - SecurityController                                      │  │
│  │  - FrontController                                         │  │
│  │  - BackController (Admin)                                  │  │
│  │  ... etc                                                   │  │
│  └────────────────────────────────────────────────────────────┘  │
└─────────────────────────────┬─────────────────────────────────────┘
                              │ Call services + Create entities
                              ▼
┌───────────────────────────────────────────────────────────────────┐
│              SERVICE LAYER (Business Logic)                       │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  ClientMarketplaceService (ResourceShopBundle)             │  │
│  │  - buildPageData()                                         │  │
│  │  - publishListing()                                        │  │
│  │  - cancelListing()                                         │  │
│  │  - createTopup()                                           │  │
│  │  - confirmTopup()                                          │  │
│  │  - validateCheckout()                                      │  │
│  │  - createOrder()                                           │  │
│  │  - debitWallet()                                           │  │
│  │  ... marketplace logic                                     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  ClientMiniShopService (ResourceShopBundle)                │  │
│  │  - buildPageData()                                         │  │
│  │  - Cart management                                         │  │
│  │  - Formatting data                                         │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Core Services                                             │  │
│  │  - ResourceReservationService                              │  │
│  │  - ProjectAcceptanceService                                │  │
│  │  - BookingStatusStore                                      │  │
│  │  - TaskProgressService                                     │  │
│  └────────────────────────────────────────────────────────────┘  │
└─────────────────────────────┬─────────────────────────────────────┘
                              │ Use repositories
                              ▼
┌───────────────────────────────────────────────────────────────────┐
│         REPOSITORY LAYER (Data Access Pattern)                    │
│                                                                   │
│  EntityRepositories (via Doctrine):                              │
│  - ResourceMarketListingRepository                               │
│  - ResourceWalletAccountRepository                               │
│  - ResourceWalletTopupRepository                                 │
│  - ResourceWalletTxnRepository                                   │
│  - ProjectResourceRepository                                     │
│  - UserRepository                                                │
│  ... (24+ repositories total)                                    │
│                                                                   │
│  Custom query methods:                                           │
│  - findOpenListings()                                            │
│  - findUserListings()                                            │
│  - findPendingTopups()                                           │
│  ... domain-specific queries                                     │
└─────────────────────────────┬─────────────────────────────────────┘
                              │ Query
                              ▼
┌───────────────────────────────────────────────────────────────────┐
│           PERSISTENCE LAYER (Doctrine ORM)                        │
│                                                                   │
│  Entities (25+):                                                 │
│  ├─ User                                                         │
│  ├─ Resource                                                     │
│  ├─ Project / ProjectResource                                    │
│  ├─ ResourceMarketListing                                        │
│  ├─ ResourceMarketOrder                                          │
│  ├─ ResourceMarketDelivery                                       │
│  ├─ ResourceMarketReview                                         │
│  ├─ ResourceWalletAccount / TopUp / Txn                          │
│  └─ ... (and others)                                             │
│                                                                   │
│  Doctrine Migrations:                                            │
│  └─ Version20260406221000.php                                    │
└─────────────────────────────┬─────────────────────────────────────┘
                              │ SQL Queries
                              ▼
┌───────────────────────────────────────────────────────────────────┐
│            DATABASE LAYER (MySQL/MariaDB)                         │
│                                                                   │
│  26 tables:                                                       │
│  - users / auth_session / password_reset                         │
│  - resources / project_resources / cataloguefournisseur          │
│  - projects / tasks / decisions / events                         │
│  - resource_market_listing / order / delivery / review           │
│  - resource_wallet_account / topup / txn                         │
│  - ... (and more)                                                │
└───────────────────────────────────────────────────────────────────┘
```

---

## 4️⃣ CHECKLIST DÉVELOPPEMENT

### ✅ FAIT
- [x] Architecture MVC
- [x] Authentification & Rôles
- [x] Marketplace C2C
- [x] Wallet & Transactions
- [x] Payment providers (STRIPE/FLOUCI/MANUAL)
- [x] Publication annonces
- [x] Panier & Checkout
- [x] Design responsive
- [x] Validation serveur
- [x] Session management

### 🔲 À FAIRE (Court terme)
- [ ] Tests unitaires/intégration
- [ ] Logging & Monitoring
- [ ] Cache (Redis/HTTP)
- [ ] Performance optimization
- [ ] Email notifications
- [ ] Image optimization
- [ ] SEO metadata
- [ ] Mobile app (API REST)

### 🔲 À FAIRE (Moyen terme)
- [ ] Admin Dashboard
- [ ] Analytics & Metrics
- [ ] Advanced filtering
- [ ] Wishlist
- [ ] Ratings & Reviews
- [ ] Push notifications
- [ ] Real-time updates (WebSocket)
- [ ] API versioning

### 🔲 À FAIRE (Long terme)
- [ ] Microservices
- [ ] Message queues
- [ ] CDN integration
- [ ] Compliance (GDPR/PCI)
- [ ] Fraud detection
- [ ] Recommendations (ML)
- [ ] Multi-language support
- [ ] Global expansion

---

## 5️⃣ CHECKLIST DÉPLOIEMENT

```
PRÉ-DÉPLOIEMENT:
  [ ] Cache clear
  [ ] Database migrations
  [ ] Assets compiled
  [ ] ENV vars configured
  [ ] Logs directory writable
  [ ] Upload directory permissions
  [ ] SSL certificate active

TESTS:
  [ ] Unit tests pass
  [ ] Integration tests pass
  [ ] Smoke tests (critical flows)
  [ ] Security scan (OWASP)
  [ ] Performance testing

MONITORING:
  [ ] Error tracking (Sentry)
  [ ] Uptime monitoring
  [ ] Database backups
  [ ] Logs aggregation
  [ ] Metrics dashboard

POST-DÉPLOIEMENT:
  [ ] Health check endpoints
  [ ] Test user flows
  [ ] Monitor error logs
  [ ] Check performance metrics
  [ ] User feedback collection
```

---

## 6️⃣ RESSOURCES & DOCUMENTATION

### Symfony Official
- https://symfony.com/doc/6.4/
- https://symfony.com/components/HttpFoundation
- https://symfony.com/doc/current/security.html

### Doctrine ORM
- https://www.doctrine-project.org/projects/orm.html
- https://www.doctrine-project.org/projects/doctrine-orm/en/2.17/index.html

### Frontend
- https://twig.symfony.com/
- https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout
- https://fontawesome.com/icons

### Tools
- PHPUnit: https://phpunit.de/
- PHPStan: https://phpstan.org/
- Symfony Maker Bundle: https://symfony.com/doc/current/bundles/SymfonyMakerBundle/

---

**Document généré:** 20 Avril 2026  
**Analysé par:** GitHub Copilot  
**Status:** ✅ Complet & Prêt pour développement  
