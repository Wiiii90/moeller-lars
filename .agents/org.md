# Org — current project state

This is the compact live organizational state for the single `org` chat on `dev`. It is not a transcript. Replace superseded facts instead of appending history.

## Completed historical milestone — v0.4.0

**Accepted on 2026-11-19 with a retrospective technical handoff boundary of 2026-11-09.** The project owner accepted the sufficiently stable v0.4 core and the previously reviewed Testing qualification without a repeated full-suite run on the later, rapidly evolving `dev` head. The exact tested snapshot was `f0d168b22437668a29485ae308880e09831c4447` on Testing; the reviewed application/test transfer to `dev` was commit `f54041ee4b75140380b543bf1a37185cc5dbd2aa` on 2026-11-09.

All 17 v0.4 issues now have zero open issues. #198 functional signoff, #199 historical qualification (Unit/Integration/Feature/Migration/JS/Browser/PHPStan/Pint evidence) and #214 milestone notes were closed **completed on 2026-11-19** with explicit evidence-limit comments. The technical milestone summary is in #214; these closures do not claim a historical Production release or an exact `main` verification. No Dashboard deployed-release announcement, Git tag, `main` promotion, history rewrite, workflow dispatch or Validation mutation was performed for this closeout.

**Milestone container closed:** on 2026-11-19 the operator ran `gh api -X PATCH repos/Wiiii90/moeller-lars/milestones/13 -f state=closed` and shared the response `{"closed_issues":17,"number":13,"open_issues":0,"state":"closed","title":"v0.4.0"}`. This confirms the GitHub milestone is closed, though a versioned `main` release is still pending.

## Active development milestone — v0.5.0

Artist-feedback and development continued beyond the historical v0.4 boundary. All post-handoff changes, including new Home/Pages/Gallery type/template architecture and performance/dialog improvements, are v0.5 work. No v0.5 Dev freeze or exact application candidate has been chosen.

Eight currently open v0.5 completion gates:
- #31 protected Biography/Exhibitions/profile reconciliation;
- #34 exact-candidate public/editorial regression comparison;
- #38 authoritative matching DB/original-media backup and isolated restore proof;
- #39 exact v0.5 Production-candidate GO/NO-GO, including prior v0.4 main promotion;
- #57 Analytics against real aggregate data;
- #59 Storage capacity/quota in the target runtime;
- #122 real Contact mail-delivery acceptance;
- #209 artist-approved Validation-source clean Production target, with source accounting/content freeze and sanitization.

**Cutover boundary:** v0.5 ends with reproducible Production target and exact candidate GO/NO-GO under #209/#39. Artist editorial approval #40, application-side Production traffic-switch validation #41 and post-cutover stabilization #42 are assigned to a **different later cutover milestone**. Platform owns the actual traffic switch and rollback separately (platform #11 and related gates). Do not conflate a Validation refresh, v0.5 candidate approval, editorial approval and Production cutover.

## Validation

Protected Validation is an active pre-release/editorial environment whose database and canonical originals must be preserved across ordinary preview updates.

For a requested new Dev preview, resolve the exact fresh `dev` SHA and use the established two-stage release contract:
1. `scripts/validation-preview.ps1 <exact-sha>` builds/publishes the exact preview image;
2. the platform Validation update consumes that same exact SHA.

Do not record private host/access details here. Last operator-confirmed running Validation Git source on 2026-11-18: `3f203cfc384bfd2e95679cf05b0abcfc54f711fe`, healthy on platform digest `sha256:776f87dce3ea20b6b135c69557a5f66b995293e1dfbe02f55e4cf75478fec2f9`. This is not a guarantee of today's runtime state; re-read `status`/`/app-release.json` before an update.

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

The historical v0.4 GitHub milestone is now fully closed. The user wants a few more Dev commits before choosing a fresh exact SHA for the imminent protected Validation preview; keep deployment on hold meanwhile. Separately, the v0.4 technical handoff commit `f54041ee4b75140380b543bf1a37185cc5dbd2aa` was never promoted to `main`: on 2026-11-19 `main` was verified at `766a59cc74a5b58d318c6b02a8087a667c09dc61` (2026-09-18), and that handoff is a descendant (2,724 commits ahead). Formal v0.4 main promotion remains pending the user-agreed Git-history sanitization and a separately authorized exact-source promotion. Do not let either action block a separately approved Validation preview.

The user intends a **near-term update of protected Validation** to a freshly selected exact `dev` commit, but has **not yet selected that commit**. Do not build/publish an image, execute a Validation update, or treat current `dev` as frozen until the user explicitly identifies it. The previously recorded Validation→local transfer was successful on an older `dev` revision; the newer irreversible Home/Gallery Hero migration and changed source have not been accepted against that mirror. `scripts/local-preview.ps1` still injects local Analytics demo data after startup; use a safe non-seeding local launch for the artist-data mirror. Right before an approved update, capture and independently verify a matching recoverable Validation database/original-media snapshot because the ordinary platform Production backup does not automatically cover protected Validation. Preserve the artist's live editorial state; never reimport older data into Validation.

Before the next `main` promotion, the explicitly agreed repository history sanitization of prior public ADR infrastructure content remains a mandatory separate coordinated gate. A historical milestone close does **not** waive that prerequisite or authorize `main` changes.
