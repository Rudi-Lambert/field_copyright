# field_copyright — PROGRESS

## Status: VERIFIED (2026-06-13)

Module enabled on rudilambert/stage. All smoke-test checks pass.

## What exists

- `CopyrightItem` field type (id: `field_copyright`): stores up to three attribution pairs
  per delta — holder (required) + photographer + creator (both opt-in via field settings).
  Columns: `title`, `uri`, `photographer_title`, `photographer_uri`, `creator_title`, `creator_uri`.
- `CopyrightDefaultWidget` — standard form widget.
- Three formatters:
  - `field_copyright_text` — plain text, holder only.
  - `field_copyright_link` — linked holder if URL present, plain text fallback.
  - `field_copyright_full` — all pairs with optional labels (symbol/text/none),
    configurable favicon provider (google / duckduckgo / none), same-site suppression.
    Favicon URLs are built client-side (browser fetches from provider); no outbound PHP HTTP.
- Twig template `field--field-copyright.html.twig`.
- Library `field_copyright/field_copyright` (CSS).
- `field_copyright.install` for schema update hooks.

## Verified (2026-06-13, sprint2 T2)

- `field_copyright` field type discoverable via `plugin.manager.field.field_type`.
- All 3 formatters discoverable via `plugin.manager.field.formatter`.
- Transient `BaseFieldDefinition::create('field_copyright')` + typed_data_manager instantiation
  works; `isEmpty()` returns false on a value; `get('title')->getValue()` correct; `getUrl()`
  returns a `Drupal\Core\Url` object.
- All 23 combined copyright-trio smoke-test checks pass:
  `web/modules/custom/copyrights/.Claude/scripts/verify-copyright.php`

## Planned expansions (from .Claude/plan/)

- expansion-3.0-entity — per-entity (not per-field) copyright strategy.
- expansion-double / expansion-triple — multi-credit (photographer + creator) — **already built** in v2.
- external-source-attribution — external source URL strategy — covered by copyrights_ext bridge.
- favicon-api-summary — favicon provider selection — **already built** in CopyrightFullFormatter.
