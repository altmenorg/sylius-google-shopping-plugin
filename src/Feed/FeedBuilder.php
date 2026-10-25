<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin\Feed;

use Altmenorg\SyliusGoogleShoppingPlugin\Image\ImageUrlResolverInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Routing\ProductUrlGeneratorInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Shipping\ShippingQuoteProviderInterface;

/**
 * Writes the RSS 2.0 feed of a catalogue (Google Merchant Center product data specification, `g:` namespace).
 *
 * One item per enabled variant of a product with variants (grouped by `g:item_group_id`), one item for the first
 * variant of a simple product. The layout (tabs, new lines, tag order) is stable: two runs on the same data give
 * the same bytes, so a feed can be compared with the previous one.
 *
 * Escaping: text written by the shop (brand, category, product type, title, description, link) goes through
 * `htmlspecialchars()`; codes, prices, URLs of images and shipping values through an XML-safe escape that leaves
 * them unchanged unless they contain `&` or `<`.
 */
final class FeedBuilder
{
    public function __construct(
        private readonly FeedOptions $options,
        private readonly ShippingQuoteProviderInterface $shipping,
        private readonly ProductUrlGeneratorInterface $urls,
        private readonly ImageUrlResolverInterface $images,
    ) {
    }

    public function build(Catalog $catalog, FeedContext $context): FeedResult
    {
        $o = $this->options;

        $data = '<?xml version="1.0" encoding="UTF-8"?>';
        $data .= "\n" . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0" xmlns:c="http://base.google.com/cns/1.0">';
        $data .= "\n" . '	<channel>';
        $data .= "\n" . '		<title>' . self::raw($o->title ?? $context->channelName) . '</title>';
        $data .= "\n" . '		<description>' . self::raw($o->description ?? $context->channelDescription) . '</description>';
        $data .= "\n" . '		<link>' . self::raw($o->link ?? $context->siteUrl) . '</link>';

        $items = $products = 0;
        foreach ($catalog->products as $p) {
            if ($p->variants === [] || in_array($p->code, $o->excludedProducts, true)) {
                continue;
            }
            ++$products;
            // with shipping_per_product, weight and shipping costs of the first variant, for all items
            $first = $p->variants[0];
            $productQuotes = $o->shippingPerProduct ? $this->quotes($p, $first, $context) : null;

            $google = $catalog->googleProductCategory($p->mainTaxonId, $o->inheritGoogleCategory);
            $productType = $this->clean(implode($o->productTypeSeparator, $catalog->taxonPath($p->mainTaxonId)));
            $title = $this->clean($p->name);
            $description = $this->description($p);
            $link = $this->urls->generate($p, $catalog, $context) . $o->linkQuery;
            $imgs = array_map(fn (string $path) => $this->images->resolve($path, $context), $p->images);
            $imageLink = $imgs[0] ?? '';
            $additional = array_slice($imgs, 1, max(0, $o->maxAdditionalImages));
            $productAvailability = $this->productInStock($p);

            $variants = $p->variantLabel !== null ? $p->variants : [$first];
            foreach ($variants as $v) {
                $isVariant = $p->variantLabel !== null;
                $id = $isVariant ? $v->code : $p->code;
                $inStock = $o->availability === 'product' ? $productAvailability : ($v->inStock ?? $p->inStock ?? self::variantInStock($v));
                $quotes = $productQuotes ?? $this->quotes($p, $v, $context);
                $weight = $o->shippingPerProduct ? $first->weight : $v->weight;

                $data .= "\n" . '		<item>';
                $data .= "\n" . '			<g:condition>new</g:condition>';
                $data .= "\n" . '			<g:id>' . self::raw($id) . '</g:id>';
                if ($isVariant) {
                    $data .= "\n" . '			<g:item_group_id>' . self::raw($p->code) . '</g:item_group_id>';
                    foreach ($this->variantTags((string) $p->variantLabel) as $tag) {
                        $data .= $this->tag('g:' . $tag, self::raw($v->value));
                    }
                }
                if ($o->useCodeAsMpn) {
                    $data .= "\n" . '			<g:mpn>' . self::raw($id) . '</g:mpn>';
                }
                $data .= "\n" . '			<g:availability>' . ($inStock ? 'in stock' : 'out of stock') . '</g:availability>';
                $data .= $this->tag('g:brand', htmlspecialchars((string) $p->brand));
                $data .= $this->tag('g:google_product_category', htmlspecialchars($google));
                $data .= $this->tag('g:product_type', htmlspecialchars($productType));
                $data .= "\n" . '			<g:price>' . self::money($v->basePrice(), $context->currencyCode) . '</g:price>';
                $data .= $this->tag('g:gtin', self::raw((string) ($v->gtin ?? $p->gtin)));
                $data .= $this->tag('g:isbn', self::raw((string) $p->isbn));
                $data .= "\n" . '			<title>' . htmlspecialchars($isVariant ? $title . $o->variantTitleSeparator . $v->value : $title) . '</title>';
                $data .= $this->tag('description', htmlspecialchars($description));
                $data .= "\n" . '			<link>' . htmlspecialchars($isVariant ? $this->variantLink($link, $v) : $link) . '</link>';
                $data .= $this->tag('g:image_link', self::raw($imageLink));
                foreach ($additional as $a) {
                    $data .= "\n" . '			<g:additional_image_link>' . self::raw($a) . '</g:additional_image_link>';
                }
                if (!$isVariant) {
                    foreach ($o->simpleProductEmptyTags as $tag) {
                        $data .= "\n" . '			<g:' . $tag . '/>';
                    }
                }
                $data .= $this->shippingBlocks($quotes, $context);
                if ($weight !== null || !$o->omitEmptyTags) {
                    $data .= "\n" . '			<g:shipping_weight>' . $this->weight((float) $weight) . '</g:shipping_weight>';
                }
                $data .= "\n" . '		</item>';
                ++$items;
            }
        }

        $data .= "\n" . '	</channel>';
        $data .= "\n" . '</rss>';

        return new FeedResult($data, $items, $products);
    }

    /** @return list<ShippingQuote> */
    private function quotes(FeedProduct $p, FeedVariant $v, FeedContext $context): array
    {
        return $context->countryCode === null ? [] : $this->shipping->quotes($p, $v, $context);
    }

    /** One `<g:shipping>` per quote: the prepended ones first, removed services left out; nothing if the product has no quote. */
    private function shippingBlocks(array $quotes, FeedContext $context): string
    {
        if ($quotes === [] || $context->countryCode === null) {
            return '';
        }
        $s = '';
        foreach ([...$this->options->prependedShipping, ...$quotes] as $i => $q) {
            if ($i >= count($this->options->prependedShipping) && in_array($q->service, $this->options->removedShippingServices, true)) {
                continue;
            }
            $s .= "\n" . '			<g:shipping>'
                . "\n" . '			  <g:country>' . self::raw($context->countryCode) . '</g:country>'
                . "\n" . '			  <g:price>' . self::raw(self::money($q->price, $context->currencyCode)) . '</g:price>'
                . "\n" . '			  <g:service>' . self::raw($q->service) . '</g:service>'
                . "\n" . '			</g:shipping>';
        }

        return $s;
    }

    /** @return list<string> tags (without `g:`) carrying the value of a variant, from the label of its axis */
    private function variantTags(string $label): array
    {
        foreach ($this->options->variantAttributes as $rule) {
            if ($rule['match'] !== '' && mb_stripos($label, $rule['match']) !== false) {
                return $rule['tags'];
            }
        }

        return $this->options->defaultVariantTags;
    }

    private function variantLink(string $link, FeedVariant $v): string
    {
        $param = $this->options->variantParameter;
        if ($param === null || $param === '') {
            return $link;
        }

        return $link . (str_contains($link, '?') ? '&' : '?') . $param . '=' . rawurlencode($v->code);
    }

    private function description(FeedProduct $p): string
    {
        $text = (string) ($this->options->descriptionSource === 'short_description' ? $p->shortDescription : $p->description);
        if ($this->options->stripHtml) {
            $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return $text;
    }

    private function clean(string $text): string
    {
        foreach ($this->options->textReplacements as $r) {
            $text = (string) preg_replace($r['pattern'], $r['replacement'], $text);
        }

        return trim($text);
    }

    /** Sum of the tracked variants (on hand − on hold) > 0, or in stock when no variant is tracked. */
    private function productInStock(FeedProduct $p): bool
    {
        if ($p->inStock !== null) {
            return $p->inStock;
        }
        $tracked = false;
        $sum = 0;
        foreach ($p->variants as $v) {
            if ($v->inStock !== null) {
                if ($v->inStock) {
                    return true;
                }
                continue;
            }
            if ($v->tracked) {
                $tracked = true;
                $sum += $v->onHand - $v->onHold;
            }
        }

        return !$tracked || $sum > 0;
    }

    private static function variantInStock(FeedVariant $v): bool
    {
        return !$v->tracked || $v->onHand - $v->onHold > 0;
    }

    /** Escaped value in its tag, or nothing when empty and `omit_empty_tags` is on. */
    private function tag(string $name, string $escaped): string
    {
        if ($escaped === '' && $this->options->omitEmptyTags) {
            return '';
        }

        return "\n" . '			<' . $name . '>' . $escaped . '</' . $name . '>';
    }

    /** "0.06 kg", "5 kg": weight in the unit of the setting, grams written as kilograms, no useless zeros. */
    private function weight(float $weight): string
    {
        $unit = $this->options->weightUnit;
        if ($unit === 'g') {
            $weight /= 1000;
            $unit = 'kg';
        }
        $s = rtrim(rtrim(number_format($weight, 6, '.', ''), '0'), '.');

        return ($s === '' ? '0' : $s) . ' ' . $unit;
    }

    private static function money(int $amount, string $currency): string
    {
        return number_format($amount / 100, 2, '.', '') . ' ' . $currency;
    }

    private static function raw(string $s): string
    {
        return htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_XML1);
    }
}
