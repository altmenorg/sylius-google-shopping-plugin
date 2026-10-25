<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

/**
 * A variant as the feed sees it. Prices are in the smallest currency unit of the channel, tax included as stored
 * by Sylius for the channel.
 */
final class FeedVariant
{
    /** Availability set by an extension; null: tracked stock (on hand − on hold > 0), or in stock if not tracked. */
    public ?bool $inStock = null;

    public ?string $gtin = null;

    /** name of the variant (translation), whatever `$value` holds */
    public string $name = '';

    /** @var array<string, mixed> free data for extensions */
    public array $extra = [];

    public function __construct(
        public readonly int $id,
        public readonly string $code,
        /** variant name, or the values of its options ("250 g", "Red / XL") */
        public string $value,
        public int $price,
        public ?int $originalPrice = null,
        public ?float $weight = null,
        public bool $tracked = false,
        public int $onHand = 0,
        public int $onHold = 0,
    ) {
    }

    /** The regular price: the higher of the price and the original (crossed-out) price of a catalogue promotion. */
    public function basePrice(): int
    {
        return max($this->price, $this->originalPrice ?? 0);
    }
}
