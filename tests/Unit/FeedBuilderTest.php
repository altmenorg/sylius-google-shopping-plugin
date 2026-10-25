<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Tests\Unit;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedBuilder;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedOptions;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedProduct;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedTaxon;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedVariant;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\ShippingQuote;
use Altmenorg\SyliusGoogleShoppingPlugin\Image\ImageUrlResolver;
use Altmenorg\SyliusGoogleShoppingPlugin\Tests\Fixtures\FakeShipping;
use Altmenorg\SyliusGoogleShoppingPlugin\Tests\Fixtures\FakeUrls;
use PHPUnit\Framework\TestCase;

final class FeedBuilderTest extends TestCase
{
    private function context(?string $country = 'FR', ?string $images = null): FeedContext
    {
        return new FeedContext('WEB', 'fr_FR', 'EUR', 'https://shop.test', $country, $images, 'Shop', 'Spices');
    }

    /** Settings close to those of a shop that reproduces a former feed byte for byte. */
    private function legacyOptions(array $overrides = []): FeedOptions
    {
        return new FeedOptions(...array_merge([
            'title' => 'Shop',
            'description' => 'Spices & teas',
            'link' => 'https://shop.test',
            'descriptionSource' => 'short_description',
            'stripHtml' => false,
            'textReplacements' => [['pattern' => '/ > /', 'replacement' => ' '], ['pattern' => '/Multiple colours/', 'replacement' => '']],
            'linkQuery' => '?utm_source=google',
            'variantParameter' => 'sku',
            'variantAttributes' => [
                ['match' => 'Couleur', 'tags' => ['color']],
                ['match' => 'Poids', 'tags' => ['unit_pricing_measure', 'size']],
            ],
            'simpleProductEmptyTags' => ['genre', 'color', 'size'],
            'useCodeAsMpn' => true,
            'omitEmptyTags' => false,
            'availability' => 'product',
            'shippingPerProduct' => true,
            'weightUnit' => 'g',
            'maxAdditionalImages' => 1,
        ], $overrides));
    }

    private function catalog(FeedProduct ...$products): Catalog
    {
        return new Catalog($products, [
            1 => new FeedTaxon(1, 'root', null, 0, 'Catalogue', 'catalogue'),
            2 => new FeedTaxon(2, 'spices', 1, 1, 'Spices', 'spices', '2073'),
            3 => new FeedTaxon(3, 'classics', 2, 2, 'Classics', 'classic-spices'),
        ]);
    }

    private function cinnamon(): FeedProduct
    {
        $p = new FeedProduct(10, 'CIN', 'Cinnamon', 'cinnamon', 'Long <b>text</b>', 'Ceylon cinnamon & more', 3);
        $p->brand = 'House';
        $p->gtin = '3700000000001';
        $p->images = ['a/main.jpg', 'a/2.jpg', 'a/3.jpg'];
        $p->variants = [new FeedVariant(100, 'CIN', 'Cinnamon', 590, null, 60.0, true, 5, 1)];

        return $p;
    }

    public function testSimpleProductItemIsWrittenExactly(): void
    {
        $shipping = new FakeShipping(['CIN' => [new ShippingQuote('Letter', 290), new ShippingQuote('Tracked', 490)]]);
        $builder = new FeedBuilder($this->legacyOptions([
            'prependedShipping' => [new ShippingQuote('Relay', 350)],
            'removedShippingServices' => ['Tracked'],
        ]), $shipping, new FakeUrls(), new ImageUrlResolver(null, ['cdn' => ['a/' => 'https://cdn.test/']]));

        $feed = $builder->build($this->catalog($this->cinnamon()), $this->context('FR', 'cdn'));

        $expected = '<?xml version="1.0" encoding="UTF-8"?>'
            . "\n" . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0" xmlns:c="http://base.google.com/cns/1.0">'
            . "\n\t<channel>"
            . "\n\t\t<title>Shop</title>"
            . "\n\t\t<description>Spices &amp; teas</description>"
            . "\n\t\t<link>https://shop.test</link>"
            . "\n\t\t<item>"
            . "\n\t\t\t<g:condition>new</g:condition>"
            . "\n\t\t\t<g:id>CIN</g:id>"
            . "\n\t\t\t<g:mpn>CIN</g:mpn>"
            . "\n\t\t\t<g:availability>in stock</g:availability>"
            . "\n\t\t\t<g:brand>House</g:brand>"
            . "\n\t\t\t<g:google_product_category>2073</g:google_product_category>"
            . "\n\t\t\t<g:product_type>Spices Classics</g:product_type>"
            . "\n\t\t\t<g:price>5.90 EUR</g:price>"
            . "\n\t\t\t<g:gtin>3700000000001</g:gtin>"
            . "\n\t\t\t<g:isbn></g:isbn>"
            . "\n\t\t\t<title>Cinnamon</title>"
            . "\n\t\t\t<description>Ceylon cinnamon &amp; more</description>"
            . "\n\t\t\t<link>https://shop.test/classic-spices/cinnamon.html?utm_source=google</link>"
            . "\n\t\t\t<g:image_link>https://cdn.test/main.jpg</g:image_link>"
            . "\n\t\t\t<g:additional_image_link>https://cdn.test/2.jpg</g:additional_image_link>"
            . "\n\t\t\t<g:genre/>"
            . "\n\t\t\t<g:color/>"
            . "\n\t\t\t<g:size/>"
            . "\n\t\t\t<g:shipping>\n\t\t\t  <g:country>FR</g:country>\n\t\t\t  <g:price>3.50 EUR</g:price>\n\t\t\t  <g:service>Relay</g:service>\n\t\t\t</g:shipping>"
            . "\n\t\t\t<g:shipping>\n\t\t\t  <g:country>FR</g:country>\n\t\t\t  <g:price>2.90 EUR</g:price>\n\t\t\t  <g:service>Letter</g:service>\n\t\t\t</g:shipping>"
            . "\n\t\t\t<g:shipping_weight>0.06 kg</g:shipping_weight>"
            . "\n\t\t</item>"
            . "\n\t</channel>"
            . "\n</rss>";

        self::assertSame($expected, $feed->xml);
        self::assertSame(1, $feed->items);
        self::assertSame(1, $feed->products);
        self::assertNotFalse(simplexml_load_string($feed->xml));
    }

    public function testProductWithVariantsGivesOneItemPerVariant(): void
    {
        $p = new FeedProduct(20, 'PEP', 'Pepper', 'pepper', null, 'Black pepper', 3);
        $p->variantLabel = 'Poids';
        $p->variants = [
            new FeedVariant(201, 'PEP-1', '100 g', 450, null, 108.0, true, 0, 0),
            new FeedVariant(202, 'PEP-2', '250 g', 900, 1000, 235.0, true, 3, 0),
        ];
        $p->variants[0]->gtin = '111';
        $shipping = new FakeShipping(['PEP' => [new ShippingQuote('Letter', 290)]]);
        $builder = new FeedBuilder($this->legacyOptions(), $shipping, new FakeUrls(), new ImageUrlResolver());

        $feed = $builder->build($this->catalog($p), $this->context());

        self::assertSame(2, $feed->items);
        self::assertSame(1, $feed->products);
        $items = simplexml_load_string($feed->xml)->channel->item;
        $g0 = $items[0]->children('g', true);
        $g1 = $items[1]->children('g', true);
        self::assertSame('PEP-1', (string) $g0->id);
        self::assertSame('PEP', (string) $g0->item_group_id);
        self::assertSame('100 g', (string) $g0->unit_pricing_measure);
        self::assertSame('100 g', (string) $g0->size);
        self::assertSame('Pepper - 250 g', (string) $items[1]->title);
        self::assertSame('https://shop.test/classic-spices/pepper.html?utm_source=google&sku=PEP-2', (string) $items[1]->link);
        // regular price: the original price of a catalogue promotion
        self::assertSame('10.00 EUR', (string) $g1->price);
        // own GTIN, else the product's (none here)
        self::assertSame('111', (string) $g0->gtin);
        self::assertSame('', (string) $g1->gtin);
        // availability of the product (3 in stock in all) for every variant
        self::assertSame('in stock', (string) $g0->availability);
        // shipping and weight of the first variant, computed once
        self::assertSame(['PEP-1'], $shipping->asked);
        self::assertSame('0.108 kg', (string) $g1->shipping_weight);
        self::assertStringNotContainsString('<g:genre/>', $feed->xml);
    }

    public function testVariantAvailabilityAndShippingPerVariantByDefault(): void
    {
        $p = new FeedProduct(20, 'TSH', 'T-shirt', 'tshirt', 'A shirt', null, null);
        $p->variantLabel = 'Colour';
        $p->variants = [
            new FeedVariant(201, 'TSH-R', 'Red', 1500, null, 0.2, true, 0, 0),
            new FeedVariant(202, 'TSH-B', 'Blue', 1500, null, 0.2, true, 4, 1),
        ];
        $shipping = new FakeShipping(['TSH' => [new ShippingQuote('Standard', 500)]]);
        $builder = new FeedBuilder(new FeedOptions(), $shipping, new FakeUrls(), new ImageUrlResolver());

        $feed = $builder->build($this->catalog($p), $this->context());
        $items = simplexml_load_string($feed->xml)->channel->item;

        self::assertSame('out of stock', (string) $items[0]->children('g', true)->availability);
        self::assertSame('in stock', (string) $items[1]->children('g', true)->availability);
        self::assertSame('Red', (string) $items[0]->children('g', true)->color);
        self::assertSame(['TSH-R', 'TSH-B'], $shipping->asked);
        self::assertSame('0.2 kg', (string) $items[0]->children('g', true)->shipping_weight);
        // default settings: empty tags left out, no mpn, plain-text description
        self::assertStringNotContainsString('<g:brand>', $feed->xml);
        self::assertStringNotContainsString('<g:gtin>', $feed->xml);
        self::assertStringNotContainsString('<g:mpn>', $feed->xml);
        self::assertStringNotContainsString('<g:google_product_category>', $feed->xml);
        self::assertSame('https://shop.test/tshirt.html', (string) $items[0]->link);
    }

    public function testExtensionAvailabilityWinsOverTheVariants(): void
    {
        $p = $this->cinnamon();
        $p->variants[0]->onHand = 0;
        $p->variants[0]->onHold = 0;
        $p->inStock = true; // a stock shared by all the variants, known to an extension
        $builder = new FeedBuilder($this->legacyOptions(), new FakeShipping(), new FakeUrls(), new ImageUrlResolver());

        self::assertStringContainsString('<g:availability>in stock</g:availability>', $builder->build($this->catalog($p), $this->context())->xml);

        $p->inStock = null;
        self::assertStringContainsString('<g:availability>out of stock</g:availability>', $builder->build($this->catalog($p), $this->context())->xml);
    }

    public function testExcludedProductsAndProductsWithoutVariantsAreLeftOut(): void
    {
        $excluded = $this->cinnamon();
        $empty = new FeedProduct(30, 'NONE', 'Nothing to sell', 'nothing');
        $kept = new FeedProduct(40, 'KEEP', 'Kept', 'kept');
        $kept->variants = [new FeedVariant(400, 'KEEP', 'Kept', 100)];
        $builder = new FeedBuilder(new FeedOptions(excludedProducts: ['CIN']), new FakeShipping(), new FakeUrls(), new ImageUrlResolver());

        $feed = $builder->build($this->catalog($excluded, $empty, $kept), $this->context());

        self::assertSame(1, $feed->items);
        self::assertSame(1, $feed->products);
        self::assertStringNotContainsString('CIN', $feed->xml);
        self::assertStringNotContainsString('NONE', $feed->xml);
        self::assertStringContainsString('<g:id>KEEP</g:id>', $feed->xml);
    }

    public function testShippingAddedOnlyToProductsThatHaveQuotesAndRemovedServicesLeftOut(): void
    {
        $other = new FeedProduct(40, 'BIG', 'Big', 'big');
        $other->variants = [new FeedVariant(400, 'BIG', 'Big', 100)];
        $shipping = new FakeShipping(['CIN' => [new ShippingQuote('Letter', 290), new ShippingQuote('Tracked', 490), new ShippingQuote('Express', 990)]]);
        $builder = new FeedBuilder(new FeedOptions(
            prependedShipping: [new ShippingQuote('Relay', 350)],
            removedShippingServices: ['Tracked'],
        ), $shipping, new FakeUrls(), new ImageUrlResolver());

        $items = simplexml_load_string($builder->build($this->catalog($this->cinnamon(), $other), $this->context())->xml)->channel->item;

        $services = [];
        foreach ($items[0]->children('g', true)->shipping as $s) {
            $services[] = (string) $s->children('g', true)->service . ' ' . $s->children('g', true)->price;
        }
        self::assertSame(['Relay 3.50 EUR', 'Letter 2.90 EUR', 'Express 9.90 EUR'], $services);
        // no computed quote (not deliverable): nothing prepended either
        self::assertCount(0, $items[1]->children('g', true)->shipping);
    }

    public function testNoShippingWithoutCountry(): void
    {
        $shipping = new FakeShipping(['CIN' => [new ShippingQuote('Letter', 290)]]);
        $builder = new FeedBuilder(new FeedOptions(), $shipping, new FakeUrls(), new ImageUrlResolver());

        $xml = $builder->build($this->catalog($this->cinnamon()), $this->context(null))->xml;

        self::assertStringNotContainsString('<g:shipping>', $xml);
        self::assertSame([], $shipping->asked);
    }

    public function testDescriptionIsPlainTextByDefault(): void
    {
        $builder = new FeedBuilder(new FeedOptions(), new FakeShipping(), new FakeUrls(), new ImageUrlResolver());

        $xml = $builder->build($this->catalog($this->cinnamon()), $this->context())->xml;

        self::assertStringContainsString('<description>Long text</description>', $xml);
        self::assertStringContainsString('<g:image_link>https://shop.test/media/image/a/main.jpg</g:image_link>', $xml);
        self::assertSame(2, substr_count($xml, '<g:additional_image_link>'));
    }
}
