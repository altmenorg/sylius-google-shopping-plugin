# Sylius Google Shopping Plugin

A Google Shopping (Merchant Center) product feed for Sylius 2, written by a console command: one RSS 2.0
file with one item per variant, shipping costs computed by the shop's own shipping methods, and a Google
product category on each taxon.

```bash
php bin/console altmenorg:google-shopping:generate
# /srv/shop/public/google-shopping.xml: 2844 items (1033 products), 6158553 bytes, 0.2 s.
```

Built for Sylius 2 (tested on Sylius 2.3, Symfony 7.4, PHP 8.3, MariaDB). MIT licence.

- **Fast and read-only.** The catalogue is read with a handful of SQL queries (no entity hydration): thousands
  of variants in well under a second. The default shipping costs go through the Sylius calculators, one
  in-memory order per variant (about 4 ms each: 11 s for 2,850 variants on our shop); a shop that can price
  from its own tables replaces that service and stays under a second. Nothing is written to the database, no network call is made.
- **Atomic.** The file is written next to its target and renamed: Merchant Center never fetches a half-written
  feed. Run it from cron as often as you like.
- **Stable output.** Same data, same bytes: a feed can be compared with the previous one.
- **Adaptable.** Everything a shop may want to change is either a setting or a replaceable service (catalogue
  reader, catalogue extensions, product URLs, image URLs, shipping costs).

## What it publishes by default

On a standard Sylius 2 shop, with no setting at all:

| Feed | Source |
|---|---|
| products | enabled, in the channel, translated in the channel's default locale; in order of id |
| items | one per enabled variant priced in the channel (grouped by `g:item_group_id` = product code); a simple product (no option, one variant) is one item |
| `g:id` | variant code (product code for a simple product) |
| `title` | product name, plus ` - <variant>` for a variant; the variant is the values of its options (`Red / XL`), else its name |
| `description` | product description, as plain text (HTML removed) |
| `link` | the Sylius shop route `sylius_shop_product_show`, on `https://<channel hostname>` |
| `g:image_link`, `g:additional_image_link` | product images, main type first, then by position, at most 10 additional; `https://<host>/media/image/<path>` |
| `g:price` | regular price, tax included as stored for the channel: the original (crossed-out) price of a catalogue promotion when there is one |
| `g:availability` | per variant: `in stock` if not tracked, or if on hand − on hold > 0 |
| `g:color` / `g:size` | the variant value, in `g:color` when the product's option is called Color, Colour or Couleur, else in `g:size` |
| `g:product_type` | names of the main taxon and its parents, root excluded: `Spices > Classics` |
| `g:google_product_category` | Google product category of the main taxon, else of its nearest parent that has one (see below) |
| `g:shipping` | **only when `country` is set**: every shipping method enabled for the channel and a zone of that country, eligible and priced by Sylius (rules, category requirements, calculators — including the shop's own) for an order of one unit of the variant; cheapest first |
| `g:shipping_weight` | variant weight, in kg |
| `<channel>` header | channel name, channel description, shop address |

Brand, GTIN, MPN and ISBN are not part of the standard Sylius model: they are left out unless a catalogue
extension provides them (see below). Empty tags are left out (`omit_empty_tags`).

## Installation

1. Require the package. It is not on Packagist: declare the GitHub repository.

   ```json
   {
       "repositories": [
           { "type": "vcs", "url": "https://github.com/altmenorg/sylius-google-shopping-plugin" }
       ],
       "require": {
           "altmenorg/sylius-google-shopping-plugin": "^0.1"
       }
   }
   ```

2. Register the bundle in `config/bundles.php` (Symfony Flex may do it for you):

   ```php
   Altmenorg\SyliusGoogleShoppingPlugin\AltmenorgSyliusGoogleShoppingPlugin::class => ['all' => true],
   ```

3. Add the interface and trait to your taxon entity:

   ```php
   use Altmenorg\SyliusGoogleShoppingPlugin\Model\GoogleProductCategoryAwareInterface;
   use Altmenorg\SyliusGoogleShoppingPlugin\Model\GoogleProductCategoryAwareTrait;
   use Sylius\Component\Core\Model\Taxon as BaseTaxon;

   #[ORM\Entity]
   #[ORM\Table(name: 'sylius_taxon')]
   class Taxon extends BaseTaxon implements GoogleProductCategoryAwareInterface
   {
       use GoogleProductCategoryAwareTrait;
   }
   ```

4. Run the migration of the plugin (it only adds `sylius_taxon.google_product_category` if missing):

   ```bash
   php bin/console doctrine:migrations:migrate
   ```

5. Write the feed, then give its address to Merchant Center:

   ```bash
   php bin/console altmenorg:google-shopping:generate --output=public/google-shopping.xml
   ```

   ```cron
   0 * * * * cd /path/to/shop && flock -n /tmp/google-shopping.lock php bin/console altmenorg:google-shopping:generate
   ```

The feed works without steps 3 and 4: the Google product category is then left empty.

## Google product category

The admin taxon form gets a **Google product category** field (General section): the id of the
[Google product taxonomy](https://support.google.com/merchants/answer/6324436) (`2073`) or its full path. A product
takes the category of its main taxon, else of the nearest parent that has one (`feed.inherit_google_category`).

To fill the categories from another platform, give a JSON object `taxon code => category`; only empty categories
are set, unless `--overwrite`:

```bash
php bin/console altmenorg:google-shopping:import-categories categories.json --dry-run
php bin/console altmenorg:google-shopping:import-categories categories.json
```

## Commands

```
altmenorg:google-shopping:generate [--output=<file>] [--images=<profile>] [--channel=<code>] [--locale=<code>] [--country=<code>]
altmenorg:google-shopping:import-categories <file.json> [--overwrite] [--dry-run]
```

## Configuration

Every setting, with its default (`config/packages/altmenorg_sylius_google_shopping.yaml`):

```yaml
altmenorg_sylius_google_shopping:
    output: public/google-shopping.xml      # relative to the project directory, or absolute
    country: null                           # ISO code of the published shipping costs; null: no g:shipping
    catalog_reader: altmenorg_sylius_google_shopping.catalog_reader.dbal
    product_url_generator: altmenorg_sylius_google_shopping.product_url_generator.sylius

    channel:
        code: null          # null: the only enabled channel
        locale: null        # null: default locale of the channel
        site_url: null      # null: https://<channel hostname>
        title: null         # feed <title>; null: channel name
        description: null   # feed <description>; null: channel description
        link: null          # feed <link>; null: site_url

    feed:
        excluded_products: []               # product codes
        description_source: description     # or short_description
        strip_html: true
        text_replacements: []               # [{ pattern: '/ - /', replacement: ' ' }], on title and product type
        product_type_separator: ' > '
        variant_title_separator: ' - '
        link_query: ''                      # e.g. '?utm_source=google&utm_medium=cpc'
        variant_parameter: null             # e.g. 'variant': link?variant=<variant code>
        variant_attributes:                 # first match in the option name (case-insensitive) → tags of the value
            - { match: color, tags: [color] }
            - { match: colour, tags: [color] }
            - { match: couleur, tags: [color] }
        default_variant_tags: [size]
        simple_product_empty_tags: []       # empty tags on simple products, e.g. [color, size]
        code_as_mpn: false                  # write the code as g:mpn
        omit_empty_tags: true
        availability: variant               # or product: "in stock" for every variant while the product has stock
        weight_unit: kg                     # unit of the Sylius weights: kg, g (published in kg), lb, oz
        inherit_google_category: true

    images:
        types: []                           # image types published; empty: all
        main_type: main
        max_additional: 10
        base_url: null                      # null: <site_url>/media/image/
        default_profile: null
        profiles: {}                        # { cdn: { 'imported/': 'https://cdn.example.com/' } }, chosen by --images

    shipping:
        provider: altmenorg_sylius_google_shopping.shipping.sylius
        per_product: false                  # shipping and weight of the first variant for all items of a product
        excluded_methods: []                # shipping method codes (pick-up points…)
        prepend: []                         # [{ service: 'Pick-up point', price: 3.50 }], added before the computed ones
        remove_services: []                 # computed quotes left out, by service name
```

## Extension points

Data the standard model does not have — a brand, a GTIN, another availability rule, a custom URL scheme, a
shipping grid of your own — goes through services, never through a fork:

| Interface | Default | To… |
|---|---|---|
| `Catalog\CatalogExtensionInterface` | none | complete the catalogue after it is read: `$product->brand`, `->gtin`, `->isbn`, `->inStock` (for the whole product), `->variantLabel`, `$variant->gtin`, `->inStock`, `->value`; filter or reorder `$catalog->products`. Autoconfigured: implement the interface in a service. |
| `Routing\ProductUrlGeneratorInterface` | `SyliusProductUrlGenerator` | build product addresses (`product_url_generator` setting) |
| `Shipping\ShippingQuoteProviderInterface` | `SyliusShippingQuoteProvider` | compute shipping costs your own way (`shipping.provider`) |
| `Image\ImageUrlResolverInterface` | `ImageUrlResolver` | serve images from elsewhere (alias the interface) |
| `Catalog\CatalogReaderInterface` | `DbalCatalogReader` | replace the reading of the catalogue altogether (`catalog_reader`) |

Example: brand and EAN from columns of your own.

```php
use Altmenorg\SyliusGoogleShoppingPlugin\Catalog\CatalogExtensionInterface;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\Catalog;
use Altmenorg\SyliusGoogleShoppingPlugin\Feed\FeedContext;

final class BrandAndEanExtension implements CatalogExtensionInterface
{
    public function __construct(private readonly \Doctrine\DBAL\Connection $db) {}

    public function extend(Catalog $catalog, FeedContext $context): void
    {
        $rows = $this->db->fetchAllAssociativeIndexed('SELECT p.id, b.name AS brand, p.ean FROM sylius_product p LEFT JOIN app_brand b ON b.id = p.brand_id');
        foreach ($catalog->products as $product) {
            $product->brand = $rows[$product->id]['brand'] ?? null;
            $product->gtin = $rows[$product->id]['ean'] ?? null;
        }
    }
}
```

## Tests

```bash
composer install && vendor/bin/phpunit
# or with the autoloader of a Sylius application:
GOOGLE_SHOPPING_AUTOLOAD=/path/to/shop/vendor/autoload.php /path/to/shop/vendor/bin/phpunit
```

## Licence

MIT, see [LICENSE](LICENSE).
