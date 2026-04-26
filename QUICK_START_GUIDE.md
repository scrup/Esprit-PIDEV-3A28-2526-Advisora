# 🚀 GUIDE DE DÉMARRAGE RAPIDE - ADVISORA

---

## 📦 INSTALLATION & SETUP

### Prérequis
```bash
- PHP 8.2+ (vérifier: php -v)
- Composer (vérifier: composer -v)
- MySQL 5.7+ ou MariaDB 10.2+
- Git
- Node.js 16+ (optional - pour assets)
```

### 1️⃣ Cloner et installer les dépendances
```bash
cd c:\Users\USER\Desktop\adi_resource

# Installer PHP dependencies
composer install

# (optionnel) Installer Node.js dependencies
npm install
```

### 2️⃣ Configurer l'environnement
```bash
# Copier le fichier ENV de base
cp .env .env.local

# Configurer la DATABASE_URL dans .env.local
# DATABASE_URL="mysql://user:password@127.0.0.1:3306/advisora"

# Optionnel: Configurer JWT_SECRET pour le panier session
# APP_SECRET=...
```

### 3️⃣ Préparer la base de données
```bash
# Créer la base de données
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# (optionnel) Charger les fixtures (données test)
php bin/console doctrine:fixtures:load --append
```

### 4️⃣ Démarrer le serveur
```bash
# Mode développement
symfony serve

# Ou traditionnel
php -S 127.0.0.1:8000 -t public

# Ou Nginx (prod-like)
docker-compose up -d
```

Accéder à: **http://localhost:8000**

---

## 🔑 AUTHENTIFICATION (SETUP INITIAL)

### Créer un utilisateur CLIENT (marketplace)

```bash
# Via Maker Command
php bin/console make:user

# Ou directement en DB:
INSERT INTO users (email, password, roles, created_at)
VALUES ('client@example.com', '$2y$13$...hashed_password', '["ROLE_CLIENT"]', NOW());

# Password hasher (ex: 'password123'):
php bin/console security:hash-password
```

### Rôles disponibles
```
ROLE_ADMIN      - Accès complet admin
ROLE_BACK       - Accès back-office
ROLE_CLIENT     - Accès marketplace C2C
ROLE_USER       - Utilisateur standard
```

### Login
```
URL: http://localhost:8000/login
Test account:
  Email: client@example.com
  Password: password123
```

---

## 🛍️ FLUX TEST - MARKETPLACE

### Étape 1: Créer des projets pour le CLIENT
```sql
-- Créer un projet pour réserver des ressources
INSERT INTO projects (idUser, name, description, status, created_at)
VALUES (1, 'Mon Projet', 'Description...', 'ACTIVE', NOW());

-- Note: idUser = user_id du CLIENT connecté
-- Récupérer: SELECT * FROM users WHERE email = 'client@example.com';
```

### Étape 2: Créer des réservations (ProjectResources)
```sql
-- Réserver une ressource pour le projet
INSERT INTO project_resources (idProj, idRs, qtyReserved, status, created_at)
VALUES (1, 1, 5, 'CONFIRMED', NOW());

-- idProj = project_id créé étape 1
-- idRs = resource_id existant
-- qtyReserved = quantité réservée
```

### Étape 3: Publier une annonce
```
URL: http://localhost:8000/boutique/publier
Form:
  - Sélectionner la réservation
  - Quantité à publier: 3 (doit être <= qtyReserved)
  - Prix unitaire: 100.00 coins
  - Image: (optionnel)
  - Note: "En bon état"
  
Submit → Annonce créée (status=LISTED)
```

### Étape 4: Voir l'annonce (AUTRE CLIENT ou même client)
```
URL: http://localhost:8000/boutique
- Annonces ouvertes affichées en grid
- Clic sur l'image → Détail produit
```

### Étape 5: Acheter l'annonce
```
Détail produit:
  - Vérifier stock restant
  - Clic "Add to cart" → Panier (session)
  - Clic "Acheter maintenant" → Checkout direct

Checkout:
  - Quantité + Projet cible
  - Vérifier solde wallet
  - Clic "Confirmer achat"
  
Résultat:
  - Coins débités du wallet
  - Commande créée (status=PENDING)
  - Livraison créée (status=PENDING)
  - Annonce stock réduit
```

### Étape 6: Recharger le wallet (optionnel)
```
URL: http://localhost:8000/boutique/wallet
Form:
  - Montant: 50 EUR
  - Provider: MANUAL (pour test)
  
Submit → Topup créé (status=PENDING)

Confirmation (admin):
  - Status → CONFIRMED
  - Coins ajoutés au wallet
  - Checkout en attente tenté automatique
```

---

## 🎯 COMMANDES PRINCIPALES

### Développement
```bash
# Linter Twig (templates)
php bin/console lint:twig templates/

# Linter YAML (config)
php bin/console lint:yaml config/

# Debug routes
php bin/console debug:router

# Debug services
php bin/console debug:container

# Debug form
php bin/console debug:form ResourceListingType

# Générer migration
php bin/console make:migration

# Exécuter migration
php bin/console doctrine:migrations:migrate

# Voir migrations status
php bin/console doctrine:migrations:status

# Clear cache
php bin/console cache:clear --env=dev
php bin/console cache:clear --env=prod
```

### Tests
```bash
# Lancer tous les tests
php bin/phpunit

# Test un fichier spécifique
php bin/phpunit tests/ProjectAcceptanceServiceTest.php

# Test avec coverage
php bin/phpunit --coverage-html coverage/

# Watch mode (si installed)
php bin/phpunit --watch
```

### Fixtures & Data
```bash
# Charger fixtures
php bin/console doctrine:fixtures:load

# Purger données
php bin/console doctrine:fixtures:load --purge-with-truncate

# Générer fake data
# (Créer un DataFixtures custom)
```

### Assets
```bash
# Build assets
npm run build

# Watch assets
npm run watch

# Production build
npm run build:prod
```

---

## 📄 FICHIERS CLÉS À CONNAÎTRE

### Configuration
```
.env.local                 - Variables d'environnement
config/services.yaml       - Enregistrement services
config/routes.yaml         - Routes principales
config/packages/security.yaml - Security config
config/packages/doctrine.yaml - Database config
```

### Marketplace (ResourceShopBundle)
```
src/ResourceShopBundle/Controller/ShopController.php
  → 930 lignes, 15+ actions, logique métier

src/ResourceShopBundle/Service/ClientMarketplaceService.php
  → Cœur métier: publish, checkout, wallet

templates/front/shop.html.twig
  → Page principale boutique

public/css/shop.css
  → Styling marketplace (2263 lignes)
```

### Entités
```
src/Entity/ResourceMarketListing.php
  → Annonces publiées

src/Entity/ResourceMarketOrder.php
  → Commandes achat

src/Entity/ResourceWalletAccount.php
  → Solde wallet

src/Entity/ResourceWalletTopup.php
  → Recharges
```

### Routes
```
config/routes.yaml                → Routes principales
src/ResourceShopBundle/Controller/ → Routes bundle
config/routes/security.yaml       → Routes login/logout
```

---

## 🐛 DÉBOGAGE COURANT

### Le formulaire ne publie pas l'annonce
```bash
# Vérifier les erreurs de validation
php bin/console debug:form ClientPublishListingType

# Vérifier CSRF token dans template
# {{ form_widget(form._token) }}

# Vérifier rôle utilisateur
# SELECT roles FROM users WHERE id = 1;
```

### Panier vide après rechargement
```bash
# Le panier est en session, persiste entre pages
# Mais se réinitialise après clôture navigateur

# Pour persistence persistante: implémenter Redis
# services.yaml:
#   arguments:
#     - "@Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler"
```

### Wallet coins non débités
```bash
# Vérifier que le CLIENT a ROLE_CLIENT
# Vérifier que le solde >= montant checkout
# Vérifier la transaction dans resource_wallet_txn

php bin/console doctrine:query:sql \
  "SELECT * FROM resource_wallet_txn ORDER BY id DESC LIMIT 10;"
```

### Annonce non visible après publication
```bash
# Vérifier status = 'LISTED'
php bin/console doctrine:query:sql \
  "SELECT * FROM resource_market_listing WHERE status='LISTED';"

# Vérifier qtyRemaining > 0
# Vérifier cache (rare en dev)
php bin/console cache:clear
```

---

## 🔒 SÉCURITÉ - CHECKLIST

- [x] Authentification obligatoire pour marketplace
- [x] CSRF tokens sur tous forms
- [x] Validation serveur stricte
- [x] Rôle-based access control
- [x] Hashing passwords
- [x] SQL injection protection (Doctrine ORM)
- [ ] Rate limiting (À implémenter)
- [ ] HTTPS en production (À configurer)
- [ ] Sanitize inputs (À vérifier)
- [ ] GDPR compliance (À implémenter)

### Sécuriser avant production
```yaml
# .env.prod
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL=mysql://...
JWT_SECRET=...secure_random_string...
MAILER_DSN=...

# Symfony config/packages/security.yaml
security:
  password_hashers:
    App\Entity\User:
      algorithm: auto
  firewalls:
    main:
      requires_fresh_login: true
      logout:
        path: app_logout
```

---

## 📊 MONITORING & LOGS

### Logs dossier
```
var/log/dev.log       - Logs développement
var/log/prod.log      - Logs production
var/log/deprecation.log - Deprecations

# Tail logs en temps réel
tail -f var/log/dev.log

# Voir erreurs Doctrine
grep -i "ERROR\|CRITICAL" var/log/dev.log
```

### Profiler Symfony
```
URL: http://localhost:8000/_profiler
- Analyze requests
- View database queries
- Check performance
- Debug variables
```

### Performance basics
```bash
# Lancer dans profiler
# Vérifier:
#   - Nombre requêtes DB (target: <10 par page)
#   - Temps total (target: <500ms)
#   - Memory usage (target: <50MB)

# Optimiser requêtes Doctrine
# Utiliser: ->with() pour eager loading
# Utiliser: QueryBuilder pour pagination
```

---

## 📚 RESSOURCES SUPPLÉMENTAIRES

### Documentation Officielle
- **Symfony**: https://symfony.com/doc/6.4/
- **Doctrine**: https://www.doctrine-project.org/
- **Twig**: https://twig.symfony.com/
- **Security**: https://symfony.com/doc/6.4/security.html

### Tutoriels
- Symfony Course: https://www.symfonycasts.com/
- Doctrine Basics: https://www.doctrine-project.org/projects/doctrine-orm/en/latest/
- CSS Grid: https://css-tricks.com/snippets/css/complete-guide-grid/

### Outils
- **PHPStan** (static analysis): https://phpstan.org/
- **PHP CodeSniffer** (code style): https://github.com/squizlabs/PHP_CodeSniffer
- **Blackfire** (profiling): https://blackfire.io/

---

## 💡 TIPS & TRICKS

### Shortcut Symfony
```bash
# Alias court pour console
alias sf='php bin/console'

# Utilisation
sf doctrine:migrations:migrate
sf debug:router
sf cache:clear
```

### Hot reload (Développement)
```bash
# Installer Symfony CLI pour auto-reload
# https://symfony.com/download

# Ou utiliser Nodemon avec PHP
npm install -g nodemon
nodemon -x "php -S 127.0.0.1:8000" -w src -w templates
```

### Debug Doctrine Queries
```yaml
# config/packages/dev/doctrine.yaml
doctrine:
  dbal:
    logging: true
```
Logs SQL dans var/log/dev.log

### Database reset rapide
```bash
# Attention: Supprime TOUT
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

---

## 🎓 NEXT STEPS

### Court terme (Semaine 1)
- [ ] Explorer la boutique (frontend)
- [ ] Tester la publication d'annonce
- [ ] Vérifier checkout + wallet
- [ ] Lire ShopController.php
- [ ] Comprendre ClientMarketplaceService

### Moyen terme (Semaine 2-3)
- [ ] Écrire des tests (TestCase)
- [ ] Optimiser requêtes DB
- [ ] Ajouter logging
- [ ] Implémenter features manquantes
- [ ] Setup production env

### Long terme (Mois 2+)
- [ ] API REST
- [ ] Mobile app
- [ ] Analytics
- [ ] Notifications
- [ ] Microservices

---

**Guide créé:** 20 Avril 2026  
**Framework:** Symfony 6.4  
**PHP:** 8.2+  
**Status:** ✅ Prêt à l'emploi  

Pour toute question: **Consultez PROJECT_ANALYSIS.md et ARCHITECTURE_DIAGRAMS.md**
