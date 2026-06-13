# BOOT.md — field_copyright + copyrights (Copyright trio) Architect

Paste this into a fresh chat opened in
`W:\Projects\MADDev\modules\custom\field_copyright\`:

```
You are the Architect/maintainer for the field_copyright + copyrights shared
module group (the "Copyright trio"). Read:
W:\Projects\MADDev\.claude\coop\ARCHITECT.md (esp. §7 shared modules) +
WORKING-SEQUENCE.md, W:\Projects\MADDev\.claude\knowledge\GLOSSARY.md, then
each module's own .Claude/SCOPE.md (field_copyright at this folder's root;
copyrights at W:\Projects\MADDev\modules\custom\copyrights\), plus the facts
below. The master is the filesystem — no remote yet; git fetch is a no-op
until a remote is added. Begin.
```

## Facts

### field_copyright
- Master: `W:\Projects\MADDev\modules\custom\field_copyright\` — git repo,
  **no remote yet** (breaking from museo's in-tree 1.0 copy; remote to be
  created before first external consumer)
- Version: `2.0.0-dev` (2.x line; breaking from the 1.0 in-tree copy)
- Distribution: master-only; no consumers on 2.x yet
- Consumers:
  - museo-avellonia holds an **in-tree 1.0 copy** (Model A, gitignored
    sub-clone); pinned to 1.x — this copy is **LOCKED, never migrate to 2.x
    without explicit Rudi sign-off**
- Folder = package name: `field_copyright`
- SCOPE: copyright field type (credit text + optional source URL); widget +
  formatter; schema. Full scope in `.Claude/SCOPE.md`.

### copyrights
- Master: `W:\Projects\MADDev\modules\custom\copyrights\` — git repo,
  **no remote yet**
- Version: `0.1.0-dev`
- Distribution: master-only; no consumers yet
- Depends on: `field_copyright >= 2.0` (hard dep; degrades gracefully on 1.0
  by hiding photographer/creator columns on the overview page)
- Folder = package name: `copyrights`
- SCOPE: admin layer — `/admin/content/copyrights` overview page, runtime
  discovery of `field_copyright` instances across all entity types, bulk
  operations. Custom Form/controller (not Views). Full scope in `.Claude/SCOPE.md`.

### Pair relationship
`copyrights` is the admin layer that sits on top of `field_copyright`.
They version together but live in separate repos. Neither has a Composer
package name assigned yet — both will need `composer.json` when a remote
is created and a first consumer adopts them.

### Versioning model
Mirrors the infopanel 1.x/2.x split. The 1.0 in-tree copy on museo-avellonia
is the "locked 1.x line"; this master folder is the 2.x dev line. Do not
back-port 2.x changes to museo's in-tree copy without explicit sign-off.

### Vision
Planned as the attribution primitive for a future comprehensive copyright
system on rudilambert.com (heavy external-content reuse). Roadmap items
(licence catalogue, SPDX integration, rights-clearance workflow, takedown
handling, source-domain analytics) are tracked in
`copyrights/.Claude/SCOPE.md` — they have no code yet.

## Discipline (shared-module specifics)

- `git fetch + status` before editing; pull if behind; surface uncommitted
  divergent work to Rudi before merging. *(Until a remote is added: `git
  status` is the check; skip fetch.)*
- Commit + push after changes; note which consumers are now behind.
- A breaking change (renamed field, changed service signature, schema/update
  hook) gets a heads-up note in the module's devnotes + the consumers' BOOTs.
- **museo-avellonia's in-tree 1.0 `field_copyright` is LOCKED — never
  migrate it to 2.x without explicit Rudi sign-off.** The 1.x/2.x split is
  intentional and permanent for that project.
- No remote yet: do not fabricate `git push` / `git fetch` steps until a
  remote is wired. First task before any external consumer is: create the
  GitHub remote, add as `origin`, push.

## Open items / current queue

- Create GitHub remote for `field_copyright` (2.x line) and push
- Create GitHub remote for `copyrights` and push
- Assign Composer package names (`maddev/field_copyright`,
  `maddev/copyrights` or equivalent) and add `composer.json` to each
- Wire first 2.x consumer (likely rudilambert or madacontact)
- `copyrights` roadmap: licence catalogue, SPDX, rights-clearance workflow,
  takedown handling, source-domain analytics — none started; each gets its
  own planning entry when work begins
