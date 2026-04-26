<?php

namespace App\Twig;

use App\Service\ShopListingImageService;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ShopImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly ShopListingImageService $shopListingImageService,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('shop_image_url', [$this, 'resolveImageUrl']),
        ];
    }

    public function resolveImageUrl(?string $path, string $variant = 'card'): string
    {
        $resolvedPath = is_string($path) ? trim($path) : '';
        if ($resolvedPath === '') {
            return '';
        }

        if (!$this->shopListingImageService->isLocalShopListingPath($resolvedPath)) {
            return $resolvedPath;
        }

        if (!$this->container->has('liip_imagine.cache.manager')) {
            return $resolvedPath;
        }

        $filter = match ($variant) {
            'thumb' => 'shop_listing_thumb',
            'detail' => 'shop_listing_detail',
            default => 'shop_listing_card',
        };

        try {
            $cacheManager = $this->container->get('liip_imagine.cache.manager');
            if (!$cacheManager instanceof CacheManager) {
                return $resolvedPath;
            }

            return $cacheManager->getBrowserPath($resolvedPath, $filter);
        } catch (\Throwable) {
            return $resolvedPath;
        }
    }
}
