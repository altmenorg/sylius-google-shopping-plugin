<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

/**
 * The catalogue of one channel, as read for the feed. Extensions may complete, filter or reorder the products:
 * the feed is written in the order of `$products`.
 */
final class Catalog
{
    /**
     * @param list<FeedProduct>       $products
     * @param array<int, FeedTaxon>   $taxons   by id
     */
    public function __construct(
        public array $products = [],
        public array $taxons = [],
    ) {
    }

    /**
     * Google product category of a taxon: its own, or (with `$inherit`) the nearest one up its parents.
     */
    public function googleProductCategory(?int $taxonId, bool $inherit = true): string
    {
        while ($taxonId !== null && isset($this->taxons[$taxonId])) {
            $taxon = $this->taxons[$taxonId];
            if ($taxon->googleProductCategory !== null && $taxon->googleProductCategory !== '') {
                return $taxon->googleProductCategory;
            }
            if (!$inherit) {
                return '';
            }
            $taxonId = $taxon->parentId;
        }

        return '';
    }

    /** Names of a taxon and its parents, root excluded: ["Spices", "Classics"]. @return list<string> */
    public function taxonPath(?int $taxonId): array
    {
        $names = [];
        while ($taxonId !== null && isset($this->taxons[$taxonId]) && $this->taxons[$taxonId]->level > 0) {
            array_unshift($names, $this->taxons[$taxonId]->name);
            $taxonId = $this->taxons[$taxonId]->parentId;
        }

        return $names;
    }
}
