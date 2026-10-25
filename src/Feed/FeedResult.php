<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

final class FeedResult
{
    public function __construct(
        public readonly string $xml,
        public readonly int $items,
        public readonly int $products,
    ) {
    }
}
