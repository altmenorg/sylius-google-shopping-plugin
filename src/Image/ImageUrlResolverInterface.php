<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Image;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;

/** Absolute address of a product image, from its `sylius_product_image.path`. */
interface ImageUrlResolverInterface
{
    public function resolve(string $path, FeedContext $context): string;
}
