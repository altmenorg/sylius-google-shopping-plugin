<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Routing;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Default product address: the shop route of Sylius (`sylius_shop_product_show`, `/{_locale}/products/{slug}`),
 * as a path, prefixed with the shop address of the feed (the console has no request to take a host from).
 */
final class SyliusProductUrlGenerator implements ProductUrlGeneratorInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly string $route = 'sylius_shop_product_show',
    ) {
    }

    public function generate(FeedProduct $product, Catalog $catalog, FeedContext $context): string
    {
        $path = $this->router->generate($this->route, ['slug' => $product->slug, '_locale' => $context->localeCode], UrlGeneratorInterface::ABSOLUTE_PATH);
        $base = $this->router->getContext()->getBaseUrl();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return rtrim($context->siteUrl, '/') . $path;
    }
}
