<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Tests\Unit;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedTaxon;
use Altmenorg\SyliusGoogleShoppingPlugin\Image\ImageUrlResolver;
use PHPUnit\Framework\TestCase;

final class CatalogTest extends TestCase
{
    private function catalog(): Catalog
    {
        return new Catalog([], [
            1 => new FeedTaxon(1, 'root', null, 0, 'Catalogue', 'catalogue'),
            2 => new FeedTaxon(2, 'spices', 1, 1, 'Spices', 'spices', '2073'),
            3 => new FeedTaxon(3, 'classics', 2, 2, 'Classics', 'classics'),
            4 => new FeedTaxon(4, 'teas', 1, 1, 'Teas', 'teas'),
            5 => new FeedTaxon(5, 'green', 4, 2, 'Green teas', 'green', '6740'),
        ]);
    }

    public function testGoogleCategoryOfTheTaxonThenOfItsParents(): void
    {
        $c = $this->catalog();

        self::assertSame('2073', $c->googleProductCategory(2));
        self::assertSame('2073', $c->googleProductCategory(3));
        self::assertSame('6740', $c->googleProductCategory(5));
        self::assertSame('', $c->googleProductCategory(4));
        self::assertSame('', $c->googleProductCategory(null));
        self::assertSame('', $c->googleProductCategory(99));
    }

    public function testGoogleCategoryWithoutInheritance(): void
    {
        self::assertSame('', $this->catalog()->googleProductCategory(3, false));
        self::assertSame('2073', $this->catalog()->googleProductCategory(2, false));
    }

    public function testTaxonPathExcludesTheRoot(): void
    {
        self::assertSame(['Spices', 'Classics'], $this->catalog()->taxonPath(3));
        self::assertSame([], $this->catalog()->taxonPath(1));
    }

    public function testImageProfiles(): void
    {
        $resolver = new ImageUrlResolver(null, [
            'cdn' => ['wz/' => 'https://cdn.test/_i/1/'],
            'www' => ['wz/' => 'https://shop.test/_i/1/'],
        ]);
        $ctx = static fn (?string $profile) => new FeedContext('WEB', 'en_US', 'EUR', 'https://shop.test', null, $profile);

        self::assertSame('https://cdn.test/_i/1/a/b.jpg', $resolver->resolve('wz/a/b.jpg', $ctx('cdn')));
        self::assertSame('https://shop.test/_i/1/a/b.jpg', $resolver->resolve('wz/a/b.jpg', $ctx('www')));
        self::assertSame('https://shop.test/media/image/ab/cd/x.jpg', $resolver->resolve('ab/cd/x.jpg', $ctx('cdn')));
        self::assertSame('https://shop.test/media/image/wz/a/b.jpg', $resolver->resolve('wz/a/b.jpg', $ctx(null)));
        self::assertSame('https://img.test/x.jpg', (new ImageUrlResolver('https://img.test/'))->resolve('x.jpg', $ctx(null)));
        self::assertSame(['cdn', 'www'], $resolver->profileNames());
    }
}
