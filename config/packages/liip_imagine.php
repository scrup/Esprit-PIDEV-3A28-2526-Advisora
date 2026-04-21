<?php

declare(strict_types=1);

use Symfony\Config\LiipImagineConfig;

return static function (LiipImagineConfig $liipImagine): void {
    $driver = match (true) {
        extension_loaded('gd') => 'gd',
        extension_loaded('imagick') => 'imagick',
        extension_loaded('gmagick') => 'gmagick',
        default => null,
    };

    if ($driver === null) {
        return;
    }

    $liipImagine->driver($driver);
    $liipImagine->twig()->mode('lazy');

    $liipImagine->filterSet('cache');

    $card = $liipImagine->filterSet('shop_listing_card');
    $card->quality(85);
    $card->filters()->thumbnail()->size([640, 480])->mode('outbound');

    $thumb = $liipImagine->filterSet('shop_listing_thumb');
    $thumb->quality(82);
    $thumb->filters()->thumbnail()->size([220, 220])->mode('outbound');

    $detail = $liipImagine->filterSet('shop_listing_detail');
    $detail->quality(88);
    $detail->filters()->thumbnail()->size([1280, 960])->mode('inset');
};
