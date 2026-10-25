<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

/**
 * Settings of the feed content, from the `altmenorg_sylius_google_shopping` bundle configuration (see
 * Configuration for the documentation of each one). Defaults suit a standard Sylius shop.
 */
final class FeedOptions
{
    /**
     * @param list<string>                                     $excludedProducts       product codes never published
     * @param list<array{pattern: string, replacement: string}> $textReplacements       preg_replace on title and product type
     * @param list<array{match: string, tags: list<string>}>   $variantAttributes      variant axis label → tags of the value
     * @param list<string>                                     $defaultVariantTags
     * @param list<string>                                     $simpleProductEmptyTags empty tags written on simple products
     * @param list<ShippingQuote>                              $prependedShipping
     * @param list<string>                                     $removedShippingServices
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $link = null,
        public readonly array $excludedProducts = [],
        public readonly string $descriptionSource = 'description',
        public readonly bool $stripHtml = true,
        public readonly array $textReplacements = [],
        public readonly string $productTypeSeparator = ' > ',
        public readonly string $variantTitleSeparator = ' - ',
        public readonly string $linkQuery = '',
        public readonly ?string $variantParameter = null,
        public readonly array $variantAttributes = [
            ['match' => 'color', 'tags' => ['color']],
            ['match' => 'colour', 'tags' => ['color']],
            ['match' => 'couleur', 'tags' => ['color']],
        ],
        public readonly array $defaultVariantTags = ['size'],
        public readonly array $simpleProductEmptyTags = [],
        public readonly bool $useCodeAsMpn = false,
        public readonly bool $omitEmptyTags = true,
        public readonly string $availability = 'variant',
        public readonly bool $shippingPerProduct = false,
        public readonly array $prependedShipping = [],
        public readonly array $removedShippingServices = [],
        public readonly string $weightUnit = 'kg',
        public readonly bool $inheritGoogleCategory = true,
        public readonly int $maxAdditionalImages = 10,
    ) {
    }

    /** @param array<string, mixed> $config the processed bundle configuration */
    public static function fromConfig(array $config): self
    {
        $feed = $config['feed'];

        return new self(
            title: $config['channel']['title'],
            description: $config['channel']['description'],
            link: $config['channel']['link'],
            excludedProducts: array_values($feed['excluded_products']),
            descriptionSource: $feed['description_source'],
            stripHtml: $feed['strip_html'],
            textReplacements: array_values($feed['text_replacements']),
            productTypeSeparator: $feed['product_type_separator'],
            variantTitleSeparator: $feed['variant_title_separator'],
            linkQuery: $feed['link_query'],
            variantParameter: $feed['variant_parameter'],
            variantAttributes: array_values($feed['variant_attributes']),
            defaultVariantTags: array_values($feed['default_variant_tags']),
            simpleProductEmptyTags: array_values($feed['simple_product_empty_tags']),
            useCodeAsMpn: $feed['code_as_mpn'],
            omitEmptyTags: $feed['omit_empty_tags'],
            availability: $feed['availability'],
            shippingPerProduct: $config['shipping']['per_product'],
            prependedShipping: array_map(
                static fn (array $q) => new ShippingQuote($q['service'], (int) round($q['price'] * 100)),
                array_values($config['shipping']['prepend']),
            ),
            removedShippingServices: array_values($config['shipping']['remove_services']),
            weightUnit: $feed['weight_unit'],
            inheritGoogleCategory: $feed['inherit_google_category'],
            maxAdditionalImages: $config['images']['max_additional'],
        );
    }
}
