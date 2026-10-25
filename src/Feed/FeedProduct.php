<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

/**
 * A product as the feed sees it: plain data read once for the whole catalogue, then completed by the catalogue
 * extensions (brand, GTIN, availability…) before the feed is written. Public and mutable on purpose.
 */
final class FeedProduct
{
    /** @var list<FeedVariant> enabled variants, in the order of the product page */
    public array $variants = [];

    /** @var list<string> image paths, main image first */
    public array $images = [];

    /**
     * Label of the variant axis ("Color", "Size", "Weight"…), used to choose the tags of each variant
     * (`variant_attributes` setting). Null: a simple product, one item for its first variant.
     */
    public ?string $variantLabel = null;

    public ?string $brand = null;

    /** fallback GTIN for variants without their own */
    public ?string $gtin = null;

    public ?string $isbn = null;

    /**
     * Availability of the whole product, set by an extension that knows better than the variants (a stock shared
     * by all variants, for example). Null: computed from the variants.
     */
    public ?bool $inStock = null;

    /** @var array<string, mixed> free data for extensions (adapter columns…) */
    public array $extra = [];

    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public string $name,
        public string $slug,
        public ?string $description = null,
        public ?string $shortDescription = null,
        public ?int $mainTaxonId = null,
    ) {
    }
}
