<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

use Doctrine\DBAL\Connection;

/**
 * Builds the context of a run from the settings and the channel: locale and currency default to those of the
 * channel, the shop address to `https://<channel hostname>`.
 */
final class FeedContextFactory
{
    public function __construct(
        private readonly Connection $db,
        private readonly ?string $channelCode = null,
        private readonly ?string $localeCode = null,
        private readonly ?string $siteUrl = null,
        private readonly ?string $countryCode = null,
        private readonly ?string $imageProfile = null,
    ) {
    }

    /**
     * @param array{channel?: ?string, locale?: ?string, country?: ?string, images?: ?string} $overrides command options
     *
     * @throws \InvalidArgumentException unknown or ambiguous channel
     */
    public function create(array $overrides = []): FeedContext
    {
        $code = $overrides['channel'] ?? $this->channelCode;
        $channels = $this->db->fetchAllAssociative(
            'SELECT c.code, c.hostname, c.name, c.description, cur.code AS currency, l.code AS locale
             FROM sylius_channel c
             LEFT JOIN sylius_currency cur ON cur.id = c.base_currency_id
             LEFT JOIN sylius_locale l ON l.id = c.default_locale_id'
            . ($code !== null ? ' WHERE c.code = :code' : ' WHERE c.enabled = 1'),
            $code !== null ? ['code' => $code] : [],
        );
        if (count($channels) !== 1) {
            throw new \InvalidArgumentException($code !== null
                ? sprintf('Unknown channel "%s".', $code)
                : 'Several enabled channels (or none): set channel.code or use --channel.');
        }
        $c = $channels[0];
        $site = $this->siteUrl ?? ($c['hostname'] ? 'https://' . $c['hostname'] : '');

        return new FeedContext(
            (string) $c['code'],
            $overrides['locale'] ?? $this->localeCode ?? (string) $c['locale'],
            (string) $c['currency'],
            rtrim($site, '/'),
            $overrides['country'] ?? $this->countryCode,
            $overrides['images'] ?? $this->imageProfile,
            (string) ($c['name'] ?? ''),
            (string) ($c['description'] ?? ''),
        );
    }
}
