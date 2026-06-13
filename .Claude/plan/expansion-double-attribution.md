# field_copyright — Double-attribution expansion (design)

**Status:** ⚠️ **SUPERSEDED** by [expansion-triple-attribution.md](expansion-triple-attribution.md) (2026-05-27).
Kept for the research/sources record. The locked design adds **three**
attribution roles (copyright / photographer / creator), not two, and
locks every other decision in §8 of this document. See the triple-
attribution doc for the implementation plan.

**Date:** 2026-05-27
**Author:** Claude session — research-driven proposal

---

## 0. tl;dr

Expand `field_copyright` from `(credit_text*, credit_url?)` to support
a **second optional attribution pair** (typically the photographer or
image creator). Add a "photographer is the copyright holder" toggle so
the simple case stays one input pair. Render an optional **favicon**
next to source URLs, with the provider (Google or DuckDuckGo) chosen
in module settings.

Three structural questions to settle before coding:

1. **Storage shape**: extend the existing field type (add columns) vs.
   ship a second field type (`field_copyright_attributed`) alongside it?
2. **Toggle semantics**: per-instance (config) vs. per-value (data)?
3. **Favicon scope**: in-module client-side `<img>` tag, or pull out
   into a separate small `favicon` module that other modules can use too?

---

## 1. Why this expansion exists

The current field models a single credit line: one piece of text, one
optional source URL. That covers the simple cases — `© 2024 Jane Doe` —
but breaks down when the **copyright holder is not the photographer**.

Mainstream image-metadata standards already model these as **separate
entities**:

- [schema.org/ImageObject](https://schema.org/ImageObject) defines
  [`creditText`](https://schema.org/creditText) (the credit string,
  maps to IPTC's Credit Line), [`copyrightHolder`](https://schema.org/copyrightHolder)
  (the legal rights owner), and `creator` / `author` (the photographer /
  artist). [Google's image SEO docs](https://developers.google.com/search/docs/appearance/structured-data/image-license-metadata)
  use a worked example where all three are different parties — credit
  goes to "Labrador PhotoLab", the creator is "Brixton Brownstone", and
  the copyright is held by "Clara Kent".
- IPTC's Photo Metadata standard distinguishes Credit Line, Copyright
  Notice, Creator, and Rights Owner — four separate fields.
- Museum-style attribution (e.g. Smithsonian) routinely credits the
  institution as rights holder AND the photographer / department
  separately. See [Smithsonian Institution Archives — Rights and
  Reproduction](https://siarchives.si.edu/what-we-do/rights-and-reproduction).

The Drupal contributed-module landscape has no module that explicitly
models this distinction:

- [Media Attribution](https://www.drupal.org/project/media_attribution)
  — taxonomy of licenses + caption.
- [Attribution & Licensing](https://www.drupal.org/project/attribution)
  — license metadata, no creator-vs-rights-holder split.
- [Supported Image Field](https://www.drupal.org/project/supported_image)
  — caption + attribution subfields, but "attribution" is a single
  free-form text field.
- [IMCE Image Credit](https://www.drupal.org/project/imce_credit) —
  single credit string attached to image files.

So a **field type that models copyright holder and photographer as
distinct (but linkable) parties** would fill a real gap on drupal.org —
not just serve this project.

---

## 2. The user's intended usage

> "I'm planning a comprehensive copyright module to handle copyrights
> for rudilambert.com, where we will be using extreme amounts of external
> content of every possible form … I'd like to make it a potential
> double double field by adding an optional image attribution …
> we'll probably need a toggle, yes no for 'photographer is the
> copyright holder', in which case the double attribution."

So:

- The module is heading toward heavy reuse on a content-aggregation
  site (rudilambert.com) where most images have **two distinct
  attributions** (e.g. "Photo: Jane Doe / Wikimedia Commons" with
  rights held by a museum).
- The simple case (photographer == rights holder) should stay one input
  pair, hidden behind a toggle.
- Favicon next to source URLs gives a visual cue of where content came
  from at a glance (Wikimedia, Flickr, museum domain, etc.).

---

## 3. Proposed storage shape

### 3a. Field-type columns (the gold-standard option)

Extend `CopyrightItem` with two more columns, parallel to the existing
pair:

| Column | Type | Required | Schema.org analogue | Purpose |
|---|---|---|---|---|
| `title` | varchar(255) | yes | `copyrightHolder` (text form) | Rights-holder credit string |
| `uri` | varchar(2048) | no | (host of `copyrightNotice`) | Rights-holder source URL |
| `creator_title` | varchar(255) | no | `creator` (text form) | Photographer / creator credit |
| `creator_uri` | varchar(2048) | no | (host of `creator` page) | Photographer source URL |

`isEmpty()` stays defined by `title` alone — an entry without a primary
credit is still empty.

**Pros**

- One field type, one storage table, simple migration: the new columns
  are added via `hook_update_N` and default to NULL — existing rows
  remain valid.
- Matches IPTC / schema.org modelling.
- The toggle in §4 becomes pure UX over a flexible data shape.

**Cons**

- More columns on every entity using the field, even when most users
  only ever fill the first pair.
- The field-type name `field_copyright` is now slightly misleading
  (it stores attribution too) — though d.org modules rename to a more
  generic id during pre-release (e.g. `attribution` or `credit`).

### 3b. Alternative: second field type

Ship `field_copyright_attributed` as a separate field type. Existing
sites keep `field_copyright`; new fields needing photographer
attribution use the new type.

**Pros**

- Zero migration. Two field types can coexist forever.
- Cleaner field-instance picker — site builders pick the right shape up
  front.

**Cons**

- Two near-identical widgets / formatters / templates to maintain.
- The toggle still needs to live somewhere, and now spans two field
  types.
- More confusing on drupal.org ("which one do I use?").

### Recommendation

**Go with 3a (extend the existing type).** The migration cost is low —
two nullable columns — and the result is a single coherent field type
that scales from "one credit" up to "creator + rights holder + favicon".
Schema.org and IPTC both validate this shape.

---

## 4. Widget UX — handling the toggle

The "photographer is the copyright holder" toggle is the centrepiece of
the editing experience. Two viable shapes:

### 4a. Toggle as a UI-only collapse (recommended)

The widget always renders **two pairs of inputs**, but the second pair
starts **collapsed** behind a checkbox labelled `"Different photographer
/ creator (add second attribution)"`. Default = unchecked (single pair,
matches the current behaviour). Checking it reveals the second pair.

**No new column** is added for the toggle state — emptiness of the
`creator_*` columns IS the signal. On load, if `creator_title` is
non-empty, the checkbox is pre-checked.

```
┌─ Copyright ─────────────────────────────────────────┐
│ Copyright text *                                    │
│ [© 2024 The Some Museum                          ]  │
│                                                     │
│ URL                                                 │
│ [https://museum.example/about               ]       │
│                                                     │
│ ☐ Different photographer / creator                  │
│                                                     │
│ ▶ (revealed when checkbox is on:)                   │
│   Photographer / creator text                       │
│   [Photo by Jane Doe                            ]   │
│   URL                                               │
│   [https://commons.wikimedia.org/...           ]    │
└─────────────────────────────────────────────────────┘
```

**Pros**

- Simple case stays one click. Power case is one click away.
- No new persisted boolean → no migration if a user toggles their mind.
- The "toggle" is a UI affordance, not data.

**Cons**

- A widget JS file is needed (Drupal can do the reveal via core
  States API too — better, no custom JS).

### 4b. Toggle as per-instance setting

Site builder ticks "Allow photographer attribution" on the field
**instance** edit page. If off, the widget renders only the first pair.
If on, the widget renders both pairs unconditionally (no per-value
collapse).

**Pros**

- Site builders can lock down a field instance to "rights holder only"
  on content types where the second pair would just be noise.

**Cons**

- Editors who occasionally need the photographer field on an otherwise
  rights-holder-only field instance have no way out.
- Less ergonomic at the editing surface.

### Recommendation

**4a** for the default. Optionally **combine** with 4b: the per-instance
setting controls whether the toggle is even available — site builders
who don't want the complexity can hide the checkbox entirely.

---

## 5. Formatter output — what gets rendered

Two existing formatters: `field_copyright_text` (text only) and
`field_copyright_link` (link when URL set).

### 5a. Backwards compatibility

Both keep working unchanged for entries that have only the first pair.
When `creator_title` is also set, the formatter renders both pairs
separated by `·` (middle dot) or a configurable separator.

Example rendering with both pairs + favicon enabled:

```html
<div class="field field--type-copyright">
  <div class="field__item">
    <span class="copyright copyright--holder">
      <span class="field__label">©</span>
      <a href="https://museum.example/about" rel="noopener noreferrer">
        <img class="favicon" src="https://www.google.com/s2/favicons?domain=museum.example&sz=16" alt="" />
        © 2024 The Some Museum
      </a>
    </span>
    <span class="copyright-sep">·</span>
    <span class="copyright copyright--creator">
      <a href="https://commons.wikimedia.org/..." rel="noopener noreferrer">
        <img class="favicon" src="https://www.google.com/s2/favicons?domain=commons.wikimedia.org&sz=16" alt="" />
        Photo by Jane Doe
      </a>
    </span>
  </div>
</div>
```

### 5b. Schema.org / IPTC microdata (nice-to-have, deferred)

When the host entity is an `ImageObject`, the formatter could emit
`itemprop="copyrightHolder"` / `itemprop="creator"` for SEO. Useful but
not blocking — flag for a future iteration.

---

## 6. Favicon — provider abstraction

The plan file `favicon-api-summary.md` (currently at
`MADDev/modules/custom/field_copyright/plan/favicon-api-summary.md`)
already covers the API details. Summary for design purposes:

| Provider | Size param | Missing-icon behaviour | Notes |
|---|---|---|---|
| Google `s2/favicons` | yes (`sz=`) | 404 → `<img onerror>` works cleanly | Easier fallback |
| DuckDuckGo `icons.duckduckgo.com/ip3/...` | no | 200 with placeholder, `onerror` fires nothing | Avoids Google dependency |

### 6a. Module setting

Add a config form at `/admin/config/content/field-copyright`:

```yaml
field_copyright.settings:
  favicon_provider: 'google'   # 'google' | 'duckduckgo' | 'none'
  favicon_size: 16             # ignored for duckduckgo
  favicon_fallback_url: ''     # optional /assets/no-favicon.svg
```

Plus per-formatter override (`Display favicon: inherit | yes | no`) so
each field display can opt out.

### 6b. Where to draw the favicon URL

Pure client-side `<img src="...">` — no backend proxy yet. Privacy
disclosure goes in `README.md`: "When the favicon feature is enabled,
your visitors' browsers will make a request to Google / DuckDuckGo for
each source domain rendered on the page. Disable in settings if this
isn't acceptable."

A server-side proxy + cache (so the third party never sees your visitors)
is a worthwhile follow-up — but it's a separate piece of complexity and
shouldn't block this iteration.

### 6c. Should the favicon live in this module?

**Open question.** Arguments for keeping it inside `field_copyright`:

- The favicon is integral to the formatter output — no value in users
  installing two modules to get one feature.
- The settings form is tiny (one provider, one size, one fallback URL).

Arguments for splitting into a `favicon` helper module:

- Other modules in the user's portfolio (the comprehensive copyright
  module for rudilambert.com? a link-list formatter?) might want the
  same capability.
- Cleaner d.org story: a tiny `favicon` module is its own valuable
  contribution; bundling it inside `field_copyright` hides it.

**Recommendation:** ship it **inside `field_copyright` for now** as a
service (`field_copyright.favicon`) so the logic is centralised. If/when
a second module wants it, extract the service into a `favicon` module
and have `field_copyright` depend on it. Cheap refactor, deferred
correctly.

---

## 7. Migration / install plan

If we go with 3a + 4a + 6 inside this module:

1. **`hook_update_N`** to add `creator_title` and `creator_uri` columns
   to existing `{field_copyright_*}` storage tables. Both nullable; no
   data backfill needed.
2. **`config/schema/field_copyright.schema.yml`** — add schema for the
   new module settings form and the new formatter settings.
3. **`config/install/field_copyright.settings.yml`** — defaults
   (`favicon_provider: google`, `favicon_size: 16`).
4. **`field_copyright.routing.yml` + `field_copyright.links.menu.yml` +
   a `SettingsForm`** for the admin page.
5. **`field_copyright.permissions.yml`** — `administer field_copyright`.
6. **Update the existing widget** for the toggle + reveal (Drupal core
   States API: `'#states' => ['visible' => [':input[name="...show_creator"]' => ['checked' => TRUE]]]`).
7. **Update both formatters** to handle the second pair + favicon.
8. **Update the Twig template** to expose the new variables.
9. **Update the CSS** (currently a single positioned overlay — needs
   updating for the two-pair layout, and the overlay default should
   become opt-in per the d.org punch list in `../CLAUDE.md`).

---

## 8. Risks & open questions

### Decisions to make BEFORE implementation

1. **Storage shape**: §3a (one type, extend columns) or §3b (two types)?
   — _Recommended: 3a._
2. **Toggle semantics**: §4a (per-value UI collapse) or §4b
   (per-instance config) or both? — _Recommended: 4a, optionally
   layered with 4b._
3. **Favicon module split**: §6c — bundle now, extract later? Or split
   from day one? — _Recommended: bundle, extract later._
4. **Field type rename**: drop the `field_` prefix from the type ID
   (`field_copyright` → `copyright` or `attribution`) before d.org
   release? Requires an update path if done later. — _Open._
5. **Where does the canonical copy live?** Currently two on-disk
   copies (museo working copy + MADDev prep area). The museo copy is
   git-tracked; the MADDev one is a scratch snapshot. **Should
   `field_copyright` become a separate git repo / shared-upstream
   module like `infopanel`, before or after this expansion lands?**
   — _Open._

### Risks

- The overlay CSS default is project-shaped (assumes a positioned
  image wrapper). The expansion is a good moment to make it opt-in
  via a separate library; otherwise consumers of the module on
  drupal.org will fight the positioning.
- A third-party favicon request per source domain on a page is a
  measurable performance hit on long pages. Browser caching helps
  after the first hit, but the privacy disclosure is non-negotiable.
- If the field is later extended again (license, date acquired, etc.),
  a paragraph type or compound field structure may eventually serve
  better than ever-more columns. Worth flagging when we cross that line.

---

## 9. What this plan does NOT cover

- The bigger "comprehensive copyright module" for rudilambert.com.
  That sounds like a separate piece (license catalogue, rights
  management, takedown workflow). This expansion makes
  `field_copyright` strong enough to be the **attribution primitive**
  the bigger module composes with — but the bigger module is its own
  design exercise.
- Schema.org microdata output (deferred to a follow-up iteration).
- Server-side favicon proxy + cache (deferred — separate piece of work).
- Per-license metadata (Creative Commons codes, SPDX identifiers).
  That's exactly what the `attribution` and `media_attribution`
  contributed modules already do — if needed, pull one in alongside
  rather than reimplementing.

---

## 10. Sources

Research underpinning this plan:

- **Schema.org**:
  [ImageObject](https://schema.org/ImageObject) ·
  [creditText](https://schema.org/creditText) ·
  [copyrightHolder](https://schema.org/copyrightHolder)
- **Google image metadata guidelines**:
  [Google Images SEO — image metadata](https://developers.google.com/search/docs/appearance/structured-data/image-license-metadata)
- **IPTC / schema.org alignment**:
  [IPTC — schema.org update for better mapping to IPTC Photo Metadata](https://iptc.org/news/schema-org-update-for-better-mapping-to-iptc-photo-metadata/)
- **Creative Commons attribution practice**:
  [CC Wiki — Recommended practices for attribution](https://wiki.creativecommons.org/wiki/Recommended_practices_for_attribution)
- **Museum / institutional practice**:
  [Smithsonian Institution Archives — Rights and Reproduction](https://siarchives.si.edu/what-we-do/rights-and-reproduction)
- **Image credit how-to guides**:
  [Pixsy — Image Credits 101](https://www.pixsy.com/image-licensing/image-credits) ·
  [York University — Image Attribution / Citations](https://copyright.info.yorku.ca/image-attribution-citations/)
- **Existing Drupal modules in the same space**:
  [Media Attribution](https://www.drupal.org/project/media_attribution) ·
  [Attribution & Licensing](https://www.drupal.org/project/attribution) ·
  [Supported Image Field](https://www.drupal.org/project/supported_image) ·
  [IMCE Image Credit](https://www.drupal.org/project/imce_credit)
- **Drupal field-type tutorials**:
  [Drupal.org — Create a custom field type](https://www.drupal.org/docs/creating-custom-modules/creating-custom-field-types-widgets-and-formatters/create-a-custom-field-type) ·
  [The Accidental Coder — Compound fields](https://theaccidentalcoder.com/compound-fields)
- **drupal.org publication requirements**:
  [README.md template](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/documenting-your-project/readmemd-template) ·
  [Drupal coding standards](https://project.pages.drupalcode.org/coding_standards/)
- **Favicon APIs**: see sibling plan `favicon-api-summary.md` (currently
  at `MADDev/modules/custom/field_copyright/plan/`).
