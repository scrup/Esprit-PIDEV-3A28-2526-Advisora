# Exemple de Bundles Externes (Shop deja realise)

## Perimetre
Ce fichier documente uniquement le **metier Shop existant** dans ce projet Symfony.  
Aucun nouveau metier n'est ajoute ici.

## Bundle custom du projet
- `App\ResourceShopBundle\ResourceShopBundle`
- Fichier: `src/ResourceShopBundle/ResourceShopBundle.php`

Ce bundle custom porte toute la logique metier de la boutique (catalogue C2C, checkout, wallet, topup, livraison, avis).

## Bundles externes utilises et personnalises pour le Shop

### 1) `DoctrineBundle` (`doctrine/doctrine-bundle`)
Utilisation dans le shop:
- lecture/ecriture des tables metier (`resource_market_listing`, `resource_market_order`, `resource_market_delivery`, `project_resources`, `resource_wallet_*`, etc.)
- transactions SQL pour securiser checkout et wallet

Customisation metier appliquee:
- regles C2C (interdiction auto-achat, quantite max, statut listing)
- debit/credit wallet acheteur/vendeur
- creation commande + livraison
- gestion stock reserve/projet

### 2) `SecurityBundle` (`symfony/security-bundle`)
Utilisation dans le shop:
- controle d'acces utilisateur

Customisation metier appliquee:
- acces boutique reserve au role `CLIENT` uniquement
- verifications dans le controleur et service metier

### 3) `TwigBundle` (`symfony/twig-bundle`)
Utilisation dans le shop:
- rendu des pages boutique (`shop`, `product`, `checkout`, `wallet`, `topups`, `publish`, `mes-annonces`)

Customisation metier appliquee:
- affichage des statuts metier (LISTED, SOLD_OUT, EN_PREPARATION, ENVOYEE, LIVREE)
- affichage wallet/coins/topups/checkouts en attente
- parcours achat et confirmation cote serveur

### 4) `FrameworkBundle` (`symfony/framework-bundle`)
Utilisation dans le shop:
- routing, session, CSRF, flash messages

Customisation metier appliquee:
- routes metier du shop dans `ShopController`
- session pour pending checkout et draft checkout
- retour utilisateur metier (success/error) apres chaque action

### 5) `KnpPaginatorBundle` (`knplabs/knp-paginator-bundle`)
Utilisation dans le shop:
- pagination serveur des annonces C2C ouvertes
- navigation par pages sans JavaScript

Customisation metier appliquee:
- tri metier cote serveur (`recent`, `price_asc`, `price_desc`, `qty_desc`)
- parametre de page dedie au shop (`c2c_page`)
- configuration du bundle dans `config/packages/knp_paginator.yaml`

### 6) `EndroidQrCodeBundle` (`endroid/qr-code-bundle`)
Utilisation dans le shop:
- generation QR cote serveur pour le suivi des commandes/livraisons
- rendu direct dans la vue commandes sans JavaScript

Customisation metier appliquee:
- payload QR metier: `ORDER#`, `TRACKING`, `STATUS`, `ROLE`
- affichage QR par commande dans la section commandes de la boutique
- configuration bundle dans `config/packages/endroid_qr_code.yaml`

### 7) `stripe/stripe-php` (SDK externe Stripe)
Utilisation dans le shop:
- creation de session Checkout Stripe pour recharges wallet
- verification serveur du paiement Stripe avant credit wallet

Customisation metier appliquee:
- topup wallet avec provider `STRIPE`
- sauvegarde `externalRef` + `paymentUrl` dans `resource_wallet_topup`
- route succes Stripe qui confirme le topup puis relance automatiquement le checkout en attente
- fallback metier: si Stripe indisponible, topup reste `PENDING` avec message

## Activation des bundles (preuve)
Fichier: `config/bundles.php`
- `App\ResourceShopBundle\ResourceShopBundle::class => ['all' => true]`
- `Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true]`
- `Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true]`
- `Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true]`
- `Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true]`
- `Knp\Bundle\PaginatorBundle\KnpPaginatorBundle::class => ['all' => true]`
- `Endroid\QrCodeBundle\EndroidQrCodeBundle::class => ['all' => true]`
  
Package externe (Composer):
- `stripe/stripe-php`

## Resume court
- **Bundle externe** = package installe via Composer (Doctrine, Twig, Security, Framework, KnpPaginator, EndroidQrCode, Stripe SDK).
- **Bundle custom** = code metier fait dans le projet (`ResourceShopBundle`).
- Le shop actuel applique la personnalisation metier dans ce bundle custom en s'appuyant sur ces bundles externes.
