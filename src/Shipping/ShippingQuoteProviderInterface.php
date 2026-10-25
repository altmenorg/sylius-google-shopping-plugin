<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Shipping;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedVariant;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\ShippingQuote;

/**
 * Shipping costs of one unit of a variant, sent alone to the country of the feed. Replace the service
 * (`shipping.provider` setting) when the shop computes them in its own way.
 */
interface ShippingQuoteProviderInterface
{
    /** @return list<ShippingQuote> in the order they are published (cheapest first, usually) */
    public function quotes(FeedProduct $product, FeedVariant $variant, FeedContext $context): array;
}
