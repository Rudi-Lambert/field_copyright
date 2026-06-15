# TECHNICAL — field_copyright

## Purpose and family role

A Drupal field type module providing copyright/attribution storage for any entity.
Stores up to three attribution pairs per delta: copyright holder (required text +
optional URL), optional photographer/source, optional original creator/artist.
Intended for drupal.org publication — no project-specific code.

Version in this tree: **2.0.0-dev** (the 2.0 build). A frozen 1.0 copy lives in
`museo-avellonia`; do not confuse them.

**Copyright trio:** `field_copyright` (this module — the field type) +
`copyrights` (admin UI + licence catalogue) + `copyrights_ext` (External Entities
provenance bridge, documented separately).

## Field type: CopyrightItem

`src/Plugin/Field/FieldType/CopyrightItem.php`

Annotation: `@FieldType(id = "field_copyright", default_widget = "field_copyright_default", default_formatter = "field_copyright_text")`.

### Storage schema (6 columns)

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `{field}_title` | varchar(255) | no | Holder credit text — required |
| `{field}_uri` | varchar(2048) | yes | Holder URL |
| `{field}_photographer_title` | varchar(255) | yes | Photographer/source text |
| `{field}_photographer_uri` | varchar(2048) | yes | Photographer/source URL |
| `{field}_creator_title` | varchar(255) | yes | Original artist text |
| `{field}_creator_uri` | varchar(2048) | yes | Original artist URL |

`isEmpty()` returns true when `title` is empty (URL alone is not a valid value).

### Per-instance field settings

- `enable_photographer` (bool, default false) — activates the photographer pair in the widget.
- `enable_creator` (bool, default false) — activates the creator pair in the widget.

With both off the field reproduces the 1.0 storage shape exactly.

### Typed properties

Six properties exposed via `propertyDefinitions()`: `title` (required string), `uri`
(optional URI), `photographer_title`, `photographer_uri`, `creator_title`,
`creator_uri` (all optional).

Helper: `getUrlFor(string $property): ?Url` — converts a stored URI property to a
`Url` object, or null if absent/invalid. `getUrl(): ?Url` — convenience for `uri`.

## Widget: CopyrightDefaultWidget

`src/Plugin/Field/FieldWidget/CopyrightDefaultWidget.php`

Annotation: `@FieldWidget(id = "field_copyright_default", field_types = {"field_copyright"})`.

Always renders the holder pair (`title` + `uri`). When `enable_photographer` or
`enable_creator` are set on the field instance, adds a `show_*` checkbox that
reveals the optional pair's inputs via the **core States API** (no custom JS).

Multi-value fields (cardinality > 1) wrap each delta in a `<fieldset>`.

`massageFormValues()` flattens the nested form structure (`photographer` sub-container,
`creator` sub-container) back to the flat field item shape, and clears optional
pairs to null when their `show_*` checkbox is unchecked.

States API selector pattern: `:input[name="{field_name}[{delta}][show_photographer]"]` —
per-delta, per-field to handle multi-value correctly.

## Formatters

### CopyrightTextFormatter (field_copyright_text) — default

`src/Plugin/Field/FieldFormatter/CopyrightTextFormatter.php`

Renders `title` as plain text. 1.0-compatible.

### CopyrightLinkFormatter (field_copyright_link)

`src/Plugin/Field/FieldFormatter/CopyrightLinkFormatter.php`

Renders `title` as a link to `uri` when a URL is present; falls back to plain text
without one. Setting: `target` (`_blank` / `_self` / omit). Always emits
`rel="noopener noreferrer"` when a target is set.

### CopyrightFullFormatter (field_copyright_full)

`src/Plugin/Field/FieldFormatter/CopyrightFullFormatter.php`

Renders all populated pairs (holder + photographer + creator), separated by a
configurable `separator` (default ` · `). Labels per pair: symbol (`©`, `📷`, `🎨`),
text (`Copyright:`, `Photo:`, `Creator:`), or none.

Settings: `link_target`, `separator`, `label_format`, `favicon_provider`,
`favicon_size`, `favicon_external_only`, `favicon_ignored_hosts`.

**Favicon resolution:** URLs pointing at the current site (or admin-configured
"additional local" hosts) can be excluded (`favicon_external_only`). Favicon
construction is pure URL-building — no PHP HTTP calls; the browser fetches the
favicon directly from the provider.

Supported providers:
- `google` — `https://www.google.com/s2/favicons?domain=<host>&sz=<size>` (supports
  size param, clean 404 fallback).
- `duckduckgo` — `https://icons.duckduckgo.com/ip3/<host>.ico` (privacy-friendly,
  fixed size, returns a placeholder when missing).

Cache: `#cache['contexts'][] = 'url.site'` — varies by host so the external-only
check resolves correctly on multi-site/multi-domain setups.

The Full formatter signals itself to the Twig layer via `#field_copyright_full: TRUE`
and `#pairs` / `#separator` render array keys.

## Template

`templates/field--field-copyright.html.twig`

Base hook: `field`. Two branches:

1. **Full formatter** — `item.is_full` is set; iterates `item.pairs[]`; each pair
   has `role`, `label`, `text`, `url`, `link_attributes`, `favicon`, `favicon_size`.
2. **Text/Link formatter** — single holder credit; variables are `copyright_text`,
   `copyright_url`, `link_attributes` (all set by `hook_preprocess_field`).

## Hook: hook_preprocess_field

`field_copyright.module` → `field_copyright_preprocess_field()`:

Fires for `field_type === 'field_copyright'`. Adds BEM classes to `$variables['attributes']`
and flattens the formatter's render output into per-item variables the template
consumes:
- Full formatter (`#field_copyright_full` key present): sets `item['is_full']`,
  `item['pairs']`, `item['separator']`.
- Link formatter (`#url` is a `Url` instance): sets `copyright_text`, `copyright_url`,
  `link_attributes`.
- Text formatter: sets `copyright_text`, `copyright_url = ''`, `link_attributes` (empty).

## Hook: hook_theme

Registers `field__field_copyright` → `field--field-copyright.html.twig`.

## Library

`field_copyright.libraries.yml` — `field_copyright/field_copyright`, attached by
both Link and Full formatters. Includes `css/field_copyright.css` (overlay-style
positioning — see Gotchas).

## Update hook: field_copyright_update_10200

`field_copyright.install`

Adds the four 2.0 nullable columns (`photographer_title`, `photographer_uri`,
`creator_title`, `creator_uri`) to every existing `field_copyright` storage table
(both base and revision variants). Data-preserving — no backfill. Clears cached
field definitions on completion. Run with `drush updb`.

On 1.0 sites the 2.0 columns are absent; `copyrights`' overview form detects this
via `$db->schema()->fieldExists()` and hides photographer/creator columns.

## Config

No module-level config. Field settings (`enable_photographer`, `enable_creator`)
are stored on each field instance's `FieldConfig`. Schema:
`config/schema/field_copyright.schema.yml`.

## Dependencies

Hard: `drupal:field` only.

## Gotchas

- **Overlay CSS is a strong default with a project-shaped assumption.** The CSS
  in `field_copyright.css` positions the field absolutely (intended for image
  overlays). For inline use elsewhere, override or split into a separate optional
  library (pre-d.org-release punch list item).
- **Don't rename the field type machine name (`field_copyright`) without an update
  path.** It is baked into schema, templates, and FormatterBase annotations.
- **The title property is named `title`, not `text`.** The schema column is
  `{field}_title`. Don't confuse with `text` when reading raw DB rows.
- **1.0 → 2.0 migration.** Run `drush updb` (which calls `field_copyright_update_10200`)
  to add the new columns to existing tables. The `copyrights` module has a bulk
  action "Copy holder text → photographer text" for migrating existing 1.0 records
  that conflated the holder text with photographer credit.

## Planned: 3.0 entity-linking

Design in `.Claude/plan/expansion-3.0-entity-linking.md`. Optional entity reference
alongside each pair: at save, the referenced entity's label + canonical URL are
denormalised into the existing varchar columns; entity type + id are stored as
metadata. Render path stays varchar-only — no entity loads on page view.

## Verify

```sh
# Drush: add the field to a content type, create a node, check DB columns.
drush php:eval "var_dump(\Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_copyright'));"
```

Unit tests: `tests/src/Unit/CopyrightItemEmptinessTest.php`,
`CopyrightFullFormatterFaviconTest.php`, `CopyrightLinkFormatterDefaultsTest.php`.

## Cross-references

- `copyrights` — admin UI and licence catalogue; hard-depends on this module.
- `copyrights_ext` — External Entities provenance bridge that attaches
  `field_copyright` to `*_ext` entities; documented separately.
