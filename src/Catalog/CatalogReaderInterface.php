<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Catalog;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;

/**
 * Reads the products of a channel for the feed. The default reader (DbalCatalogReader) reads the standard Sylius
 * tables; to add data of your own model, prefer a CatalogExtensionInterface over replacing the reader.
 */
interface CatalogReaderInterface
{
    public function read(FeedContext $context): Catalog;
}
