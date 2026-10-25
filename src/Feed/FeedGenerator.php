<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

use Altmenorg\SyliusGoogleShoppingPlugin\Catalog\CatalogExtensionInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Catalog\CatalogReaderInterface;

/** Reads the catalogue, lets the extensions complete it, writes the feed. Read-only: nothing is saved. */
final class FeedGenerator
{
    /**
     * @param iterable<CatalogExtensionInterface> $extensions
     */
    public function __construct(
        private readonly CatalogReaderInterface $reader,
        private readonly iterable $extensions,
        private readonly FeedBuilder $builder,
    ) {
    }

    public function generate(FeedContext $context): FeedResult
    {
        $catalog = $this->reader->read($context);
        foreach ($this->extensions as $extension) {
            $extension->extend($catalog, $context);
        }

        return $this->builder->build($catalog, $context);
    }
}
