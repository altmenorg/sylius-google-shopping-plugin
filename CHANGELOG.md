# Changelog

## 0.1.0 — 2026-10-26

First release.

### Added

- `altmenorg:google-shopping:generate` (`--output`, `--images`, `--channel`, `--locale`, `--country`): RSS 2.0
  Google Shopping feed, one item per enabled variant, written atomically (temporary file and rename).
- Default catalogue reader on the standard Sylius tables (DBAL, a handful of queries for the whole catalogue):
  products enabled in the channel, priced variants, option values, taxons, images.
- Default shipping costs from the shop's own shipping methods: an order of one unit of the variant built in
  memory (never saved), Sylius eligibility checks and calculators, for the zones of the feed country.
- Default product URLs from the Sylius shop route, default image URLs from `/media/image/`, image profiles
  (path prefix → base URL) chosen by `--images`.
- Settings for everything shop-specific: channel header, excluded products, description source and HTML
  stripping, text replacements, link query and variant parameter, variant tags by option name, empty tags on
  simple products, code as MPN, empty tags omitted or written, availability per variant or per product, weight
  unit, image types and count, shipping per product or per variant, excluded shipping methods, prepended and
  removed shipping services.
- Extension points: `CatalogExtensionInterface` (autoconfigured, to complete, filter or reorder the catalogue),
  `ProductUrlGeneratorInterface`, `ShippingQuoteProviderInterface`, `ImageUrlResolverInterface`,
  `CatalogReaderInterface`.
- Google product category on the taxon: `GoogleProductCategoryAwareInterface` and trait, migration that adds
  only the missing column, field in the admin taxon form, inheritance from the nearest parent (setting).
- `altmenorg:google-shopping:import-categories <file.json>` (`--overwrite`, `--dry-run`): fills the categories of
  taxons from a JSON object, empty ones only by default.
- Translations: English, French.
- Unit tests.
