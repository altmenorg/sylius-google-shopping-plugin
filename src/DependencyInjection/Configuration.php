<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('altmenorg_sylius_google_shopping');
        $root = $treeBuilder->getRootNode();
        $root
            ->children()
                ->scalarNode('output')
                    ->info('Feed file written by altmenorg:google-shopping:generate (--output), relative to the project directory or absolute.')
                    ->defaultValue('public/google-shopping.xml')
                ->end()
                ->scalarNode('country')
                    ->info('Country (ISO code) of the published shipping costs. Null: no shipping costs in the feed.')
                    ->defaultNull()
                ->end()
                ->scalarNode('catalog_reader')
                    ->info('Service reading the catalogue (CatalogReaderInterface).')
                    ->defaultValue('altmenorg_sylius_google_shopping.catalog_reader.dbal')
                ->end()
                ->scalarNode('product_url_generator')
                    ->info('Service building the address of a product page (ProductUrlGeneratorInterface).')
                    ->defaultValue('altmenorg_sylius_google_shopping.product_url_generator.sylius')
                ->end()
                ->append($this->channel())
                ->append($this->feed())
                ->append($this->images())
                ->append($this->shipping())
            ->end();

        return $treeBuilder;
    }

    private function channel(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('channel'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('code')->info('Channel of the feed (--channel). Null: the only enabled channel.')->defaultNull()->end()
                ->scalarNode('locale')->info('Locale of the texts (--locale). Null: default locale of the channel.')->defaultNull()->end()
                ->scalarNode('site_url')->info('Shop address used in links. Null: https://<channel hostname>.')->defaultNull()->end()
                ->scalarNode('title')->info('<title> of the feed. Null: channel name.')->defaultNull()->end()
                ->scalarNode('description')->info('<description> of the feed. Null: channel description.')->defaultNull()->end()
                ->scalarNode('link')->info('<link> of the feed. Null: the shop address.')->defaultNull()->end()
            ->end();

        return $node;
    }

    private function feed(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('feed'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('excluded_products')->info('Product codes never published.')->scalarPrototype()->end()->end()
                ->enumNode('description_source')->values(['description', 'short_description'])->defaultValue('description')->end()
                ->booleanNode('strip_html')->info('Turn the description into plain text.')->defaultTrue()->end()
                ->arrayNode('text_replacements')
                    ->info('preg_replace() applied to the title and the product type, in order, then trim().')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('pattern')->isRequired()->end()
                            ->scalarNode('replacement')->defaultValue('')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('product_type_separator')->defaultValue(' > ')->end()
                ->scalarNode('variant_title_separator')->info('Between the product name and the variant value in the title.')->defaultValue(' - ')->end()
                ->scalarNode('link_query')->info('Appended to every product link, e.g. "?utm_source=google".')->defaultValue('')->end()
                ->scalarNode('variant_parameter')->info('Query parameter carrying the variant code in variant links (null: none).')->defaultNull()->end()
                ->arrayNode('variant_attributes')
                    ->info('First rule whose "match" is found in the variant axis label (case-insensitive) gives the tags carrying the variant value.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('match')->isRequired()->end()
                            ->arrayNode('tags')->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        ['match' => 'color', 'tags' => ['color']],
                        ['match' => 'colour', 'tags' => ['color']],
                        ['match' => 'couleur', 'tags' => ['color']],
                    ])
                ->end()
                ->arrayNode('default_variant_tags')->scalarPrototype()->end()->defaultValue(['size'])->end()
                ->arrayNode('simple_product_empty_tags')->info('Empty tags written on simple products (e.g. ["color", "size"]).')->scalarPrototype()->end()->end()
                ->booleanNode('code_as_mpn')->info('Write the variant (or product) code as g:mpn.')->defaultFalse()->end()
                ->booleanNode('omit_empty_tags')->info('Leave out tags without a value (brand, gtin, isbn, category…).')->defaultTrue()->end()
                ->enumNode('availability')
                    ->info('variant: each variant its own stock; product: every variant "in stock" while the product has stock.')
                    ->values(['variant', 'product'])->defaultValue('variant')
                ->end()
                ->enumNode('weight_unit')->info('Unit of the variant weights in Sylius ("g" is published in kg).')->values(['kg', 'g', 'lb', 'oz'])->defaultValue('kg')->end()
                ->booleanNode('inherit_google_category')->info('A taxon without Google category takes the one of its nearest parent.')->defaultTrue()->end()
            ->end();

        return $node;
    }

    private function images(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('images'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('types')->info('Image types published (empty: all).')->scalarPrototype()->end()->end()
                ->scalarNode('main_type')->info('Type of the main image, published first.')->defaultValue('main')->end()
                ->integerNode('max_additional')->min(0)->defaultValue(10)->end()
                ->scalarNode('base_url')->info('Base of the image addresses. Null: <site>/media/image/.')->defaultNull()->end()
                ->scalarNode('default_profile')->info('Image profile used without --images.')->defaultNull()->end()
                ->arrayNode('profiles')
                    ->info('Profile name => {path prefix: base URL}: images whose path starts with the prefix are served from that base.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->useAttributeAsKey('prefix')
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function shipping(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('shipping'))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('provider')
                    ->info('Service computing the shipping costs (ShippingQuoteProviderInterface).')
                    ->defaultValue('altmenorg_sylius_google_shopping.shipping.sylius')
                ->end()
                ->booleanNode('per_product')->info('Shipping costs and weight of the first variant for all the items of a product.')->defaultFalse()->end()
                ->arrayNode('excluded_methods')->info('Shipping method codes never published (pick-up points…).')->scalarPrototype()->end()->end()
                ->arrayNode('prepend')
                    ->info('Quotes added before the computed ones, on products that have at least one.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('service')->isRequired()->end()
                            ->floatNode('price')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('remove_services')->info('Computed quotes left out, by service name.')->scalarPrototype()->end()->end()
            ->end();

        return $node;
    }
}
