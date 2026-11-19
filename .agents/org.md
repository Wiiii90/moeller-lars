# Org — current project state

This is the compact live organizational state for the single `org` chat on `dev`. It is not a transcript. Replace superseded facts instead of appending history.

## Completed historical milestone — v0.4.0

**Accepted on 2026-11-19 with a retrospective technical handoff boundary of 2026-11-09.** The project owner accepted the sufficiently stable v0.4 core and the previously reviewed Testing qualification without a repeated full-suite run on the later, rapidly evolving `dev` head. The exact tested snapshot was `f0d168b22437668a29485ae308880e09831c4447` on Testing; the reviewed application/test transfer to `dev` was commit `f54041ee4b75140380b543bf1a37185cc5dbd2aa` on 2026-11-09.

All 17 v0.4 issues now have zero open issues. #198 functional signoff, #199 historical qualification (Unit/Integration/Feature/Migration/JS/Browser/PHPStan/Pint evidence) and #214 milestone notes were closed **completed on 2026-11-19** with explicit evidence-limit comments. The technical milestone summary is in #214; these closures do not claim a historical Production release or an exact `main` verification. No Dashboard deployed-release announcement, Git tag, `main` promotion, history rewrite, workflow dispatch or Validation mutation was performed for this closeout.

**Milestone container closed, release not yet finalized:** on 2026-11-19 the operator confirmed Milestone #13 `v0.4.0` is closed with 17 closed / 0 open issues. The user later chose to establish a **new official v0.4 code/release boundary on a current integrated Dev candidate**, rather than promoting the 2026-11-09 historical marker. That marker remains the earlier Testing evidence, not the planned `main` target. When the final candidate is selected and qualified, reconcile the existing #198/#199/#214 closeout records and release notes to its exact source/evidence; do not silently represent the 2026-11-09 qualification as covering later commits. This decision does not reopen the GitHub milestone by itself.

## Active development milestone — v0.5.0

v0.5 preparation and artist-feedback development have overlapped with the as-yet-unshipped v0.4 code release. By the owner's 2026-11-19 decision, the integrated current candidate and the Testing/Refactor improvements selected for the upcoming `main` promotion will form the **official v0.4 release boundary**. Treat post-2026-11-09 Home/Pages/Gallery changes as part of that final v0.4 source once selected; do not categorize them exclusively as v0.5 merely because they occurred after the historical Testing handoff. The eight v0.5 Production-readiness Issues remain open. No v0.5 release candidate or Production cutover is authorized.

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

**Approved two-pass Validation and official v0.4 release sequence (2026-11-19):**
1. The user will choose an exact current `dev` commit later today after the remaining active Dev changes. **Not selected yet**; do not freeze or deploy current HEAD by inference.
2. First pass: publish that exact immutable preview image and update protected Validation using the existing app-preview → platform-update contract, preserving the artist's PostgreSQL/media state. Before any irreversible schema migration, verify one current, matching, independently recoverable Validation database/original-media point; keep the checks bounded and targeted, not a new extensive audit.
3. The user will perform short manual Validation/browser/editorial checks while Testing catches up on the chosen source. Integrate reviewed Testing changes and any approved Refactor corrections via the branch-safe workstream protocol; those changes may create a **different final source SHA**. No extra CI/workflows without separate approval.
4. Freeze the integrated and reviewed final v0.4 application SHA; reconcile release notes and previous #198/#199/#214 closeout claims to exact final evidence. Before `main` promotion execute the separately agreed history-sanitization gate in a controlled write freeze, with preserved branch ancestry and independently verified refs; **never** reset `main` or promote the obsolete 2026-11-09 source instead.
5. Promote the **entire exact final integrated source** to protected `main` through the approved promotion-branch/PR process. After final qualification on `main` and immutable image publication, update Validation a **second time** to that final main/release image digest, and verify health, migrations/media and the requested short checks. Production and actual cutover are not part of this operation.

**Current state:** No first candidate SHA selected; no deployment, Test integration, `main` promotion, ref rewrite or release workflow execution has been performed as part of this plan. The operator-confirmed Validation Git SHA as of 2026-11-18 was `3f203cfc384bfd2e95679cf05b0abcfc54f711fe`; do not claim it is still current without checking. Validation editorial data/media are protected. The older local artist-state mirror was verified on `5889f6bd56f5e47984f70e74e31053866b767397`, not on the later irreversible Home/Gallery Hero migration. `scripts/local-preview.ps1` still injects local Analytics demo data, so do not use its unchanged seeding behavior against the artist mirror.

**Open policy constraint:** Pre-`main` sanitization of public ADR infrastructure history is already user-agreed but **not yet implemented**. No silent force updates, no loss of Dev branches/worker ancestry and no History rewrite without the separately approved and coordinated preservation steps.
