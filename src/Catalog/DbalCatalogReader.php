<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Catalog;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedTaxon;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedVariant;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\ParameterType;

/**
 * Default reader: the standard Sylius tables, read with DBAL in a handful of queries for the whole catalogue
 * (no entity hydration: a catalogue of thousands of variants is read in well under a second).
 *
 *  - products: enabled, in the channel, translated in the locale of the feed; in order of id;
 *  - variants: enabled, priced in the channel, in order of position;
 *  - variant axis: a product with options (or with more than one variant) has variants, its label is the names
 *    of its options ("Color", "Size, Color"), the value of a variant the values of its options ("Red / XL"),
 *    else its name;
 *  - taxons: name, slug, parent, level, and the Google product category (column added by the plugin);
 *  - images: the paths of the product images of the `images.types` setting (all when empty), main type first,
 *    then in order of position.
 *
 * Brand, GTIN and ISBN are not in the standard model: they stay empty unless a catalogue extension sets them.
 */
final class DbalCatalogReader implements CatalogReaderInterface
{
    /**
     * @param list<string> $imageTypes
     */
    public function __construct(
        private readonly Connection $db,
        private readonly array $imageTypes = [],
        private readonly string $mainImageType = 'main',
    ) {
    }

    public function read(FeedContext $context): Catalog
    {
        $catalog = new Catalog([], $this->taxons($context));

        $products = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT p.id, p.code, p.main_taxon_id, pt.name, pt.slug, pt.description, pt.short_description
             FROM sylius_product p
             JOIN sylius_product_channels pc ON pc.product_id = p.id
             JOIN sylius_channel c ON c.id = pc.channel_id AND c.code = :channel
             JOIN sylius_product_translation pt ON pt.translatable_id = p.id AND pt.locale = :locale
             WHERE p.enabled = 1
             ORDER BY p.id',
            ['channel' => $context->channelCode, 'locale' => $context->localeCode],
        ) as $r) {
            $products[(int) $r['id']] = new FeedProduct(
                (int) $r['id'],
                (string) $r['code'],
                (string) $r['name'],
                (string) $r['slug'],
                $r['description'] !== null ? (string) $r['description'] : null,
                $r['short_description'] !== null ? (string) $r['short_description'] : null,
                $r['main_taxon_id'] !== null ? (int) $r['main_taxon_id'] : null,
            );
        }

        $optionValues = $this->variantOptionValues($context);
        foreach ($this->db->fetchAllAssociative(
            'SELECT v.id, v.product_id, v.code, v.weight, v.tracked, v.on_hand, v.on_hold, vt.name, cp.price, cp.original_price
             FROM sylius_product_variant v
             JOIN sylius_channel_pricing cp ON cp.product_variant_id = v.id AND cp.channel_code = :channel
             LEFT JOIN sylius_product_variant_translation vt ON vt.translatable_id = v.id AND vt.locale = :locale
             WHERE v.enabled = 1
             ORDER BY v.product_id, v.position, v.id',
            ['channel' => $context->channelCode, 'locale' => $context->localeCode],
        ) as $r) {
            $product = $products[(int) $r['product_id']] ?? null;
            if ($product === null || $r['price'] === null) {
                continue;
            }
            $product->variants[] = $variant = new FeedVariant(
                (int) $r['id'],
                (string) $r['code'],
                $optionValues[(int) $r['id']] ?? (string) ($r['name'] ?? ''),
                (int) $r['price'],
                $r['original_price'] !== null ? (int) $r['original_price'] : null,
                $r['weight'] !== null ? (float) $r['weight'] : null,
                (bool) $r['tracked'],
                (int) $r['on_hand'],
                (int) $r['on_hold'],
            );
            $variant->name = (string) ($r['name'] ?? '');
        }

        $optionNames = $this->productOptionNames($context);
        foreach ($products as $id => $product) {
            if (isset($optionNames[$id])) {
                $product->variantLabel = $optionNames[$id];
            } elseif (count($product->variants) > 1) {
                $product->variantLabel = '';
            }
        }

        foreach ($this->images() as $ownerId => $paths) {
            if (isset($products[$ownerId])) {
                $products[$ownerId]->images = $paths;
            }
        }

        $catalog->products = array_values($products);

        return $catalog;
    }

    /** @return array<int, FeedTaxon> */
    private function taxons(FeedContext $context): array
    {
        $sql = 'SELECT t.id, t.code, t.parent_id, t.tree_level, tt.name, tt.slug%s
                FROM sylius_taxon t
                LEFT JOIN sylius_taxon_translation tt ON tt.translatable_id = t.id AND tt.locale = :locale';
        try {
            $rows = $this->db->fetchAllAssociative(sprintf($sql, ', t.google_product_category'), ['locale' => $context->localeCode]);
        } catch (DbalException) {
            // migration of the plugin not run yet: no Google product category
            $rows = $this->db->fetchAllAssociative(sprintf($sql, ''), ['locale' => $context->localeCode]);
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = new FeedTaxon(
                (int) $r['id'],
                (string) $r['code'],
                $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
                (int) $r['tree_level'],
                (string) ($r['name'] ?? ''),
                (string) ($r['slug'] ?? ''),
                isset($r['google_product_category']) && $r['google_product_category'] !== '' ? (string) $r['google_product_category'] : null,
            );
        }

        return $out;
    }

    /** Names of the options of each product, in order of option position: "Size, Color". @return array<int, string> */
    private function productOptionNames(FeedContext $context): array
    {
        $out = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT po.product_id, ot.name
             FROM sylius_product_options po
             JOIN sylius_product_option o ON o.id = po.option_id
             LEFT JOIN sylius_product_option_translation ot ON ot.translatable_id = o.id AND ot.locale = :locale
             ORDER BY po.product_id, o.position, o.id',
            ['locale' => $context->localeCode],
        ) as $r) {
            $id = (int) $r['product_id'];
            $out[$id] = isset($out[$id]) ? $out[$id] . ', ' . $r['name'] : (string) $r['name'];
        }

        return $out;
    }

    /** Values of the options of each variant, in order of option position: "Red / XL". @return array<int, string> */
    private function variantOptionValues(FeedContext $context): array
    {
        $out = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT vov.variant_id, vt.value
             FROM sylius_product_variant_option_value vov
             JOIN sylius_product_option_value ov ON ov.id = vov.option_value_id
             JOIN sylius_product_option o ON o.id = ov.option_id
             LEFT JOIN sylius_product_option_value_translation vt ON vt.translatable_id = ov.id AND vt.locale = :locale
             ORDER BY vov.variant_id, o.position, o.id',
            ['locale' => $context->localeCode],
        ) as $r) {
            $id = (int) $r['variant_id'];
            $out[$id] = isset($out[$id]) ? $out[$id] . ' / ' . $r['value'] : (string) $r['value'];
        }

        return $out;
    }

    /** @return array<int, list<string>> */
    private function images(): array
    {
        $order = ' ORDER BY owner_id, COALESCE(type, \'\') <> ?, position, id';
        $rows = $this->imageTypes === []
            ? $this->db->fetchAllAssociative('SELECT owner_id, path FROM sylius_product_image' . $order, [$this->mainImageType])
            : $this->db->fetchAllAssociative(
                'SELECT owner_id, path FROM sylius_product_image WHERE type IN (?)' . $order,
                [$this->imageTypes, $this->mainImageType],
                [ArrayParameterType::STRING, ParameterType::STRING],
            );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['owner_id']][] = (string) $r['path'];
        }

        return $out;
    }
}
