<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
/**
 * Controleur HTTP du module shop.
 *
 * La classe reste volontairement fine: les routes et helpers sont separes par
 * domaine pour rendre la boutique plus facile a lire, maintenir et presenter.
 */
final class ShopController extends AbstractController
{
    private const CART_SESSION_KEY = 'shop_cart';
    private const PENDING_BUY_SESSION_KEY = 'shop_pending_market_buy';
    private const CHECKOUT_CART_SESSION_KEY = 'shop_checkout_cart';
    private const CHECKOUT_DRAFT_SESSION_KEY = 'shop_checkout_draft';
    use ShopPageActionsTrait;
    use ShopMarketplaceActionsTrait;
    use ShopWalletActionsTrait;
    use ShopCartActionsTrait;
    use ShopSupportTrait;
}
