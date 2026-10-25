<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Image;

use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;

/**
 * Default resolver: `<base_url><path>`, base URL `<site>/media/image/` (the original files Sylius keeps in
 * `public/media/image`). An image profile (`images.profiles` setting, chosen by `--images`) maps path prefixes to
 * other bases: images imported from another platform, a CDN…
 *
 *   profiles: { cdn: { 'imported/': 'https://cdn.example.com/' } }
 *   imported/a.jpg → https://cdn.example.com/a.jpg ; other/b.jpg → https://shop.example.com/media/image/other/b.jpg
 */
final class ImageUrlResolver implements ImageUrlResolverInterface
{
    /**
     * @param array<string, array<string, string>> $profiles profile => path prefix => base URL
     */
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly array $profiles = [],
    ) {
    }

    public function resolve(string $path, FeedContext $context): string
    {
        foreach ($this->profiles[$context->imageProfile ?? ''] ?? [] as $prefix => $base) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return $base . substr($path, strlen($prefix));
            }
        }

        return ($this->baseUrl ?? rtrim($context->siteUrl, '/') . '/media/image/') . $path;
    }

    /** @return list<string> */
    public function profileNames(): array
    {
        return array_keys($this->profiles);
    }
}
