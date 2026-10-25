<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

/** Column `sylius_taxon.google_product_category`, added by the migration of the plugin. */
trait GoogleProductCategoryAwareTrait
{
    #[ORM\Column(name: 'google_product_category', type: 'string', length: 255, nullable: true)]
    protected ?string $googleProductCategory = null;

    public function getGoogleProductCategory(): ?string
    {
        return $this->googleProductCategory;
    }

    public function setGoogleProductCategory(?string $googleProductCategory): void
    {
        $googleProductCategory = $googleProductCategory !== null ? trim($googleProductCategory) : null;
        $this->googleProductCategory = $googleProductCategory === '' ? null : $googleProductCategory;
    }
}
