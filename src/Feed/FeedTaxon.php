<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

final class FeedTaxon
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly ?int $parentId,
        public readonly int $level,
        public string $name,
        public string $slug,
        /** Google product category (id or full path), set on the taxon in the admin */
        public ?string $googleProductCategory = null,
    ) {
    }
}
