<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Catalog;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;

/**
 * Completes the catalogue read by the reader, before the feed is written: brand, GTIN, availability, variant
 * axis, order or selection of the products, from columns the standard Sylius model does not have.
 *
 * Services implementing it are autoconfigured (tag `altmenorg_sylius_google_shopping.catalog_extension`) and
 * called in order of their `priority` attribute, highest first.
 */
interface CatalogExtensionInterface
{
    public function extend(Catalog $catalog, FeedContext $context): void;
}
