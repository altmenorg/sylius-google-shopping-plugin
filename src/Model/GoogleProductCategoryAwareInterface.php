<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Model;

/**
 * A taxon that carries a Google product category: the id of the Google taxonomy ("2073") or its full path
 * ("Food, Beverages & Tobacco > Food Items > Seasonings & Spices"). Products get the category of their main taxon,
 * else of its nearest parent that has one.
 */
interface GoogleProductCategoryAwareInterface
{
    public function getGoogleProductCategory(): ?string;

    public function setGoogleProductCategory(?string $googleProductCategory): void;
}
