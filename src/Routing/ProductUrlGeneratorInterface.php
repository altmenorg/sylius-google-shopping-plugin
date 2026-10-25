<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Routing;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;

/**
 * Absolute address of a product page, without query string (the feed adds `link_query` and the variant
 * parameter). Replace the service (`product_url_generator` setting) when the shop has its own product URLs.
 */
interface ProductUrlGeneratorInterface
{
    public function generate(FeedProduct $product, Catalog $catalog, FeedContext $context): string;
}
