<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Tests\Fixtures;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedVariant;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\ShippingQuote;
use Altmenorg\SyliusGoogleShoppingPlugin\Routing\ProductUrlGeneratorInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Shipping\ShippingQuoteProviderInterface;

/** `<site>/<taxon slug>/<slug>.html`, like a shop that kept the URLs of its former platform. */
final class FakeUrls implements ProductUrlGeneratorInterface
{
    public function generate(FeedProduct $product, Catalog $catalog, FeedContext $context): string
    {
        $taxon = $product->mainTaxonId !== null ? $catalog->taxons[$product->mainTaxonId]->slug . '/' : '';

        return $context->siteUrl . '/' . $taxon . $product->slug . '.html';
    }
}
