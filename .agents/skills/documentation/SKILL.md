---
name: documentation
description: Maintain source-verified architecture and concise canonical documentation for the long-lived docs branch of moeller-lars, with controlled dev merges and explicit verification boundaries.
---

# Documentation workstream

## Start and ownership

1. Read repository [AGENTS.md](../../../AGENTS.md), then [DOCS_SKILL.md](../../../DOCS_SKILL.md) **in full**. The root DOCS_SKILL is the changing work state; this file is the stable method. Read [docs/README.md](../../../docs/README.md) for current normative owners, [architecture/README.md](../../../docs/architecture/README.md) for the evidence catalog, [cross-feature compositions](../../../docs/architecture/cross-feature-compositions.md) for multi-owner flows, and [Worker Protocol](../../../docs/WORKER-PROTOCOL.md) for safe cross-consumer change review and eventual integration.
2. Fetch fresh **`docs` and `dev`** HEADs at the start of work. Write documentation, inventories and skills only to **`docs`**. `dev` now owns both implementation/contracts and the **single active test structure**; the former dedicated `testing` workstream was merged into `dev` and is not a second suite. Read current `dev` tests, `tests/Pest.php` and `phpunit.xml` when asserting coverage; label earlier branch SHAs historical. The user-authorized `P:\moeller-lars-docs` worktree exists but must **not** be used for routine documentation edits or per-commit checks. Run local PHP/generators there **only via Codex** for a rare, separately agreed, sufficiently batched technical verification; do not ask the user to perform local Docs commands. No other branches/worktrees and no writes to `dev`/`main`. For the technical closeout, inspect the checked-out `docs` source; newer `dev` source/test reads retain separate SHA provenance and are not silently imported.
3. Triage changed `dev` paths periodically rather than chasing every commit. Only import coherent relevant batches via a real conflict-reviewed **two-parent merge** on `docs`, with expected-HEAD guard and no force-push. Never resurrect a separate `testing` import/worktree or parallel test system. Never merge `docs` to `dev`/`main` without an explicit decision.
4. **No CI interaction** whatsoever: do not dispatch, inspect, poll, re-run, cancel, read reports/statuses, or edit workflows. `dev` test sources may be **read**, never written, executed or reported as passing by this docs workstream. Standalone safe source checks are permitted; never touch persistent Preview/Production data.

## Source evidence before claims

- Prefer the real current PHP/Blade/CSS/JS, relevant migrations, API contracts and verified browser feedback to superseded descriptions. Pin the source SHA for each generated inventory in its owning documentation or intrinsic provenance; never replace a historical input SHA with the current HEAD. A successful byte comparison proves reproduction from the declared input, not current application semantics. Use direct file links/line anchors wherever practical; identify the owner class/method, not just a filename.
- **Separate evidence types:** current `dev` application/contract source and **current `dev` authored test assertions** at an exact SHA; historically pinned former-`testing` evidence; parser fixtures; tests actually executed against an isolated compatible application SHA; browser acceptance; physical migrated PostgreSQL catalog. Test filenames/file counts are not case counts and authored assertions are not executions. A former branch comparison is not a present-day testsuite architecture.
- An import, use-declaration or constructor type is **not a runtime call**. An Eloquent method is **not a physical foreign key**. A proposed state transition is **not a proven temporal/scheduler transition**. Laravel discovery, DI, Livewire and JS events require their own traced entrance points.
- Domain/lifecycle diagrams must show guards, no-op paths, error/rollback handling, indirect mutations and whether state is persisted or derived. Public Working/LIVE reads, History payloads, JSON component media references and release/cutover environment boundaries are separate models.
- For SQL architecture: only a **fully migrated, explicitly disposable isolated PostgreSQL database** may be used to query real `pg_catalog`. Read-only schema exporter may run there; do not `migrate:fresh`, reset or mutate a live/preview application database.
- A source generator must clearly declare its coverage (parsed syntax, dynamic exclusions, skipped files and expected source SHA) and its exact outputs. Generated files are derived, not hand-maintained. Execute `--write` then `--check` **only** inside an expressly agreed Codex bulk verification with an isolated full checkout and safe runtime; normal docs source-reconciliation stays remote/read-only against `dev` and writes only Docs blobs to `docs`. Otherwise build a labeled source audit and keep the native check open.

## Editing and de-duplication

- **Normative application behavior has one documented owner.** Before editing a paragraph, search related contracts, inspect its actual code owner and replace obsolete assertions in the owning document. In other files use links instead of copying the same policy.
- Do not transplant historical v0.3 acceptance into the actively evolving v0.4 candidate; exact browser-approved SHA and explicit user decision control acceptance. v0.5 cutover is planned but contingent on validation and platform readiness.
- Keep legacy/import compatibility, live runtime, schema-hash-only artifacts and deployment operations distinct; never delete a migration/model/view simply because current UI no longer exposes it.
- Avoid creating generic new UI rules. For accepted admin UI, consult the actual [ui-skills.md](../../../ui-skills.md), current per-topic contracts and accepted code before any normative rewrite. The separate Public UI grammar must likewise be derived from actual source and accepted feedback, not invented from admin CSS.
- Prefer smaller task-specific documents, brief technical explanations, source-linked tables and code-backed Mermaid. Avoid repetition, chronological worker diaries, rejected browser candidates as current truth, decorative diagrams and brittle pixel/HTML-class assertions.
- Remove/replace obsolete files **only** after reference search and proving their contents superseded. New generated diagrams live under `docs/architecture/generated/` and should be indexed, not merely dumped into the root.

For new `dev` rich-text/locale/public changes, update the **existing** normative owner before creating any topic document, and preserve the live `dev` root `AGENTS.md`/`ui-skills.md`/`TESTING_SKILL.md` and temporary rights workstream during eventual docs integration. Do not copy an old Docs root workflow over a newer dev version. Worker/chat numbers identify scope only, not new branches or files.

## End of each working unit

1. Update the owning documents, architecture index and **DOCS_SKILL.md in the same commit or explicitly paired commit** with the new findings, source versions, actual verification and next concrete step. Retire stale checkpoint claims instead of endlessly stacking contradictory statuses.
2. Re-read remote `docs` HEAD before updating refs; reconcile concurrent commits and `dev` merges.
3. For routine GitHub-only documentation edits, verify changed Markdown destinations against the remote Docs tree without executing local tools. The read-only native `php scripts/check-documentation-links.php --check` belongs **only** in rare, authorized Codex batch checks, never after each small commit merely because a worktree exists; it covers ordinary Markdown paths and heading fragments, not HTML/MDX/dynamic destinations. Do not claim this full-repo command ran if only GitHub source/tree comparisons were available.
4. Report committed paths/SHA, what is proved, what is still not tested and any distinct product defect uncovered. Do not silently “fix” a runtime discrepancy on the documentation branch.
5. A new chat resumes from `DOCS_SKILL.md` rather than repeating a full repository audit.

## Current architecture index

See [docs/architecture/README.md](../../../docs/architecture/README.md) for the source inventory and [docs/TESTING.md](../../../docs/TESTING.md) for the **single** active `dev` test system. Historic counts (248 PHP types, 20 import graphs, etc.) are versioned inventories, not live full-repository totals. The actual native source-only checker/generator results and artifact decisions are owned by the completion matrix. They do not imply an executed PostgreSQL schema export or application test.
