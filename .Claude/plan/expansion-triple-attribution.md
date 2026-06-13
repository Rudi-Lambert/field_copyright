# field_copyright 2.0 — Triple-attribution + favicon (locked design)

**Status:** ✅ Decisions locked 2026-05-27 — ready to build.
**Version:** field_copyright **2.0** (museo working copy stays at 1.0
until 2.0 ships + the update routine runs).
**Supersedes:** `expansion-double-attribution.md` (v1 proposal — kept
for the research/sources record).

---

## 0. tl;dr

Expand `field_copyright` to model **three optional attribution roles**:
**copyright holder** (required), **photographer**, and **creator**.
Every new behaviour is **opt-in per field instance** — default settings
reproduce 1.0 behaviour exactly. Add a settings-selectable favicon
provider (Google / DuckDuckGo / off). Ship a `hook_update_N` that adds
the new columns to existing 1.0 installs without touching data.

### Why three, not two

| Role | schema.org | IPTC | Example |
|---|---|---|---|
| **Copyright holder** | `copyrightHolder` | Copyright Notice | Museum X, Estate of Y |
| **Photographer** | (photographer is a *kind* of `creator`) | Creator | Jane Doe |
| **Creator** | `creator` / `author` | Creator (original work) | The painter, sculptor, or original maker of the depicted work |

The split matters in three common real-world cases:

1. **Photograph of an artwork** (museum case): original artist (creator)
   ≠ museum photographer (photographer) ≠ museum (copyright holder).
2. **Stock photo of someone's work**: photographer ≠ rights holder
   (which is often the agency).
3. **Wikimedia-style reuse**: the platform credit, the photographer,
   and the rights holder can all be different parties.

For rudilambert.com's external-content reuse, all three regularly
appear.

---

## 1. Decisions (locked)

| # | Question | Decision |
|---|---|---|
| 1 | Storage shape | **Extend `field_copyright` columns.** Drupal field storage is already flat one-row-per-delta with one column per subfield — no joins. No need for the [Custom Field](https://www.drupal.org/project/custom_field) contrib module (its value is *site-builder-defined* subfields, which we don't need for a fixed type). |
| 2 | Toggle semantics | **Per-value UI collapse via core States API** + **per-instance gating** on whether the toggle is even offered. Combined approach. |
| 3 | Favicon split | **Bundle inside `field_copyright` for now** as a service. Extract to a `favicon` helper module later if/when a second module wants it. |
| 4 | Field-type rename | **Keep `field_copyright`.** "Copyright" is how users search for and think about this; "attribution" is technically correct but too vague. Lower-case `copyright` machine name (drop the `field_` prefix) is reserved for a possible future structural cleanup. |
| 5 | Versioning | **Museo working copy = 1.0** (frozen). **MADDev/modules/custom/field_copyright = 2.0 dev area.** Write an update routine. Once 2.0 stabilises, project repos pick it up via the shared-upstream mechanism (same pattern as `infopanel`). |
| 6 | Default behaviour | **Maximum configurability — every new feature opt-in, defaults reproduce 1.0.** On a fresh field instance, you see exactly the 1.0 form: one text input, one URL input. Site builders enable extras per instance. |

---

## 2. Storage — the one table, six columns

After 2.0, the field's storage table (e.g. `node__field_copyright`)
gets these columns per delta:

| Column (after `field_copyright_` prefix) | Type | Nullable | Role | Notes |
|---|---|---|---|---|
| `title` | varchar(255) | NO | Copyright holder text | Existing 1.0 column — unchanged |
| `uri`   | varchar(2048) | YES | Copyright holder URL | Existing 1.0 column — unchanged |
| `photographer_title` | varchar(255) | YES | Photographer credit text | **New in 2.0** |
| `photographer_uri`   | varchar(2048) | YES | Photographer URL | **New in 2.0** |
| `creator_title` | varchar(255) | YES | Creator (original artist) text | **New in 2.0** |
| `creator_uri`   | varchar(2048) | YES | Creator URL | **New in 2.0** |

That's one row per delta in one table. To read all six values is one
SELECT, zero JOINs — the layout the user asked for.

`isEmpty()` is still defined by `title` alone: a row without a primary
copyright credit is empty even if photographer/creator are filled.

No new persisted "toggle" booleans — the *presence* of a value in
`photographer_title` or `creator_title` is the signal that section was
filled in.

---

## 3. Field-instance settings (per-instance config)

Settings page = the field-instance edit form (`/admin/structure/types/...
/fields/...`). All default to OFF, reproducing 1.0 behaviour.

| Setting | Default | Effect |
|---|---|---|
| `enable_photographer` | OFF | Show the "Add photographer credit" toggle in the widget |
| `enable_creator` | OFF | Show the "Add creator credit" toggle in the widget |
| `photographer_label` | "Photographer / source" | Label for the photographer pair |
| `creator_label` | "Original creator / artist" | Label for the creator pair |
| `photographer_required_with_url` | OFF | If photographer URL is filled, require text too |
| `creator_required_with_url` | OFF | If creator URL is filled, require text too |

Site builders who want all three pairs always visible can enable both
toggles AND skip the per-value collapse below; site builders who want
strict 1.0 behaviour leave everything off.

---

## 4. Widget UX

Two layers of optionality.

### Layer A — per-instance gating

If `enable_photographer` is OFF for this field instance, the
photographer pair never renders. Same for `enable_creator`. This is
what site builders control on the field-instance edit form.

### Layer B — per-value collapse (core States API, no custom JS)

When a section is enabled per-instance, the widget renders a checkbox
above each optional section. The pair below collapses unless the
checkbox is on.

```
┌─ Copyright ─────────────────────────────────────────┐
│ Copyright text *                                    │
│ [© 2024 The Some Museum                          ]  │
│                                                     │
│ URL                                                 │
│ [https://museum.example/about               ]       │
│                                                     │
│ ☐ Add photographer credit                           │  ← only shown if
│   (revealed when on:)                               │     enable_photographer
│   Photographer / source                             │     is ON for the instance
│   [Photo by Jane Doe                            ]   │
│   URL                                               │
│   [https://commons.wikimedia.org/...           ]    │
│                                                     │
│ ☐ Add creator / original artist credit              │  ← only shown if
│   (revealed when on:)                               │     enable_creator
│   Creator                                           │     is ON
│   [The Painter                                  ]   │
│   URL                                               │
│   [https://en.wikipedia.org/wiki/The_Painter    ]   │
└─────────────────────────────────────────────────────┘
```

On load, each checkbox pre-checks if its `*_title` column has a value.
On save, if a checkbox is unchecked, that pair's values are cleared
(stored as NULL).

Implementation: pure Drupal core States API — no custom JS, no library.
The reveal is declarative in the form array:

```php
$element['photographer'] = [
  '#type' => 'fieldset',
  '#states' => [
    'visible' => [
      ':input[name$="[show_photographer]"]' => ['checked' => TRUE],
    ],
  ],
];
```

### Cardinality > 1

When the field's cardinality is > 1, each delta is still wrapped in a
`<fieldset>` (existing 1.0 behaviour) so multi-value forms stay
visually grouped.

---

## 5. Formatters

Two existing formatters stay, two new ones added — all backwards
compatible with 1.0.

| Formatter ID | Existing? | Default | Notes |
|---|---|---|---|
| `field_copyright_text` | yes (1.0) | Plain text, copyright only | Unchanged. Renders `title` only — ignores all extras. |
| `field_copyright_link` | yes (1.0) | Link if URL, copyright only | Unchanged. Renders `title` (linked if `uri` set) only — ignores all extras. |
| `field_copyright_full` | **new in 2.0** | Renders all enabled pairs, links where URL present | The "show everything" formatter. |
| `field_copyright_smart` | **new in 2.0** | Renders all populated pairs with separator | Adapts to what's filled, doesn't render empty sections. |

Per-formatter settings (all formatters):

| Setting | Default | Effect |
|---|---|---|
| `show_favicon` | inherit module default | "inherit" / "yes" / "no" |
| `favicon_size` | inherit module default | px |
| `link_target` | `_blank` (1.0 default) | for any rendered link |
| `separator` (full/smart only) | ` · ` | between rendered pairs |
| `label_format` (full/smart only) | `'symbol'` | `'symbol'` (©, 📷, 🎨), `'text'` ("Copyright:", "Photo:", "Creator:"), or `'none'` |

Critically: a 1.0 site with the default `field_copyright_text` formatter
displays **exactly the same output** after the 2.0 update, even if the
field instance has photographer/creator columns added.

### Sample rendered HTML (full formatter, all three pairs, favicon on)

```html
<div class="field field--type-field-copyright">
  <div class="field__item">

    <span class="copyright copyright--holder">
      <span class="copyright__label">©</span>
      <a href="https://museum.example/about" target="_blank" rel="noopener noreferrer">
        <img class="copyright__favicon" src="https://www.google.com/s2/favicons?domain=museum.example&sz=16" alt="" width="16" height="16" />
        The Some Museum
      </a>
    </span>

    <span class="copyright__sep"> · </span>

    <span class="copyright copyright--photographer">
      <span class="copyright__label">📷</span>
      <a href="https://commons.wikimedia.org/..." target="_blank" rel="noopener noreferrer">
        <img class="copyright__favicon" src="https://www.google.com/s2/favicons?domain=commons.wikimedia.org&sz=16" alt="" width="16" height="16" />
        Jane Doe
      </a>
    </span>

    <span class="copyright__sep"> · </span>

    <span class="copyright copyright--creator">
      <span class="copyright__label">🎨</span>
      The Painter
    </span>

  </div>
</div>
```

---

## 6. Module settings (site-wide)

New admin form at `/admin/config/content/field-copyright`. Schema in
`config/schema/field_copyright.schema.yml`. Defaults shipped in
`config/install/field_copyright.settings.yml`.

| Key | Default | Effect |
|---|---|---|
| `favicon_provider` | `'none'` | `'google'` / `'duckduckgo'` / `'none'` — site-wide default for new formatters |
| `favicon_size` | `16` | Default px size (ignored for DuckDuckGo) |
| `favicon_fallback_url` | empty | Optional URL shown when provider returns 404 / placeholder |

**Default `favicon_provider: 'none'`** — favicon is opt-in. A 1.0
upgrade gets no favicon by default; only after a site admin sets a
provider does anything new appear in rendered output.

Permission: `administer field_copyright`.

---

## 7. Favicon implementation

Service `field_copyright.favicon`:

```php
public function getFaviconUrl(string $url, ?string $providerOverride = NULL, ?int $sizeOverride = NULL): ?string;
```

Returns a fully-formed `<img src="...">` URL for the given page URL's
domain, or NULL if `provider === 'none'`. Pure URL construction, no
HTTP calls from PHP — the browser fetches the favicon directly.

- **Google**: `https://www.google.com/s2/favicons?domain={host}&sz={size}`
  — missing icon returns 404, `<img onerror>` handles fallback cleanly.
- **DuckDuckGo**: `https://icons.duckduckgo.com/ip3/{host}.ico` —
  always 200, `onerror` won't fire on missing. For DuckDuckGo we
  accept this limitation in 2.0; a server-side proxy + cache is a
  candidate for 2.1.

Privacy disclosure goes in `README.md`: "When favicon is enabled, each
visitor's browser hits Google / DuckDuckGo with one request per unique
source domain rendered on the page."

(See sibling plan `favicon-api-summary.md` for full API details.)

---

## 8. 1.0 → 2.0 update routine

### Drupal-module versioning quick primer

- `.info.yml` on **d.org-hosted** modules has **no `version:` key** —
  packaging adds the version from the git tag.
- `.info.yml` on **manually-managed** modules CAN carry `version: 2.0`.
- Schema migrations happen via `hook_update_N` in `<module>.install`:
  the function name `field_copyright_update_10200` means
  "Drupal 10 / sequence 200" — the digits are an arbitrary climbing
  number, and the convention is `<major-core><three-digit-sequence>`.
- After install/code-deploy, the site admin runs `drush updatedb` (or
  `/update.php`) which finds the highest registered hook number and
  runs each new one in order.

### The actual update routine

`field_copyright.install`:

```php
/**
 * Add photographer and creator columns for field_copyright 2.0.
 */
function field_copyright_update_10200(&$sandbox): string {
  $schema = \Drupal::database()->schema();
  $field_manager = \Drupal::entityDefinitionUpdateManager();

  // Iterate every field storage definition of type 'field_copyright'
  // and add the four new columns to its storage table(s).
  // (Detailed implementation lifted from core Field API examples —
  // see core/modules/text/text.install for the canonical pattern.)

  return t('Added photographer_* and creator_* columns to all field_copyright storage tables.');
}
```

Key properties of the update:

- **Data-preserving**: existing `title` / `uri` values untouched.
- **Default NULL**: new columns are nullable; no backfill needed.
- **Per-instance config left at 1.0 defaults**: `enable_photographer`
  and `enable_creator` ship as OFF, so upgraded sites see no UI
  change in editing or rendering until a site builder turns them on.
- **One-shot, no batch**: schema ALTER on the (typically few) storage
  tables is fast enough not to need batching.

### Rollback

Reverting to 1.0:

1. `drush en field_copyright_rollback` (a one-shot module shipped
   alongside 2.0, or just a documented hand-run snippet) drops the
   four new columns.
2. `composer require drupal/field_copyright:^1` or git-revert the
   module to a 1.0 commit.
3. `drush cr`.

We accept that rollback **loses photographer/creator data**. That's
documented in `README.md` under "Upgrading from 1.x".

---

## 9. File / config layout after 2.0

```
field_copyright/
├── CLAUDE.md
├── SCOPE.md
├── README.md                                          ← NEW (d.org template)
├── LICENSE.txt                                        ← NEW (GPL-2.0-or-later)
├── field_copyright.info.yml
├── field_copyright.install                            ← NEW (hook_update_10200)
├── field_copyright.libraries.yml
├── field_copyright.links.menu.yml                     ← NEW (admin menu link)
├── field_copyright.module
├── field_copyright.permissions.yml                    ← NEW (administer field_copyright)
├── field_copyright.routing.yml                        ← NEW (settings form route)
├── field_copyright.services.yml                       ← NEW (favicon service)
├── composer.json                                      ← NEW (for DrupalCI)
├── config/
│   ├── install/
│   │   └── field_copyright.settings.yml               ← NEW (favicon defaults)
│   └── schema/
│       └── field_copyright.schema.yml                 ← NEW (all settings keys)
├── css/
│   ├── field_copyright.css                            ← becomes opt-in overlay variant
│   └── field_copyright-inline.css                     ← NEW (neutral inline default)
├── src/
│   ├── Form/SettingsForm.php                          ← NEW
│   ├── Service/FaviconUrlBuilder.php                  ← NEW
│   └── Plugin/Field/
│       ├── FieldType/CopyrightItem.php                ← UPDATED (4 new columns + property defs)
│       ├── FieldWidget/CopyrightDefaultWidget.php     ← UPDATED (States API reveal, per-instance settings)
│       └── FieldFormatter/
│           ├── CopyrightTextFormatter.php             ← UNCHANGED behaviour
│           ├── CopyrightLinkFormatter.php             ← UNCHANGED behaviour (favicon opt-in)
│           ├── CopyrightFullFormatter.php             ← NEW
│           └── CopyrightSmartFormatter.php            ← NEW
├── templates/
│   ├── field--field-copyright.html.twig               ← UPDATED (handles all pairs + favicon)
│   └── field--field-copyright--text-only.html.twig    ← NEW (1.0-compatible fallback)
├── tests/src/
│   ├── Kernel/CopyrightItemTest.php                   ← NEW
│   └── Functional/CopyrightWidgetTest.php             ← NEW
└── plan/
    ├── expansion-double-attribution.md                ← v1, kept for record
    ├── expansion-triple-attribution.md                ← this file (locked v2 design)
    └── favicon-api-summary.md                         ← API reference
```

---

## 10. Build order (suggested PR sequence)

Small, reviewable chunks. Each chunk is shippable alone.

1. **PR 1 — Storage update** (`hook_update_10200` + schema). Land the
   four nullable columns. No widget/formatter changes yet. Site looks
   identical.
2. **PR 2 — Widget States API reveal** + per-instance `enable_*`
   settings. New columns become editable when the site builder turns
   them on. Default still hides everything → 1.0 behaviour.
3. **PR 3 — Two new formatters** (`Full`, `Smart`). New display options
   appear in the formatter dropdown. Existing displays untouched.
4. **PR 4 — Favicon service + module settings form** + per-formatter
   favicon settings. Provider defaults to `'none'`.
5. **PR 5 — CSS split**: neutral inline default + opt-in overlay
   library. Existing sites pick up overlay via the library that 1.0
   loaded.
6. **PR 6 — README.md, LICENSE.txt, composer.json, schema, tests**.
   Pre-d.org polish.

---

## 11. Open questions still parked

- **MADDev/modules/custom/field_copyright as 2.0 dev area** — should
  I `git init` it now and start the 2.0 work there, leaving the museo
  copy frozen at 1.0? Or do PR-1's update routine + storage change in
  the museo copy first to prove it works end-to-end before extracting?
  — _Pending user call. Affects whether the next session works in
  MADDev/modules/custom/ or in museo/web/modules/custom/._
- **Should `field_copyright_text` / `field_copyright_link` formatters
  also be able to render the photographer/creator if the site builder
  asks?** Strict reading of "defaults to 1.0 behaviour" says no — they
  stay pure. The new `Full` / `Smart` formatters exist for the
  three-pair case. — _Going with: keep 1.0 formatters pure. Speak up if
  this is wrong._
- **Schema.org microdata emission** (`itemprop="copyrightHolder"` etc.
  on `ImageObject` hosts) — deferred to 2.1. Genuinely useful for SEO
  but adds complexity around detecting host entity context.

---

## 12. Sources

(Re-listed for completeness — same set as v1 doc.)

- **schema.org**: [ImageObject](https://schema.org/ImageObject) ·
  [creditText](https://schema.org/creditText) ·
  [copyrightHolder](https://schema.org/copyrightHolder)
- **Google image metadata guidelines**:
  [Google Images SEO — image metadata](https://developers.google.com/search/docs/appearance/structured-data/image-license-metadata)
- **IPTC / schema.org alignment**:
  [IPTC — schema.org update for better mapping to IPTC Photo Metadata](https://iptc.org/news/schema-org-update-for-better-mapping-to-iptc-photo-metadata/)
- **Existing Drupal modules in the same space**:
  [Custom Field](https://www.drupal.org/project/custom_field) ·
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
- **Favicon APIs**: sibling plan `favicon-api-summary.md`.
