<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\DependencyInjection;

use Altmenorg\SyliusGoogleShoppingPlugin\Catalog\CatalogExtensionInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Catalog\CatalogReaderInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedOptions;
use Altmenorg\SyliusGoogleShoppingPlugin\Routing\ProductUrlGeneratorInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Shipping\ShippingQuoteProviderInterface;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Yaml\Yaml;

final class AltmenorgSyliusGoogleShoppingExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $literal = self::literal($config);

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.php');

        $prefix = 'altmenorg_sylius_google_shopping';
        $container->getDefinition("$prefix.options")
            ->setFactory([FeedOptions::class, 'fromConfig'])
            ->setArguments([$literal]);
        $container->getDefinition("$prefix.catalog_reader.dbal")
            ->setArgument(1, $literal['images']['types'])
            ->setArgument(2, $literal['images']['main_type']);
        $container->getDefinition("$prefix.image_url_resolver")
            ->setArguments([$literal['images']['base_url'], $literal['images']['profiles']]);
        $container->getDefinition("$prefix.shipping.sylius")
            ->setArgument(11, $literal['shipping']['excluded_methods']);
        $container->getDefinition("$prefix.context_factory")->setArguments([
            $container->getDefinition("$prefix.context_factory")->getArgument(0),
            $literal['channel']['code'],
            $literal['channel']['locale'],
            $literal['channel']['site_url'],
            $literal['country'],
            $literal['images']['default_profile'],
        ]);
        // the output may use parameters (%kernel.project_dir%): not escaped
        $container->getDefinition("$prefix.command.generate")->setArgument(3, $config['output']);

        $container->setAlias(CatalogReaderInterface::class, $config['catalog_reader']);
        $container->setAlias(ProductUrlGeneratorInterface::class, $config['product_url_generator']);
        $container->setAlias(ShippingQuoteProviderInterface::class, $config['shipping']['provider']);
        $container->registerForAutoconfiguration(CatalogExtensionInterface::class)
            ->addTag('altmenorg_sylius_google_shopping.catalog_extension');
    }

    /**
     * Configuration values as literals: "%" doubled, so that a pattern or a URL containing it is not read as a
     * container parameter.
     */
    private static function literal(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[is_string($k) ? str_replace('%', '%%', $k) : $k] = self::literal($v);
            }

            return $out;
        }

        return is_string($value) ? str_replace('%', '%%', $value) : $value;
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);

        if ($container->hasExtension('sylius_twig_hooks')) {
            $hooks = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/twig_hooks.yaml');
            $container->prependExtensionConfig('sylius_twig_hooks', $hooks['sylius_twig_hooks']);
        }
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Altmenorg\SyliusGoogleShoppingPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@AltmenorgSyliusGoogleShoppingPlugin/migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return ['Sylius\Bundle\CoreBundle\Migrations'];
    }
}
