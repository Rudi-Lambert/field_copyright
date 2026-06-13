# field_copyright — Module Scope

## What this module is

A small, reusable Drupal field type module intended for drupal.org publication.
Provides a `copyright` field type: required credit text with an optional source URL.
Designed for attaching copyright/attribution information to images, media entities,
and any other licensed content.

## Responsibilities

- `copyright` field type definition (credit text + optional URL)
- Field widget and formatter
- Schema definition

## Does NOT own

- Any project-specific content types or fields — those attach this field type
  but live in their own modules
- Any media handling logic

## Dependencies

- `drupal:field`

## Notes

- Standalone field type — no coupling to other custom modules in this project
- Suitable for extraction to a separate repository when ready to publish
