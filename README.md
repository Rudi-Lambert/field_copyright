# Copyright Field

A Drupal field type that stores copyright and attribution information: a required
copyright-holder credit with an optional source URL, plus opt-in photographer and
creator credits (each with their own optional URL). Designed for media, images, and
any licensed content that needs attribution lines.

## Features

- **Required holder credit** — copyright text (e.g. `© 2024 The Museum`) with an
  optional linked URL.
- **Optional photographer / source credit** — per field instance, enabled by the
  admin via a field setting. Revealed per-delta by a checkbox; hidden when not in
  use (no dead UI on every item).
- **Optional original creator / artist credit** — same per-instance opt-in, for the
  "photograph of an artwork" case (three separate credits on one item).
- **Three formatters:**
  - *Text only* — plain holder credit; 1.0-compatible.
  - *Link (if URL available)* — linked holder credit; falls back to plain text.
  - *Full* — all populated pairs with configurable separator, label format
    (symbol/text/none), and optional favicons via Google or DuckDuckGo.
- **Favicon support** — the Full formatter can display a site favicon next to
  source URLs. Favicons are fetched by the browser directly (no PHP HTTP calls).
  External-URL-only mode and an ignore-list for local domains are available.
- **Backwards-compatible 2.0 upgrade** — the two new pairs are added by a database
  update hook; existing 1.0 data is preserved. With both per-instance toggles off,
  the field behaves identically to 1.0.

## Requirements

- Drupal 10.5+ or Drupal 11.2+
- `drupal:field`

## Installation

```sh
drush en field_copyright
drush cr
```

After enabling: add a **Copyright** field to any content type, media bundle, or
other entity. In the field settings you can enable the photographer and creator
credits for that instance.

## The copyright trio

`field_copyright` provides the field type. Its siblings:

- **`copyrights`** — admin overview page listing every copyright record site-wide,
  with bulk operations (copy holder → photographer, clear pairs, bulk-edit, find/
  replace). Also provides the licence catalogue (`copyright_licence` config entity)
  and the Copyright Notice block.
- **`copyrights_ext`** — External Entities provenance bridge that attaches
  `field_copyright` to `*_ext` entities; documented separately.

## Upgrading from 1.0 to 2.0

Run database updates to add the photographer and creator columns to existing tables:

```sh
drush updb
```

Data is preserved. Use the `copyrights` module's bulk-action *Copy holder text →
photographer text* to migrate existing 1.0 records that stored the photographer
credit in the holder text field.

## Status

**2.0.0-dev** — actively developed in the rudilambert playground. The 3.0 design
(optional entity-reference linking for any of the three roles, stored as
denormalised text at save) is locked in `.Claude/plan/expansion-3.0-entity-linking.md`.

## Maintainers

- Rudi Lambert
