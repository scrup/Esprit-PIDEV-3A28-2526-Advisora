# Shop Module - Guide de Validation (Prof)

Ce document explique le fonctionnement du module shop, page par page, pour pouvoir le presenter en soutenance.

## 1) Architecture technique

- Backend Symfony:
  - `src/Controller/ShopController.php`
  - `src/Service/ClientMarketplaceService.php`
  - `src/Service/ClientMiniShopService.php`
  - `src/Service/ShopListingImageService.php`
  - `src/Service/ShopGeneratedImageService.php`
  - `src/Controller/ShopImageController.php`
- Front Twig:
  - `templates/front/shop/index.html.twig`
  - `templates/front/shop/product.html.twig`
  - `templates/front/shop/checkout.html.twig`
  - `templates/front/shop/checkout_cart.html.twig`
  - `templates/front/shop/wallet.html.twig`
  - `templates/front/shop/publish.html.twig`
  - `templates/front/shop/my_listings.html.twig`
- Style:
  - `public/css/shop.css`

## 2) Regle d acces

- Toutes les routes shop utilisent `requireClient()`.
- Un utilisateur non connecte ou non `CLIENT` est bloque (`AccessDenied`).

## 3) Flux metier global

### A. Publication C2C

1. Le client publie depuis une reservation (projet + ressource + quantite + prix).
2. Si `image_url` vide/invalide:
   - une image auto est generee via le service image.
3. L annonce est inseree en `LISTED` dans `resource_market_listing`.

### B. Achat C2C simple

1. Le client choisit une annonce ouverte (`LISTED`, `qtyRemaining > 0`, vendeur different).
2. Le service verifie:
   - stock reel vendeur (reservation - autres annonces),
   - solde wallet acheteur.
3. Si ok:
   - debit wallet acheteur,
   - credit wallet vendeur,
   - creation commande + livraison,
   - tentative sync Fiabilo.
4. Redirection vers la fiche produit section avis (`#shop-reviews`).

### C. Achat C2C panier global (multi-annonces)

1. Le panier C2C est stocke en session (`shop_checkout_cart`).
2. Checkout global execute chaque ligne en sequence.
3. Chaque ligne cree sa commande/livraison metier.
4. En fin de flux:
   - succes: redirection vers section avis d une annonce achetee,
   - partiel/erreur: lignes restantes conservees dans le panier.

### D. Solde insuffisant (auto-topup)

1. Le contexte achat est stocke en session `shop_pending_market_buy`.
2. Une recharge est creee automatiquement (provider choisi).
3. Si provider Stripe et URL de paiement disponible:
   - redirection Stripe.
4. Au retour Stripe success (ou confirmation manuelle):
   - reprise auto du checkout en attente.

## 4) Logique wallet

- Table compte: `resource_wallet_account` (`balanceCoins`).
- Mouvements: `resource_wallet_txn` (`TOPUP`, `SHOP_BUY`, `SHOP_SELL`).
- Conversion:
  - taux configurable via `SHOP_COIN_RATE` (fallback `10.0`).
  - `coinsToMoney()` pour calcul recharge manquante.

## 5) Avis clients

- Soumission via `submitReview()`.
- Le client ne peut pas noter sa propre annonce.
- Un seul avis par client et par annonce.
- La vue produit/checkout montre:
  - note moyenne,
  - liste des avis,
  - formulaire avis.
- Apres commande, redirection directe vers la section avis.

## 6) Gestion d images

- `ShopListingImageService`:
  - valide URL image custom,
  - stocke upload si fourni,
  - fallback auto base sur le nom exact de la ressource.
- `ShopImageController` + `ShopGeneratedImageService`:
  - generation image/SVG dynamique.
- Twig helper:
  - `shop_image_url(...)` via extension Twig dediee.

## 7) Sessions utilisees

- `shop_cart`: panier catalogue fournisseur.
- `shop_checkout_cart`: panier C2C multi-annonces.
- `shop_checkout_draft`: compat legacy (single checkout).
- `shop_pending_market_buy`: contexte achat a reprendre apres topup.

## 8) Recharges en attente (etat actuel)

- L ancienne page "recharges en attente" est desactivee dans le parcours utilisateur.
- Toute navigation topup redirige vers wallet.
- Le mecanisme topup/reprise reste actif cote backend.

## 9) Points de controle pour demo prof

1. Publication sans image URL:
   - l annonce recupere une image auto.
2. Ajout panier C2C multi-produits:
   - checkout global affiche total unique en coins.
3. Achat avec solde suffisant:
   - pas de Stripe,
   - commande creee,
   - redirection vers avis.
4. Achat avec solde insuffisant:
   - creation topup auto,
   - reprise checkout apres confirmation.
5. Avis client:
   - visible dans fiche produit,
   - moyenne mise a jour.

## 10) Commandes de verification

- Syntaxe PHP:
  - `php -l src/Controller/ShopController.php`
- Twig:
  - `php bin/console lint:twig templates/front/shop`
- Container:
  - `php bin/console lint:container --env=dev`
- Cache:
  - `php bin/console cache:clear --env=dev`

## 11) Message de conclusion metier

Le module shop est organise en architecture Symfony claire (controller/service/templates), avec separation des responsabilites:
- Controller: orchestration HTTP/session/redirections.
- Services: regles metier et transactions SQL.
- Templates/CSS: rendu UI moderne.

Le flux couvre publication, panier, achat, wallet, livraison, avis, et reprise auto apres recharge.
