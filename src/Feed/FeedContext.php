<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

/** What one run of the feed is about: channel, locale, currency, shop address, shipping country, image profile. */
final class FeedContext
{
    public function __construct(
        public readonly string $channelCode,
        public readonly string $localeCode,
        public readonly string $currencyCode,
        /** shop address without trailing slash: "https://www.example.com" */
        public readonly string $siteUrl,
        /** ISO country code of the published shipping costs; null: no shipping costs in the feed */
        public readonly ?string $countryCode = null,
        public readonly ?string $imageProfile = null,
        /** channel name and description, for the channel header when the settings leave them empty */
        public readonly string $channelName = '',
        public readonly string $channelDescription = '',
    ) {
    }
}
