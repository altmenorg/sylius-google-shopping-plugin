<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Google product category on the taxon. Added only if missing, so that a shop which already created the column
 * (same name and type) can adopt the plugin without a data migration.
 */
final class Version20261026000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Google Shopping: google_product_category on sylius_taxon.';
    }

    public function up(Schema $schema): void
    {
        $taxon = $schema->getTable('sylius_taxon');
        if (!$taxon->hasColumn('google_product_category')) {
            $taxon->addColumn('google_product_category', Types::STRING, ['length' => 255, 'notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $taxon = $schema->getTable('sylius_taxon');
        if ($taxon->hasColumn('google_product_category')) {
            $taxon->dropColumn('google_product_category');
        }
    }
}
