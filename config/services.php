<?php

declare(strict_types=1);

use Altmenorg\SyliusGoogleShoppingPlugin\Catalog\DbalCatalogReader;
use Altmenorg\SyliusGoogleShoppingPlugin\Command\GenerateFeedCommand;
use Altmenorg\SyliusGoogleShoppingPlugin\Command\ImportCategoriesCommand;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedBuilder;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContextFactory;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedGenerator;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedOptions;
use Altmenorg\SyliusGoogleShoppingPlugin\Form\Extension\TaxonTypeExtension;
use Altmenorg\SyliusGoogleShoppingPlugin\Image\ImageUrlResolver;
use Altmenorg\SyliusGoogleShoppingPlugin\Image\ImageUrlResolverInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Routing\SyliusProductUrlGenerator;
use Altmenorg\SyliusGoogleShoppingPlugin\Shipping\SyliusShippingQuoteProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/*
 * Arguments that come from the bundle configuration are set by AltmenorgSyliusGoogleShoppingExtension::load()
 * (not through parameters: patterns and URLs may contain "%").
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->private();
    $prefix = 'altmenorg_sylius_google_shopping';

    $services->set("$prefix.options", FeedOptions::class);

    $services->set("$prefix.catalog_reader.dbal", DbalCatalogReader::class)
        ->args([service('doctrine.dbal.default_connection')]);

    $services->set("$prefix.product_url_generator.sylius", SyliusProductUrlGenerator::class)
        ->args([service('router')]);

    $services->set("$prefix.image_url_resolver", ImageUrlResolver::class);
    $services->alias(ImageUrlResolverInterface::class, "$prefix.image_url_resolver");

    $services->set("$prefix.shipping.sylius", SyliusShippingQuoteProvider::class)
        ->args([
            service('sylius.repository.channel'),
            service('sylius.repository.product_variant'),
            service('sylius.repository.shipping_method'),
            service('sylius.matcher.zone'),
            service('sylius.checker.shipping_method_eligibility'),
            service('sylius.registry.shipping_calculator'),
            service('sylius.factory.order'),
            service('sylius.factory.order_item'),
            service('sylius.factory.order_item_unit'),
            service('sylius.factory.shipment'),
            service('sylius.factory.address'),
        ]);

    $services->set("$prefix.builder", FeedBuilder::class)
        ->args([
            service("$prefix.options"),
            service(\Altmenorg\SyliusGoogleShoppingPlugin\Shipping\ShippingQuoteProviderInterface::class),
            service(\Altmenorg\SyliusGoogleShoppingPlugin\Routing\ProductUrlGeneratorInterface::class),
            service(ImageUrlResolverInterface::class),
        ]);

    $services->set("$prefix.generator", FeedGenerator::class)
        ->args([
            service(\Altmenorg\SyliusGoogleShoppingPlugin\Catalog\CatalogReaderInterface::class),
            tagged_iterator("$prefix.catalog_extension"),
            service("$prefix.builder"),
        ]);
    $services->alias(FeedGenerator::class, "$prefix.generator");

    $services->set("$prefix.context_factory", FeedContextFactory::class)
        ->args([service('doctrine.dbal.default_connection')]);
    $services->alias(FeedContextFactory::class, "$prefix.context_factory");

    $services->set("$prefix.command.generate", GenerateFeedCommand::class)
        ->args([
            service("$prefix.generator"),
            service("$prefix.context_factory"),
            service(ImageUrlResolverInterface::class),
            '', // output, from the configuration
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set("$prefix.command.import_categories", ImportCategoriesCommand::class)
        ->args([service('doctrine.dbal.default_connection')])
        ->tag('console.command');

    $services->set("$prefix.form.extension.taxon", TaxonTypeExtension::class)
        ->tag('form.type_extension');
};
