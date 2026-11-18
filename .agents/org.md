# Org — current project state

This is the compact live organizational state for the single `org` chat on `dev`. It is not a transcript. Replace superseded facts instead of appending history.

## Current milestone — v0.4.0

**Development state:** artist-feedback work is still active. The user has **not** declared a Dev freeze and no final v0.4 source SHA exists yet.

Open v0.4 closing gates:
- **#198 — functional/failure-state validation:** bounded closeout only. v0.4 does not require a permanently bug-free application; at freeze the core feature set must work without a known high-severity ordinary-use blocker.
- **#199 — verification suite/release checks:** final Testing reconciliation and executed isolated qualification happen only after the user declares the exact Dev freeze.
- **#214 — release notes and milestone closeout:** pre-freeze themes are prepared; final date, Git range, evidence, Dashboard entries and milestone closure wait for the exact freeze.

Accepted foundational v0.4 Issues already closed by user decision:
- #177 Activity / Undo / Notification foundation;
- #197 public frontend foundation;
- #207 Admin controls/tables consistency foundation;
- #208 shared responsive foundation.

Do not reopen those merely because later artist-driven changes expose a concrete regression. Track/fix the concrete owner instead.

## Next milestone — v0.5.0

Current open v0.5 gates:
- #31 protected Biography/Exhibitions/profile reconciliation;
- #34 exact-candidate public/editorial regression comparison;
- #38 authoritative database + original-media backup/restore proof;
- #39 exact v0.5 Production-candidate GO/NO-GO;
- #57 Analytics against real aggregate data;
- #59 Storage capacity/quota in target runtime;
- #122 real Contact mail-delivery acceptance;
- #209 artist-approved Validation cutover dataset.

v0.5 is already partially exercised in practice because protected Validation is used for real artist editorial work, but v0.4 should still receive a clean historical close before the v0.5 release-candidate gates are treated as the active milestone.

## Validation

Protected Validation is an active pre-release/editorial environment whose database and canonical originals must be preserved across ordinary preview updates.

For a requested new Dev preview, resolve the exact fresh `dev` SHA and use the established two-stage release contract:
1. `scripts/validation-preview.ps1 <exact-sha>` builds/publishes the exact preview image;
2. the platform Validation update consumes that same exact SHA.

Do not record private host/access details here. No current deployed Validation SHA is independently confirmed in Org state; verify before claiming one.

## Local artist-state mirror from Validation

**Completed rehearsal, 2026-11-18 (user-supplied operator output):** Validation's logical PostgreSQL dump and private media archive were transferred with matching SHA-256 checksums, then imported into the persistent local preview database/media mount. The import reported 768 extracted media files. Local `dev@5889f6bd56f5e47984f70e74e31053866b767397` started successfully, all listed forward migrations through `2026_11_18_000002` were marked Ran, and `php artisan media:verify` reported `ok: true`, 269 MediaAsset rows checked, zero failures. Preview startup/health passed; **artist/browser acceptance is not yet confirmed**.

The source Validation database/media remain unchanged by this read-only export. The local mirror is a development/migration rehearsal, not a cutover candidate or a substitute for the independent Production backup/restore gate #38. Later Validation edits require a fresh matching data/media copy before an exact rehearsal; deployment still applies forward migrations independently to then-current Validation data.

**Cleanup outstanding:** after the user verifies the local health and the staged cleanup command succeeds, confirm deletion of only the temporary transfer archive, redundant local-playground backup and former local media directory. Do not assume deletion from a command being provided. Server-side temporary export cleanup was prescribed but is not independently confirmed.

## Main / repository-history gate

Before the next approved promotion to `main`, remove the historical public ADR infrastructure material from reachable Git history. The user decision is made: the old ADR content should be removed from history, not merely deleted from the current tree.

Do **not** interrupt ordinary active worker development for this now. Perform the coordinated history rewrite only at the later pre-main write-freeze, covering all relevant branches/tags/refs and follow-up clone/cache reconciliation. The current release contract owns the mandatory reminder.

The shared protocol now also forbids concrete private operational topology/access details in the public application repository so this class of exposure is not repeated.

## Cross-workstream / live-file hygiene

Org does not duplicate active worker assignments; inspect the owning branch roster when needed.

Current audit (2026-11-03, later pass):
- **Dev Worker 1:** currently the weakest live record. Roster correctly says Exhibition date precision/address/map/editor work, but the file header still claims older Admin-dialog/media ownership. The file has grown to about 33k characters/154 lines with extensive superseded Exhibition chronology. Durable learning promotion is good, but replacement-chat continuity is poor until the owner compacts it to current Exhibition scope, accepted date semantics/inventory, current source state, browser/data gates and one Next.
- **Dev Worker 2:** materially improved to about 7.7k characters and organized around current media invariants plus one grouped Testing handoff. Durable media lifecycle rules reached the Dev skill. One final dated cross-worker startup note can disappear once handed off; otherwise healthy.
- **Dev Worker 3:** correctly retired. Roster row and `.agents/workers/dev-3.md` are both gone, so slot 3 is actually reusable. Durable Rich Text lessons/tests were handed to Testing before retirement.
- **Dev Worker 4:** mostly healthy and current, but contains a concrete contradiction: top/state says the local Artwork migration was reported successful (Batch 76), while final `Next` still says “Migration not executed.” The same file also names retired Dev Worker 3 in ownership/deferred text. Must reconcile these stale statements on its next write. Content size (~9k) is acceptable only if duplicate implementation bullets continue to collapse as acceptance advances.
- **Testing Worker 1:** clearly improved to about 5.4k characters and consolidated old reconciliation history. Current source-parity evidence, media handoff and Rich Text handoff are distinguishable; execution limits are explicit. It still has two current handoff sections, but both are genuinely active acceptance groups rather than old chronology. Healthy for now.
- **Docs Worker 1:** remains the largest unresolved token sink (~14.6k) and has not been compacted since 31 October. It still carries completed October transfer history, obsolete setup milestones and old source baselines. Its durable work itself is strong; before new Docs work the owner must promotion-aware compact to current reconciled baseline, unpublished durable deltas, verification limits and next publication gate.
- **Refactor Worker 1:** healthy and compact (~2.3k). It now collects user-supplied future-sweep observations explicitly as unverified examples, not established bugs; current Refactor skill captures the general schema-evolution/compatibility lesson. No application refactor is falsely claimed.

**Protocol propagation:** the new promotion/compaction Shared Protocol is now present on `testing` as well as `dev`. It is still absent from branch-local `docs` and `refactor` protocol snapshots. Their role skills already retain durable-feedback rules, so no immediate lesson is unprotected, but reviewed shared-method synchronization remains pending before those branches can rely on the exact new compaction/current-state contract.

## Repository language hygiene

Project rule: **all repository-authored prose is English only**. German is acceptable only as intentionally preserved product/source content, locale data, proper names or verbatim artist text, never as explanatory documentation/process prose.

Audit on current `dev` found a real violation, not just isolated wording:
- `.agents/workers/dev-1.md` contains extensive German live-state prose and must be translated/compacted by its owning worker on its next substantive write.
- Dev Worker 2, Dev Worker 4, current shared skills/protocol, Org state and the sampled main product/operations documents are English.
- `docs/admin/dialogs.md` contains a mixed German section.
- Numerous permanent `docs/architecture/*.md` reports are predominantly or materially German, including Activity/Undo, notification transport, publication calls/working operations, runtime boundaries/entrypoints, Artwork/Gallery publication, Eloquent relations, Exhibition map, import boundaries, inventory, Journal lifecycle, lifecycles, media cleanup, PHP source audit, Publication, schema evolution, structured components, UI surfaces and Public security/SPA lifecycle.
- `docs/public/rights-privacy.md` contains at least a mixed German subsection; `docs/migration/invariants.md` has at least one German heading-fragment dependency.

This is now a **Docs normalization gate**: translate in place to English, preserve technical/source meaning, update heading-fragment links where necessary, and do not create duplicate English copies. Because the active Docs branch owns durable documentation, Org records the gate rather than rewriting that branch's canonical corpus itself. The English-only rule is published on `dev`; the Docs branch must receive the reviewed shared-method update as part of its next synchronization.

## Immediate organizational objective

**Validation preparation, 2026-11-18:** The user wants to prepare another protected Validation preview but explicitly does **not** select or approve the current `dev` commit for deployment. No preview-image publication, Validation mutation, Continuous Integration run or release promotion is authorized by preparation alone.

- **Source position:** `dev` is moving with parallel workers (observed `afed72943b564fb4ea7cc70b8bdcdeebf6d7d37c`); the next candidate must be selected by its exact fresh commit, not assumed to be current HEAD.
- **Migration repair:** Dev Worker 5 corrected the first Gallery `site_sections_template_check` ordering defect on `dev` for both Working and Committed schemas. The larger forward-only Home-to-Gallery Hero activation migration `2026_11_18_050000_activate_page_templates_and_gallery_hero.php` is source-integrated but **not yet verified** against the imported artist-data mirror. Its downgrade explicitly throws. The last evidenced successful local migration/media run was on older `5889f6bd56f5e47984f70e74e31053866b767397`; a subsequent preview start failed before the repair. A successful current Admin/public runtime or historical restore is not yet proved.
- **Preparation gates:** (1) read-only Validation lifecycle/status and capacity, plus temporary-export hygiene; (2) finish a current local migration/media/browser rehearsal on the imported artist data without the Analytics demo injector in `scripts/local-preview.ps1`; (3) immediately before any approved Validation update, create/check an independent, matched Validation PostgreSQL and original-media recovery point, transfer and checksum-verify it off-host, then remove only temporary server exports; (4) select the exact accepted Git source and use the existing explicit image-preview then platform `update <SHA>` path, followed by post-update checks. Never treat the platform's normal Production backup as coverage of ephemeral Validation state.
- **Pending operator evidence:** whether the old local transfer/backup/media directories were actually removed, the current deployed Validation identity and read-only status, and the current local migration/health/media/browser outcomes. Do not mark these complete from commands merely proposed.

In parallel, artist-feedback development remains active; no Dev freeze has been declared. At a later explicit v0.4 freeze use #198 → #199 → #214 for milestone closure; pre-main Git-history cleanup remains a separate authorized gate.
