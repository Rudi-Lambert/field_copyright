# field_copyright 3.0 — Optional entity-reference linking (locked design)

**Status:** ✅ Decisions locked 2026-05-28 — ready to build.
**Version:** field_copyright **3.0**.
**Supersedes:** nothing. Extends [2.0](expansion-triple-attribution.md).
**Museo notes:** museo doesn't need this iteration. Ship after museo is
stabilised on 2.0.

---

## 0. tl;dr

Optional entity-reference behaviour for any of the three attribution
roles (holder / photographer / creator). When enabled, the widget
shows a "Link entity" autocomplete next to each role's text inputs.
Picking an entity stores its type + id as metadata **and copies its
label and canonical URL into the existing varchar columns** at save
time.

**The render path stays varchar-only** — no entity loads, no extra
cache tags, no per-page perf cost. The entity reference is metadata
used for canonicalising repeated citations, schema.org JSON-LD
emission (future), and explicit "resync from entity" admin actions.

Three new instance settings (small, not 15):

1. `enable_entity_linking` — bool, default OFF.
2. `linkable_entity_types` — list of entity-type IDs allowed for
   linking (e.g. `[taxonomy_term, user, node]`). Shared across all
   three roles — if one role can link to taxonomy terms, all can.
3. `entity_label_mode` — `'allow_override'` (default) or `'lock'`.
   Controls whether the editor can edit the auto-filled text + URL
   after picking an entity.

Six new nullable columns (target_type + target_id × 3 roles).

---

## 1. Why 3.0 exists

For a content-aggregation site like **rudilambert.com**, the same
photographer or rights holder appears across many pages. 2.0 stores
each citation as free text — so "Marcus Avellonia" gets typed 50
separate times, with whatever variations creep in. Linking to a
shared entity (a taxonomy term, a Person custom entity, a user)
canonicalises the citation: edit the entity once, every reference
catches up (via the manual resync action in the copyrights module).

**Why denormalize-at-save?** Naive entity-reference rendering would
mean an entity load per cited contributor per page render. On long
aggregation pages with many copyright fields, that's a measurable
perf hit. Denormalising at save means render stays as cheap as 2.0,
and the "entity is canonical" benefit shows up at admin / edit / SEO
time, not at every page view.

This is a textbook denormalization pattern (cache the resolved value
at write time, refresh on demand). It works because copyright
citations change far less often than page views happen.

---

## 2. Decisions (locked)

| # | Question | Decision |
|---|---|---|
| 1 | Same field type, or sibling? | **Same.** `field_copyright` grows columns. The instance setting `enable_entity_linking` toggles whether the entity surface exists. Fields with linking off look exactly like 2.0. |
| 2 | Per-role config or shared? | **Shared.** One `enable_entity_linking` + one `linkable_entity_types` list applies to all three roles. The user case: site structure dictates this, not per-role taste. |
| 3 | Storage of resolved values | **Denormalize at save.** Entity label → text column, entity canonical URL → uri column. Render reads only varchars. |
| 4 | Editor override of text after entity pick | **Instance setting** (`entity_label_mode`). Default `allow_override`; `lock` makes the auto-filled fields read-only. Applies to both text and URL together. |
| 5 | Staleness when entity changes | **Capture and forget.** No auto-sync on entity update. The copyrights admin module gets a new bulk action `Resync selected from entity` for explicit refresh. |
| 6 | Entity deletion | **Clean up dangling target_ids.** `hook_entity_delete` listens for entity deletions and NULLs out matching `target_type` / `target_id` columns. Text snapshot stays intact — it's still a valid (textual) citation. |
| 7 | Auto-create entities on the fly | **Deferred to 3.1.** Free text already works for the "I don't have an entity yet" case. Promote text → entity later via a future bulk action. |
| 8 | Widget shape | **Separate text input + "Link entity" autocomplete per role.** Unambiguous about which mode you're in. Drupal `#ajax` populates the text when an entity is picked. |

---

## 3. Storage shape

After 3.0, the field's storage table has **12 columns per delta**:

| Column (after `field_copyright_` prefix) | Type | Nullable | Notes |
|---|---|---|---|
| `title` | varchar(255) | NO | Holder text — existing 1.0 |
| `uri` | varchar(2048) | YES | Holder URL — existing 1.0 |
| `target_type` | varchar(64) | YES | **New in 3.0.** Holder entity type ID (e.g. `taxonomy_term`) |
| `target_id` | varchar(255) | YES | **New in 3.0.** Holder entity ID (varchar to cover both integer and UUID/string IDs) |
| `photographer_title` | varchar(255) | YES | Existing 2.0 |
| `photographer_uri` | varchar(2048) | YES | Existing 2.0 |
| `photographer_target_type` | varchar(64) | YES | **New in 3.0.** |
| `photographer_target_id` | varchar(255) | YES | **New in 3.0.** |
| `creator_title` | varchar(255) | YES | Existing 2.0 |
| `creator_uri` | varchar(2048) | YES | Existing 2.0 |
| `creator_target_type` | varchar(64) | YES | **New in 3.0.** |
| `creator_target_id` | varchar(255) | YES | **New in 3.0.** |

All six new columns are nullable; an instance with `enable_entity_linking`
off behaves and stores exactly like 2.0.

### Why not Drupal `DataReferenceDefinition`?

Drupal's entity_reference field stores just `target_id`, because the
target type is fixed per field. We need a **polymorphic** reference —
each row may point at a different entity type. We do this by storing
`target_type` + `target_id` as plain varchar columns and wiring
deletion cleanup manually via `hook_entity_delete`. Same pattern as
the contrib `dynamic_entity_reference` module.

---

## 4. Instance config (3 settings)

On the field instance edit form:

| Setting | Type | Default | Effect |
|---|---|---|---|
| `enable_entity_linking` | bool | FALSE | Master switch. When off, none of the entity surface exists — field is pure 2.0. |
| `linkable_entity_types` | list&lt;string&gt; | `[]` | Entity-type IDs allowed in the autocomplete (e.g. `taxonomy_term`, `user`, `node`, `media`). Shared across the three roles. |
| `entity_label_mode` | string | `allow_override` | `allow_override`: text + URL inputs stay editable after picking an entity. `lock`: text + URL become read-only when an entity is linked. |

Per-target-bundle restrictions (e.g. "only nodes of type `article`")
are **not** in 3.0 — defer to 3.1 if a real workflow asks. v1 ships
"any bundle of the allowed types."

Schema additions (`config/schema/field_copyright.schema.yml`):

```yaml
field.field_settings.field_copyright:
  mapping:
    enable_photographer: { type: boolean }
    enable_creator:      { type: boolean }
    enable_entity_linking: { type: boolean }
    linkable_entity_types:
      type: sequence
      sequence: { type: string }
    entity_label_mode:   { type: string }
```

---

## 5. Widget UX

When `enable_entity_linking` is on, each role's section renders:

```
┌─ Copyright ─────────────────────────────────────────┐
│ Copyright text *                                    │
│ [© 2024 The Some Museum                          ]  │
│                                                     │
│ URL                                                 │
│ [https://museum.example/about               ]       │
│                                                     │
│ 🔗 Link to entity:                                  │
│ [Search by name…                              ▼]   │  ← entity autocomplete,
│                                                     │     filtered to the
│                                                     │     allowed types
│ (linked: taxonomy_term/47 — The Some Museum)        │  ← shown after pick
│ [✕ Unlink]                                          │
└─────────────────────────────────────────────────────┘
```

### On picking an entity

Via Drupal `#ajax`:

1. Autocomplete returns entity_type:entity_id.
2. AJAX rebuilds the role section: text input + URL input get
   populated with `$entity->label()` and `$entity->toUrl()->toString()`.
3. If `entity_label_mode === 'lock'`, the text + URL inputs are
   rendered with `#disabled => TRUE`. Editor cannot edit.
4. If `entity_label_mode === 'allow_override'`, they stay editable —
   editor can override the auto-filled values.

### On submit

`massageFormValues()`:

- If `target_type` + `target_id` are submitted (entity is picked):
  - In `'lock'` mode: load the entity, force `title` and `uri` to the
    entity's current label / canonical URL. Ignore submitted text /
    URL.
  - In `'allow_override'` mode: store whatever the editor submitted
    in `title` / `uri`. Entity ref is stored as metadata.
- If only text is submitted (no entity picked): store text + URL,
  leave `target_type` / `target_id` NULL.
- On "Unlink": clear `target_type` + `target_id`, leave text + URL
  untouched.

### Cardinality & States

Multi-value fields wrap deltas in fieldsets like 2.0. Photographer
and creator roles offer their existing "Add photographer credit" /
"Add creator credit" reveal checkboxes; once revealed, the role gets
the same entity-linking surface as the holder.

---

## 6. Formatter changes — almost none

The Text and Link formatters (1.0) read only `title` / `uri` — they
keep working unchanged, because those columns continue to hold the
displayable values.

The Full formatter (2.0) reads `title` / `uri` / `photographer_*` /
`creator_*` — also unchanged.

**Optional** addition to the Full formatter render array per pair:

```php
'entity_ref' => $item->target_id
  ? ['type' => $item->target_type, 'id' => $item->target_id]
  : NULL,
```

Templates can use this for:

- Microdata / JSON-LD output (`itemscope itemtype="…/Person"` etc.)
- CSS class hints (`copyright--linked` vs `copyright--text`)
- Edit-mode overlays in admin contexts

Not required to ship. Schema.org JSON-LD emission deferred to 3.1.

---

## 7. Copyrights admin module additions

The copyrights module gets two small additions:

### 7a. New bulk action

`resync_from_entity` — for selected rows where `target_id` is set,
loads the entity, overwrites text + URL columns with the entity's
current label and canonical URL. Skips rows with no entity link.
Skips broken refs (entity deleted between save and resync — but
`hook_entity_delete` will normally have cleared those already).

### 7b. New column hint in the overview table

A small badge/icon column shows whether a row is entity-linked, and
to what:

```
[🔗 term/47]  © 2024 The Some Museum     https://museum.example/about
[—]           © 2024 Independent Artist  —
```

Click-through on the badge opens the entity's edit form in a new
tab.

---

## 8. Save-time flow

```
┌─────────────────────────────────────────────────────────────┐
│  Editor submits entity form                                 │
│  Widget massageFormValues()                                 │
└─────────────────────────────────────────────────────────────┘
                       │
            target_id submitted?
              ┌────────┴────────┐
              │ no              │ yes
              ▼                 ▼
   Store submitted    Load entity from
   text + URL,        target_type + target_id
   NULL refs          │
                      │
              entity_label_mode?
                ┌─────┴────┐
                │ lock     │ allow_override
                ▼          ▼
       Force title +   Store editor's
       URL from        text + URL
       entity          (entity ref kept
                       as metadata)
```

Single entity load per linked role per save. Negligible perf cost on
the save path.

---

## 9. Entity deletion cleanup

`copyrights_entity_delete()` in `copyrights.module` (the admin module
owns this; `field_copyright` stays pure-field):

```php
function copyrights_entity_delete(EntityInterface $entity): void {
  $type = $entity->getEntityTypeId();
  $id   = $entity->id();

  $storages = \Drupal::entityTypeManager()
    ->getStorage('field_storage_config')
    ->loadByProperties(['type' => 'field_copyright']);

  foreach ($storages as $storage) {
    $table = $storage->getTargetEntityTypeId() . '__' . $storage->getName();
    $field = $storage->getName();
    foreach (['', 'photographer_', 'creator_'] as $role) {
      \Drupal::database()->update($table)
        ->condition($field . '_' . $role . 'target_type', $type)
        ->condition($field . '_' . $role . 'target_id',   $id)
        ->fields([
          $field . '_' . $role . 'target_type' => NULL,
          $field . '_' . $role . 'target_id'   => NULL,
        ])
        ->execute();
    }
  }
}
```

Text snapshot stays. Only the broken ref is cleaned. (Note: this
lives in the **copyrights** module, not field_copyright, so the
field type itself doesn't need to depend on a hook system that
spans entity types. Keeps field_copyright lean.)

---

## 10. 2.0 → 3.0 update routine

`field_copyright_update_10300` — adds 6 nullable columns to every
existing storage table. Same shape as the 2.0 update; data-
preserving, no backfill.

```php
function field_copyright_update_10300(): string {
  // Iterate all field_copyright storages.
  // For each storage table (and its _revision twin if revisionable):
  //   - addField <field>_target_type        varchar(64),  nullable
  //   - addField <field>_target_id          varchar(255), nullable
  //   - addField <field>_photographer_target_type varchar(64),  nullable
  //   - addField <field>_photographer_target_id   varchar(255), nullable
  //   - addField <field>_creator_target_type      varchar(64),  nullable
  //   - addField <field>_creator_target_id        varchar(255), nullable
  // Clear entity field manager cache.
}
```

Existing instances stay at 2.0 behaviour — `enable_entity_linking`
ships as FALSE, and 2.0 storage / widget / formatter all keep
working unchanged.

---

## 11. File / config layout after 3.0

```
field_copyright/
├── field_copyright.info.yml                     ← version: 3.0.0-dev
├── field_copyright.install                      ← +hook_update_10300
├── src/Plugin/Field/
│   ├── FieldType/CopyrightItem.php              ← +6 properties, +schema
│   ├── FieldWidget/CopyrightDefaultWidget.php   ← +entity autocomplete + #ajax populate
│   └── FieldFormatter/                          ← unchanged (entity_ref var optional)
├── config/schema/field_copyright.schema.yml     ← +3 instance settings
└── plan/expansion-3.0-entity-linking.md         ← this file
```

```
copyrights/
├── copyrights.module                            ← +hook_entity_delete cleanup
└── src/Form/CopyrightsOverviewForm.php          ← +resync_from_entity action, +entity badge column
```

---

## 12. Open questions parked

- **Auto-create entities on the fly.** Deferred to 3.1. Plain text
  already covers the "no entity yet" case; a future bulk action
  promotes text rows to entity-ref rows when a matching label is
  found / created.
- **Per-bundle target restrictions.** A site might want to allow
  linking only to taxonomy terms in a specific vocabulary, not any
  term. Deferred to 3.1 — v1 allows any bundle of the configured
  entity types.
- **Schema.org JSON-LD emission.** With entity refs in play, the
  Full formatter could emit proper `itemscope itemtype` blocks for
  linked rows. Deferred to 3.1.
- **Different lock for text vs URL.** Some workflows want
  "auto-fill the text from the entity but let me set a different
  link URL" (museum case: entity is the Wikipedia article, but the
  display link should be the museum's own page). v1 locks both
  together. If a real use case complains, split into two settings.
- **The storage ceiling.** 12 columns is fine. A 4.0 adding another
  pair (e.g. licence) would push to ~16 columns and start to feel
  wrong. At that point the right move is JSON columns or a paragraph
  sub-structure, not more flat columns. Flagging now so we cross
  the threshold deliberately.

---

## 13. Build order (suggested PR sequence on the 3.0 branch)

Each PR shippable alone. Each leaves the site in a working state.

1. **PR 1 — Storage update + property defs.** `hook_update_10300`
   adds the 6 columns. `CopyrightItem` declares the 6 new
   properties. Widget / formatter untouched. Site looks identical;
   `enable_entity_linking` not yet a real setting.
2. **PR 2 — Instance settings + widget surface (no #ajax yet).**
   Add the 3 instance settings. Widget renders an entity autocomplete
   per role when `enable_entity_linking` is on. Picking and saving
   stores target_type+id; massageFormValues populates text + URL
   from the entity on save. No live AJAX populate yet — text +
   URL are filled on submit, editor sees them after page reload.
3. **PR 3 — #ajax live populate.** Picking an entity rebuilds the
   role section; text + URL are auto-filled visibly. Honour the
   `lock` mode by setting `#disabled` on the inputs.
4. **PR 4 — copyrights module: entity-delete hook + badge column +
   resync bulk action.**
5. **PR 5 — Schema config + tests + README touches.** Pre-d.org
   polish.

---

## 14. Sources

Same as the [2.0 plan](expansion-triple-attribution.md). Additional
references for the entity-reference / polymorphic-ref aspects:

- [Dynamic Entity Reference module](https://www.drupal.org/project/dynamic_entity_reference)
  — established pattern for polymorphic entity refs (target_type +
  target_id columns).
- [Drupal: Create a custom field type](https://www.drupal.org/docs/creating-custom-modules/creating-custom-field-types-widgets-and-formatters/create-a-custom-field-type)
- [schema.org `copyrightHolder`](https://schema.org/copyrightHolder)
  / [`creator`](https://schema.org/creator) — both accept `Person`,
  `Organization`, or text, validating the polymorphic-by-text/by-entity
  model.
