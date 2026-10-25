<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

final class ShippingQuote
{
    public function __construct(
        /** service name shown by Google ("Standard", "Express"…), usually the shipping method name */
        public readonly string $service,
        /** price in the smallest currency unit */
        public readonly int $price,
    ) {
    }
}
