# External-source attribution display mode (design notes)

**Status:** Design notes — concept captured, not yet locked. Targets a
post-2.0 release (3.x), composes with the [entity-linking 3.0 plan](expansion-3.0-entity-linking.md)
but is independent of it.

**Date:** 2026-05-28
**Author:** Claude session — captured from user description.

---

## 0. The concept in one sentence

When a piece of content (typically an image) is shown that **comes from
another site**, the copyright credit isn't a `© 2024 Some Person`
text-with-favicon line — it's *just* a favicon of the source site, the
site's name (or hostname), and a link to the source page. The favicon
is the credit; the text is supporting context.

---

## 1. What the user described

> "If you're showing an external image, from another website, the
> copyright text and link shown on the image is simply a favicon of the
> site it came from, the site name or url, and a link to the site, if
> possible to the page the image came from."

This reframes what a favicon is for in this module:

- **Today's role (2.0 Full formatter):** the favicon is a small visual
  hint next to a regular credit line ("© 2024 Some Person" with a tiny
  Wikimedia icon if the URL points there). The text is primary; the
  favicon is garnish. The `favicon_external_only` 2.0 setting hides
  the favicon for on-site URLs because on-site URLs don't need this
  hint.

- **3.x role (this concept):** the favicon IS the credit. A separate
  display mode where the rendering says "this came from
  commons.wikimedia.org" — favicon + hostname + link — without any
  © / photographer / creator text at all (or with that text reduced
  to a hover tooltip).

The two modes coexist. Editors / site builders pick per display.

---

## 2. Where it might live (open question)

Three reasonable shapes; pick one before building.

### Shape A — a new formatter alongside Full

`CopyrightExternalSourceFormatter` (id `field_copyright_external_source`).
Renders **only when the URL host is external** to the current site;
falls back to nothing (or to the holder text) when the URL points at
the current site. Per-formatter settings: site-name strategy
(hostname / linked-entity-label / explicit text), label position
(before / after favicon), favicon size.

- **Pros:** clean separation, no mode-flag in existing formatters,
  display config picks per view-mode.
- **Cons:** another formatter to maintain; if a site builder wants
  some pairs rendered "normally" and some as external-source, they
  need a different approach.

### Shape B — a mode setting on Full

Add `display_mode` setting to `CopyrightFullFormatter`:
`'attribution'` (default — current 2.0 behaviour) vs.
`'external_source'` (the new mode). When `external_source`, per pair:

- If `parse_url($url)['host']` is the same as the current site host
  → render normally (full text + label).
- If the URL host is external → render as favicon + site-name + link
  only, no holder text.

- **Pros:** one formatter, one config-knob, mode applies uniformly to
  all three roles in the same display.
- **Cons:** Full formatter's behaviour now depends on the mode flag —
  more code paths in one place, more test cases.

### Shape C — per-item flag on the field type

Add a `is_external_source` boolean column to each role. Editor marks
the pair as "this is an external source" at edit time. The formatter
renders external-source pairs differently regardless of which
formatter is in use.

- **Pros:** content-level decision, not display-level. Same data
  flows through all formatters consistently.
- **Cons:** adds three more columns (6 columns for editor flags
  across the three roles? or one shared?). Heavy. Editor burden.

**Strong recommendation:** Shape A (new formatter). Smallest blast
radius, no mode-flag complexity, and editors who want a mix can use
view-modes or different displays per content type.

---

## 3. The visual shape

For a single linked attribution where the source is external:

```
[favicon]  commons.wikimedia.org  ↗
```

Linked. That's it. The host name is the link text; the favicon is the
visual hook. Optional `↗` glyph signals "external link". No © symbol,
no holder name string, no photographer text.

For a three-pair record where ONLY the photographer URL is external,
mode A's formatter renders only that pair (skipping holder and creator
because their URLs are internal or missing).

When in 3.0 with entity-linked records: if the holder entity has a
URL field or a `link_field`, the favicon can come from THAT entity's
URL. The site-name can come from the entity's label. This is where the
external-source formatter and the 3.0 entity-linking design compose
nicely.

---

## 4. Site-name resolution — strategies

The "site name" rendered next to the favicon needs to come from
somewhere. Options:

| Strategy | Cost | Notes |
|---|---|---|
| **Hostname** (`parse_url($url, PHP_URL_HOST)`, optionally strip `www.`) | Zero | Always available. Looks like `commons.wikimedia.org`. Slightly technical. |
| **Hostname → friendly map** | Small | Configurable per-formatter or module-wide: `commons.wikimedia.org` → `Wikimedia Commons`. Curatable, but maintenance. |
| **Linked entity's label** (3.0) | Free for linked items, NULL for unlinked | When the role has an entity ref, use the entity's label. Best UX when 3.0 is in play. |
| **`<title>` of the source page** | One HTTP fetch + cache | Authoritative but slow and requires a fetcher. Probably 3.x or later. |
| **Manual override** | Editor friction | Add a `display_text` column. Same problem as Shape C above. |

**Recommendation:** start with hostname (zero-cost, always works);
add hostname-→-friendly map and entity-label fallback as the next two
iterations. Don't fetch pages — that's a different problem.

---

## 5. Detection — what counts as "external"?

Same logic as the 2.0 `favicon_external_only` setting introduced in
commit `d549a64`:

- Normalise host: lowercase, strip leading `www.`.
- Compare URL host to current request host (`\Drupal::requestStack()
  ->getCurrentRequest()->getHost()`, normalised).
- If they differ → external.

Edge cases to handle later:

- **Multi-domain sites** — `museum.org` and `collection.museum.org`
  might both be "us". Allow a configurable list of "internal hosts"
  per the module config.
- **CDN-served content** — a media file served from `cdn.museum.org`
  but conceptually from `museum.org`. Same as above; covered by the
  internal-hosts list.

For v1: just the current request host comparison. The 2.0 setting
already does this; the external-source formatter reuses the
implementation.

---

## 6. Composition with the rest of the field

This formatter is **read-only** — it doesn't change the field type,
the widget, or the schema. It just renders the existing 2.0 (and 3.0)
data differently when an external URL is present.

- **No new columns.** Works on `*_title` / `*_uri` / (3.0) `*_target_*`.
- **No new widget settings.** The same widget collects the data.
- **No new field-instance settings.** The decision is per *display*,
  not per data record.
- **Template** — likely a new theme hook
  `field_copyright_external_source` with its own twig template, so
  the existing `field--field-copyright.html.twig` doesn't grow another
  branch. Preprocess builds the same `pairs` array but tagged
  `display=external_source` and the new template renders accordingly.

---

## 7. Composition with 3.0 entity-linking

The external-source formatter gets meaningfully better when the holder
(or photographer, or creator) is an entity reference:

- **Favicon** comes from the entity's stored URL (or the entity's own
  `url` field, if it has one).
- **Site name** comes from the entity's label.
- **Link** goes to the entity's canonical URL on the current site (or
  to the external URL — configurable).

So a "Wikimedia Commons" entity (a taxonomy term or a custom Source
entity) becomes the source of truth for "what does the credit look
like" — single source of truth, easy to update everywhere at once.

When 3.0's `entity_label_mode = lock` is on, this composition is
strongest: the credit IS the entity, not a snapshot of it.

---

## 8. Open questions (parked until build time)

- **Site-name strategy** — pick one default; allow per-formatter
  override.
- **Same-site behaviour when no external URL present** — render
  nothing? Fall back to Full formatter output? Hide the whole field?
- **Multiple external pairs** — if both holder and photographer point
  to different external sites, render both? Separator? Or pick one
  by some precedence (holder first)?
- **Mixed pair (one external, one same-site)** — what does the output
  look like? Probably: render only the external one.
- **The little ↗ glyph** — emit it always, optional, never?
- **Microdata** — when the linked entity is a `Person` or
  `Organization`, emit JSON-LD itemref blocks. Already on the 3.0 plan
  as a "deferred to 3.1" item; this formatter is the natural place to
  emit them.

---

## 9. Build-when

After 2.0 stabilises on museo and after 3.0 ships (or alongside its
final PR sequence). It's not a 2.x feature — too much UX divergence.
It's also not a 3.0 blocker — 3.0 can ship with entity-linking and
not change the formatter story. So: 3.1 or as 3.0's PR 6 (after
the locked 3.0 sequence in [`expansion-3.0-entity-linking.md`](expansion-3.0-entity-linking.md)
§13).

---

## 10. Quick example

A page on rudilambert.com displays a Wikimedia image. The image
media's `field_copyright` holds:

```
holder.title:   "Wikimedia Commons"
holder.uri:     "https://commons.wikimedia.org/wiki/File:foo.jpg"
photographer.title: "Photo by Jane Doe"
photographer.uri:   "https://commons.wikimedia.org/wiki/User:JaneDoe"
```

The display is configured with the new `CopyrightExternalSourceFormatter`
(Shape A). What renders:

```
[🌐] commons.wikimedia.org ↗
```

(where `[🌐]` is the actual favicon img, host shortened, linked to the
holder URI). The photographer URL is ignored because it points to the
same external site — the source-of-source attribution. The user clicks
through to Wikimedia, where the full credit information lives.

A page on museo-avellonia.se shows a specimen photographed by the
museum's own photographer:

```
holder.title:        "Museo Avellonia"
holder.uri:          "https://museo-avellonia.se/about"
photographer.title:  "Photo by Göran Sjöberg"
photographer.uri:    "https://museo-avellonia.se/people/goran-sjoberg"
```

The external-source formatter renders **nothing** — every URL is
internal. The site builder paired this display with a fallback (the
regular Full formatter, or just the holder text) to handle the
internal case.

That fallback wiring is the trickiest open question — see §8.
