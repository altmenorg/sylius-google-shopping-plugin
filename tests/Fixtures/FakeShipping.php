<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Tests\Fixtures;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedVariant;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\ShippingQuote;
use Altmenorg\SyliusGoogleShoppingPlugin\Routing\ProductUrlGeneratorInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Shipping\ShippingQuoteProviderInterface;

/** Shipping costs given per product code; records the variants it was asked about. */
final class FakeShipping implements ShippingQuoteProviderInterface
{
    /** @var list<string> */
    public array $asked = [];

    /** @param array<string, list<ShippingQuote>> $quotes */
    public function __construct(private readonly array $quotes = [])
    {
    }

    public function quotes(FeedProduct $product, FeedVariant $variant, FeedContext $context): array
    {
        $this->asked[] = $variant->code;

        return $this->quotes[$product->code] ?? [];
    }
}
