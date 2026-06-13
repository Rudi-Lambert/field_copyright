# field_copyright — Claude notes

Module-specific session guidance. Cross-cutting rules live in:

- `..\CLAUDE.md` (cross-module, drupal.org standards, scripts policy)
- `W:\Projects\MADDev\CLAUDE.md` (Drupal philosophy)
- `~\.claude\CLAUDE.md` (machine setup)
- `.Claude/SCOPE.md` (this module's responsibilities — read first)

---

## What this module is

A Drupal **field type** — `field_copyright` — providing a credit/attribution
field with required text + optional source URL. Designed for image and media
copyright lines.

**Audience:** drupal.org. Keep it generic, no project-specific assumptions.

**Current state:** this repo is **2.0.0-dev** (info.yml). Code today
is identical to 1.0 — the 2.0 build hasn't started yet. The museo copy
is the 1.0 frozen baseline.

**Iteration 2.0 (in 2.0 dev repo at this location):** triple attribution
(copyright holder + optional photographer + optional creator),
per-instance gating, per-value collapse via core States API, optional
favicon next to source URLs. Every new behaviour is opt-in — defaults
reproduce 1.0 output exactly. **Locked design:**
[.Claude/plan/expansion-triple-attribution.md](.Claude/plan/expansion-triple-attribution.md).

**Iteration 3.0 (planned, after museo is stable on 2.0):** optional
entity-reference linking for any of the three roles. Editor picks a
taxonomy term / user / node / custom entity → at save, the entity's
label + canonical URL are denormalized into the existing varchar
columns; entity type + id are stored alongside as metadata. Render
path stays varchar-only — no entity loads on page view, no extra
cache tags. Off by default; enabled per field instance. **Locked
design:** [.Claude/plan/expansion-3.0-entity-linking.md](.Claude/plan/expansion-3.0-entity-linking.md).

The v1 proposal `.Claude/plan/expansion-double-attribution.md` is superseded
by the 2.0 plan but kept for the research/sources record.

---

## Layout

```
field_copyright/
├── CLAUDE.md                                ← this file
├── SCOPE.md                                 ← module responsibility boundary
├── README.md                                ← (to add before d.org release)
├── LICENSE.txt                              ← (to add — GPL-2.0-or-later)
├── field_copyright.info.yml
├── field_copyright.libraries.yml
├── field_copyright.module                   ← hooks + preprocess
├── css/field_copyright.css                  ← overlay-style copyright line
├── src/Plugin/Field/
│   ├── FieldType/CopyrightItem.php
│   ├── FieldWidget/CopyrightDefaultWidget.php
│   └── FieldFormatter/
│       ├── CopyrightTextFormatter.php       ← text only
│       └── CopyrightLinkFormatter.php       ← link if URL, else text
├── templates/field--field-copyright.html.twig
└── plan/                                    ← design docs for ongoing work
    └── favicon-api-summary.md               ← (currently lives in the MADDev
                                                  shared-prep copy — see note)
```

> **Canonical / development location.** This file lives in the canonical
> 2.0 development copy at `W:\Projects\MADDev\modules\custom\field_copyright\`
> (its own git repo, branch `main`, first commit b8e6802 imported the
> 1.0 codebase from the museo project on 2026-05-27).
>
> A **second copy of the 1.0 code** still lives at
> `W:\Projects\MADDev\museo-avellonia\www\web\modules\custom\field_copyright\`,
> tracked inside the museo project repo. That copy is **frozen at 1.0**
> until 2.0 ships; bug fixes there get mirrored here, but no new work
> happens there. When 2.0 stabilises and gets a GitHub remote, the museo
> copy will be replaced by a fresh `git clone` of this repo (same
> shared-upstream pattern as `infopanel`).

---

## Field type internals (current)

`CopyrightItem` stores two columns:

| Column | Type | Required | Notes |
|---|---|---|---|
| `title` | varchar(255) | yes | The credit text, e.g. `© 2024 Jane Doe` |
| `uri`   | varchar(2048) | no | Optional source URL |

- `isEmpty()` returns TRUE when `title` is empty (URL alone is not a
  valid value).
- Sample value generator yields a `© <year> <name>` string and sometimes
  a URL.
- Field type ID is `field_copyright` (module-name-prefixed). **Don't
  rename without an update path.**

The widget renders two inputs (text + url). When the field cardinality
is > 1, it auto-wraps each delta in a `<fieldset>` for visual grouping.

The link formatter exposes a `target` setting (`_blank` / `_self` / none)
and always emits `rel="noopener noreferrer"` when a target is set.

The preprocess in `field_copyright.module` exposes `copyright_text`,
`copyright_url`, and `link_attributes` to the Twig template so the
template stays simple.

CSS makes the field a **positioned overlay** — meant to sit absolutely
in the corner of a positioned container (e.g. an image wrapper), with
low-opacity background that becomes opaque on hover. **This is a strong
default with a clear use case, but it's also a project-shaped choice.**
Before d.org release, the overlay CSS should be optional / split into a
separate library, with a neutral default.

---

## What this module does NOT own

- Any project-specific content type or paragraph type wiring — those
  attach this field but live in their own modules.
- Any image / media handling logic.
- Anywhere that copyright text actually gets STORED on entities — that's
  the host entity's responsibility.

---

## Conventions for working here

- **Don't add project-specific code.** If you find yourself referencing
  `museo_avellonia`, `butterfly_specimen`, or any project name — stop.
  It belongs in the project module.
- **Strings in `t()` / `$this->t()` everywhere user-facing.** This
  module ships abroad.
- **Schema schema schema.** Any new setting key needs a matching entry
  in `config/schema/field_copyright.schema.yml` (file to be created
  when the first setting is added).
- **Coding standard:** Drupal PHP (≈ PSR-12 + Drupal quirks). Two-space
  indent, type hints + return types, `/** {@inheritdoc} */` on
  overrides.
- **Plan / design notes go in `.Claude/plan/`** — not in the module's deployable
  files. The `.Claude/` directory should be excluded from packaging once
  publishing to d.org (add to a `.gitattributes` `export-ignore` rule
  when ready).
- **Temp scripts** (e.g. test data, ad-hoc loaders): use the project's
  `scripts/` folder, not this module's tree.

---

## Open work (see .Claude/plan/)

- **2.0 — Triple attribution + favicon.** Locked design in
  `.Claude/plan/expansion-triple-attribution.md`. Storage: 4 new nullable
  columns on the existing field type (one flat table — see §2 of the
  plan). Build order: 6 small PRs (§10 of the plan), each shippable
  alone.
- The museo working copy = 1.0 (frozen). 2.0 work happens HERE
  (decided 2026-05-27). When 2.0 stabilises + has a GitHub remote, the
  museo copy gets replaced by a fresh clone of this repo. See §10 of
  the plan for the suggested PR sequence (6 small steps).

---

## Pre-d.org-release punch list

(Tracked here so it doesn't get lost.)

- [ ] Add `README.md` per drupal.org README template.
- [ ] Add `LICENSE.txt` (GPL-2.0-or-later).
- [ ] Add `config/schema/field_copyright.schema.yml` (once the first
      formatter / widget setting needs schema).
- [ ] Add `tests/src/Kernel/FieldCopyrightTest.php` (basic CRUD test).
- [ ] Make overlay CSS opt-in (separate library) — the absolute-position
      overlay assumes a positioned image wrapper; default should be
      neutral.
- [ ] Replace `field_copyright` field-type machine name with `copyright`?
      Decide before release — requires update path if changed later.
- [ ] Run `phpcs --standard=Drupal,DrupalPractice` clean.
- [ ] Smoke test on a clean Drupal 11 install (drush pmu / drush en
      cycle reproduces a working field).
